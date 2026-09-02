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
 * Edit-instance form building, validation and saving for the Stripe enrolment plugin.
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2025 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_stripepayment;

use MoodleQuickForm;

/**
 * The "Add/edit enrolment method" form: building its fields, validating
 * submitted data, and persisting an instance once the form's cost field
 * has been unformatted back to a plain float.
 *
 * Split out of {@see \enrol_stripepayment_plugin} purely to keep that
 * mandatory enrol_plugin subclass from being one huge file; every method
 * here is used only by that class.
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2025 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
trait instance_form_trait {
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
}
