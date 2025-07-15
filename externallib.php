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

require_once("$CFG->libdir/externallib.php"); // support for previous version of moodle 4.2
require_once("$CFG->libdir/enrollib.php");
require_once('Stripe/init.php');
use \Stripe\Stripe as Stripe;
use \Stripe\Coupon as Coupon;
use \Stripe\Customer as Customer;
use \Stripe\Checkout\Session as Session;
use \Stripe\PaymentIntent as PaymentIntent;

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
    public static function stripepayment_couponsettings_parameters() {
        return new external_function_parameters(
            [
                'couponid' => new external_value(PARAM_RAW, 'The coupon id to operate on'),
                'instance_id' => new external_value(PARAM_RAW, 'Update instance id'),
            ]
        );
    }

    /**
     * return type of couponsettings functioin
     */
    public static function stripepayment_couponsettings_returns() {
        return new external_single_structure(
            [
                'status' => new external_value(PARAM_RAW, 'status: true if success')
            ]
        );
    }

    /**
     * function for couponsettings and offer set.
     * @param number $couponid
     * @param number $instanceid
     * @return array
     */
    public static function stripepayment_couponsettings($couponid, $instanceid) {
        global $DB;
        $plugin = enrol_get_plugin('stripepayment');
        $plugininstance = $DB->get_record("enrol", ["id" => $instanceid, "status" => 0]);
        if (!$plugininstance) {
            throw new invalid_parameter_exception('Invalid instance ID');
        }

        $cost = (float)$plugininstance->cost > 0 ? (float)$plugininstance->cost : (float)$plugin->get_config('cost');
        $cost = format_float($cost, 2, false);

        Stripe::setApiKey($plugin->get_config('secretkey'));

        try {
            $coupon = Coupon::retrieve($couponid);
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

        return ['status' => $cost];
    }

    /**
     * declare parameters type for stripepayment_free_enrolsettings
     */
    public static function stripepayment_free_enrolsettings_parameters() {
        return new external_function_parameters(
            [
                'user_id' => new external_value(PARAM_RAW, 'Update data user id'),
                'couponid' => new external_value(PARAM_RAW, 'Update data coupon id'),
                'instance_id' => new external_value(PARAM_RAW, 'Update data instance id'),
            ],
        );
    }

    /**
     * declare return type for stripepayment_free_enrolsettings
     */
    public static function stripepayment_free_enrolsettings_returns() {
        return new external_single_structure(
            [
                'status' => new external_value(PARAM_RAW, 'status: true if success'),
            ]
        );
    }

    /**
     * for free enrolsetting
     * @param number $userid
     * @param number $userid
     * @param number $instanceid
     */
    public static function stripepayment_free_enrolsettings($userid, $couponid, $instanceid) {
        global $DB, $CFG;

        $validateddata = self::validate_data( $userid, $instanceid);
        $plugininstance = $validateddata[0];
        $course = $validateddata[1];
        $user = $validateddata[3];

        $plugin = enrol_get_plugin('stripepayment');
        $secretkey = $plugin->get_config('secretkey');
        Stripe::setApiKey($secretkey);

        $data = new stdClass();
        $data->coupon_id = $couponid;
        $data->userid = $userid;
        $data->instanceid = $instanceid;
        $data->stripeEmail = $user->email;
        $data->courseid = $plugininstance->courseid;
        $data->timeupdated = time();

        // Check existing customer.
        $checkcustomer = $DB->get_records('enrol_stripepayment', ['receiver_email' => $user->email]);
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
                $customer = Customer::create([
                    "email" => $data->stripeEmail,
                    "name" => fullname($user),
                    "coupon" => $data->coupon_id,
                    "description" => get_string('charge_description1', 'enrol_stripepayment')
                ]);
                $data->receiver_id = $customer->id;
            }
        } catch (Exception $e) {
            self::message_stripepayment_error_to_admin($e->getMessage(), $data);
            redirect($CFG->wwwroot);
        }

        $data->receiver_email = $user->email;
        $data->payment_status = 'succeeded';
        $data->item_name = $course->fullname;
        $DB->insert_record("enrol_stripepayment", $data);

        // Enrol user
        $plugin->enrol_user($plugininstance, $user->id, $plugininstance->roleid, time(), $plugininstance->enrolperiod ? time() + $plugininstance->enrolperiod : 0);

        $result = ['status' => 'working'];
        return $result;
    }

    /**
     * define parameter type of stripe_js_method
     */
    public static function stripe_js_method_parameters() {
        return new external_function_parameters(
            [
                'user_id' => new external_value(PARAM_RAW, 'Update data user id'),
                'couponid' => new external_value(PARAM_RAW, 'Update coupon id'),
                'instance_id' => new external_value(PARAM_RAW, 'Update instance id'),
            ],
        );
    }

    /**
     * return type of stripe js method
     */
    public static function stripe_js_method_returns() {
        return new external_single_structure(
            [
                'status' => new external_value(PARAM_RAW, 'status: true if success'),
            ]
        );
    }

    /**
     * function for create Checkout Session and process payment
     * @param number $userid
     * @param number $couponid
     * @param number $instanceid
     * @return array
     */
    public static function stripe_js_method( $userid, $couponid, $instanceid ) {
        global $CFG, $DB;
        $plugin = enrol_get_plugin('stripepayment');
        $secretkey = $plugin->get_config('secretkey');
        $usertoken = $plugin->get_config('webservice_token');
        
        // validate users, course, conntext, plugininstance
        $validateddata = self::validate_data( $userid, $instanceid);
        $plugininstance = $validateddata[0];
        $course = $validateddata[1];
        $context = $validateddata[2];
        $user = $validateddata[3];
        
        $amount = $plugin->get_stripe_amount($plugininstance->cost, $plugininstance->currency, false);
        $courseid = $plugininstance->courseid;
        $currency = $plugininstance->currency;
        $description  = format_string($course->fullname, true, ['context' => $context]);
        $shortname = format_string($course->shortname, true, ['context' => $context]);

        if (empty($secretkey) || empty($courseid) || empty($amount) || empty($currency) || empty($description)) {
            redirect($CFG->wwwroot.'/course/view.php?id='.$courseid);
        } else {
            // Set API key
            Stripe::setApiKey($secretkey);
            $response = [
                'status' => 0,
                'error' => [
                    'message' => get_string('invalidrequest', 'enrol_stripepayment'),
                ],
            ];
            // retrieve Stripe customer_id if previously set
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
                        "description" => get_string('charge_description1', 'enrol_stripepayment')
                    ]);
                } else {
                    $customer = $customers->data[0];
                }
                $receiverid = $customer->id;
            }
            // Create new Checkout Session for the order 
            try {
                $session = Session::create([
                    'customer' => $receiverid,
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
                        'quantity' => 1,
                    ]],
                    'discounts' => [[
                        'coupon' => $couponid,
                    ]],
                    'metadata' => [
                        'course_shortname' => $shortname,
                        'course_id' => $course->id,
                    ],
                    'mode' => 'payment',
                    'success_url' => $CFG->wwwroot.'/webservice/rest/server.php?wstoken=' .$usertoken. '&wsfunction=moodle_stripepayment_success_stripe_url&moodlewsrestformat=json&sessionid={CHECKOUT_SESSION_ID}&user_id=' .$userid. '&couponid=' .$couponid. '&instance_id=' .$instanceid. '',
                    'cancel_url' => $CFG->wwwroot.'/course/view.php?id='.$courseid,
                ]);
            } catch (Exception $e) {
                $apierror = $e->getMessage();
            }
            if (empty($apierror) && $session) {
                $response = [
                    'status' => 1,
                    'message' => get_string('sessioncreated', 'enrol_stripepayment'),
                    'sessionId' => $session['id'],
                ];
            } else {
                $response = [
                    'status' => 0,
                    'error' => [
                        'message' => get_string('sessioncreatefail', 'enrol_stripepayment') .$apierror,
                    ],
                ];
            }
            // Return response 
            $passsessionid = isset($response['sessionId']) && !empty($response['sessionId']) ? $response['sessionId'] : '';
            $result = [];
            $result['status'] = $passsessionid;
            return $result;
            die;
        }
    }
    /**
     * function for define parameter type for success_stripe_url
     */
    public static function success_stripe_url_parameters() {
        return new external_function_parameters(
            [
                'sessionid' => new external_value(PARAM_RAW, 'The item id to operate on'),
                'user_id' => new external_value(PARAM_RAW, 'Update data user id'),
                'couponid'  => new external_value(PARAM_RAW, 'The item id to operate coupon id'),
                'instance_id'  => new external_value(PARAM_RAW, 'The item id to operate instance id'),
            ]
        );
    }
    
    /**
     * function for define return type for success_stripe_url
     */
    public static function success_stripe_url_returns() {
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
    public static function success_stripe_url($sessionid, $userid, $couponid, $instanceid) {
        global $DB, $CFG, $PAGE, $OUTPUT;
        $data = new stdClass();
        $plugin = enrol_get_plugin('stripepayment');
        $secretkey = $plugin->get_config('secretkey');
        Stripe::setApiKey($secretkey);
        $checkoutsession = Session::retrieve($sessionid);
        $charge = PaymentIntent::retrieve($checkoutsession->payment_intent);
        $data->coupon_id = $couponid;
        $data->stripeEmail = $charge->receipt_email;
        $data->receiver_id = $charge->customer;

        // validate users, course, conntext, plugininstance
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
            if (!isset($charge->failure_message) || is_null($charge->failure_message)) {
                $charge->failure_message = 'NA';
            }
            if (!isset($charge->failure_code) || is_null($charge->failure_code)) {
                $charge->failure_code = 'NA';
            }
            $data->receiver_email = $checkoutsession->customer_details->email;
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

            $destination = "$CFG->wwwroot/course/view.php?id=$course->id";
            $fullname = format_string($course->fullname, true, ['context' => $context]);
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
        } catch (Exception $e) {
            self::message_stripepayment_error_to_admin($e->getMessage(), ['sessionid' => $sessionid]);
            throw new invalid_parameter_exception($e->getMessage());
        }
        
    }

    /**
     * validate plugininstance, course, user, context if validate then ok 
     * else send message to admin 
     */
    public static function validate_data( $userid, $instanceid ) {
        global $DB, $CFG;

        // Validate enrolment instance.
        if (! $plugininstance = $DB->get_record("enrol", ["id" => $instanceid, "status" => 0])) {
            self::message_stripepayment_error_to_admin(get_string('invalidinstance', 'enrol_stripepayment'), $data);
            redirect($CFG->wwwroot);
        }

        // Validate course.
        if (! $course = $DB->get_record("course", ["id" => $plugininstance->courseid])) {
            self::message_stripepayment_error_to_admin(get_string('invalidcourseid', 'enrol_stripepayment'), $data);
            redirect($CFG->wwwroot);
        }

        // Validate context.
        if (! $context = context_course::instance($course->id, IGNORE_MISSING)) {
            self::message_stripepayment_error_to_admin(get_string('invalidcontextid', 'enrol_stripepayment'), $data);
            redirect($CFG->wwwroot);
        }

        //validate user
        if (! $user = $DB->get_record("user", ["id" => $userid])) {
            self::message_stripepayment_error_to_admin("Not orderdetails valid user id", $data);
            redirect($CFG->wwwroot.'/course/view.php?id='.$course->id);
        }
        return [$plugininstance, $course, $context, $user];
    }

    /**
     * send email with errror message to admin 
     * @param string  $subject
     * @param array $data
     */
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
