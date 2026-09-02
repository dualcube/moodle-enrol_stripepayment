<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Edit-instance form and backup/restore/cron lifecycle for the Stripe enrolment plugin.
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2025 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_stripepayment;

use backup;
use MoodleQuickForm;
use progress_trace;
use restore_enrolments_structure_step;
use stdClass;
use text_progress_trace;

/**
 * Everything about an enrol_stripepayment_plugin instance's data lifecycle: the
 * "Add/edit enrolment method" form (building its fields, validating submitted
 * data), persisting an instance, and the hooks Moodle core calls outside of the
 * course page and edit form - mapping instances/enrolments during course
 * restore, and processing enrolment expirations on cron and manual sync.
 *
 * Extends {@see presentation_base} - the top of the chain leading up to
 * enrol_stripepayment_plugin (lib.php). The edit form and backup/cron live
 * together in this one class (rather than each in their own) because PHPMD's
 * DepthOfInheritance rule caps how many links this chain can have - see
 * {@see presentation_base}, the other merged link, and {@see plugin_base}'s
 * docblock for why this is a chain of small classes at all.
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2025 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class instance_lifecycle_base extends presentation_base {
    /**
     * Add elements to the edit instance form.
     *
     * @param stdClass $instance
     * @param MoodleQuickForm $mform
     * @param context $context
     * @return bool
     */
    public function edit_instance_form($instance, MoodleQuickForm $mform, $context) {
        // Main fields.
        $mform->addElement('text', 'name', get_string('custominstancename', 'enrol'));
        $mform->setType('name', PARAM_TEXT);

        $options = util::get_status_options();
        $mform->addElement('select', 'status', get_string('status', 'enrol_stripepayment'), $options);
        $mform->setDefault('status', $this->get_config('status'));

        $costarray = [];
        $costarray[] =& $mform->createElement('text', 'cost', get_string('cost', 'enrol_stripepayment'), ['size' => 4]);
        $mform->setDefault('cost', format_float($this->get_config('cost'), 2, true));
        $mform->setType('cost', PARAM_FLOAT);
        $mform->addGroup($costarray, 'costar', get_string('cost', 'enrol_stripepayment'), [' '], false);

        // Currency select.
        $currency = util::get_currencies();
        $mform->addElement('select', 'currency', get_string('currency', 'enrol_stripepayment'), $currency);
        $mform->setDefault('currency', $this->get_config('currency'));

        $roles = $this->get_roleid_options($instance, $context);
        // Assign role.
        $mform->addElement('select', 'roleid', get_string('assignrole', 'enrol_stripepayment'), $roles);
        $mform->setDefault('roleid', $this->get_config('roleid'));

        $mform->addElement('text', 'customint3', get_string('maxenrolled', 'enrol_stripepayment'));
        $mform->setDefault('maxenrolled', 'customint3');
        $mform->addHelpButton('customint3', 'maxenrolled', 'enrol_stripepayment');
        $mform->setType('customint3', PARAM_INT);

        $mform->addElement(
            'duration',
            'enrolperiod',
            get_string('enrolperiod', 'enrol_stripepayment'),
            ['optional' => true, 'defaultunit' => 86400]
        );
        $mform->setDefault('enrolperiod', $this->get_config('enrolperiod'));
        $mform->addHelpButton('enrolperiod', 'enrolperiod', 'enrol_stripepayment');

        $mform->addElement(
            'date_time_selector',
            'enrolstartdate',
            get_string('enrolstartdate', 'enrol_stripepayment'),
            ['optional' => true]
        );
        $mform->setDefault('enrolstartdate', 0);
        $mform->addHelpButton('enrolstartdate', 'enrolstartdate', 'enrol_stripepayment');

        $mform->addElement(
            'date_time_selector',
            'enrolenddate',
            get_string('enrolenddate', 'enrol_stripepayment'),
            ['optional' => true]
        );
        $mform->setDefault('enrolenddate', 0);
        $mform->addHelpButton('enrolenddate', 'enrolenddate', 'enrol_stripepayment');

        if (enrol_accessing_via_instance($instance)) {
            $mform->addElement(
                'static',
                'selfwarn',
                get_string('instanceeditselfwarning', 'core_enrol'),
                get_string('instanceeditselfwarningtext', 'core_enrol')
            );
        }
    }

    /**
     * Perform custom validation of the data used to edit the instance.
     *
     * @param array $data array of ("fieldname"=>value) of submitted data
     * @param array $files array of uploaded files "element_name"=>tmp_file_path
     * @param object $instance The instance loaded from the DB
     * @param context $context The context of the instance we are editing
     * @return array of "element_name"=>"error_description" if there are errors,
     *         or an empty array if everything is OK.
     * @return array
     */
    public function edit_instance_validation($data, $files, $instance, $context) {
        // Required by enrol_plugin's signature but unused here.
        unset($files);

        $errors = [];

        if (!empty($data['enrolenddate']) && $data['enrolenddate'] < $data['enrolstartdate']) {
            $errors['enrolenddate'] = get_string('enrolenddaterror', 'enrol_stripepayment');
        }

        $cost = str_replace(get_string('decsep', 'langconfig'), '.', $data['cost']);
        if (!is_numeric($cost)) {
            $errors['cost'] = get_string('costerror', 'enrol_paypal');
        }

        $validstatus = array_keys(util::get_status_options());
        $validcurrency = array_keys(util::get_currencies());
        $validroles = array_keys($this->get_roleid_options($instance, $context));
        $tovalidate = [
            'name' => PARAM_TEXT,
            'status' => $validstatus,
            'currency' => $validcurrency,
            'roleid' => $validroles,
            'enrolperiod' => PARAM_INT,
            'enrolstartdate' => PARAM_INT,
            'enrolenddate' => PARAM_INT,
        ];

        // Now validate the cost value.
        $currency = $data['currency'] ?? 'USD';

        // Minimum amounts for different currencies.
        $minamount = util::minamount($currency);

        // Check if cost is 0 or less (not allowed).
        if ($cost <= 0) {
            $errors['costar'] = get_string('costzeroerror', 'enrol_stripepayment');
        } else if ($cost < $minamount) {
            $errors['costar'] = get_string(
                'costminimumerror',
                'enrol_stripepayment',
                $currency . ' ' . number_format($minamount, 2)
            );
        }
        $typeerrors = $this->validate_param_types($data, $tovalidate);
        $errors = [...$errors, ...$typeerrors];
        return $errors;
    }

     /**
      * Return an array of valid options for the roleid.
      *
      * @param stdClass $instance
      * @param context $context
      * @return array
      */
    protected function get_roleid_options($instance, $context) {
        if ($instance->id) {
            $roles = get_default_enrol_roles($context, $instance->roleid);
        } else {
            $roles = get_default_enrol_roles($context, $this->get_config('roleid'));
        }
        return $roles;
    }

    /**
     * Update instance of enrol plugin.
     * @param stdClass $instance
     * @param stdClass $data modified instance fields
     * @return boolean
     */
    public function update_instance($instance, $data) {
        if ($data) {
            $data->cost = unformat_float($data->cost);
        }
        return parent::update_instance($instance, $data);
    }

    /**
     * Adds a new instance of the enrol_stripepayment plugin.
     *
     * @param stdClass $course The course object for which the enrolment instance is being created.
     * @param array|null $fields Optional instance fields, may include cost and other settings.
     * @return int|null The ID of the newly created instance, or null if it cannot be created.
     */
    public function add_instance($course, ?array $fields = null) {
        if ($fields && !empty($fields['cost'])) {
            $fields['cost'] = unformat_float($fields['cost']);
        }
        return parent::add_instance($course, $fields);
    }

    /**
     * Restore instance and map settings.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $course
     * @param int $oldid
     */
    public function restore_instance(restore_enrolments_structure_step $step, stdClass $data, $course, $oldid) {
        global $DB;
        if ($step->get_task()->get_target() == backup::TARGET_NEW_COURSE) {
            $merge = false;
        } else {
            $merge = [
                'courseid'   => $data->courseid,
                'enrol'      => $this->get_name(),
                'roleid'     => $data->roleid,
                'cost'       => $data->cost,
                'currency'   => $data->currency,
            ];
        }
        if ($merge && $instances = $DB->get_records('enrol', $merge, 'id')) {
            $instance = reset($instances);
            $instanceid = $instance->id;
        } else {
            $instanceid = $this->add_instance($course, (array)$data);
        }
        $step->set_mapping('enrol', $oldid, $instanceid);
    }

    /**
     * Restore user enrolment.
     *
     * @param restore_enrolments_structure_step $step
     * @param stdClass $data
     * @param stdClass $instance
     * @param int $userid
     * @param int $oldinstancestatus
     */
    public function restore_user_enrolment(restore_enrolments_structure_step $step, $data, $instance, $userid, $oldinstancestatus) {
        // Required by enrol_plugin's signature but unused here.
        unset($step, $oldinstancestatus);

        $this->enrol_user($instance, $userid, null, $data->timestart, $data->timeend, $data->status);
    }

    /**
     * Set up cron for the plugin (if any).
     *
     */
    public function cron() {
        $trace = new text_progress_trace();
        $this->process_expirations($trace);
    }

    /**
     * Execute synchronisation.
     * @param progress_trace $trace
     * @return int exit code, 0 means ok
     */
    public function sync(progress_trace $trace) {
        $this->process_expirations($trace);
        return 0;
    }
}
