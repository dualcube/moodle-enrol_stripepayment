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
 * Listens for Instant Payment Notification from Stripe
 *
 * This script waits for Payment notification from Stripe,
 * then double checks that data by sending it back to Stripe.
 * If Stripe verifies this then it sets up the enrolment for that
 * user.
 *
 * @package    enrol_stripepayment
 * @copyright  2019 Dualcube Team
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Disable moodle specific debug messages and any errors in output,
// comment out when debugging or better look into error log!
define('NO_DEBUG_DISPLAY', true);
global $DB, $USER, $CFG, $_SESSION;
require('Stripe/init.php');
require("../../config.php");
require_once("lib.php");
require_once($CFG->libdir.'/enrollib.php');
require_once($CFG->libdir . '/filelib.php');

require_login();

$data = new stdClass();

$session_id = $_GET['session_id'];
$plugin = enrol_get_plugin('stripepayment');
$secretkey = $plugin->get_config('secretkey');
\Stripe\Stripe::setApiKey($secretkey);

$checkout_session = \Stripe\Checkout\Session::retrieve($session_id); 
$charge = \Stripe\PaymentIntent::retrieve($checkout_session->payment_intent);

$data->coupon_id = $_SESSION['coupon_id'];

$data->stripeEmail = $charge->receipt_email;
$data->courseid = $_SESSION['courseid'];
$data->instanceid = $_SESSION['instance_id'];
$data->userid = (int)$_SESSION['user_id'];

if (! $user = $DB->get_record("user", array("id" => $data->userid))) {
    message_stripepayment_error_to_admin("Not a valid user id", $data);
    redirect($CFG->wwwroot);
}

if (! $course = $DB->get_record("course", array("id" => $data->courseid))) {
    message_stripepayment_error_to_admin("Not a valid course id", $data);
    redirect($CFG->wwwroot);
}

if (! $context = context_course::instance($course->id, IGNORE_MISSING)) {
    message_stripepayment_error_to_admin("Not a valid context id", $data);
    redirect($CFG->wwwroot);
}

$PAGE->set_context($context);

if (! $plugininstance = $DB->get_record("enrol", array("id" => $data->instanceid, "status" => 0))) {
    message_stripepayment_error_to_admin("Not a valid instance id", $data);
    redirect($CFG->wwwroot);
}

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
    if ($data->coupon_id && $data->coupon_id != 0) {
        $coupon = \Stripe\Coupon::retrieve($data->coupon_id);
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

    $checkcustomer = $DB->get_records('enrol_stripepayment',
    array('receiver_email' => $data->stripeEmail));
    foreach ($checkcustomer as $keydata => $valuedata) {
        $checkcustomer = $valuedata;
    }

    if (!$checkcustomer) {
        $customerarray = array("email" => $data->stripeEmail,
        "description" => get_string('charge_description1', 'enrol_stripepayment'));
        if ($iscoupon) {
            $customerarray["coupon"] = $data->coupon_id;
        }
        $charge1 = \Stripe\Customer::create($customerarray);
        $data->receiver_id = $charge1->id;
    } else {
        if ($iscoupon) {
            $cu = \Stripe\Customer::retrieve($checkcustomer->receiver_id);
            $cu->coupon = $data->coupon_id;
            $cu->save();
        } else {
            $cu = \Stripe\Customer::retrieve($checkcustomer->receiver_id);
            $cu->coupon = null;
            $cu->save();
        }
        $data->receiver_id = $checkcustomer->receiver_id;
    }

    // Send the file, this line will be reached if no error was thrown above.

    if (!isset($charge->failure_message) || is_null($charge->failure_message)) {
        $charge->failure_message = 'NA';
    }
    if (!isset($charge->failure_code) || is_null($charge->failure_code)) {
        $charge->failure_code = 'NA';
    }

    $data->receiver_email = $data->stripeEmail;
    $data->txn_id = $charge->id;
    $data->tax = $charge->amount / 100;
    $data->memo = $charge->payment_method;
    $data->payment_status = $charge->status;
    $data->pending_reason = $charge->failure_message;
    $data->reason_code = $charge->failure_code;

    // Stripe Authentication Checking.

    $checkemail = $charge->charges->data[0]->billing_details->email;

    $destination = "$CFG->wwwroot/course/view.php?id=$course->id";

    if ($checkemail != $USER->email) {
        redirect($destination, 'Stripe Authentication Error');
    }

    // ALL CLEAR !

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

    if (!empty($mailstudents)) {
        $a = new stdClass();
        $a->coursename = format_string($course->fullname, true, array('context' => $coursecontext));
        $a->profileurl = "$CFG->wwwroot/user/view.php?id=$user->id";

        $userfrom = empty($teacher) ? core_user::get_support_user() : $teacher;
        $subject = get_string("enrolmentnew", 'enrol', $shortname);
        $fullmessage = get_string('welcometocoursetext', '', $a);
        $fullmessagehtml = html_to_text('<p>'.get_string('welcometocoursetext', '', $a).'</p>');

        // Send test email.
        ob_start();
        $success = email_to_user($user, $userfrom, $subject, $fullmessage, $fullmessagehtml);
        $smtplog = ob_get_contents();
        ob_end_clean();
    }

    if (!empty($mailteachers) && !empty($teacher)) {
        $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
        $a->user = fullname($user);

        $subject = get_string("enrolmentnew", 'enrol', $shortname);
        $fullmessage = get_string('enrolmentnewuser', 'enrol', $a);
        $fullmessagehtml = html_to_text('<p>'.get_string('enrolmentnewuser', 'enrol', $a).'</p>');

        // Send test email.
        ob_start();
        $success = email_to_user($teacher, $user, $subject, $fullmessage, $fullmessagehtml);
        $smtplog = ob_get_contents();
        ob_end_clean();
    }

    if (!empty($mailadmins)) {
        $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
        $a->user = fullname($user);
        $admins = get_admins();
        foreach ($admins as $admin) {
            $subject = get_string("enrolmentnew", 'enrol', $shortname);
            $fullmessage = get_string('enrolmentnewuser', 'enrol', $a);
            $fullmessagehtml = html_to_text('<p>'.get_string('enrolmentnewuser', 'enrol', $a).'</p>');

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
        redirect($destination, get_string('paymentthanks', '', $fullname));

    } else {   // Somehow they aren't enrolled yet!
        $PAGE->set_url($destination);
        echo $OUTPUT->header();
        $a = new stdClass();
        $a->teacher = get_string('defaultcourseteacher');
        $a->fullname = $fullname;
        notice(get_string('paymentsorry', '', $a), $destination);
    }
} catch (Stripe_CardError $e) {
    // Catch the errors in any way you like.
    echo 'Error';
}

// Catch the errors in any way you like.

catch (Stripe_InvalidRequestError $e) {
    // Invalid parameters were supplied to Stripe's API.
    echo 'Invalid parameters were supplied to Stripe\'s API';

} catch (Stripe_AuthenticationError $e) {
    // Authentication with Stripe's API failed
    // (maybe you changed API keys recently).
    echo 'Authentication with Stripe\'s API failed';

} catch (Stripe_ApiConnectionError $e) {
    // Network communication with Stripe failed.
    echo 'Network communication with Stripe failed';
} catch (Stripe_Error $e) {

    // Display a very generic error to the user, and maybe send
    // yourself an email.
    echo 'Stripe Error';
} catch (Exception $e) {

    // Something else happened, completely unrelated to Stripe.
    echo 'Something else happened, completely unrelated to Stripe';
}

/**
 * Send payment error message to the admin.
 *
 * @param string $subject
 * @param stdClass $data
 */
function message_stripepayment_error_to_admin($subject, $data) {
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