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
$string['apply'] = 'Apply';
$string['assignrole'] = 'Assign role';
$string['canntenrol'] = 'Enrolment is disabled or inactive';
$string['canntenrolearly'] = 'You cannot enrol yet. Enrolment starts on {$a}.';
$string['canntenrollate'] = 'You cannot enrol anymore. Enrolment ended on {$a}.';
$string['charge_description1'] = "create customer for email receipt";
$string['cost'] = 'Enrol cost';
$string['costerror'] = 'The enrolment cost is not numeric';
$string['costzeroerror'] = 'Cost cannot be 0 or negative. Free enrollment is only available through coupon application that reduces cost to 0.';
$string['costminimumerror'] = 'Amount is less than supported minimum ({$a}). Please set a higher amount.';
$string['couponminimumerror'] = 'After applying the coupon, the amount ({$a->amount}) is less than the supported minimum ({$a->minimum}). Please contact admin for assistance.';
$string['couponappling'] = 'Applying...';
$string['couponcode'] = 'Coupon code';
$string['create_user_token'] = 'Then enable Moodle REST protocol on your site';
$string['currency'] = 'Currency';
$string['defaultrole'] = 'Default role assignment';
$string['defaultrole_desc'] = 'Select role which should be assigned to users during stripe enrolments';
$string['discount'] = 'Discount';
$string['discountapplied'] = 'Discount applied';
$string['enablecouponsection'] = 'Enable coupon section';
$string['enablewebservicesfirst'] = 'REQUIRED: first enable web services globally';
$string['enabledrestprotocol'] = ' You must also create a token of moodle_enrol_stripepayment service with administrator privilege ';
$string['enrol'] = 'Enrol';
$string['enrolbtncolor'] = 'Choose enroll button color';
$string['enrolbtncolordes'] = 'Choose your own custom Color scheme for the Enroll button.';
$string['enrolnow'] = 'Enrol now';
$string['enrolenddate'] = 'End date';
$string['enrolenddate_help'] = 'If enabled, users can be enrolled until this date only.';
$string['enrolenddaterror'] = 'Enrolment end date cannot be earlier than start date';
$string['enrolperiod'] = 'Enrolment duration';
$string['enrolperioddesc'] = 'Default length of time that the enrolment is valid. If set to zero, the enrolment duration will be unlimited by default.';
$string['enrolperiod_help'] = 'Length of time that the enrolment is valid, starting with the moment the user is enrolled. If disabled, the enrolment duration will be unlimited.';
$string['enrolstartdate'] = 'Start date';
$string['enrolstartdate_help'] = 'If enabled, users can be enrolled from this date onward only.';
$string['entercoupon'] = 'Please enter a coupon code';
$string['error'] = 'Error! ';
$string['expiredaction'] = 'Enrolment expiration action';
$string['expiredactionhelp'] = 'Select action to carry out when user enrolment expires. Please note that some user data and settings are purged from course during course unenrolment.';
$string['fromhere'] = 'from here';
$string['invalidcontextid'] = 'Not a valid context id! ';
$string['invalidcoupon'] = 'Invalid coupon!';
$string['invalidcouponcode'] = 'Invalid coupon code';
$string['invalidcourseid'] = 'Not a valid course id!';
$string['invalidinstance'] = 'Not a valid instance id!';
$string['invalidrequest'] = 'Invalid request!';
$string['invaliduserid'] = 'Not a valid user id! ';
$string['livemode'] = 'Live Mode';
$string['mailadmins'] = 'Notify admin';
$string['mailstudents'] = 'Notify students';
$string['mailteachers'] = 'Notify teachers';
$string['maxenrolled'] = 'Max enrolled users';
$string['maxenrolled_help'] = 'Specifies the maximum number of users that can stripepayment enrol. 0 means no limit.';
$string['maxenrolledreached'] = 'Maximum number of users allowed to stripepayment-enrol was already reached.';
$string['maxenrolledhelp'] = 'Stripe enrolment messages';
$string['messageprovider:stripepayment_enrolment'] = 'Stripe payment enrolment notifications';
$string['pleasewait'] = 'Please wait...';
$string['pluginname'] = 'Stripe Payment';
$string['pluginnamedesc'] = 'The stripe module allows you to set up paid courses.  If the cost for any course is zero, then students are not asked to pay for entry.  There is a site-wide cost that you set here as a default for the whole site and then a course setting that you can set for each course individually. The course cost overrides the site cost.';
$string['publishablekey'] = 'Stripe Publishable Key';
$string['publishablekeydesc'] = 'The API publishable key of stripe account';
$string['secretkey'] = 'Stripe secret Key';
$string['secretkeydesc'] = 'The API secret key of stripe account';
$string['paymenterror'] = 'Payment session creation failed.';
$string['sessioncreated'] = 'Checkout session created successfully!';
$string['sessioncreatefail'] = 'Checkout session creation failed! ';
$string['status'] = 'Allow stripe enrolments';
$string['status_desc'] = 'Allow users to use stripe to enrol into a course by default.';
$string['stripe:config'] = 'Configure stripe enrol instances';
$string['stripe:manage'] = 'Manage enrolled users';
$string['stripe:unenrol'] = 'Unenrol users from course';
$string['stripe:unenrolself'] = 'Unenrol self from the course';
$string['stripeerror'] = 'Stripe error ';
$string['stripepayment:manage'] = 'Manage stripepayment';
$string['stripepayment:unenrol'] = 'Unenrol stripepayment';
$string['subtotal'] = 'Subtotal';
$string['stripepayment:unenrolself'] = 'Unenrolself stripepayment';
$string['stripemodesettings'] = 'Stripe Mode Settings';
$string['stripemodedesc'] = 'Select whether to use Test or Live Stripe API keys. Test mode is safe for development and testing. Live mode processes real payments.';
$string['stripemode'] = 'Stripe Mode';
$string['stripemodesettingsdesc'] = 'Configure Live and Test mode API keys and switch between them easily.';
$string['tokenemptyerror'] = 'Web service token could not be empty';
$string['totaldue'] = 'Total due';
$string['testmode'] = 'Test Mode';
$string['testpublishablekey'] = 'Test Publishable Key';
$string['testpublishablekeydesc'] = 'Your Stripe test publishable key (starts with pk_test_)';
$string['testsecretkey'] = 'Test Secret Key';
$string['testsecretkeydesc'] = 'Your Stripe test secret key (starts with sk_test_)';
$string['testapikeys'] = 'Test Mode API Keys';
$string['testapikeysdesc'] = 'These keys are used when Test Mode is selected. Test keys start with "pk_test_" and "sk_test_".';
$string['unenrolselfconfirm'] = 'Do you really want to unenrol yourself from course "{$a}"?';
$string['unmatchedcourse'] = 'Course id does not match to the course settings, received: ';
$string['webservicetokenstring'] = 'User token';
$string['currentmodestatus'] = 'Current Mode Status';
$string['liveapikeys'] = 'Live Mode API Keys';
$string['liveapikeysdesc'] = 'These keys are used when Live Mode is selected. Live keys start with "pk_live_" and "sk_live_". ⚠️ CAUTION: Live keys process real payments!';
$string['livepublishablekey'] = 'Live Publishable Key';
$string['livepublishablekeydesc'] = 'Your Stripe live publishable key (starts with pk_live_) ⚠️ LIVE KEY';
$string['livesecretkey'] = 'Live Secret Key';
$string['livesecretkeydesc'] = 'Your Stripe live secret key (starts with sk_live_) ⚠️ LIVE KEY';
$string['legacyapikeys'] = 'Legacy API Keys (Deprecated)';
$string['legacyapikeysdesc'] = 'These fields are maintained for backward compatibility. Use the Test/Live mode keys above instead.';