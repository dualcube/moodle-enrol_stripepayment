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


echo PARAM_RAW;

exit;

require('../../config.php');
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


print_r($_REQUEST);

// Keep out casual intruders.
if (empty(required_param('stripeToken', PARAM_RAW))) {
    print_error(get_string('stripe_sorry', 'enrol_stripepayment'));
}

$data = new stdClass();

$data->cmd = required_param('cmd', PARAM_RAW);
$data->charset = required_param('charset', PARAM_RAW);
$data->item_name = required_param('item_name', PARAM_TEXT);
$data->item_name = required_param('item_number', PARAM_TEXT);
$data->item_name = required_param('quantity', PARAM_INT);
$data->on0 = optional_param('on0', array(), PARAM_TEXT);
$data->os0 = optional_param('os0', array(), PARAM_TEXT);
$data->custom = optional_param('custom', array(), PARAM_RAW);
$data->currency_code = required_param('currency_code', PARAM_RAW);
$data->amount = required_param('amount', PARAM_RAW);
$data->for_auction = required_param('for_auction', PARAM_BOOL);
$data->no_note = required_param('no_note', PARAM_INT);
$data->no_shipping = required_param('no_shipping', PARAM_INT);
$data->rm = required_param('rm', PARAM_RAW);
$data->cbt = optional_param('cbt', array(), PARAM_TEXT);
$data->first_name = required_param('first_name', PARAM_TEXT);
$data->last_name = required_param('last_name', PARAM_TEXT);
$data->address = optional_param('address', array(), PARAM_TEXT);
$data->city = optional_param('city', array(), PARAM_TEXT);
$data->email = required_param('email', PARAM_EMAIL);
$data->country = optional_param('country', array(), PARAM_TEXT);
$data->stripeToken = required_param('stripeToken', PARAM_RAW);
$data->stripeTokenType = required_param('stripeTokenType', PARAM_RAW);
$data->stripeEmail = required_param('stripeEmail', PARAM_EMAIL);

$custom = explode('-', $data->custom);
$data->userid           = (int)$custom[0];
$data->courseid         = (int)$custom[1];
$data->instanceid       = (int)$custom[2];
$data->payment_gross    = $data->amount;
$data->payment_currency = $data->currency_code;
$data->timeupdated      = time();
// Get the user and course records.

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

// Let's say each article costs 15.00 bucks.

try {

    require_once('Stripe/lib/Stripe.php');

    Stripe::setApiKey($plugin->get_config('secretkey'));
    $charge1 = Stripe_Customer::create(array(
        "email" => required_param('stripeEmail', PARAM_EMAIL),
        "description" => get_string('charge_description1', 'enrol_stripepayment')
    ));
    $charge = Stripe_Charge::create(array(
      "amount" => $cost * 100,
      "currency" => $plugininstance->currency,
      "card" => required_param('stripeToken', PARAM_RAW),
      "description" => get_string('charge_description2', 'enrol_stripepayment'),
      "receipt_email" => required_param('stripeEmail', PARAM_EMAIL)
    ));
    // Send the file, this line will be reached if no error was thrown above.
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