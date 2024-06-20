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
require_once("$CFG->libdir/externallib.php"); // support for previous version of moodle 4.2
require_once("$CFG->libdir/enrollib.php");
require_once('Stripe/init.php');
use \Stripe\Stripe as Stripe;
use \Stripe\Coupon as Coupon;
use \Stripe\Customer as Customer;
use \Stripe\Checkout\Session as Session;
use \Stripe\PaymentIntent as PaymentIntent;

defined('MOODLE_INTERNAL') || die();

/**
 * External library for stripepayment
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2019 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class moodle_enrol_stripepayment_external extends external_api {

    /// Coupon Settings Methods
    public static function stripepayment_couponsettings_parameters() {
        return new external_function_parameters(
            array(
                'coupon_id' => new external_value(PARAM_RAW, 'The coupon id to operate on'),
                'instance_id' => new external_value(PARAM_RAW, 'Update instance id')
            )
        );
    }

    public static function stripepayment_couponsettings_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_RAW, 'status: true if success')
            )
        );
    }

    public static function stripepayment_couponsettings($coupon_id, $instance_id) {
        global $DB;

        $plugin = enrol_get_plugin('stripepayment');
        $plugininstance = $DB->get_record("enrol", array("id" => $instance_id, "status" => 0));
        if (!$plugininstance) {
            throw new invalid_parameter_exception('Invalid instance ID');
        }

        $cost = (float)$plugininstance->cost > 0 ? (float)$plugininstance->cost : (float)$plugin->get_config('cost');
        $cost = format_float($cost, 2, false);

        Stripe::setApiKey($plugin->get_config('secretkey'));

        try {
            $coupon = Coupon::retrieve($coupon_id);
            if ($coupon->valid) {
                if (isset($coupon->percent_off)) {
                    $cost -= $cost * ($coupon->percent_off / 100);
                } else if (isset($coupon->amount_off)) {
                    $cost -= $coupon->amount_off / 100;
                }
                $cost = format_float($cost, 2, false);
            } else {
                throw new Exception(get_string('invalidcoupon', 'enrol_stripepayment'));
            }
        } catch (Exception $e) {
            throw new invalid_parameter_exception($e->getMessage());
        }

        return array('status' => $cost);
    }

    // Free Enrol Settings Methods
    public static function stripepayment_free_enrolsettings_parameters() {
        return new external_function_parameters(
            array(
                'user_id' => new external_value(PARAM_RAW, 'Update data user id'),
                'couponid' => new external_value(PARAM_RAW, 'Update data coupon id'),
                'instance_id' => new external_value(PARAM_RAW, 'Update data instance id')
            )
        );
    }

    public static function stripepayment_free_enrolsettings_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_RAW, 'status: true if success')
            )
        );
    }

    public static function stripepayment_free_enrolsettings($user_id, $couponid, $instance_id) {
        global $DB, $CFG;

        // Validate user
        if (! $user = $DB->get_record("user", array("id" => $user_id))) {
            self::message_stripepayment_error_to_admin(get_string('invaliduserid', 'enrol_stripepayment'), ['user_id' => $user_id]);
            redirect($CFG->wwwroot);
        }

        // Validate plugin instance
        if (! $plugininstance = $DB->get_record("enrol", array("id" => $instance_id, "status" => 0))) {
            self::message_stripepayment_error_to_admin(get_string('invalidinstance', 'enrol_stripepayment'), ['instance_id' => $instance_id]);
            redirect($CFG->wwwroot);
        }

        // Validate course
        if (! $course = $DB->get_record("course", array("id" => $plugininstance->courseid))) {
            self::message_stripepayment_error_to_admin(get_string('invalidcourseid', 'enrol_stripepayment'), ['courseid' => $plugininstance->courseid]);
            redirect($CFG->wwwroot);
        }

        // Validate context
        if (! $context = context_course::instance($course->id, IGNORE_MISSING)) {
            self::message_stripepayment_error_to_admin(get_string('invalidcontextid', 'enrol_stripepayment'), ['contextid' => $course->id]);
            redirect($CFG->wwwroot);
        }

        $plugin = enrol_get_plugin('stripepayment');
        $secretkey = $plugin->get_config('secretkey');
        Stripe::setApiKey($secretkey);

        $data = new stdClass();
        $data->coupon_id = $couponid;
        $data->userid = $user_id;
        $data->instanceid = $instance_id;
        $data->stripeEmail = $user->email;
        $data->courseid = $plugininstance->courseid;
        $data->timeupdated = time();

        // Check existing customer
        $checkcustomer = $DB->get_records('enrol_stripepayment', array('receiver_email' => $user->email));
        if ($checkcustomer) {
            $checkcustomer = reset($checkcustomer);
        }

        try {
            if ($checkcustomer && $checkcustomer->receiver_id) {
                $customer = Customer::retrieve($checkcustomer->receiver_id);
                $customer->coupon = $couponid;
                $customer->save();
                $data->receiver_id = $checkcustomer->receiver_id;
            } else {
                $customer = Customer::create(array("email" => $data->stripeEmail, "coupon" => $data->coupon_id, "description" => get_string('charge_description1', 'enrol_stripepayment')));
                $data->receiver_id = $customer->id;
            }
        } catch (Exception $e) {
            self::message_stripepayment_error_to_admin($e->getMessage(), $data);
            redirect($CFG->wwwroot);
        }

        $data->receiver_email = $user->email;
        $data->payment_status = 'succeeded';
        $DB->insert_record("enrol_stripepayment", $data);

        // Enrol user
        $plugin->enrol_user($plugininstance, $user->id, $plugininstance->roleid, time(), $plugininstance->enrolperiod ? time() + $plugininstance->enrolperiod : 0);

        // Send notifications
        self::send_enrolment_notification($plugin, $user, $course, $context);

        $result = array('status' => 'working');
        return $result;
    }

    // Stripe JS Method
    public static function stripe_js_method_parameters() {
        return new external_function_parameters(
            array(
                'user_id' => new external_value(PARAM_RAW, 'Update data user id'),
                'couponid' => new external_value(PARAM_RAW, 'Update coupon id'),
                'instance_id' => new external_value(PARAM_RAW, 'Update instance id')
            )
        );
    }

    public static function stripe_js_method_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_RAW, 'status: true if success')
            )
        );
    }

    public static function stripe_js_method( $user_id, $couponid, $instance_id ) {
        global $CFG, $DB;
        $plugin = enrol_get_plugin('stripepayment');
        $secretkey = $plugin->get_config('secretkey');
        $user_token = $plugin->get_config('webservice_token');
        if (! $plugininstance = $DB->get_record("enrol", array("id" => $instance_id, "status" => 0))) {
            self::message_stripepayment_error_to_admin(get_string('invalidinstance', 'enrol_stripepayment'), $data);
            redirect($CFG->wwwroot);
        }
        $amount = $plugin->get_stripe_amount($plugininstance->cost, $plugininstance->currency, false);
        $courseid = $plugininstance->courseid;
        $currency = $plugininstance->currency;
        if (! $course = $DB->get_record("course", array("id" => $courseid))) {
            self::message_stripepayment_error_to_admin(get_string('invalidcourseid', 'enrol_stripepayment'), $data);
            redirect($CFG->wwwroot);
        }
        if (! $context = context_course::instance($course->id, IGNORE_MISSING)) {
            self::message_stripepayment_error_to_admin(get_string('invalidcontextid', 'enrol_stripepayment'), $data);
            redirect($CFG->wwwroot);
        }
        $description  = format_string($course->fullname, true, array('context' => $context));
        $shortname = format_string($course->shortname, true, array('context' => $context));
        if (! $user = $DB->get_record("user", array("id" => $user_id))) {
            self::message_stripepayment_error_to_admin("Not orderdetails valid user id", $data);
            redirect($CFG->wwwroot.'/course/view.php?id='.$courseid);
        }
        if (empty($secretkey) || empty($courseid) || empty($amount) || empty($currency) || empty($description)) {
            redirect($CFG->wwwroot.'/course/view.php?id='.$courseid);
        } else {
            // Set API key 
            Stripe::setApiKey($secretkey); 
            $response = [
                'status' => 0, 
                'error' => [
                    'message' => get_string('invalidrequest', 'enrol_stripepayment')  
                ]
            ];
            // retrieve Stripe customer_id if previously set
            $checkcustomer = $DB->get_records('enrol_stripepayment',
            ['receiver_email' => $user->email]);
            foreach ($checkcustomer as $keydata => $valuedata) {
                $checkcustomer = $valuedata;
            }
            if ($checkcustomer) {
                $receiver_id = $checkcustomer->receiver_id;
                $receiver_email = null;   // must not be set if customer id provided
            } else {
                $customers = Customer::all(['email' => $user->email]);
                if(empty($customers->data)){
                    $customerarray = array("email" => $user->email,
                    "description" => get_string('charge_description1', 'enrol_stripepayment'));
                    $customer = Customer::create($customerarray);
                } else {
                    $customer = $customers->data[0];
                }
                $receiver_id = $customer->id;
            }
            // Create new Checkout Session for the order 
            try {
                $session = Session::create([ 
                    'customer' => $receiver_id,
                    'customer_email' => $receiver_email,
                    'payment_intent_data' => ['description' => $description ],
                    'payment_method_types' => ['card'],
                    'line_items' => [[ 
                        'price_data' => [ 
                            'product_data' => [ 
                                'name' => $description, 
                                'metadata' => [ 
                                    'pro_id' => $courseid 
                                ], 
                                'description' => $description,
                            ],
                            'unit_amount' => $amount, 
                            'currency' => $currency, 
                        ],
                        'quantity' => 1 
                    ]],
                    'discounts' => [[
                        'coupon' => $couponid,
                    ]],
                    'metadata' => [
                        'course_shortname' => $shortname,
                        'course_id' => $course->id,
                    ],
                    'mode' => 'payment',
                    'success_url' => $CFG->wwwroot.'/webservice/rest/server.php?wstoken=' .$user_token. '&wsfunction=moodle_stripepayment_success_stripe_url&moodlewsrestformat=json&session_id={CHECKOUT_SESSION_ID}&user_id=' .$user_id. '&couponid=' .$couponid. '&instance_id=' .$instance_id. '',
                    'cancel_url' => $CFG->wwwroot.'/course/view.php?id='.$courseid, 
                ]);
            } catch(Exception $e) {
                $api_error = $e->getMessage();
            }
            if(empty($api_error) && $session) {
                $response = [
                    'status' => 1, 
                    'message' => get_string('sessioncreated', 'enrol_stripepayment'), 
                    'sessionId' => $session['id'] 
                ];
            } else {
                $response = [
                    'status' => 0,
                    'error' => [
                        'message' => get_string('sessioncreatefail', 'enrol_stripepayment') .$api_error    
                    ]
                ];
                
            }
            // Return response 
            $pass_session_id = isset($response['sessionId']) && !empty($response['sessionId']) ? $response['sessionId'] : '';
            $result = [];
            $result['status'] = $pass_session_id;
            return $result;
            die;
        }
    }
    /// Success Stripe URL Method
    public static function success_stripe_url_parameters() {
        return new external_function_parameters(
            array(
                'session_id' => new external_value(PARAM_RAW, 'The item id to operate on'),
                'user_id' => new external_value(PARAM_RAW, 'Update data user id'),
                'couponid'  => new external_value(PARAM_RAW, 'The item id to operate coupon id'),
                'instance_id'  => new external_value(PARAM_RAW, 'The item id to operate instance id')
            )  
        );
    }

    public static function success_stripe_url_returns() {
        return new external_single_structure(
            array(
                'status' => new external_value(PARAM_RAW, 'status: true if success')
            )
        );
    }

    public static function success_stripe_url($session_id, $user_id, $couponid, $instance_id) {
        global $DB, $CFG, $PAGE, $OUTPUT;
        $data = new stdClass();
        $plugin = enrol_get_plugin('stripepayment');
        $secretkey = $plugin->get_config('secretkey');
        Stripe::setApiKey($secretkey);
        $checkout_session = Session::retrieve($session_id);
        file_put_contents("C://xampp/htdocs/moodle4.4/enrol/stripepayment/log.txt", var_export($checkout_session->payment_status, true) . "\n", FILE_APPEND);
        $charge = PaymentIntent::retrieve($checkout_session->payment_intent);
        $data->coupon_id = $couponid;
        $data->stripeEmail = $charge->receipt_email;
        $data->receiver_id = $charge->customer;
        if (! $plugininstance = $DB->get_record("enrol", ["id" => $instance_id, "status" => 0])) {
            self::message_stripepayment_error_to_admin(get_string('invalidinstance', 'enrol_stripepayment'), $data);
            redirect($CFG->wwwroot);
        }
        $courseid = $plugininstance->courseid;
        $data->courseid = $courseid;
        $data->instanceid = $instance_id;
        $data->userid = (int)$user_id;
        $data->timeupdated = time();
        if (! $user = $DB->get_record("user", array("id" => $data->userid))) {
            self::message_stripepayment_error_to_admin(get_string('invaliduserid', 'enrol_stripepayment'), $data);
            redirect($CFG->wwwroot);
        }
        if (! $course = $DB->get_record("course", array("id" => $data->courseid))) {
            self::message_stripepayment_error_to_admin(get_string('invalidcourseid', 'enrol_stripepayment'), $data);
            redirect($CFG->wwwroot);
        }
        if (! $context = context_course::instance($course->id, IGNORE_MISSING)) {
            self::message_stripepayment_error_to_admin(get_string('invalidcontextid', 'enrol_stripepayment'), $data);
            redirect($CFG->wwwroot);
        }
        if( $checkout_session->payment_status !== 'paid') {
            self::message_stripepayment_error_to_admin("Payment status: ".$checkout_session->payment_status, $data);
            redirect($CFG->wwwroot);
        }
        $PAGE->set_context($context);
        // Check that amount paid is the correct amount.
        if ( (float) $plugininstance->cost <= 0 ) {
            $cost = (float) $plugin->get_config('cost');
        } else {
            $cost = (float) $plugininstance->cost;
        }
        // Use the same rounding of floats as on the enrol form.
        $cost = format_float($cost, 2, false);
        try {
            if ($data->coupon_id && $data->coupon_id != '0') {
                $coupon = Coupon::retrieve($data->coupon_id);
                if (!$coupon->valid) {
                    redirect($CFG->wwwroot.'/enrol/index.php?id='.$data->courseid, get_string("invalidcouponcodevalue",
                        "enrol_stripepayment", $data->coupon_id));
                } else {
                    if (isset($coupon->percent_off)) {
                        $cost = $cost - ( $cost * ( $coupon->percent_off / 100 ) );
                    } elseif (isset($coupon->amount_off)) {
                        $cost = (($cost * 100) - $coupon->amount_off) / 100;
                    }
                }
            }
            // Send the file, this line will be reached if no error was thrown above.
            if (!isset($charge->failure_message) || is_null($charge->failure_message)) {
                $charge->failure_message = 'NA';
            }
            if (!isset($charge->failure_code) || is_null($charge->failure_code)) {
                $charge->failure_code = 'NA';
            }
            $data->receiver_email = $checkout_session->customer_details->email;
            $data->txn_id = $charge->id;
            $data->tax = $charge->amount / 100;
            $data->memo = $charge->payment_method;
            $data->payment_status = $charge->status;
            $data->pending_reason = $charge->failure_message;
            $data->reason_code = $charge->failure_code;
            $data->item_name = $course->fullname;
            $DB->insert_record("enrol_stripepayment", $data);
            if ($plugininstance->enrolperiod) {
                $timestart = time();
                $timeend   = $timestart + $plugininstance->enrolperiod;
            } else {
                $timestart = 0;
                $timeend   = 0;
            }
            // Enrol user.
            $plugin->enrol_user($plugininstance, $user->id, $plugininstance->roleid, $timestart, $timeend);
            if ($users = get_users_by_capability($context, 'moodle/course:update', 'u.*', 'u.id ASC',
                                                     '', '', '', '', false, true)) {
                $users = sort_by_roleassignment_authority($users, $context);
                $teacher = array_shift($users);
            } else {
                $teacher = false;
            }
            $mailstudents = $plugin->get_config('mailstudents');
            $mailteachers = $plugin->get_config('mailteachers');
            $mailadmins   = $plugin->get_config('mailadmins');
            $shortname = format_string($course->shortname, true, array('context' => $context));
            $coursecontext = context_course::instance($course->id);
            $orderdetails = new stdClass();
            $orderdetails->coursename = format_string($course->fullname, true, array('context' => $coursecontext));
            $subject = get_string("enrolmentnew", 'enrol', $shortname);
            $orderdetails->user = fullname($user);
            if (!empty($mailstudents)) {
                $orderdetails->profileurl = "$CFG->wwwroot/user/view.php?id=$user->id";
                $userfrom = empty($teacher) ? core_user::get_support_user() : $teacher;
                $fullmessage = get_string('welcometocoursetext', '', $orderdetails);
                $fullmessagehtml = html_to_text('<p>'.get_string('welcometocoursetext', '', $orderdetails).'</p>');
                // Send test email.
                ob_start();
                email_to_user($user, $userfrom, $subject, $fullmessage, $fullmessagehtml);
                ob_get_contents();
                ob_end_clean();
            }
            if (!empty($mailteachers) && !empty($teacher)) {
                $fullmessage = get_string('enrolmentnewuser', 'enrol', $orderdetails);
                $fullmessagehtml = html_to_text('<p>'.get_string('enrolmentnewuser', 'enrol', $orderdetails).'</p>');
                // Send test email.
                ob_start();
                email_to_user($teacher, $user, $subject, $fullmessage, $fullmessagehtml);
                ob_get_contents();
                ob_end_clean();
            }
            if (!empty($mailadmins)) {
                $admins = get_admins();
                foreach ($admins as $admin) {
                    $fullmessage = get_string('enrolmentnewuser', 'enrol', $orderdetails);
                    $fullmessagehtml = html_to_text('<p>'.get_string('enrolmentnewuser', 'enrol', $orderdetails).'</p>');
                    // Send test email.
                    ob_start();
                    email_to_user($admin, $user, $subject, $fullmessage, $fullmessagehtml);
                    ob_get_contents();
                    ob_end_clean();
                }
            }
            $destination = "$CFG->wwwroot/course/view.php?id=$course->id";
            $fullname = format_string($course->fullname, true, array('context' => $context));
            if (is_enrolled($context, $user, '', true)) { 
                redirect($destination, get_string('paymentthanks', '', $fullname));
            } else {
                // Somehow they aren't enrolled yet!
                $PAGE->set_url($destination);
                echo $OUTPUT->header();
                $orderdetails = new stdClass();
                $orderdetails->teacher = get_string('defaultcourseteacher');
                $orderdetails->fullname = $fullname;
                notice(get_string('paymentsorry', '', $orderdetails), $destination);
            }
        }
        catch (Exception $e) {
            self::message_stripepayment_error_to_admin($e->getMessage(), array('session_id' => $session_id));
            throw new invalid_parameter_exception($e->getMessage());
        }
    }
    public static function message_stripepayment_error_to_admin($subject, $data) {
        $admin = get_admin();
        $site = get_site();
        $message = "$site->fullname:  Transaction failed.\n\n$subject\n\n";
        foreach ($data as $key => $value) {
            $message .= s($key) ." => ". s($value)."\n";
        }
        $subject = "STRIPE PAYMENT ERROR: ".$subject;
        $fullmessage = $message;
        $fullmessagehtml = html_to_text('<p>'.$message.'</p>');
        // Send test email.
        ob_start();
        email_to_user($admin, $admin, $subject, $fullmessage, $fullmessagehtml);
        ob_get_contents();
        ob_end_clean();
    }
}
