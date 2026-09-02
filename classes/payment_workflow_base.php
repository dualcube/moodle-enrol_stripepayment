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
 * Checkout page, edit-instance form, and backup/cron for the Stripe enrolment plugin.
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2025 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_stripepayment;

use backup;
use context_course;
use core\output\notification;
use core_enrol\output\enrol_page;
use MoodleQuickForm;
use progress_trace;
use restore_enrolments_structure_step;
use stdClass;
use text_progress_trace;

/**
 * Running a Stripe-payment enrolment instance end to end, beyond what
 * {@see plugin_base} already covers: the course-page checkout itself (its
 * eligibility checks in {@see self::enrol_page_hook()}, the plain-notification
 * and Stripe-checkout rendering it calls), the "Add/edit enrolment method" form
 * (building its fields, validating submitted data, persisting an instance), and
 * the hooks Moodle core calls outside of those two pages - mapping
 * instances/enrolments during course restore, and processing enrolment
 * expirations on cron and manual sync.
 *
 * Extends {@see plugin_base} - see that class's docblock for why this is a
 * chain of two small classes rather than one big one.
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2025 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class payment_workflow_base extends plugin_base {
    /**
     * Returns link to page which may be used to add new instance of enrolment plugin in course.
     * @param stdClass $instance
     * @return string
     */
    public function enrol_page_hook(stdClass $instance) {
        global $USER, $DB;

        if (!util::can_more_user_enrol($instance)) {
            return $this->enrolment_page_message(get_string('maxenrolledreached', 'enrol_stripepayment'), $instance);
        }

        if ($DB->record_exists('user_enrolments', ['userid' => $USER->id, 'enrolid' => $instance->id])) {
            return '';
        }

        if ($instance->enrolstartdate != 0 && $instance->enrolstartdate > time()) {
            return $this->enrolment_page_message(
                get_string('canntenrolearly', 'enrol_stripepayment', userdate($instance->enrolstartdate)),
                $instance
            );
        }

        if ($instance->enrolenddate != 0 && $instance->enrolenddate < time()) {
            return $this->enrolment_page_message(
                get_string('canntenrollate', 'enrol_stripepayment', userdate($instance->enrolenddate)),
                $instance
            );
        }

        if (!$this->validate_instance_accessibility($instance)['accessible']) {
            return $this->enrolment_page_message(get_string('paymentmethodnotfound', 'enrol_stripepayment'), $instance);
        }

        return $this->render_enrol_page($instance);
    }

    /**
     * Returns notification message.
     * @param string $message
     * @param stdClass $instance
     * @return string
     */
    protected function enrolment_page_message($message, $instance) {
        global $OUTPUT;
        $notification = new notification($message, 'info', false);
        $notification->set_extra_classes(['mb-0']);
        $enrolpage = new enrol_page(
            instance: $instance,
            header: $this->get_instance_name($instance),
            body: $OUTPUT->render($notification)
        );
        return $OUTPUT->render($enrolpage);
    }

    /**
     * Returns enrol page.
     * @param stdClass $instance
     * @return string
     */
    protected function render_enrol_page($instance) {
        global $OUTPUT, $PAGE;  // Added $PAGE to global declarations.

        $course = get_course($instance->courseid);
        $cost = ((float) $instance->cost <= 0) ? (float) $this->get_config('cost') : (float) $instance->cost;
        $name = $this->get_instance_name($instance);
        $cost = format_float($cost, 2, false);

        $templatedata = [
            'currency' => $instance->currency,
            'cost' => format_float($cost, 2, true),
            'coursename' => format_string($course->fullname, true, ['context' => context_course::instance($course->id)]),
            'instanceid' => $instance->id,
            'enrolbtncolor' => $this->get_config('enrolbtncolor'),
            'enablecouponsection' => $this->get_config('enablecouponsection'),
        ];

        $body = $OUTPUT->render_from_template('enrol_stripepayment/enrol_page', $templatedata);

        $PAGE->requires->js_call_amd(
            'enrol_stripepayment/stripe_payment',
            'stripePayment',
            [
                null, // Couponid starts as null.
                [
                    'id' => $instance->id,
                    'cost' => $instance->cost,
                    'currency' => $instance->currency,
                    'courseid' => $instance->courseid,
                ],
            ]
        );

        $enrolpage = new enrol_page(
            instance: $instance,
            header: $name,
            body: $body
        );
        return $OUTPUT->render($enrolpage);
    }

    /**
     * Validate if current API keys can access the products/prices for an instance - NEW METHOD.
     *
     * @param stdClass $instance The enrolment instance
     * @return array Array with 'accessible' boolean and 'error' message
     */
    protected function validate_instance_accessibility($instance) {
        $secretkey = stripe_client::get_current_secret_key();

        if (empty($secretkey)) {
            return ['accessible' => false, 'error' => 'No API key configured'];
        }

        // If instance doesn't have custom price IDs, it's accessible (will create new prices).
        if (empty($instance->customtext1)) {
            return ['accessible' => true, 'error' => ''];
        }
    }

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
