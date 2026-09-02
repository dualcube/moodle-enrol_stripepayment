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
 * Course-listing and course-page presentation for the Stripe enrolment plugin.
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2025 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_stripepayment;

use context_course;
use core\exception\moodle_exception;
use core\output\notification;
use core_enrol\output\enrol_page;
use moodle_url;
use navigation_node;
use pix_icon;
use stdClass;

/**
 * Everything about how enrol_stripepayment_plugin instances are shown to users:
 * the "Enrolment methods" page (icons, admin nav/edit links, instance naming,
 * the newinstance link) and the course-page checkout itself, including its
 * eligibility checks in {@see self::enrol_page_hook()}.
 *
 * Extends {@see plugin_base} - see that class's docblock for why this is a
 * chain of small classes rather than one big one. Navigation and the checkout
 * page live together in this one class (rather than each in their own) because
 * PHPMD's DepthOfInheritance rule caps how many links this chain can have - see
 * {@see instance_lifecycle_base}, the other merged link.
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2025 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class presentation_base extends plugin_base {
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
}
