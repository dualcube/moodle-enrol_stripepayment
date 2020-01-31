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
require("../../config.php");
require_login();
require_once($CFG->libdir.'/enrollib.php');
require_once('Stripe/init.php');

$answer = 'wrong';
$couponid = required_param('coupon_id', PARAM_RAW);
$plugin = enrol_get_plugin('stripepayment');
$courseid = required_param('courseid', PARAM_RAW);

global $DB;
$plugininstance = $DB->get_record("enrol", array("enrol" => 'stripepayment', "status" => 0, 'courseid' => $courseid));


if ( (float) $plugininstance->cost <= 0 ) {
    $cost = ( float ) $plugin->get_config('cost');
} else {
    $cost = (float) $plugininstance->cost;
}
$cost = format_float($cost, 2, false);


\Stripe\Stripe::setApiKey($plugin->get_config('secretkey'));
// Needs if coupon_id is not blank.
try {
    $coupon = \Stripe\Coupon::retrieve( $couponid );
} catch (Stripe_InvalidRequestError $e) {
    // Variable $answer is already set to false.
    echo $answer;
}

if ($coupon->valid) {
    if (isset($coupon->percent_off)) {
        $cost = $cost - ( $cost * ( $coupon->percent_off / 100 ) );
    } else if (isset($coupon->amount_off)) {
        $cost = (($cost * 100) - $coupon->amount_off) / 100;
    }
    $cost = format_float($cost, 2, false);
    echo $cost;  // This sends 0 or 1.
}
