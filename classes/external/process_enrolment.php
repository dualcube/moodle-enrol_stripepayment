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
 * External process payment for stripepayment
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2025 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_stripepayment\external;
use context_course;
use core\exception\moodle_exception;
use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use enrol_stripepayment\enrolment_notifier;
use enrol_stripepayment\stripe_client;
use enrol_stripepayment\util;
use moodle_url;
use stdClass;

/**
 * External process payment for stripepayment
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2025 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class process_enrolment extends external_api {
    /**
     * function for define parameter type for process_payment
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            [
                'sessionid' => new external_value(PARAM_TEXT, 'The Stripe Checkout session id'),
                'userid' => new external_value(PARAM_INT, 'The authenticated user completing checkout'),
            ]
        );
    }

    /**
     * function for define return type for process_payment
     */
    public static function execute_returns() {
        return new external_single_structure(
            [
                'status' => new external_value(PARAM_RAW, 'status: success or error'),
                'redirecturl' => new external_value(PARAM_URL, 'Where the browser should go next'),
            ]
        );
    }

    /**
     * After the user returns from Stripe Checkout, retrieve the session, confirm it
     * was genuinely paid at the price we expected, and enrol the student.
     *
     * instanceid and couponid are deliberately not accepted as parameters here: both
     * are read back from the Checkout Session's own metadata (set server-side, by us,
     * when the session was created in process_payment) rather than from anything the
     * browser could have appended to the return URL.
     *
     * This is an AJAX webservice function like apply_coupon and process_payment - called
     * over core/ajax with the user's own session, never a site-wide token - so, unlike a
     * plain page, it must return data rather than redirect()/echo output itself; the
     * caller (amd/src/process_enrolment.js) does the actual browser navigation.
     *
     * @param string $sessionid Stripe Checkout session id
     * @param int $userid The authenticated user completing checkout
     * @return array
     */
    public static function execute($sessionid, $userid) {
        global $PAGE, $DB;
        $checkoutsession = stripe_client::stripe_api_request(
            'checkout_session_retrieve',
            $sessionid
        );
        $instanceid = (int) ($checkoutsession['metadata']['instanceid'] ?? 0);
        $couponid = $checkoutsession['metadata']['couponid'] ?? '';
        $chargeinfo = self::extract_charge_info($checkoutsession);
        $user = \core_user::get_user($userid);
        $instance = $DB->get_record("enrol", ["id" => $instanceid, "status" => 0]);
        if (!$instance) {
            throw new moodle_exception('enrollmentinstancenotfound', 'enrol_stripepayment');
        }
        $course = get_course($instance->courseid);
        $context = context_course::instance($course->id);
        $enrolmentdata = self::prepare_enrollment_data(
            $chargeinfo,
            $couponid,
            $instance,
            $course,
            $user,
            $checkoutsession
        );

        if (!self::validate_payment_status($checkoutsession, $enrolmentdata)) {
            return [
                'status' => 'error',
                'redirecturl' => (new moodle_url('/'))->out(false),
            ];
        }

        $PAGE->set_context($context);
        try {
            self::enrol_user_to_course($instance, $user);
            $DB->insert_record("enrol_stripepayment", $enrolmentdata);
            enrolment_notifier::send_enrollment_notifications($course, $context, $user, util::get_core());
            return self::build_success_result($course, $context, $user);
        } catch (moodle_exception $e) {
            enrolment_notifier::message_stripepayment_error_to_admin($e->getMessage(), ['sessionid' => $sessionid]);
            throw new moodle_exception('invalidtransaction', 'enrol_stripepayment', '', $e->getMessage());
        }
    }

    /**
     * Extract charge info
     *
     * @param array $checkoutsession
     * @return object
     */
    private static function extract_charge_info($checkoutsession) {
        // If 100% discount → no payment_intent.
        if (empty($checkoutsession['payment_intent'])) {
            return (object)[
                'charge'        => null,
                'email'         => $checkoutsession['customer_details']['email'] ?? '',
                'paymentstatus' => $checkoutsession['payment_status'],
                'txnid'         => $checkoutsession['id'],
            ];
        }

        $charge = stripe_client::stripe_api_request(
            'payment_intent_retrieve',
            $checkoutsession['payment_intent']
        );

        return (object)[
            'charge'        => $charge,
            'email'         => $charge['charges']['data'][0]['receipt_email']
                                ?? ($checkoutsession['customer_details']['email'] ?? ''),
            'paymentstatus' => $charge['status'],
            'txnid'         => $charge['id'],
        ];
    }

    /**
     * Prepare enrollment data
     * @param object $chargeinfo
     * @param number $couponid
     * @param object $instance
     * @param object $course
     * @param object $user
     * @param array $checkoutsession
     * @return object
     */
    private static function prepare_enrollment_data(
        $chargeinfo,
        $couponid,
        $instance,
        $course,
        $user,
        $checkoutsession
    ) {
        $data = new stdClass();

        $data->couponid       = $couponid;
        $data->courseid       = $instance->courseid;
        $data->instanceid     = $instance->id;
        $data->userid         = $user->id;
        $data->timeupdated    = time();
        $data->customeremail  = $user->email;
        $data->customerid     = $checkoutsession['customer'];
        $data->txnid          = $chargeinfo->txnid;
        $data->price          = $chargeinfo->charge
            ? util::from_stripe_amount($chargeinfo->charge['amount'], $chargeinfo->charge['currency'] ?? 'USD')
            : 0;
        $data->memo           = $chargeinfo->charge['payment_method'] ?? 'none';
        $data->paymentstatus  = $chargeinfo->paymentstatus;
        $data->pendingreason  = $chargeinfo->charge['last_payment_error']['message'] ?? 'NA';
        $data->reasoncode     = $chargeinfo->charge['last_payment_error']['code'] ?? 'NA';
        $data->itemname       = $course->fullname;
        $data->paymenttype    = $chargeinfo->charge ? 'stripe' : 'free';

        return $data;
    }

    /**
     * Validate payment status
     *
     * Besides the status/course/user checks this plugin has always made, this also
     * confirms Stripe actually captured the exact amount and currency recorded in the
     * session's own metadata at creation time (see process_payment::get_session_params) -
     * without this, nothing stops a tampered checkout flow from paying less than the
     * instance's real price and still being treated as a valid purchase.
     *
     * @param array $checkoutsession
     * @param object $enrolmentdata
     * @return bool
     */
    private static function validate_payment_status($checkoutsession, $enrolmentdata) {
        global $DB;
        if (
            $checkoutsession['payment_status'] === 'paid'
            && $checkoutsession['metadata']['courseid'] == $enrolmentdata->courseid
            && $checkoutsession['metadata']['userid'] == $enrolmentdata->userid
            && isset($checkoutsession['metadata']['expectedamount'], $checkoutsession['metadata']['expectedcurrency'])
            && (int) $checkoutsession['amount_total'] === (int) $checkoutsession['metadata']['expectedamount']
            && strtoupper($checkoutsession['currency']) === strtoupper($checkoutsession['metadata']['expectedcurrency'])
            && !$DB->record_exists('enrol_stripepayment', ['txnid' => $enrolmentdata->txnid])
        ) {
            return true;
        }

        enrolment_notifier::message_stripepayment_error_to_admin(
            "Payment status: " . $checkoutsession['payment_status'],
            $enrolmentdata,
        );

        return false;
    }

    /**
     * Enrol user to course
     * @param object $instance
     * @param object $user
     */
    private static function enrol_user_to_course($instance, $user) {
        $timestart = time();
        $timeend   = $instance->enrolperiod
            ? $timestart + $instance->enrolperiod
            : 0;

        util::get_core()->enrol_user(
            $instance,
            $user->id,
            $instance->roleid,
            $timestart,
            $timeend
        );
    }

    /**
     * Build the success result once enrol_user() has run.
     *
     * \core\notification::success()/warning() queue a session-flash message - the same
     * mechanism redirect($url, $message) relies on - so the message still shows up once
     * the client-side redirect (in amd/src/process_enrolment.js) lands on the destination
     * page, even though this function itself never redirects or renders anything.
     *
     * @param object $course
     * @param object $context
     * @param object $user
     * @return array
     */
    private static function build_success_result($course, $context, $user) {
        $destination = new moodle_url('/course/view.php', ['id' => $course->id]);
        $fullname = format_string($course->fullname, true, ['context' => $context]);

        if (is_enrolled($context, $user, '', true)) {
            \core\notification::success(get_string('paymentthanks', '', $fullname));
        } else {
            $orderdetails = (object)[
                'teacher'  => get_string('defaultcourseteacher'),
                'fullname' => $fullname,
            ];
            \core\notification::warning(get_string('paymentsorry', '', $orderdetails));
        }

        return [
            'status' => 'success',
            'redirecturl' => $destination->out(false),
        ];
    }
}
