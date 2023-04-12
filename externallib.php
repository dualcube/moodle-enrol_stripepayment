<?php
require_once("$CFG->libdir/externallib.php");
require_once("$CFG->libdir/enrollib.php");
require_once('Stripe/init.php');
use \Stripe\Stripe as Stripe;
use \Stripe\Coupon as Coupon;
use \Stripe\Customer as Customer;
use \Stripe\Checkout\Session as Session;
use \Stripe\PaymentIntent as PaymentIntent;
class moodle_enrol_stripepayment_external extends external_api {
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
        global $DB, $CFG;
        $plugin = enrol_get_plugin('stripepayment');
        $couponid = $coupon_id;
        $plugininstance = $DB->get_record("enrol", array("id" => $instance_id, "status" => 0));
        $courseid = $plugininstance->courseid;
        if ( (float) $plugininstance->cost <= 0 ) {
            $cost = ( float ) $plugin->get_config('cost');
        } else {
            $cost = (float) $plugininstance->cost;
        }
        $cost = format_float($cost, 2, false);
        Stripe::setApiKey(enrol_get_plugin('stripepayment')->get_config('secretkey'));
        // Throws an exception if coupon not found (handled by calling js code)
        $coupon = Coupon::retrieve( $couponid );
        if ($coupon->valid) {
            if (isset($coupon->percent_off)) {
                $cost = $cost - ( $cost * ( $coupon->percent_off / 100 ) );
            } else if (isset($coupon->amount_off)) {
                $cost = (($cost * 100) - $coupon->amount_off) / 100;
            }
            $cost = format_float($cost, 2, false);
        }
        else{
             throw new Exception(get_string('invalidcoupon', 'enrol_stripepayment'));
        }
        $result = array();
        $result['status'] = $cost;
        return $result;
        die;
    }
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
    public static function stripepayment_free_enrolsettings($couponid, $user_id, $instance_id ) {
        global $DB, $CFG, $PAGE;
        $data = new stdClass();
        $data->coupon_id = $couponid;
        $data->userid           = (int)$user_id;
        $data->instanceid       = (int)$instance_id;
        $data->timeupdated      = time();
        if (! $user = $DB->get_record("user", array("id" => $data->userid))) {
            self::message_stripepayment_error_to_admin(get_string('invaliduserid', 'enrol_stripepayment'), $data);
            redirect($CFG->wwwroot);
        }
        $data->stripeEmail = $user->email;
        if (! $plugininstance = $DB->get_record("enrol", array("id" => $data->instanceid, "status" => 0))) {
            self::message_stripepayment_error_to_admin(get_string('invalidinstance', 'enrol_stripepayment'), $data);
            redirect($CFG->wwwroot);
        }
        $data->courseid         = (int)$plugininstance->courseid;
        if (! $course = $DB->get_record("course", array("id" => $data->courseid))) {
            self::message_stripepayment_error_to_admin(get_string('invalidcourseid', 'enrol_stripepayment'), $data);
            redirect($CFG->wwwroot);
        }
        if (! $context = context_course::instance($course->id, IGNORE_MISSING)) {
            self::message_stripepayment_error_to_admin(get_string('invalidcontextid', 'enrol_stripepayment'), $data);
            redirect($CFG->wwwroot);
        }
        $description = format_string($course->fullname, true, array('context' => $context));
        $data->item_name = $description;
        $PAGE->set_context($context);
        // If currency is incorrectly set then someone maybe trying to cheat the system.
        if ($data->courseid != $plugininstance->courseid) {
            self::message_stripepayment_error_to_admin(get_string('unmatchedcourse', 'enrol_stripepayment').$data->courseid, $data);
            redirect($CFG->wwwroot);
        }
        $plugin = enrol_get_plugin('stripepayment');
        $secretkey = $plugin->get_config('secretkey');
        Stripe::setApiKey($secretkey);
        $checkcustomer = $DB->get_records('enrol_stripepayment',
        array('receiver_email' => $data->stripeEmail));
        foreach ($checkcustomer as $keydata => $valuedata) {
            $checkcustomer = $valuedata;
        }
        $coupon = null;
        if($checkcustomer->receiver_id){
            $coupon = Customer::retrieve($checkcustomer->receiver_id);
        }
        if ($checkcustomer->receiver_id && $coupon != null) {
            $coupon->coupon = $data->coupon_id;
            $coupon->save();
            $data->receiver_id = $checkcustomer->receiver_id;
        } else {
            $customerarray = array("email" => $data->stripeEmail,
            "description" => get_string('charge_description1', 'enrol_stripepayment'));
            $customerarray["coupon"] = $data->coupon_id;
            $customer = Customer::create($customerarray);
            $data->receiver_id = $customer->id;
        }
        $data->receiver_email = $user->email;
        $data->payment_status = 'succeeded';
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
            // Pass $view=true to filter hidden caps if the user cannot see them.
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
        if (!empty($mailstudents)) {
            $orderdetails->coursename = format_string($course->fullname, true, array('context' => $coursecontext));
            $orderdetails->profileurl = "$CFG->wwwroot/user/view.php?id=$user->id";
            $userfrom = empty($teacher) ? core_user::get_support_user() : $teacher;
            $subject = get_string("enrolmentnew", 'enrol', $shortname);
            $fullmessage = get_string('welcometocoursetext', '', $orderdetails);
            $fullmessagehtml = html_to_text('<p>'.get_string('welcometocoursetext', '', $orderdetails).'</p>');
            // Send test email.
            ob_start();
            $success = email_to_user($user, $userfrom, $subject, $fullmessage, $fullmessagehtml);
            $smtplog = ob_get_contents();
            ob_end_clean();
        }
        if (!empty($mailteachers) && !empty($teacher)) {
            $orderdetails->course = format_string($course->fullname, true, array('context' => $coursecontext));
            $orderdetails->user = fullname($user);
            $subject = get_string("enrolmentnew", 'enrol', $shortname);
            $fullmessage = get_string('enrolmentnewuser', 'enrol', $orderdetails);
            $fullmessagehtml = html_to_text('<p>'.get_string('enrolmentnewuser', 'enrol', $orderdetails).'</p>');
            // Send test email.
            ob_start();
            $success = email_to_user($teacher, $user, $subject, $fullmessage, $fullmessagehtml);
            $smtplog = ob_get_contents();
            ob_end_clean();
        }
        if (!empty($mailadmins)) {
            $orderdetails->course = format_string($course->fullname, true, array('context' => $coursecontext));
            $orderdetails->user = fullname($user);
            $admins = get_admins();
            foreach ($admins as $admin) {
                $subject = get_string("enrolmentnew", 'enrol', $shortname);
                $fullmessage = get_string('enrolmentnewuser', 'enrol', $orderdetails);
                $fullmessagehtml = html_to_text('<p>'.get_string('enrolmentnewuser', 'enrol', $orderdetails).'</p>');
                // Send test email.
                ob_start();
                $success = email_to_user($admin, $user, $subject, $fullmessage, $fullmessagehtml);
                $smtplog = ob_get_contents();
                ob_end_clean();
            }
        }
        $destination = "$CFG->wwwroot/course/view.php?id=$course->id";
        $fullname = format_string($course->fullname, true, array('context' => $context));
        if (is_enrolled($context, null, '', true)) { // TODO: use real stripe check.
            redirect($destination, get_string('enrollsuccess', 'enrol_stripepayment') .$fullname);
        } else {   
            // Somehow they aren't enrolled yet!
            $PAGE->set_url($destination);
            echo $OUTPUT->header();
            $orderdetails = new stdClass();
            $orderdetails->teacher = get_string('defaultcourseteacher');
            $orderdetails->fullname = $fullname;
            notice(get_string('paymentsorry', '', $orderdetails), $destination);
        }
        $result = array();
        $result['status'] = 'working';
        return $result;
        die;
    }
    /**
     * Send payment error message to the admin.
     *
     * @param string $subject
     * @param stdClass $data
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
        $success = email_to_user($admin, $admin, $subject, $fullmessage, $fullmessagehtml);
        $smtplog = ob_get_contents();
        ob_end_clean();
    }
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
    public static function stripe_js_method( $user_id, $couponid, $instance_id) {
        global $CFG, $DB;
        $user_id = $user_id;
        $plugin = enrol_get_plugin('stripepayment');
        $secretkey = $plugin->get_config('secretkey');
        $user_token = $plugin->get_config('webservice_token');
        if (! $plugininstance = $DB->get_record("enrol", array("id" => $instance_id, "status" => 0))) {
            self::message_stripepayment_error_to_admin(get_string('invalidinstance', 'enrol_stripepayment'), $data);
            redirect($CFG->wwwroot);
        }
        $amount = $plugin->get_stripe_amount($plugininstance->cost, $instance->currency, false);
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
        $description = $description = format_string($course->fullname, true, array('context' => $context));
        if (! $user = $DB->get_record("user", array("id" => $user_id))) {
            self::message_stripepayment_error_to_admin("Not orderdetails valid user id", $data);
            redirect($CFG->wwwroot.'/course/view.php?id='.$courseid);
        }
        if (empty($secretkey) || empty($courseid) || empty($amount) || empty($currency) || empty($description)) {
            redirect($CFG->wwwroot.'/course/view.php?id='.$courseid);
        } else {
            // Set API key 
            Stripe::setApiKey($secretkey); 
            $response = array( 
                'status' => 0, 
                'error' => array( 
                    'message' => get_string('invalidrequest', 'enrol_stripepayment')  
                ) 
            );
            // retrieve Stripe customer_id if previously set
            $checkcustomer = $DB->get_records('enrol_stripepayment',
            array('receiver_email' => $user->email));
            foreach ($checkcustomer as $keydata => $valuedata) {
                $checkcustomer = $valuedata;
            }
            if ($checkcustomer) {
                $receiver_id = $checkcustomer->receiver_id;
                $receiver_email = null;   // must not be set if customer id provided
            } else {
                $receiver_id = null;  // Stripe will create customer id in checkout
                $receiver_email = $user->email;
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
                    'mode' => 'payment',
                    'success_url' => $CFG->wwwroot.'/webservice/rest/server.php?wstoken=' .$user_token. '&wsfunction=moodle_stripepayment_success_stripe_url&moodlewsrestformat=json&session_id={CHECKOUT_SESSION_ID}&user_id=' .$user_id. '&couponid=' .$couponid. '&instance_id=' .$instance_id. '',
                    'cancel_url' => $CFG->wwwroot.'/course/view.php?id='.$courseid, 
                ]);
            } catch(Exception $e) {
                $api_error = $e->getMessage();
            }
            if(empty($api_error) && $session) { 
                $response = array( 
                    'status' => 1, 
                    'message' => get_string('sessioncreated', 'enrol_stripepayment'), 
                    'sessionId' => $session['id'] 
                ); 
            } else { 
                $response = array( 
                    'status' => 0,
                    'error' => array( 
                        'message' => get_string('sessioncreatefail', 'enrol_stripepayment') .$api_error    
                    )
                ); 
            }
            // Return response 
            $pass_session_id = isset($response['sessionId']) && !empty($response['sessionId']) ? $response['sessionId'] : '';
            $result = array();
            $result['status'] = $pass_session_id;
            return $result;
            die;
        }
    }
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
        $session_id = $session_id;
        $plugin = enrol_get_plugin('stripepayment');
        $secretkey = $plugin->get_config('secretkey');
        Stripe::setApiKey($secretkey);
        $checkout_session = Session::retrieve($session_id); 
        $charge = PaymentIntent::retrieve($checkout_session->payment_intent);
        $data->coupon_id = $couponid;
        $data->stripeEmail = $charge->receipt_email;
        $data->receiver_id = $charge->customer;
        if (! $plugininstance = $DB->get_record("enrol", array("id" => $instance_id, "status" => 0))) {
            self::message_stripepayment_error_to_admin(get_string('invalidinstance', 'enrol_stripepayment'), $data);
            redirect($CFG->wwwroot);
        }
        $courseid = $plugininstance->courseid;
        $data->courseid = $courseid;
        $data->instanceid = $instance_id;
        $user_id = $user_id;
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
            $iscoupon = false;
            if ($data->coupon_id && $data->coupon_id != '0') {
                $coupon = Coupon::retrieve($data->coupon_id);
                if (!$coupon->valid) {
                    redirect($CFG->wwwroot.'/enrol/index.php?id='.$data->courseid, get_string("invalidcouponcodevalue",
                        "enrol_stripepayment", $data->coupon_id));
                } else {
                    $iscoupon = true;
                    if (isset($coupon->percent_off)) {
                        $cost = $cost - ( $cost * ( $coupon->percent_off / 100 ) );
                    } elseif (isset($coupon->amount_off)) {
                        $cost = (($cost * 100) - $coupon->amount_off) / 100;
                    }
                }
            }
            // if coupon used, redeem that, saving with the customer
            if ($iscoupon) {
                $coupon = Customer::retrieve($data->receiver_id);
                $coupon->coupon = $data->coupon_id;
                $coupon->save();
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
            // Stripe Authentication Checking.
            $checkemail = $charge->charges->data[0]->billing_details->email;
            // ALL CLEAR !
            // $DB->insert_record("enrol_stripepayment", $data);
            if ($plugininstance->enrolperiod) {
                $timestart = time();
                $timeend   = $timestart + $plugininstance->enrolperiod;
            } else {
                $timestart = 0;
                $timeend   = 0;
            }
            // Enrol user.
            $plugin->enrol_user($plugininstance, $user->id, $plugininstance->roleid, $timestart, $timeend);
            // Pass $view=true to filter hidden caps if the user cannot see them.
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
            if (!empty($mailstudents)) {
                $orderdetails = new stdClass();
                $orderdetails->coursename = format_string($course->fullname, true, array('context' => $coursecontext));
                $orderdetails->profileurl = "$CFG->wwwroot/user/view.php?id=$user->id";
                $userfrom = empty($teacher) ? core_user::get_support_user() : $teacher;
                $subject = get_string("enrolmentnew", 'enrol', $shortname);
                $fullmessage = get_string('welcometocoursetext', '', $orderdetails);
                $fullmessagehtml = html_to_text('<p>'.get_string('welcometocoursetext', '', $orderdetails).'</p>');
                // Send test email.
                ob_start();
                $success = email_to_user($user, $userfrom, $subject, $fullmessage, $fullmessagehtml);
                $smtplog = ob_get_contents();
                ob_end_clean();
            }
            if (!empty($mailteachers) && !empty($teacher)) {
                $orderdetails->course = format_string($course->fullname, true, array('context' => $coursecontext));
                $orderdetails->user = fullname($user);
                $subject = get_string("enrolmentnew", 'enrol', $shortname);
                $fullmessage = get_string('enrolmentnewuser', 'enrol', $orderdetails);
                $fullmessagehtml = html_to_text('<p>'.get_string('enrolmentnewuser', 'enrol', $orderdetails).'</p>');
                // Send test email.
                ob_start();
                $success = email_to_user($teacher, $user, $subject, $fullmessage, $fullmessagehtml);
                $smtplog = ob_get_contents();
                ob_end_clean();
            }
            if (!empty($mailadmins)) {
                $orderdetails->course = format_string($course->fullname, true, array('context' => $coursecontext));
                $orderdetails->user = fullname($user);
                $admins = get_admins();
                foreach ($admins as $admin) {
                    $subject = get_string("enrolmentnew", 'enrol', $shortname);
                    $fullmessage = get_string('enrolmentnewuser', 'enrol', $orderdetails);
                    $fullmessagehtml = html_to_text('<p>'.get_string('enrolmentnewuser', 'enrol', $orderdetails).'</p>');
                    // Send test email.
                    ob_start();
                    $success = email_to_user($admin, $user, $subject, $fullmessage, $fullmessagehtml);
                    $smtplog = ob_get_contents();
                    ob_end_clean();
                }
            }
            $destination = "$CFG->wwwroot/course/view.php?id=$course->id";
            $fullname = format_string($course->fullname, true, array('context' => $context));
            if (is_enrolled($context, $user, '', true)) { // TODO: use real stripe check.
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
        } catch (Stripe_CardError $e) {
            // Catch the errors in any way you like.
            echo get_string('error', 'enrol_stripepayment');
        }
        // Catch the errors in any way you like.
        catch (Stripe_InvalidRequestError $e) {
            // Invalid parameters were supplied to Stripe's API.
            echo get_string('invalidstripeparam', 'enrol_stripepayment');
        } catch (Stripe_AuthenticationError $e) {
            // Authentication with Stripe's API failed
            // (maybe you changed API keys recently).
            echo get_string('stripeauthfail', 'enrol_stripepayment');
        } catch (Stripe_ApiConnectionError $e) {
            // Network communication with Stripe failed.
            echo get_string('connectionfailed', 'enrol_stripepayment');
        } catch (Stripe_Error $e) {
            // Display orderdetails very generic error to the user, and maybe send
            // yourself an email.
            echo get_string('stripeerror', 'enrol_stripepayment');
        } catch (Exception $e) {
            // Something else happened, completely unrelated to Stripe.
            echo get_string('notstripeerror', 'enrol_stripepayment');
        }
    }
}
