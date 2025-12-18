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
 * Stripe enrolment plugin.
 *
 * This plugin allows you to set up paid courses.
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2019 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


use core_enrol\output\enrol_page;
use enrol_stripepayment\util;
use core\exception\moodle_exception;
use core\output\notification;

/**
 * Stripe enrolment plugin implementation.
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2019 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class enrol_stripepayment_plugin extends enrol_plugin {
    /**
     * Returns optional enrolment information icons.
     *
     * This is used in course list for quick overview of enrolment options.
     *
     * We are not using single instance parameter because sometimes
     * we might want to prevent icon repetition when multiple instances
     * of one type exist. One instance may also produce several icons.
     *
     * @param array $instances all enrol instances of this type in one course
     * @return array of pix_icon
     */
    public function get_info_icons(array $instances) {
        $found = false;
        foreach ($instances as $instance) {
            if ($instance->enrolstartdate != 0 && $instance->enrolstartdate > time()) {
                continue;
            }
            if ($instance->enrolenddate != 0 && $instance->enrolenddate < time()) {
                continue;
            }
            $found = true;
            break;
        }
        if ($found) {
            return [new pix_icon('icon', get_string('pluginname', 'enrol_stripepayment'), 'enrol_stripepayment')];
        }
        return [];
    }

    /**
     * Lists all protected user roles.
     * @return bool(true or false)
     */
    public function roles_protected() {
        // Users with role assign cap may tweak the roles later.
        return false;
    }

    /**
     * Defines if user can be unenrolled.
     * @param stdClass $instance of the plugin
     * @return bool(true or false)
     */
    public function allow_unenrol(stdClass $instance) {
        // Users with unenrol cap may unenrol other users manually - requires enrol/stripe:unenrol.
        return true;
    }

    /**
     * Defines if user can be managed from admin.
     * @param stdClass $instance of the plugin
     * @return bool(true or false)
     */
    public function allow_manage(stdClass $instance) {
        // Users with manage cap may tweak period and status - requires enrol/stripe:manage.
        return true;
    }

    /**
     * Defines if 'enrol me' link will be shown on course page.
     * @param stdClass $instance of the plugin
     * @return bool(true or false)
     */
    public function show_enrolme_link(stdClass $instance) {
        return $instance->status == ENROL_INSTANCE_ENABLED;
    }

    /**
     * Sets up navigation entries.
     *
     * @param navigation_node $instancesnode
     * @param stdClass $instance
     * @return void
     */
    public function add_course_navigation($instancesnode, stdClass $instance) {
        if ($instance->enrol !== 'stripepayment') {
             throw new moodle_exception('invalidenroltype', 'enrol_stripepayment');
        }
        if (has_capability('enrol/stripepayment:manage', context_course::instance($instance->courseid))) {
            $managelink = new moodle_url(
                '/enrol/editinstance.php',
                [
                    'courseid' => $instance->courseid,
                    'id' => $instance->id,
                    'type' => 'stripepayment',
                ]
            );
            $instancesnode->add($this->get_instance_name($instance), $managelink, navigation_node::TYPE_SETTING);
        }
    }

    /**
     * Returns edit icons for the page with list of instances
     * @param stdClass $instance
     * @return array
     */
    public function get_action_icons(stdClass $instance) {
        global $OUTPUT;
        if ($instance->enrol !== 'stripepayment') {
            throw new moodle_exception('invalidenrolinstance', 'enrol_stripepayment');
        }
        $icons = [];
        if (has_capability('enrol/stripepayment:manage', context_course::instance($instance->courseid))) {
            $linkparams = [
                'courseid' => $instance->courseid,
                'id' => $instance->id,
                'type' => $instance->enrol,
            ];
            $editlink = new moodle_url('/enrol/editinstance.php', $linkparams);
            $icon = new pix_icon('t/edit', get_string('edit'), 'core', ['class' => 'iconsmall']);
            $icons[] = $OUTPUT->action_icon($editlink, $icon);
        }
        return $icons;
    }

    /**
     * Returns link to page which may be used to add new instance of enrolment plugin in course.
     * @param int $courseid
     * @return moodle_url page url
     */
    public function get_newinstance_link($courseid) {
        $context = context_course::instance($courseid, MUST_EXIST);
        if (!has_capability('moodle/course:enrolconfig', $context) || !has_capability('enrol/stripepayment:manage', $context)) {
            return null;
        }
        // Multiple instances supported - different cost for different roles.
        return new moodle_url('/enrol/editinstance.php', ['courseid' => $courseid, 'type' => 'stripepayment']);
    }

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
    public function enrolment_page_message($message, $instance) {
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
    public function render_enrol_page($instance) {
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
     * Returns localised name of enrol instance
     *
     * @param stdClass $instance (null is accepted too)
     * @return string
     */
    public function get_instance_name($instance) {
        global $DB;
        if (empty($instance->name)) {
            if (!empty($instance->roleid) && $role = $DB->get_record('role', ['id' => $instance->roleid])) {
                $role = ' (' . role_get_name($role, context_course::instance($instance->courseid, IGNORE_MISSING)) . ')';
            } else {
                $role = '';
            }
            return get_string('pluginname', 'enrol_' . $this->get_name()) . $role;
        } else {
            return format_string($instance->name);
        }
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
        $this->enrol_user($instance, $userid, null, $data->timestart, $data->timeend, $data->status);
    }

    /**
     * Gets an array of the user enrolment actions
     *
     * @param course_enrolment_manager $manager
     * @param stdClass $ue A user enrolment object
     * @return array An array of user_enrolment_actions
     */
    public function get_user_enrolment_actions(course_enrolment_manager $manager, $ue) {
        $actions = [];
        $context = $manager->get_context();
        $instance = $ue->enrolmentinstance;
        $params = $manager->get_moodlepage()->url->params();
        $params['ue'] = $ue->id;
        if ($this->allow_manage($instance) && has_capability('enrol/stripepayment:manage', $context)) {
            $actions[] = new user_enrolment_action(
                new pix_icon('t/edit', ''),
                get_string('edit'),
                new moodle_url('/enrol/editenrolment.php', $params),
                ['class' => 'editenrollink', 'rel' => $ue->id]
            );
        }
        if ($this->allow_unenrol($instance) && has_capability('enrol/stripepayment:unenrol', $context)) {
            $actions[] = new user_enrolment_action(
                new pix_icon('t/delete', ''),
                get_string('unenrol', 'enrol'),
                new moodle_url('/enrol/unenroluser.php', $params),
                ['class' => 'unenrollink', 'rel' => $ue->id]
            );
        }
        return $actions;
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

    /**
     * Is it possible to delete enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_delete_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/stripepayment:manage', $context);
    }

    /**
     * Is it possible to hide/show enrol instance via standard UI?
     *
     * @param stdClass $instance
     * @return bool
     */
    public function can_hide_show_instance($instance) {
        $context = context_course::instance($instance->courseid);
        return has_capability('enrol/stripepayment:manage', $context);
    }

    /**
     * We are a good plugin and don't invent our own UI/validation code path.
     *
     * @return boolean
     */
    public function use_standard_editing_ui() {
        return true;
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
     * Validate if current API keys can access the products/prices for an instance - NEW METHOD.
     *
     * @param stdClass $instance The enrolment instance
     * @return array Array with 'accessible' boolean and 'error' message
     */
    public function validate_instance_accessibility($instance) {
        $secretkey = util::get_current_secret_key();

        if (empty($secretkey)) {
            return ['accessible' => false, 'error' => 'No API key configured'];
        }

        // If instance doesn't have custom price IDs, it's accessible (will create new prices).
        if (empty($instance->customtext1)) {
            return ['accessible' => true, 'error' => ''];
        }
    }
}
