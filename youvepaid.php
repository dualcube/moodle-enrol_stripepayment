<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
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
 * @copyright  2019 Dualcube, Arkaprava Midya, Parthajeet Chakraborty, Louis Bronne (Natagora)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// Disable moodle specific debug messages and any errors in output,
// comment out when debugging or better look into error log!
define('NO_DEBUG_DISPLAY', true);

print_r($_REQUEST);

require('/home/ubuntu/moodle/config.php');
// require('../../config.php'); //:TODO: remettre ceci avant de passer en production!!!
require_once('lib.php');
if($CFG->version < 2018101900)
{
    require_once($CFG->libdir.'/eventslib.php');
}
require_once($CFG->libdir.'/enrollib.php');
require_once($CFG->libdir.'/filelib.php');


require_login();
// Stripe does not like when we return error messages here,
// the custom handler just logs exceptions and stops.
set_exception_handler('enrol_stripepayment_charge_exception_handler');


$data = new stdClass();

$param_session_id = required_param('session',PARAM_RAW);
$param_course_id = required_param('c',PARAM_INT);
$param_instance_id = required_param('i',PARAM_INT);
$param_user_id = required_param('u',PARAM_INT);

if (! $user = $DB->get_record("user", array("id" => $param_user_id))) {
    message_stripepayment_error_to_admin("Not a valid user id", $data);
    redirect($CFG->wwwroot);
}

if (! $course = $DB->get_record("course", array("id" => $param_course_id))) {
    message_stripepayment_error_to_admin("Not a valid course id", $data);
    redirect($CFG->wwwroot);
}

if (! $plugininstance = $DB->get_record("enrol", array("id" => $param_instance_id, "status" => 0))) {
    message_stripepayment_error_to_admin("Not a valid instance id", $data);
    redirect($CFG->wwwroot);
}

if (! $context = context_course::instance($course->id, IGNORE_MISSING)) {
    message_stripepayment_error_to_admin("Not a valid context id", $data);
    redirect($CFG->wwwroot);
}

$shortname = format_string($course->shortname, true, array('context' => $context));
$coursefullname  = format_string($course->fullname, true, array('context' => $context));
$courseshortname = $shortname;
$userfullname    = fullname($user);
$userfirstname   = $user->firstname;
$userlastname    = $user->lastname;
$useraddress     = $user->address;
$usercity        = $user->city;
$useremail       = $user->email;

// preparing data values (same template as in earlier versions of enrol_stripepayment)

$data->cmd = "_xclick";
$data->charset = "utf-8";
$data->item_name = $coursefullname;
$data->item_name = $courseshortname; // should be item_number (original error left)
$data->item_name = 1;
$data->on0 = get_string("user");
$data->os0 = $userfullname;
$data->custom = "{$param_user_id}-{$param_course_id}-{$param_instance_id}";
$data->currency_code = $plugininstance->currency;
$data->amount = (float) $plugininstance->cost;
$data->for_auction = False;
$data->no_note = 1;
$data->no_shipping = 1;
$data->rm = 2;
$data->cbt = get_string("continuetocourse");
$data->first_name = $userfirstname;
$data->last_name = $userlastname;
$data->address = $useraddress;
$data->city = $usercity;
$data->email = $user->email;
$data->country = $user->country;
$data->stripeToken = "";
$data->stripeTokenType = "";
$data->stripeEmail = "";

$custom = explode('-', $data->custom);
$data->userid           = (int)$param_user_id;
$data->courseid         = (int)$param_course_id;
$data->instanceid       = (int)$param_instance_id;
$data->payment_gross    = $data->amount;
$data->payment_currency = $data->currency_code;
$data->timeupdated      = time();
// Get the user and course records.

$PAGE->set_context($context);

 // If currency is incorrectly set then someone maybe trying to cheat the system.

if ($data->courseid != $plugininstance->courseid) {
    message_stripepayment_error_to_admin("Course Id does not match to the course settings, received: ".$data->courseid, $data);
    redirect($CFG->wwwroot);
}

$plugin = enrol_get_plugin('stripepayment');

// Check that amount paid is the correct amount.
if ( (float) $plugininstance->cost <= 0 ) {
    $cost = (float) $plugin->get_config('cost');
} else {
    $cost = (float) $plugininstance->cost;
}

// Use the same rounding of floats as on the enrol form.
$cost = format_float($cost, 2, false);

try {

    require_once('Stripe/lib/Stripe.php');

    Stripe::setApiKey($plugin->get_config('secretkey'));
	
	$session = \Stripe\Checkout\Session::retrieve($param_session_id);
	
	$payment_intent_id = $session["payment_intent"];
	
	echo "payment_intent = $payment_intent_id";

    $charge = \Stripe\Charge::all([
		'payment_intent' => $payment_intent_id
		]);
		
	print_r($charge);

	echo "We chould check now if the amount is correct.";
	if ($charge->amount / 100 - (float)$plugininstance->cost < -0.01) {
		throw new Exception('Amount paid on Stripe is lower than payment due');
	}
/*
    $charge = Stripe_Charge::create(array(
      "amount" => $cost * 100,
      "currency" => $plugininstance->currency,
      "card" => required_param('stripeToken', PARAM_RAW),
      "description" => get_string('charge_description2', 'enrol_stripepayment'),
      "receipt_email" => required_param('stripeEmail', PARAM_EMAIL)
    ));
	
	*/
    // Send the file, this line will be reached if no error was thrown above.
	// TODO: Louis
	
    $data->txn_id = $charge->balance_transaction;
    $data->tax = $charge->amount / 100;
    $data->memo = $charge->id;
    $data->payment_status = $charge->status;
    $data->pending_reason = $charge->failure_message;
    $data->reason_code = $charge->failure_code;

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


    if (!empty($mailstudents)) {
            $a = new stdClass();
            $a->coursename = format_string($course->fullname, true, array('context' => $coursecontext));
            $a->profileurl = "$CFG->wwwroot/user/view.php?id=$user->id";

            $eventdata = new \core\message\message();
            $eventdata->modulename        = 'moodle';
            $eventdata->component         = 'enrol_stripepayment';
            $eventdata->name              = 'stripepayment_enrolment';
            $eventdata->userfrom          = empty($teacher) ? core_user::get_support_user() : $teacher;
            $eventdata->userto            = $user;
            $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
            $eventdata->fullmessage       = get_string('welcometocoursetext', '', $a);
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml   = '';
            $eventdata->smallmessage      = '';
            message_send($eventdata);
    }

    if (!empty($mailteachers) && !empty($teacher)) {
            $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
            $a->user = fullname($user);

            $eventdata = new \core\message\message();
            $eventdata->modulename        = 'moodle';
            $eventdata->component         = 'enrol_stripepayment';
            $eventdata->name              = 'stripepayment_enrolment';
            $eventdata->userfrom          = $user;
            $eventdata->userto            = $teacher;
            $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
            $eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml   = '';
            $eventdata->smallmessage      = '';
            message_send($eventdata);
    }

    if (!empty($mailadmins)) {
        $a->course = format_string($course->fullname, true, array('context' => $coursecontext));
        $a->user = fullname($user);
        $admins = get_admins();
        foreach ($admins as $admin) {
            $eventdata = new \core\message\message();
            $eventdata->modulename        = 'moodle';
            $eventdata->component         = 'enrol_stripepayment';
            $eventdata->name              = 'stripepayment_enrolment';
            $eventdata->userfrom          = $user;
            $eventdata->userto            = $admin;
            $eventdata->subject           = get_string("enrolmentnew", 'enrol', $shortname);
            $eventdata->fullmessage       = get_string('enrolmentnewuser', 'enrol', $a);
            $eventdata->fullmessageformat = FORMAT_PLAIN;
            $eventdata->fullmessagehtml   = '';
            $eventdata->smallmessage      = '';
            message_send($eventdata);
        }
    }

    $destination = "$CFG->wwwroot/course/view.php?id=$course->id";

    $fullname = format_string($course->fullname, true, array('context' => $context));

    if (is_enrolled($context, null, '', true)) {
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
    echo 'Something else happened, completely unrelated to Stripe ('.$e->getMessage().')';
}


    // --- HELPER FUNCTIONS --------------------------------------------------------------------------------------!

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

    $eventdata = new stdClass();
    $eventdata->modulename        = 'moodle';
    $eventdata->component         = 'enrol_stripepayment';
    $eventdata->name              = 'stripepayment_enrolment';
    $eventdata->userfrom          = $admin;
    $eventdata->userto            = $admin;
    $eventdata->subject           = "STRIPE PAYMENT ERROR: ".$subject;
    $eventdata->fullmessage       = $message;
    $eventdata->fullmessageformat = FORMAT_PLAIN;
    $eventdata->fullmessagehtml   = '';
    $eventdata->smallmessage      = '';
    message_send($eventdata);
}