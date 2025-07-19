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
 * External library for stripepayment
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2019 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once("$CFG->libdir/externallib.php"); // Support for previous version of moodle 4.2.
require_once("$CFG->libdir/enrollib.php");
require_once('vendor/stripe/stripe-php/init.php');
use Stripe\Stripe as Stripe;
use Stripe\Coupon as Coupon;
use Stripe\Customer as Customer;
use Stripe\Checkout\Session as Session;
use Stripe\PaymentIntent as PaymentIntent;

/**
 * External library for stripepayment
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2019 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_enrol_stripepayment_external extends external_api {

    /**
     * Parameter for couponsettings function
     */
    public static function stripepayment_applycoupon_parameters() {
        return new external_function_parameters(
            [
                'couponid' => new external_value(PARAM_RAW, 'The coupon id to operate on'),
                'instanceid' => new external_value(PARAM_RAW, 'Update instance id'),
            ]
        );
    }

    /**
     * return type of couponsettings functioin
     */
    public static function stripepayment_applycoupon_returns() {
        return new external_single_structure(
            [
                'status' => new external_value(PARAM_RAW, 'status: true if success'),
                'couponname' => new external_value(PARAM_RAW, 'coupon name', VALUE_OPTIONAL),
                'coupon_type' => new external_value(PARAM_RAW, 'coupon type: percent_off or amount_off', VALUE_OPTIONAL),
                'discountvalue' => new external_value(PARAM_RAW, 'discount value', VALUE_OPTIONAL),
                'originalcost' => new external_value(PARAM_RAW, 'original cost before discount', VALUE_OPTIONAL),
                'currency' => new external_value(PARAM_RAW, 'currency code', VALUE_OPTIONAL),
                'discountamount' => new external_value(PARAM_RAW, 'discount amount', VALUE_OPTIONAL),
                'uistate' => new external_value(PARAM_RAW, 'UI state: paid|error', VALUE_OPTIONAL),
                'message' => new external_value(PARAM_RAW, 'provides message', VALUE_OPTIONAL),
                'showsections' => new external_single_structure([
                    'paidenrollment' => new external_value(PARAM_BOOL, 'show payment button'),
                    'discountsection' => new external_value(PARAM_BOOL, 'show discount section'),
                ], 'sections to show/hide', VALUE_OPTIONAL),
            ]
        );
    }

    /**
     * function for couponsettings with validation
     * @param string $couponid
     * @param int $instanceid
     * @return array
     */
    public static function stripepayment_applycoupon($couponid, $instanceid) {
        global $DB;

        // Enhanced input validation.
        if (empty($couponid) || trim($couponid) === '') {
            throw new invalid_parameter_exception('Coupon code cannot be empty');
        }

        if (!is_numeric($instanceid) || $instanceid <= 0) {
            throw new invalid_parameter_exception('Invalid instance ID format');
        }

        $plugin = enrol_get_plugin('stripepayment');
        $plugininstance = $DB->get_record("enrol", ["id" => $instanceid, "status" => 0]);
        if (!$plugininstance) {
            throw new invalid_parameter_exception('Enrollment instance not found or disabled');
        }

        // Validate Stripe configuration.
        $secretkey = $plugin->get_config('secretkey');
        if (empty($secretkey)) {
            throw new invalid_parameter_exception('Stripe configuration incomplete');
        }

        $originalcost = (float)$plugininstance->cost > 0 ? (float)$plugininstance->cost : (float)$plugin->get_config('cost');
        $cost = $originalcost;
        $currency = $plugininstance->currency ? $plugininstance->currency : 'USD';

        $cost = format_float($cost, 2, false);
        $originalcost = format_float($originalcost, 2, false);

        Stripe::setApiKey($secretkey);

        $couponname = '';
        $coupontype = '';
        $discountvalue = 0;
        $discountamount = 0;

        try {
            $coupon = Coupon::retrieve($couponid);

            // Enhanced coupon validation.
            if (!$coupon || !$coupon->valid) {
                throw new Exception(get_string('invalidcoupon', 'enrol_stripepayment'));
            }

            // Check if coupon has expired.
            if (isset($coupon->redeem_by) && $coupon->redeem_by < time()) {
                throw new Exception('Coupon has expired');
            }

            // Check if coupon has usage limits.
            if (isset($coupon->max_redemptions) && isset($coupon->times_redeemed) &&
                $coupon->times_redeemed >= $coupon->max_redemptions) {
                throw new Exception('Coupon usage limit exceeded');
            }

            $couponname = isset($coupon->name) ? $coupon->name : $couponid;

            if (isset($coupon->percent_off)) {
                $discountamount = $cost * ($coupon->percent_off / 100);
                $cost -= $discountamount;
                $coupontype = 'percent_off';
                $discountvalue = $coupon->percent_off;
            } else if (isset($coupon->amount_off)) {
                // Ensure currency matches.
                if (isset($coupon->currency) && strtoupper($coupon->currency) !== strtoupper($currency)) {
                    throw new Exception('Coupon currency does not match course currency');
                }
                $discountamount = $coupon->amount_off / 100;
                $cost -= $discountamount;
                $coupontype = 'amount_off';
                $discountvalue = $coupon->amount_off / 100;
            } else {
                throw new Exception('Invalid coupon type');
            }

            // Ensure cost doesn't go negative.
            $cost = max(0, $cost);
            $cost = format_float($cost, 2, false);
            $discountamount = format_float($discountamount, 2, false);

        } catch (Exception $e) {
            // Log the error for debugging.
            debugging('Stripe coupon validation failed: ' . $e->getMessage());
            throw new invalid_parameter_exception($e->getMessage());
        }

        // Add minimum cost validation after coupon application.
        $minamount = [
            'USD' => 0.5, 'AED' => 2.0, 'AUD' => 0.5, 'BGN' => 1.0, 'BRL' => 0.5,
            'CAD' => 0.5, 'CHF' => 0.5, 'CZK' => 15.0, 'DKK' => 2.5, 'EUR' => 0.5,
            'GBP' => 0.3, 'HKD' => 4.0, 'HUF' => 175.0, 'INR' => 0.5, 'JPY' => 50,
            'MXN' => 10, 'MYR' => 2, 'NOK' => 3.0, 'NZD' => 0.5, 'PLN' => 2.0,
            'RON' => 2.0, 'SEK' => 3.0, 'SGD' => 0.5, 'THB' => 10,
        ];
        $minamount = isset($minamount[$currency]) ? $minamount[$currency] : 0.5;

        // Calculate UI state for display purposes only.
        $uistate = [
            'state' => 'paid',
            'errormessage' => '',
            'showsections' => [
                'paidenrollment' => true,
                'discountsection' => ($discountamount > 0),
            ]
        ];

        if ($cost > 0 && $cost < $minamount) {
            // Cost is above 0 but below minimum threshold - show error.
            $uistate['state'] = 'error';
            $uistate['errormessage'] = get_string('couponminimumerror', 'enrol_stripepayment', [
                'amount' => $currency . ' ' . number_format($cost, 2),
                'minimum' => $currency . ' ' . number_format($minamount, 2)
            ]);
            $uistate['showsections']['paidenrollment'] = false;
        }

        return [
            'status' => $cost,
            'couponname' => $couponname,
            'discountamount' => $coupontype,
            'discountvalue' => $discountvalue,
            'originalcost' => $originalcost,
            'currency' => $currency,
            'discountamount' => $discountamount,
            'uistate' => $uistate['state'],
            'message' => $uistate['state'] === 'error' ? $uistate['errormessage'] : 'Coupon applied successfully.',
            'showsections' => $uistate['showsections'],
        ];
    }

    /**
     * Enrollment and notification function
     * @param stdClass $plugininstance The enrollment instance
     * @param stdClass $course The course object
     * @param stdClass $context The course context
     * @param stdClass $user The user to enroll
     * @param stdClass $enrollmentdata The enrollment data to insert into enrol_stripepayment table
     * @return bool Success status
     */
    private static function enroll_user_and_send_notifications($plugininstance, $course, $context, $user, $enrollmentdata) {
        global $DB;

        $plugin = enrol_get_plugin('stripepayment');

        // Insert enrollment record.
        $DB->insert_record("enrol_stripepayment", $enrollmentdata);

        // Calculate enrollment period.
        if ($plugininstance->enrolperiod) {
            $timestart = time();
            $timeend = $timestart + $plugininstance->enrolperiod;
        } else {
            $timestart = time();
            $timeend = 0;
        }

        // Enroll user.
        $plugin->enrol_user($plugininstance, $user->id, $plugininstance->roleid, $timestart, $timeend);

        // Send notifications (same logic for both free and paid enrollment).
        self::send_enrollment_notifications($course, $context, $user, $plugin);

        return true;
    }

    /**
     * Send enrollment notifications to students, teachers, and admins
     * @param stdClass $course The course object
     * @param stdClass $context The course context
     * @param stdClass $user The enrolled user
     * @param object $plugin The enrollment plugin instance
     */
    private static function send_enrollment_notifications($course, $context, $user, $plugin) {
        global $CFG;

        // Get teacher.
        if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC',
                                                 '', '', '', '', false, true)) {
            $users = sort_by_roleassignment_authority($users, $context);
            $teacher = array_shift($users);
        } else {
            $teacher = false;
        }

        // Get notification settings.
        $mailstudents = $plugin->get_config('mailstudents');
        $mailteachers = $plugin->get_config('mailteachers');
        $mailadmins   = $plugin->get_config('mailadmins');

        // Prepare common data.
        $shortname = format_string($course->shortname, true, ['context' => $context]);
        $coursecontext = context_course::instance($course->id);
        $orderdetails = new stdClass();
        $orderdetails->coursename = format_string($course->fullname, true, ['context' => $coursecontext]);
        $orderdetails->course = format_string($course->fullname, true, ['context' => $coursecontext]);
        $subject = get_string("enrolmentnew", 'enrol', $shortname);
        $orderdetails->user = fullname($user);

        // Send notification to student.
        if (!empty($mailstudents)) {
            $orderdetails->profileurl = "$CFG->wwwroot/user/view.php?id=$user->id";
            $userfrom = empty($teacher) ? core_user::get_noreply_user() : $teacher;
            $fullmessage = get_string('welcometocoursetext', '', $orderdetails);
            $fullmessagehtml = '<p>'.get_string('welcometocoursetext', '', $orderdetails).'</p>';

            $message = new \core\message\message();
            $message->courseid = $course->id;
            $message->component = 'enrol_stripepayment';
            $message->name = 'stripepayment_enrolment';
            $message->userfrom = $userfrom;
            $message->userto = $user;
            $message->subject = $subject;
            $message->fullmessage = $fullmessage;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = $fullmessagehtml;
            $message->smallmessage = get_string('enrolmentnew', 'enrol', $shortname);
            $message->notification = 1;
            $message->contexturl = new \moodle_url('/course/view.php', ['id' => $course->id]);
            $message->contexturlname = $orderdetails->coursename;

            $messageid = message_send($message);
            if (!$messageid) {
                debugging('Failed to send stripepayment enrolment notification to student: ' . $user->id, DEBUG_DEVELOPER);
            }
        }

        // Send notification to teacher.
        if (!empty($mailteachers) && !empty($teacher)) {
            $fullmessage = get_string('enrolmentnewuser', 'enrol', $orderdetails);
            $fullmessagehtml = '<p>'.get_string('enrolmentnewuser', 'enrol', $orderdetails).'</p>';
            $message = new \core\message\message();
            $message->courseid = $course->id;
            $message->component = 'enrol_stripepayment';
            $message->name = 'stripepayment_enrolment';
            $message->userfrom = $user;
            $message->userto = $teacher;
            $message->subject = $subject;
            $message->fullmessage = $fullmessage;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = $fullmessagehtml;
            $message->smallmessage = get_string('enrolmentnew', 'enrol', $shortname);
            $message->notification = 1;
            $message->contexturl = new \moodle_url('/course/view.php', ['id' => $course->id]);
            $message->contexturlname = $orderdetails->coursename;
            $messageid = message_send($message);
            if (!$messageid) {
                debugging('Failed to send stripepayment enrolment notification to teacher: ' . $teacher->id, DEBUG_DEVELOPER);
            }
        }

        // Send notification to admins.
        if (!empty($mailadmins)) {
            $admins = get_admins();
            foreach ($admins as $admin) {
                $fullmessage = get_string('enrolmentnewuser', 'enrol', $orderdetails);
                $fullmessagehtml = '<p>'.get_string('enrolmentnewuser', 'enrol', $orderdetails).'</p>';

                $message = new \core\message\message();
                $message->courseid = $course->id;
                $message->component = 'enrol_stripepayment';
                $message->name = 'stripepayment_enrolment';
                $message->userfrom = $user;
                $message->userto = $admin;
                $message->subject = $subject;
                $message->fullmessage = $fullmessage;
                $message->fullmessageformat = FORMAT_PLAIN;
                $message->fullmessagehtml = $fullmessagehtml;
                $message->smallmessage = get_string('enrolmentnew', 'enrol', $shortname);
                $message->notification = 1;
                $message->contexturl = new \moodle_url('/course/view.php', ['id' => $course->id]);
                $message->contexturlname = $orderdetails->coursename;

                $messageid = message_send($message);
                if (!$messageid) {
                    debugging('Failed to send stripepayment enrolment notification to admin: ' . $admin->id, DEBUG_DEVELOPER);
                }
            }
        }
    }

    /**
     * define parameter type of stripepayment_enrol
     */
    public static function stripepayment_enrol_parameters() {
        return new external_function_parameters(
            [
                'userid' => new external_value(PARAM_RAW, 'Update data user id'),
                'couponid' => new external_value(PARAM_RAW, 'Update coupon id'),
                'instanceid' => new external_value(PARAM_RAW, 'Update instance id'),
            ],
        );
    }

    /**
     * return type of stripe js method
     */
    public static function stripepayment_enrol_returns() {
        return new external_single_structure(
            [
                'status' => new external_value(PARAM_RAW, 'status: true if success or 0 if failure'),
                'redirecturl' => new external_value(PARAM_URL, 'Stripe Checkout URL', VALUE_OPTIONAL),
                'error' => new external_single_structure(
                [
                    'message' => new external_value(PARAM_TEXT, 'Error message', VALUE_OPTIONAL),
                ], VALUE_OPTIONAL
            )
            ]
        );
    }

    /**
     * Function for create Checkout Session and process payment
     * @param int $userid
     * @param string $couponid
     * @param int $instanceid
     * @return array
     */
    public static function stripepayment_enrol($userid, $couponid, $instanceid ) {
        global $CFG, $DB;

        // Input validation.
        if (!is_numeric($userid) || $userid <= 0) {
            return [
                'status' => 0,
                'error' => ['message' => 'Invalid user ID'],
            ];
        }

        if (!is_numeric($instanceid) || $instanceid <= 0) {
            return [
                'status' => 0,
                'error' => ['message' => 'Invalid instance ID'],
            ];
        }

        $plugin = enrol_get_plugin('stripepayment');
        $secretkey = $plugin->get_config('secretkey');
        $usertoken = $plugin->get_config('webservice_token');

        // Validate Stripe configuration.
        if (empty($secretkey)) {
            return [
                'status' => 0,
                'error' => ['message' => 'Stripe configuration incomplete'],
            ];
        }

        // Validate users, course, context, plugininstance.
        try {
            $validateddata = self::validate_data($userid, $instanceid);
            $plugininstance = $validateddata[0];
            $course = $validateddata[1];
            $context = $validateddata[2];
            $user = $validateddata[3];
        } catch (Exception $e) {
            return [
                'status' => 0,
                'error' => ['message' => 'Validation failed: ' . $e->getMessage()],
            ];
        }

        // Calculate final cost after coupon application and retrieve coupon details.
        $finalcost = $plugininstance->cost;
        $amount = $plugin->get_stripe_amount($finalcost, $plugininstance->currency, false);
        $courseid = $plugininstance->courseid;
        $currency = $plugininstance->currency;
        $description  = format_string($course->fullname, true, ['context' => $context]);
        $shortname = format_string($course->shortname, true, ['context' => $context]);
        if (empty($secretkey) || empty($courseid) || empty($amount) || empty($currency) || empty($description)) {
            redirect($CFG->wwwroot.'/course/view.php?id='.$courseid);
        } else {
            // Set API key.
            Stripe::setApiKey($secretkey);
            $response = [
                'status' => 0,
                'error' => [
                    'message' => get_string('invalidrequest', 'enrol_stripepayment'),
                ],
            ];
            // Retrieve Stripe customer_id if previously set.
            $checkcustomer = $DB->get_records('enrol_stripepayment',
            ['receiver_email' => $user->email]);
            foreach ($checkcustomer as $keydata => $valuedata) {
                $checkcustomer = $valuedata;
            }
            if ($checkcustomer) {
                $receiverid = $checkcustomer->receiver_id;
            } else {
                $customers = Customer::all(['email' => $user->email]);
                if ( empty($customers->data) ) {
                    $customer = Customer::create([
                        "email" => $user->email,
                        "name" => fullname($user),
                        "description" => get_string('charge_description1', 'enrol_stripepayment'),
                    ]);
                } else {
                    $customer = $customers->data[0];
                }
                $receiverid = $customer->id;
            }
            // Create new Checkout Session for the order.
            try {
                $sessionparams = [
                    'customer' => $receiverid,
                    'payment_intent_data' => ['description' => $description ],
                    'payment_method_types' => ['card'],
                    'line_items' => [[
                        'price_data' => [
                            'product_data' => [
                                'name' => $description,
                                'metadata' => [
                                    'pro_id' => $courseid,
                                ],
                                'description' => $description,
                            ],
                            'unit_amount' => $amount,
                            'currency' => $currency,
                        ],
                        'quantity' => 1,
                    ]],

                    'discounts' => [['coupon' => $couponid]],

                    'metadata' => [
                        'course_shortname' => $shortname,
                        'course_id' => $course->id,
                        'couponid' => $couponid,
                    ],
                    'mode' => 'payment',
                    'success_url' => $CFG->wwwroot . '/webservice/rest/server.php?wstoken=' . $usertoken .
                    '&wsfunction=moodle_stripepayment_process_payment' .
                    '&moodlewsrestformat=json' .
                    '&sessionid={CHECKOUT_SESSION_ID}' .
                    '&userid=' . $userid .
                    '&couponid=' . $couponid .
                    '&instanceid=' . $instanceid,
                    'cancel_url' => $CFG->wwwroot . '/course/view.php?id=' . $courseid,
                ];

                $session = Session::create($sessionparams);
            } catch (Exception $e) {
                $apierror = $e->getMessage();
            }
            if (empty($apierror) && $session) {
                $response = [
                    'status' => 'success',
                    'redirecturl' => $session->url, // Stripe Checkout URL.
                    'error' => []
                ];
            } else {
                $response = [
                    'status' => 0,
                    'redirecturl' => null,
                    'error' => [
                        'message' => $apierror,
                    ],
                ];
            }
            return $response;
        }
    }
    /**
     * function for define parameter type for process_payment
     */
    public static function process_payment_parameters() {
        return new external_function_parameters(
            [
                'sessionid' => new external_value(PARAM_RAW, 'The item id to operate on'),
                'userid' => new external_value(PARAM_RAW, 'Update data user id'),
                'couponid'  => new external_value(PARAM_RAW, 'The item id to operate coupon id'),
                'instanceid'  => new external_value(PARAM_RAW, 'The item id to operate instance id'),
            ]
        );
    }
    /**
     * function for define return type for process_payment
     */
    public static function process_payment_returns() {
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
    public static function process_payment($sessionid, $userid, $couponid, $instanceid) {
        global $DB, $CFG, $PAGE, $OUTPUT;
        $data = new stdClass();
        $plugin = enrol_get_plugin('stripepayment');
        $secretkey = $plugin->get_config('secretkey');
        Stripe::setApiKey($secretkey);
        $checkoutsession = Session::retrieve($sessionid);

        // For 100% discount, no payment_intent is created.
        if (!empty($checkoutsession->payment_intent)) {
            $charge = PaymentIntent::retrieve($checkoutsession->payment_intent);
            $email = $charge->receipt_email;
            $paymentstatus = $charge->status;
            $txnid = $charge->id;
        } else {
            // Free checkout session (0 amount, no PaymentIntent).
            $charge = null;
            $email = $checkoutsession->customer_details->email;
            $paymentstatus = $checkoutsession->payment_status;
            $txnid = $checkoutsession->id;
        }

        $data->coupon_id = $couponid;
        $data->stripeEmail = $email;

        // Validate users, course, conntext, plugininstance.
        $validateddata = self::validate_data( $userid, $instanceid);
        $plugininstance = $validateddata[0];
        $course = $validateddata[1];
        $context = $validateddata[2];
        $user = $validateddata[3];
        $courseid = $plugininstance->courseid;
        $data->courseid = $courseid;
        $data->instanceid = $instanceid;
        $data->userid = (int)$userid;
        $data->timeupdated = time();

        if ( $checkoutsession->payment_status !== 'paid') {
            self::message_stripepayment_error_to_admin("Payment status: ".$checkoutsession->payment_status, $data);
            redirect($CFG->wwwroot);
        }
        $PAGE->set_context($context);
        try {
            // Send the file, this line will be reached if no error was thrown above.
            $failuremessage = $charge ? ($charge->failure_message ?? 'NA') : 'NA';
            $failurecode = $charge ? ($charge->failure_code ?? 'NA') : 'NA';
            $data->coupon_id = $couponid;
            $data->receiver_email = $user->email; // Use user email from database instead of Stripe response.
            $data->receiver_id = $checkoutsession->customer;
            $data->txn_id = $txnid;
            $data->price = $charge ? $charge->amount / 100 : 0;
            $data->memo = $charge ? $charge->payment_method : 'none';
            $data->payment_status = $paymentstatus;
            $data->pending_reason = $failuremessage;
            $data->reason_code = $failurecode;
            $data->item_name = $course->fullname;
            $data->payment_type = $charge ? 'stripe' : 'free';

            // Use consolidated enrollment and notification function.
            self::enroll_user_and_send_notifications($plugininstance, $course, $context, $user, $data);
            $destination = "$CFG->wwwroot/course/view.php?id=$course->id";
            $fullname = format_string($course->fullname, true, ['context' => $context]);
            if (is_enrolled($context, $user, '', true)) {
                redirect($destination, get_string('paymentthanks', '', $fullname));
            } else {
                // Somehow they aren't enrolled yet!.
                $PAGE->set_url($destination);
                echo $OUTPUT->header();
                $orderdetails = new stdClass();
                $orderdetails->teacher = get_string('defaultcourseteacher');
                $orderdetails->fullname = $fullname;
                notice(get_string('paymentsorry', '', $orderdetails), $destination);
            }
        } catch (Exception $e) {
            self::message_stripepayment_error_to_admin($e->getMessage(), ['sessionid' => $sessionid]);
            throw new invalid_parameter_exception($e->getMessage());
        }
    }

    /**
     * validate plugininstance, course, user, context if validate then ok
     * else send message to admin
     */
    public static function validate_data($userid, $instanceid ) {
        global $DB, $CFG;

        // Validate enrolment instance.
        if (! $plugininstance = $DB->get_record("enrol", ["id" => $instanceid, "status" => 0])) {
            self::message_stripepayment_error_to_admin(
                get_string('invalidinstance', 'enrol_stripepayment'), ["id" => $plugininstance->courseid]);
            redirect($CFG->wwwroot);
        }

        // Validate course.
        if (! $course = $DB->get_record("course", ["id" => $plugininstance->courseid])) {
            self::message_stripepayment_error_to_admin(
                get_string('invalidcourseid', 'enrol_stripepayment'), ["id" => $plugininstance->courseid]);
            redirect($CFG->wwwroot);
        }

        // Validate context.
        if (! $context = context_course::instance($course->id, IGNORE_MISSING)) {
            self::message_stripepayment_error_to_admin(get_string(
                'invalidcontextid', 'enrol_stripepayment'), ["id" => $course->id]);
            redirect($CFG->wwwroot);
        }

        // Validate user.
        if (! $user = $DB->get_record("user", ["id" => $userid])) {
            self::message_stripepayment_error_to_admin("Not orderdetails valid user id", ["id" => $userid]);
            redirect($CFG->wwwroot.'/course/view.php?id='.$course->id);
        }
        return [$plugininstance, $course, $context, $user];
    }

    /**
     * send error message to admin using Message API
     * @param string  $subject
     * @param array $data
     */
    public static function message_stripepayment_error_to_admin($subject, $data) {
        global $PAGE;
        $PAGE->set_context(context_system::instance());

        $admin = get_admin();
        $site = get_site();
        $messagebody = "$site->fullname:  Transaction failed.\n\n$subject\n\n";
        foreach ($data as $key => $value) {
            $messagebody .= s($key) ." => ". s($value)."\n";
        }
        $messagesubject = "STRIPE PAYMENT ERROR: ".$subject;
        $fullmessage = $messagebody;
        $fullmessagehtml = '<p>'.nl2br(s($messagebody)).'</p>';

        // Send message using Message API.
        $message = new \core\message\message();
        $message->courseid = SITEID;
        $message->component = 'enrol_stripepayment';
        $message->name = 'stripepayment_enrolment';
        $message->userfrom = core_user::get_noreply_user();
        $message->userto = $admin;
        $message->subject = $messagesubject;
        $message->fullmessage = $fullmessage;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = $fullmessagehtml;
        $message->smallmessage = 'Stripe payment error occurred';
        $message->notification = 1;
        $message->contexturl = new \moodle_url('/admin/index.php');
        $message->contexturlname = 'Site administration';

        $messageid = message_send($message);
        if (!$messageid) {
            debugging('Failed to send stripepayment error notification to admin: ' . $admin->id, DEBUG_DEVELOPER);
        }
    }
}
