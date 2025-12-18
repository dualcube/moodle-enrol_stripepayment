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
                'sessionid' => new external_value(PARAM_TEXT, 'The item id to operate on'),
                'userid' => new external_value(PARAM_INT, 'Update data user id'),
                'couponid'  => new external_value(PARAM_RAW, 'The item id to operate coupon id'),
                'instanceid'  => new external_value(PARAM_INT, 'The item id to operate instance id'),
            ]
        );
    }

    /**
     * function for define return type for process_payment
     */
    public static function execute_returns() {
        return new external_single_structure(
            [
                'status' => new external_value(PARAM_RAW, 'status: true if success'),
            ]
        );
    }

    /**
     * after creating checkout charge the payment intent and after payment enrol the student to the course
     * @param number $sessionid
     * @param number $userid
     * @param number $couponid
     * @param number $instanceid
     */
    public static function execute($sessionid, $userid, $couponid, $instanceid) {
        global $PAGE, $DB;
        $checkoutsession = util::stripe_api_request(
            'checkout_session_retrieve',
            $sessionid
        );
        $chargeinfo = self::extract_charge_info($checkoutsession);
        $user = \core_user::get_user($userid);
        $instance = $DB->get_record("enrol", ["id" => $instanceid, "status" => 0]);
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

        if (self::validate_payment_status($checkoutsession, $enrolmentdata)) {
            $PAGE->set_context($context);
            try {
                self::enrol_user_to_course($instance, $user);
                $DB->insert_record("enrol_stripepayment", $enrolmentdata);
                util::send_enrollment_notifications($course, $context, $user, util::get_core());
                self::redirect_user_to_course($course, $context, $user);
            } catch (moodle_exception $e) {
                util::message_stripepayment_error_to_admin($e->getMessage(), ['sessionid' => $sessionid]);
                throw new moodle_exception('invalidtransaction', 'enrol_stripepayment', '', $e->getMessage());
            }
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

        $charge = util::stripe_api_request(
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
        $data->price          = $chargeinfo->charge ? ($chargeinfo->charge['amount'] / 100) : 0;
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
     * @param array $checkoutsession
     * @param object $enrolmentdata
     */
    private static function validate_payment_status($checkoutsession, $enrolmentdata) {
        global $DB;
        if (
            $checkoutsession['payment_status'] === 'paid'
            && $checkoutsession['metadata']['courseid'] == $enrolmentdata->courseid
            && $checkoutsession['metadata']['userid'] == $enrolmentdata->userid
            && !$DB->record_exists('enrol_stripepaymentpro', ['txnid' => $enrolmentdata->txnid])
        ) {
            return true;
        }

        util::message_stripepayment_error_to_admin(
            "Payment status: " . $checkoutsession['payment_status'],
            $enrolmentdata,
        );

        redirect(new moodle_url('/'));
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
     * Redirect user to course page
     * @param object $course
     * @param object $context
     * @param object $user
     */
    private static function redirect_user_to_course($course, $context, $user) {
        global $PAGE, $OUTPUT;

        $destination = new moodle_url('/course/view.php', ['id' => $course->id]);
        $fullname = format_string($course->fullname, true, ['context' => $context]);

        if (is_enrolled($context, $user, '', true)) {
            redirect($destination, get_string('paymentthanks', '', $fullname));
        }

        $PAGE->set_url($destination);
        echo $OUTPUT->header();
        $orderdetails = (object)[
            'teacher'  => get_string('defaultcourseteacher'),
            'fullname' => $fullname,
        ];
        notice(get_string('paymentsorry', '', $orderdetails), $destination);
    }
}
