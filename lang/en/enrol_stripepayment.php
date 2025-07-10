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
 * Strings for component 'enrol_stripepayment', language 'en'.
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2019 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
defined('MOODLE_INTERNAL') || die();
// $string['applycode'] = 'Apply code';
$string['assignrole'] = 'Assign role';
$string['buy_now'] = 'Buy now';
// $string['canntenrol'] = 'Enrolment is disabled or inactive';
$string['charge_description1'] = "create customer for email receipt";
// $string['charge_description2'] = 'Charge for Course Enrolment Cost.';
// $string['connectionfailed'] = 'Network communication with Stripe failed';
$string['cost'] = 'Enrol cost';
$string['costerror'] = 'The enrolment cost is not numeric';
// $string['costorkey'] = 'Please choose one of the following methods of enrolment.';
// $string['couponapplied'] = 'Coupon Applied';
$string['couponcode'] = 'Coupon Code';
// $string['couponcodedescription'] = 'If you have any coupon please apply here';
$string['create_user_token'] = 'Then enable Moodle REST protocol on your site';
$string['currency'] = 'Currency';
$string['defaultrole'] = 'Default role assignment';
$string['defaultrole_desc'] = 'Select role which should be assigned to users during Stripe enrolments';
$string['enable_coupon_section'] = 'Enable coupon section';
$string['enable_webservices_first'] = 'REQUIRED: First enable web services globally';
$string['enabled_rest_protocol'] = ' You must also create a token of moodle_enrol_stripepayment service with Administrator privilege ';
$string['enrol'] = 'Enrol';
$string['enrol_btn_color'] = 'Choose Enroll button Color';
$string['enrol_btn_color_des'] = 'Choose your own custom Color scheme for the Enroll Button.';
$string['enrol_now'] = 'Enrol Now';
$string['enrolenddate'] = 'End date';
$string['enrolenddate_help'] = 'If enabled, users can be enrolled until this date only.';
$string['enrolenddaterror'] = 'Enrolment end date cannot be earlier than start date';
// $string['enrollsuccess'] = 'Thankyou! Now you are enrolled into the course ';
$string['enrolperiod'] = 'Enrolment duration';
$string['enrolperiod_desc'] = 'Default length of time that the enrolment is valid. If set to zero, the enrolment duration will be unlimited by default.';
$string['enrolperiod_help'] = 'Length of time that the enrolment is valid, starting with the moment the user is enrolled. If disabled, the enrolment duration will be unlimited.';
$string['enrolstartdate'] = 'Start date';
$string['enrolstartdate_help'] = 'If enabled, users can be enrolled from this date onward only.';
$string['error'] = 'Error! ';
$string['expiredaction'] = 'Enrolment expiration action';
$string['expiredaction_help'] = 'Select action to carry out when user enrolment expires. Please note that some user data and settings are purged from course during course unenrolment.';
// $string['final_cost'] = 'Final Cost';
$string['from_here'] = 'from here';
$string['invalidcontextid'] = 'Not a valid context id! ';
$string['invalidcoupon'] = 'Invalid coupon!';
$string['invalidcouponcode'] = 'Invalid Coupon Code';
// $string['invalidcouponcodevalue'] = 'Coupon Code {$a} is not valid!';
$string['invalidcourseid'] = 'Not a valid course id!';
$string['invalidinstance'] = 'Not a valid instance id!';
$string['invalidrequest'] = 'Invalid Request!';
// $string['invalidstripeparam'] = 'Invalid parameters were supplied to Stripe API';
$string['invaliduserid'] = 'Not a valid user id! ';
$string['mailadmins'] = 'Notify admin';
$string['mailstudents'] = 'Notify students';
$string['mailteachers'] = 'Notify teachers';
$string['maxenrolled'] = 'Max enrolled users';
$string['maxenrolled_help'] = 'Specifies the maximum number of users that can stripepayment enrol. 0 means no limit.';
$string['maxenrolledreached'] = 'Maximum number of users allowed to stripepayment-enrol was already reached.';
$string['messageprovider:stripe_enrolment'] = 'Stripe enrolment messages';
$string['messageprovider:stripepayment_enrolment'] = 'Stripe payment enrolment notifications';
// $string['newcost'] = 'New Cost';
// $string['nocost'] = 'There is no cost associated with enrolling in this course!';
// $string['notstripeerror'] = 'Something else happened, completely unrelated to Stripe';
$string['please_wait'] = 'Please wait...';
// $string['plugin_slug'] = 'stripepayment';
$string['pluginname'] = 'Stripe Payment';
$string['pluginname_desc'] = 'The Stripe module allows you to set up paid courses.  If the cost for any course is zero, then students are not asked to pay for entry.  There is a site-wide cost that you set here as a default for the whole site and then a course setting that you can set for each course individually. The course cost overrides the site cost.';
$string['publishablekey'] = 'Stripe Publishable Key';
$string['publishablekey_desc'] = 'The API Publishable Key of Stripe account';
$string['secretkey'] = 'Stripe Secret Key';
$string['secretkey_desc'] = 'The API Secret Key of Stripe account';
// $string['sendpaymentbutton'] = 'Send payment via Stripe';
$string['sessioncreated'] = 'Checkout Session created successfully!';
$string['sessioncreatefail'] = 'Checkout Session creation failed! ';
$string['status'] = 'Allow Stripe enrolments';
$string['status_desc'] = 'Allow users to use Stripe to enrol into a course by default.';
$string['stripe:config'] = 'Configure Stripe enrol instances';
$string['stripe:manage'] = 'Manage enrolled users';
$string['stripe:unenrol'] = 'Unenrol users from course';
$string['stripe:unenrolself'] = 'Unenrol self from the course';
// $string['stripe_sorry'] = "Sorry, you can not use the script that way.";
// $string['stripeaccepted'] = 'Stripe payments accepted';
// $string['stripeauthfail'] = 'Authentication with Stripe API failed';
$string['stripeerror'] = 'Stripe Error ';
// $string['stripepayment:config'] = 'Configure stripepayment';
$string['stripepayment:manage'] = 'Manage stripepayment';
$string['stripepayment:unenrol'] = 'Unenrol stripepayment';
$string['stripepayment:unenrolself'] = 'Unenrolself stripepayment';
$string['token_empty_error'] = 'Web service token could not be empty';
$string['unenrolselfconfirm'] = 'Do you really want to unenrol yourself from course "{$a}"?';
$string['unmatchedcourse'] = 'Course Id does not match to the course settings, received: ';
$string['webservice_token_string'] = 'User Token';
