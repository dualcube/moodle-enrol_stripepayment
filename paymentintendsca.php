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

require_once('../../config.php');
require('Stripe/init.php');
global $DB, $USER, $CFG;

$secretkey = required_param('secretkey', PARAM_RAW);
$courseid = required_param('courseid', PARAM_RAW);
$amount = required_param('amount', PARAM_RAW);
$currency = required_param('currency', PARAM_RAW);
$description = required_param('description', PARAM_RAW);
$receiptemail = required_param('receiptemail', PARAM_RAW);

if(empty($secretkey) || empty($courseid) || empty($amount) || empty($currency) || empty($description) || empty($receiptemail)) {
	redirect($CFG->wwwroot.'/course/view.php?id='.$courseid);
}else{
	\Stripe\Stripe::setApiKey($secretkey);

	$intent = \Stripe\PaymentIntent::create([
	    'amount' => $amount,
	    'payment_method_types' => ['card'],
	    'currency' => $currency,
	    'description' => $description,
	    'receipt_email' => $receiptemail,
	    'setup_future_usage' => 'off_session',
	]);
}

if (isset($intent->client_secret)) {
	echo $intent->client_secret;
}

die;