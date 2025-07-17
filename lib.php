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
defined('MOODLE_INTERNAL') || die();

use core_enrol\output\enrol_page;

global $CFG;
require_once($CFG->dirroot.'/lib/adminlib.php');

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
     * Lists all currencies available for plugin.
     * @return $currencies
     */
    public function get_currencies() {
        // See https://www.stripe.com/cgi-bin/webscr?cmd=p/sell/mc/mc_intro-outside,
        // 3-character ISO-4217: https://cms.stripe.com/us/cgi-bin/?cmd=
        // _render-content&content_ID=developer/e_howto_api_currency_codes.
        $codes = [
            'USD', 'AED', 'AFN', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN', 'BAM', 'BBD', 'BDT', 'BGN', 'BIF', 'BMD',
            'BND', 'BOB', 'BRL', 'BSD', 'BWP', 'BZD', 'CAD', 'CDF', 'CHF', 'CLP', 'CNY', 'COP', 'CRC', 'CVE', 'CZK', 'DJF', 'DKK',
            'DOP', 'DZD', 'EGP', 'ETB', 'EUR', 'FJD', 'FKP', 'GBP', 'GEL', 'GIP', 'GMD', 'GNF', 'GTQ', 'GYD', 'HKD', 'HNL', 'HRK',
            'HTG', 'HUF', 'IDR', 'ILS', 'INR', 'ISK', 'JMD', 'JPY', 'KES', 'KGS', 'KHR', 'KMF', 'KRW', 'KYD', 'KZT', 'LAK', 'LBP',
            'LKR', 'LRD', 'LSL', 'MAD', 'MDL', 'MGA', 'MKD', 'MMK', 'MNT', 'MOP', 'MRO', 'MUR', 'MVR', 'MWK', 'MXN', 'MYR', 'MZN',
            'NAD', 'NGN', 'NIO', 'NOK', 'NPR', 'NZD', 'PAB', 'PEN', 'PGK', 'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RSD', 'RUB',
            'RWF', 'SAR', 'SBD', 'SCR', 'SEK', 'SGD', 'SHP', 'SLL', 'SOS', 'SRD', 'STD', 'SZL', 'THB', 'TJS', 'TOP', 'TRY', 'TTD',
            'TWD', 'TZS', 'UAH', 'UGX', 'UYU', 'UZS', 'VND', 'VUV', 'WST', 'XAF', 'XCD', 'XOF', 'XPF', 'YER', 'ZAR'];
        $currencies = [];
        foreach ($codes as $c) {
            $currencies[$c] = new lang_string($c, 'core_currencies');
        }
        return $currencies;
    }
    /**
     * Get stripe amount
     * @return $Stripe ammount
     */
    public function get_stripe_amount($cost, $currency, $reverse) {
        $nodecimalcurrencies = ["bif", "clp", "djf", "gnf", "jpy", "kmf", "krw", "mga", "pyg",
            "rwf", "ugx", "vnd", "vuv", "xaf", "xof", "xpf"];
        if (!$currency) {
            $currency = 'USD';
        }
        if (in_array(strtolower($currency), $nodecimalcurrencies)) {
            return abs($cost);
        } else {
            if ($reverse) {
                return abs( (float) $cost / 100);
            } else {
                return abs( (float) $cost * 100);
            }
        }
    }
    /**
     * Return Currency as per country code
     *
     * @param integer $currency the country code
     * @return Country currency sign
     */
    public function show_currency_symbol($currency ) {
        $currencies = [
            'aed' => 'AED', 'afn' => '&#1547;', 'all' => '&#76;&#101;&#107;',
            'amd' => 'AMD', 'ang' => '&#402;', 'aoa' => 'AOA', 'ars' => '&#36;',
            'aud' => '&#36;', 'awg' => '&#402;', 'azn' => '&#1084;&#1072;&#1085;',
            'bam' => '&#75;&#77;', 'bbd' => '&#36;', 'bdt' => 'BDT', 'bgn' => '&#1083;&#1074;',
            'bhd' => 'BHD', 'bif' => 'BIF', 'bmd' => '&#36;', 'bnd' => '&#36;',
            'bob' => '&#36;&#98;', 'brl' => '&#82;&#36;', 'bsd' => '&#36;', 'btn' => 'BTN',
            'bwp' => '&#80;', 'byr' => '&#112;&#46;', 'bzd' => '&#66;&#90;&#36;',
            'cad' => '&#36;', 'cdf' => 'CDF', 'chf' => '&#67;&#72;&#70;', 'clp' => '&#36;',
            'cny' => '&#165;', 'cop' => '&#36;', 'crc' => '&#8353;', 'cuc' => 'CUC', 'cup' => '&#8369;',
            'cve' => 'CVE', 'czk' => '&#75;&#269;', 'djf' => 'DJF', 'dkk' => '&#107;&#114;',
            'dop' => '&#82;&#68;&#36;', 'dzd' => 'DZD', 'egp' => '&#163;', 'ern' => 'ERN', 'etb' => 'ETB',
            'eur' => '&#8364;', 'fjd' => '&#36;', 'fkp' => '&#163;', 'gbp' => '&#163;', 'gel' => 'GEL',
            'ggp' => '&#163;', 'ghs' => '&#162;', 'gip' => '&#163;', 'gmd' => 'GMD', 'gnf' => 'GNF',
            'gtq' => '&#81;', 'gyd' => '&#36;', 'hkd' => '&#36;', 'hnl' => '&#76;', 'hrk' => '&#107;&#110;',
            'htg' => 'HTG', 'huf' => '&#70;&#116;', 'idr' => '&#82;&#112;', 'ils' => '&#8362;',
            'imp' => '&#163;', 'inr' => '&#8377;', 'iqd' => 'IQD', 'irr' => '&#65020;', 'isk' => '&#107;&#114;',
            'jep' => '&#163;', 'jmd' => '&#74;&#36;', 'jod' => 'JOD', 'jpy' => '&#165;',
            'kes' => 'KES', 'kgs' => '&#1083;&#1074;', 'khr' => '&#6107;', 'kmf' => 'KMF', 'kpw' => '&#8361;',
            'krw' => '&#8361;', 'kwd' => 'KWD', 'kyd' => '&#36;', 'kzt' => '&#1083;&#1074;',
            'lak' => '&#8365;', 'lbp' => '&#163;', 'lkr' => '&#8360;', 'lrd' => '&#36;', 'lsl' => 'LSL',
            'lyd' => 'LYD', 'mad' => 'MAD', 'mdl' => 'MDL', 'mga' => 'MGA', 'mkd' => '&#1076;&#1077;&#1085;',
            'mmk' => 'MMK', 'mnt' => '&#8366;', 'mop' => 'MOP', 'mro' => 'MRO', 'mur' => '&#8360;',
            'mvr' => 'MVR', 'mwk' => 'MWK', 'mxn' => '&#36;', 'myr' => '&#82;&#77;', 'mzn' => '&#77;&#84;',
            'nad' => '&#36;', 'ngn' => '&#8358;', 'nio' => '&#67;&#36;', 'nok' => '&#107;&#114;', 'npr' => '&#8360;',
            'nzd' => '&#36;', 'omr' => '&#65020;', 'pab' => '&#66;&#47;&#46;', 'pen' => '&#83;&#47;&#46;',
            'pgk' => 'PGK', 'php' => '&#8369;', 'pkr' => '&#8360;', 'pln' => '&#122;&#322;', 'prb' => 'PRB',
            'pyg' => '&#71;&#115;', 'qar' => '&#65020;', 'ron' => '&#108;&#101;&#105;', 'rsd' => '&#1044;&#1080;&#1085;&#46;',
            'rub' => '&#1088;&#1091;&#1073;', 'rwf' => 'RWF', 'sar' => '&#65020;', 'sbd' => '&#36;', 'scr' => '&#8360;',
            'sdg' => 'SDG', 'sek' => '&#107;&#114;', 'sgd' => '&#36;', 'shp' => '&#163;', 'sll' => 'SLL', 'sos' => '&#83;',
            'srd' => '&#36;', 'ssp' => 'SSP', 'std' => 'STD', 'syp' => '&#163;', 'szl' => 'SZL', 'thb' => '&#3647;', 'tjs' => 'TJS',
            'tmt' => 'TMT', 'tnd' => 'TND', 'top' => 'TOP', 'try' => '&#8378;', 'ttd' => '&#84;&#84;&#36;',
            'twd' => '&#78;&#84;&#36;',
            'tzs' => 'TZS', 'uah' => '&#8372;', 'ugx' => 'UGX', 'usd' => '&#36;', 'uyu' => '&#36;&#85;',
            'uzs' => '&#1083;&#1074;',
            'vef' => '&#66;&#115;', 'vnd' => '&#8363;', 'vuv' => 'VUV', 'wst' => 'WST', 'xaf' => 'XAF',
            'xcd' => '&#36;', 'xof' => 'XOF',
            'xpf' => 'XPF', 'yer' => '&#65020;', 'zar' => '&#82;', 'zmw' => 'ZMW',
        ];
        if ( array_key_exists( $currency, $currencies) ) {
            $symbol = $currencies[$currency];
        } else {
            $symbol = $currency;
        }
        return $symbol;
    }
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
        return ($instance->status == ENROL_INSTANCE_ENABLED);
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
             throw new coding_exception('Invalid enrol instance type!');
        }
        $context = context_course::instance($instance->courseid);
        if (has_capability('enrol/stripepayment:manage', $context)) {
            $managelink = new moodle_url('/enrol/editinstance.php',
            ['courseid' => $instance->courseid, 'id' => $instance->id, 'type' => 'stripepayment']);
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
            throw new coding_exception('invalid enrol instance!');
        }
        $context = context_course::instance($instance->courseid);
        $icons = [];
        if (has_capability('enrol/stripepayment:manage', $context)) {
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
     * Creates course enrol form, checks if form submitted
     * and enrols user if necessary. It can also redirect.
     *
     * @param stdClass $instance
     * @return string html text, usually a form in a text box
     */
    public function enrol_page_hook(stdClass $instance) {
        global $CFG, $USER, $OUTPUT, $DB, $PAGE;  // Added $PAGE to global declarations.

        $enrolstatus = $this->can_stripepayment_enrol($instance);
        if (!$enrolstatus) {
            $notification = new \core\output\notification(get_string('maxenrolledreached', 'enrol_stripepayment'), 'error', false);
            $notification->set_extra_classes(['mb-0']);
            $enrolpage = new enrol_page(
                instance: $instance,
                header: $this->get_instance_name($instance),
                body: $OUTPUT->render($notification));
            return $OUTPUT->render($enrolpage);
        }

        if ($DB->record_exists('user_enrolments', ['userid' => $USER->id, 'enrolid' => $instance->id])) {
            return '';
        }

        // Check enrollment date restrictions and show appropriate messages
        if ($instance->enrolstartdate != 0 && $instance->enrolstartdate > time()) {
            $notification = new \core\output\notification(
                get_string('canntenrolearly', 'enrol_stripepayment', userdate($instance->enrolstartdate)),
                'info',
                false
            );
            $notification->set_extra_classes(['mb-0']);
            $enrolpage = new enrol_page(
                instance: $instance,
                header: $this->get_instance_name($instance),
                body: $OUTPUT->render($notification)
            );
            return $OUTPUT->render($enrolpage);
        }

        if ($instance->enrolenddate != 0 && $instance->enrolenddate < time()) {
            $notification = new \core\output\notification(
                get_string('canntenrollate', 'enrol_stripepayment', userdate($instance->enrolenddate)),
                'error',
                false
            );
            $notification->set_extra_classes(['mb-0']);
            $enrolpage = new enrol_page(
                instance: $instance,
                header: $this->get_instance_name($instance),
                body: $OUTPUT->render($notification)
            );
            return $OUTPUT->render($enrolpage);
        }

        $course = $DB->get_record('course', ['id' => $instance->courseid]);
        $context = context_course::instance($course->id);

        if ( (float) $instance->cost <= 0 ) {
            $cost = (float) $this->get_config('cost');
        } else {
            $cost = (float) $instance->cost;
        }

        $name = $this->get_instance_name($instance);
        $localisedcost = format_float($cost, 2, true);
        $cost = format_float($cost, 2, false);
        // Prepare data for the template - always use the same template regardless of cost
        $templatedata = [
            'currency' => $instance->currency,
            'cost' => $localisedcost,
            'coursename' => format_string($course->fullname, true, ['context' => $context]),
            'instanceid' => $instance->id,
            'wwwroot' => $CFG->wwwroot,
            'enrolbtncolor' => $this->get_config('enrolbtncolor'),
            'enablecouponsection' => $this->get_config('enablecouponsection'),
        ];

        // Render the payment form using the template.
        $body = $OUTPUT->render_from_template('enrol_stripepayment/enrol_page', $templatedata);

        // Set up the required JavaScript for Stripe integration.
        $plugin = enrol_get_plugin('stripepayment');
        $publishablekey = $plugin->get_config('publishablekey');
        $PAGE->requires->js_call_amd('enrol_stripepayment/stripe_payment', 'stripe_payment',
            [
                $USER->id,
                $publishablekey,
                null, // Couponid starts as null.
                $instance->id,
                get_string("pleasewait", "enrol_stripepayment"),
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
     * Creates can stripepayament enrol.
     *
     * @param stdClass $instance
     * @return string html text, usually a form in a text box
     */
    public function can_stripepayment_enrol(stdClass $instance) {
        global $DB;
        if ($instance->customint3 > 0) {
            // Max enrol limit specified.
            $count = $DB->count_records('user_enrolments', ['enrolid' => $instance->id]);
            if ($count >= $instance->customint3) {
                // Bad luck, no more stripepayment enrolments here.
                return false;
            }
        }
        return true;
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
            $enrol = $this->get_name();
            return get_string('pluginname', 'enrol_'.$enrol) . $role;
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
        if ($this->allow_unenrol($instance) && has_capability("enrol/stripepayment:unenrol", $context)) {
            $url = new moodle_url('/enrol/unenroluser.php', $params);
            $actions[] = new user_enrolment_action(new pix_icon('t/delete', ''),
            get_string('unenrol', 'enrol'), $url, ['class' => 'unenrollink', 'rel' => $ue->id]);
        }
        if ($this->allow_manage($instance) && has_capability("enrol/stripepayment:manage", $context)) {
            $url = new moodle_url('/enrol/editenrolment.php', $params);
            $actions[] = new user_enrolment_action(new pix_icon('t/edit', ''),
            get_string('edit'), $url, ['class' => 'editenrollink', 'rel' => $ue->id]);
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

        $options = [ENROL_INSTANCE_ENABLED  => get_string('yes'),
                         ENROL_INSTANCE_DISABLED => get_string('no')];
        $mform->addElement('select', 'status', get_string('status', 'enrol_stripepayment'), $options);
        $mform->setDefault('status', $this->get_config('status'));

        $costarray = [];
        $costarray[] =& $mform->createElement('text', 'cost', get_string('cost', 'enrol_stripepayment'), ['size' => 4]);
        $mform->setDefault('cost', format_float($this->get_config('cost'), 2, true));
        $mform->setType('cost', PARAM_FLOAT);
        $mform->addGroup($costarray, 'costar', get_string('cost', 'enrol_stripepayment'), [' '], false);

        // Currency select.
        $currency = $this->get_currencies();
        $mform->addElement('select', 'currency', get_string('currency', 'enrol_stripepayment'), $currency);
        $mform->setDefault('currency', $this->get_config('currency'));

        if ($instance->id) {
            $roles = get_default_enrol_roles($context, $instance->roleid);
        } else {
            $roles = get_default_enrol_roles($context, $this->get_config('roleid'));
        }
        // Assign role.
        $mform->addElement('select', 'roleid', get_string('assignrole', 'enrol_stripepayment'), $roles);
        $mform->setDefault('roleid', $this->get_config('roleid'));

        $mform->addElement('text', 'customint3', get_string('maxenrolled', 'enrol_stripepayment'));
        $mform->setDefault('maxenrolled', 'customint3');
        $mform->addHelpButton('customint3', 'maxenrolled', 'enrol_stripepayment');
        $mform->setType('customint3', PARAM_INT);

        $mform->addElement('duration', 'enrolperiod', get_string('enrolperiod', 'enrol_stripepayment'),
        ['optional' => true, 'defaultunit' => 86400]);
        $mform->setDefault('enrolperiod', $this->get_config('enrolperiod'));
        $mform->addHelpButton('enrolperiod', 'enrolperiod', 'enrol_stripepayment');

        $mform->addElement('date_time_selector', 'enrolstartdate', get_string('enrolstartdate', 'enrol_stripepayment'),
        ['optional' => true]);
        $mform->setDefault('enrolstartdate', 0);
        $mform->addHelpButton('enrolstartdate', 'enrolstartdate', 'enrol_stripepayment');

        $mform->addElement('date_time_selector', 'enrolenddate', get_string('enrolenddate', 'enrol_stripepayment'),
        ['optional' => true]);
        $mform->setDefault('enrolenddate', 0);
        $mform->addHelpButton('enrolenddate', 'enrolenddate', 'enrol_stripepayment');

        if (enrol_accessing_via_instance($instance)) {
            $mform->addElement('static', 'selfwarn', get_string('instanceeditselfwarning', 'core_enrol'),
            get_string('instanceeditselfwarningtext', 'core_enrol'));
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
     * @return void
     */
    public function edit_instance_validation($data, $files, $instance, $context) {
        $errors = [];

        if (!empty($data['enrolenddate']) && $data['enrolenddate'] < $data['enrolstartdate']) {
            $errors['enrolenddate'] = get_string('enrolenddaterror', 'enrol_stripepayment');
        }

        // Handle cost field - it might be in a group called 'costar'
        $costvalue = null;
        $costfieldexists = false;

        // Debug: Log the data structure
        error_log("Stripepayment validation data: " . print_r($data, true));

        if (isset($data['costar']['cost'])) {
            $costvalue = $data['costar']['cost'];
            $costfieldexists = true;
        } elseif (isset($data['cost'])) {
            $costvalue = $data['cost'];
            $costfieldexists = true;
        } elseif (isset($data['costar']) && is_array($data['costar'])) {
            // Check if costar is an array with numeric index
            if (isset($data['costar'][0])) {
                $costvalue = $data['costar'][0];
                $costfieldexists = true;
            }
        }

        if ($costfieldexists) {
            // Handle empty cost value (treat as 0)
            if ($costvalue === '' || $costvalue === null) {
                $cost = 0.0;
            } else {
                $cost = str_replace(get_string('decsep', 'langconfig'), '.', $costvalue);
                if (!is_numeric($cost)) {
                    $errors['costar'] = get_string('costerror', 'enrol_stripepayment');
                    return $errors; // Return early if not numeric
                }
                $cost = (float)$cost;
            }

            // Debug: Log the cost value for troubleshooting
            error_log("Stripepayment validation: cost = " . $cost . ", costvalue = " . var_export($costvalue, true));

            // Now validate the cost value
            $currency = isset($data['currency']) ? $data['currency'] : 'USD';

            // Minimum amounts for different currencies
            $minamount = [
                'USD' => 0.5, 'AED' => 2.0, 'AUD' => 0.5, 'BGN' => 1.0, 'BRL' => 0.5,
                'CAD' => 0.5, 'CHF' => 0.5, 'CZK' => 15.0, 'DKK' => 2.5, 'EUR' => 0.5,
                'GBP' => 0.3, 'HKD' => 4.0, 'HUF' => 175.0, 'INR' => 0.5, 'JPY' => 50,
                'MXN' => 10, 'MYR' => 2, 'NOK' => 3.0, 'NZD' => 0.5, 'PLN' => 2.0,
                'RON' => 2.0, 'SEK' => 3.0, 'SGD' => 0.5, 'THB' => 10,
            ];

            $minamount = isset($minamount[$currency]) ? $minamount[$currency] : 0.5;

            // Check if cost is 0 or less (not allowed)
            if ($cost <= 0) {
                $errors['costar'] = get_string('costzeroerror', 'enrol_stripepayment');
            }
            // Check if cost is below minimum threshold
            else if ($cost < $minamount) {
                $errors['costar'] = get_string('costminimumerror', 'enrol_stripepayment',
                    $currency . ' ' . number_format($minamount, 2));
            }
        }
        return $errors;
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
     * Add new instance of enrol plugin.
     * @param object $course
     * @param array $fields instance fields
     * @return int id of new instance, null if can not be created
     */
    public function add_instance($course, ?array $fields = null) {
        if ($fields && !empty($fields['cost'])) {
            $fields['cost'] = unformat_float($fields['cost']);
        }
        return parent::add_instance($course, $fields);
    }
}


/**
 * class for helping built the admin setting form
 */
class admin_enrol_stripepayment_configtext extends admin_setting_configtext {
    /**
     * Writes the setting value to the configuration.
     *
     * Performs validation and handles special cases for webservice token and empty integer values.
     *
     * @param string $data The submitted setting value.
     * @return string Empty string on success, or an error message string on failure.
     */
    public function write_setting($data) {
        if ($this->name == 'webservice_token' && $data == '') {
            return get_string('tokenemptyerror', 'enrol_stripepayment');
        }
        if ($this->paramtype === PARAM_INT && $data === '') {
            // Don't complain if '' used instead of 0.
            $data = 0;
        }
        $validated = $this->validate($data);
        if ($validated !== true) {
            return $validated;
        }
        return ($this->config_write($this->name, $data) ? '' : get_string('errorsetting', 'admin'));
    }
    /**
     * Validate data before storage.
     *
     * @param string $data The string to be validated.
     * @return bool|string true for success or error string if invalid.
     */
    public function validate($data) {
        $cleaned = clean_param($data, PARAM_TEXT);
        if ($cleaned === '') {
            return get_string('required');
        }
        return parent::validate($data);
    }
}
