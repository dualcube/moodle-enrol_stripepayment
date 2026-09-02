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
 * Course-page checkout rendering for the Stripe enrolment plugin.
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2025 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_stripepayment;

use context_course;
use core\output\notification;
use core_enrol\output\enrol_page;
use stdClass;

/**
 * Renders the "enrol me" checkout on the course page: the eligibility
 * checks in {@see self::enrol_page_hook()}, the plain notification shown
 * when checkout isn't available, and the actual Stripe checkout markup.
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
trait payment_page_trait {
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
