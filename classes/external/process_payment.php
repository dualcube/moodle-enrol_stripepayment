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
 * External enrol function for stripepayment
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2025 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_stripepayment\external;
use core\exception\moodle_exception;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use enrol_stripepayment\stripe_client;
use enrol_stripepayment\util;
use context_course;
use moodle_url;

/**
 * External enrol function for stripepayment
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2025 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_payment extends external_api {
    /**
     * define parameter type of stripepayment_enrol
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            [
                'couponid' => new external_value(PARAM_RAW, 'Coupon code to apply at checkout, or empty for none'),
                'instanceid' => new external_value(PARAM_INT, 'The enrol instance id'),
            ]
        );
    }

    /**
     * return type of stripe js method
     */
    public static function execute_returns() {
        return new external_single_structure(
            [
                'status' => new external_value(PARAM_RAW, 'status: true if success or 0 if failure'),
                'redirecturl' => new external_value(PARAM_URL, 'Stripe Checkout URL', VALUE_OPTIONAL),
                'error' => new external_single_structure(
                    [
                        'message' => new external_value(PARAM_TEXT, 'Error message', VALUE_OPTIONAL),
                    ],
                    VALUE_OPTIONAL
                ),
            ]
        );
    }

    /**
     * process payment using stripe checkout session
     *
     * @param string $couponid Coupon code
     * @param int $instanceid Enrol instance id
     * @return array
     */
    public static function execute($couponid, $instanceid) {
        $instance = self::get_enrol_instance($instanceid);
        self::require_enrolable($instance);
        $sessionparams = self::get_session_params($couponid, $instance);
        $session = stripe_client::stripe_api_request('checkout_session_create', '', $sessionparams);
        return [
            'status' => 'success',
            'redirecturl' => $session['url'],
            'error' => [],
        ];
    }

    /**
     * Load an enabled enrol instance by id.
     *
     * The client only ever gets to say which instance it means (by id) - every
     * price-relevant field (cost, currency) is then read back from this record, never
     * from the request, so a tampered client value can't change what gets charged.
     *
     * @param int $instanceid
     * @return \stdClass
     */
    private static function get_enrol_instance($instanceid) {
        global $DB;
        $instance = $DB->get_record('enrol', ['id' => $instanceid, 'enrol' => 'stripepayment'], '*', IGNORE_MISSING);
        if (!$instance) {
            throw new moodle_exception('enrollmentinstancenotfound', 'enrol_stripepayment');
        }
        return $instance;
    }

    /**
     * Re-check, server-side, the same eligibility rules the enrolment page itself enforces
     * before showing the checkout button - a direct webservice call must not be able to
     * start a checkout the UI would have refused to offer.
     *
     * @param \stdClass $instance
     */
    private static function require_enrolable($instance) {
        if ($instance->status != ENROL_INSTANCE_ENABLED) {
            throw new moodle_exception('paymentmethodnotfound', 'enrol_stripepayment');
        }
        if ($instance->enrolstartdate != 0 && $instance->enrolstartdate > time()) {
            throw new moodle_exception('canntenrolearly', 'enrol_stripepayment', '', userdate($instance->enrolstartdate));
        }
        if ($instance->enrolenddate != 0 && $instance->enrolenddate < time()) {
            throw new moodle_exception('canntenrollate', 'enrol_stripepayment', '', userdate($instance->enrolenddate));
        }
        if (!util::can_more_user_enrol($instance)) {
            throw new moodle_exception('maxenrolledreached', 'enrol_stripepayment');
        }
    }

    /**
     * Get checkout session params
     *
     * @param string $couponid Coupon code
     * @param \stdClass $instance enrol instance record
     * @return array
     */
    private static function get_session_params($couponid, $instance) {
        global $USER;
        $course = get_course($instance->courseid);
        $context = context_course::instance($course->id);
        $cost = util::get_instance_cost($instance);
        $currency = util::get_instance_currency($instance);
        $amount = util::to_stripe_amount($cost, $currency);
        $coursename = format_string($course->fullname, true, ['context' => $context]);
        $customerid = self::get_stripe_customer_id($USER);
        $sessionparams = [
            'customer' => $customerid,
            'payment_intent_data' => ['description' => get_string('intentdescription', 'enrol_stripepayment', $coursename)],
            'payment_method_types' => ['card'],
            'line_items' => [[
                'price_data' => [
                    'product_data' => [
                        'name' => $coursename,
                        'metadata' => ['product_id' => $instance->courseid],
                        'description' => get_string('productdescription', 'enrol_stripepayment', $coursename),
                    ],
                    'unit_amount' => $amount,
                    'currency' => $currency,
                ],
                'quantity' => 1,
            ]],
            'discounts' => [['coupon' => $couponid]],
            'metadata' => [
                'courseshortname' => format_string($course->shortname, true, ['context' => $context]),
                'courseid' => $course->id,
                'instanceid' => $instance->id,
                'couponid' => $couponid,
                'userid' => $USER->id,
                // Recorded here, server-side, at the moment the price was computed, so
                // process_enrolment can later confirm Stripe actually captured this exact
                // amount rather than trusting anything echoed back from the browser.
                'expectedamount' => $amount,
                'expectedcurrency' => $currency,
            ],
            'mode' => 'payment',
            // Straight back to the course page, not a page of this plugin's own: there's
            // no plugin-owned landing page in this flow, only
            // enrol_stripepayment_before_footer() (lib.php) picking the session id back
            // up from the URL on whatever page the browser lands on. Stripe substitutes
            // the literal "{CHECKOUT_SESSION_ID}" token itself; it must reach Stripe
            // un-encoded, so it's appended after moodle_url has built the rest of the URL
            // rather than being passed in as one of its params.
            'success_url' => new moodle_url('/course/view.php', ['id' => $instance->courseid])
                . '&stripe_session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => new moodle_url('/course/view.php', ['id' => $instance->courseid]),
        ];

        return $sessionparams;
    }

    /**
     * Get stripe customer id
     *
     * @param object $user User object
     * @return string
     */
    public static function get_stripe_customer_id($user) {
        global $DB;
        $customerrecord = $DB->get_record('enrol_stripepayment', ['customeremail' => $user->email], '*', IGNORE_MISSING);
        $customerid = $customerrecord?->customerid;

        if ($customerid) {
            try {
                stripe_client::stripe_api_request('customer_retrieve', $customerid);
            } catch (\Exception $e) {
                $customerid = null;
            }
        } else {
            $customers = stripe_client::stripe_api_request('customer_list', '', [
                'email' => $user->email,
                'limit' => 1,
            ]);
            if (!empty($customers['data'])) {
                $customerid = $customers['data'][0]['id'] ?? null;
            } else {
                $newcustomer = stripe_client::stripe_api_request('customer_create', '', [
                    'email' => $user->email,
                    'name' => fullname($user),
                ]);
                $customerid = $newcustomer['id'] ?? null;
            }
        }

        return $customerid;
    }
}
