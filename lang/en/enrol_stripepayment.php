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

$string['adminmessage'] = ' Hello,<br /> A new student has enrolled in a course. <br />Student Name: {$a->username}<br /> Course Name: {$a->course}<br /> Best regards,<br /> {$a->sitename} Team';
$string['apply'] = 'Apply';
$string['assignrole'] = 'Assign role';
$string['canntenrolearly'] = 'You cannot enrol yet. Enrolment starts on {$a}.';
$string['canntenrollate'] = 'You cannot enrol anymore. Enrolment ended on {$a}.';
$string['cost'] = 'Enrol cost';
$string['costminimumerror'] = 'Amount is less than supported minimum ({$a}). Please set a higher amount.';
$string['costzeroerror'] = 'Cost cannot be 0 or negative. Free enrollment is only available through coupon application that reduces cost to 0.';
$string['couponappliedsuccessfully'] = 'Coupon applied successfully.';
$string['couponappling'] = 'Applying...';
$string['couponapply'] = 'Apply';
$string['couponcodeempty'] = 'Coupon code cannot be empty';
$string['couponcurrencymismatch'] = 'Coupon currency does not match course currency';
$string['couponhasexpired'] = 'Coupon has expired.';
$string['couponlimitexceeded'] = 'Coupon usage limit exceeded';
$string['couponminimumerror'] = 'After applying the coupon, the amount ({$a->amount}) is less than the supported minimum ({$a->minimum}). Please contact admin for assistance.';
$string['createusertoken'] = 'Then enable Moodle REST protocol on your site';
$string['crlerror'] = 'cURL error {$a}';
$string['currency'] = 'Currency';
$string['currentmodestatus'] = 'Current Mode Status';
$string['defaultrole'] = 'Default role assignment';
$string['defaultroledesc'] = 'Select role which should be assigned to users during stripe enrolments';
$string['discount'] = 'Discount';
$string['discountapplied'] = 'Discount applied';
$string['enablecouponsection'] = 'Enable coupon section';
$string['enabledrestprotocol'] = ' You must also create a token of {$a} service with Administrator privilege ';
$string['enablewebservicesfirst'] = 'REQUIRED: first enable web services globally';
$string['enrolbtncolor'] = 'Choose enroll button color';
$string['enrolbtncolordes'] = 'Choose your own custom Color scheme for the Enroll button.';
$string['enrolenddate'] = 'End date';
$string['enrolenddate_help'] = 'If enabled, users can be enrolled until this date only.';
$string['enrolenddaterror'] = 'Enrolment end date cannot be earlier than start date';
$string['enrollmentinstancenotfound'] = 'Enrollment instance not found or disabled';
$string['enrolmentnew'] = 'New Student Enrollment: {$a->username} in {$a->course}';
$string['enrolmentuser'] = 'You"re Enrolled: {$a} Awaits You.';
$string['enrolnow'] = 'Enrol now';
$string['enrolperiod'] = 'Enrolment duration';
$string['enrolperiod_help'] = 'Length of time that the enrolment is valid, starting with the moment the user is enrolled. If disabled, the enrolment duration will be unlimited.';
$string['enrolperioddesc'] = 'Default length of time that the enrolment is valid. If set to zero, the enrolment duration will be unlimited by default.';
$string['enrolstartdate'] = 'Start date';
$string['enrolstartdate_help'] = 'If enabled, users can be enrolled from this date onward only.';
$string['entercoupon'] = 'Please enter a coupon code';
$string['errorinvalidpublishablekeyformat'] = 'Publishable key format is incorrect for {$a} mode.';
$string['errorinvalidsecretkeyformat'] = 'Secret key format is incorrect for {$a} mode.';
$string['errormissingpublishablekey'] = 'Publishable key is missing for {$a} mode.';
$string['errormissingsecretkey'] = 'Secret key is missing for {$a} mode.';
$string['errorcouponidrequired'] = 'Coupon ID is required for coupon retrieval';
$string['errorcustomeridrequired'] = 'Customer ID is required for customer retrieval';
$string['errorpaymentintentidrequired'] = 'Payment Intent ID is required for payment intent retrieval';
$string['errorsessionidrequired'] = 'Session ID is required for checkout session retrieval';
$string['expiredaction'] = 'Enrolment expiration action';
$string['expiredactionhelp'] = 'Select action to carry out when user enrolment expires. Please note that some user data and settings are purged from course during course unenrolment.';
$string['fromhere'] = 'from here';
$string['infomodetext'] = 'To switch to Live Mode, change the mode above and save settings.';
$string['infomodetextlive'] = 'To switch to Test Mode, change the mode above and save settings.';
$string['intentdescription'] = 'Payment for {$a}';
$string['invalidcontextid'] = 'Not a valid context id! ';
$string['invalidcoupon'] = 'Invalid coupon!';
$string['invalidcoupontype'] = 'Invalid coupon type';
$string['invalidcourseid'] = 'Not a valid course id!';
$string['invalidenrolinstance'] = 'invalid enrol instance!';
$string['invalidenroltype'] = 'Invalid enrol instance type!';
$string['invalidinstance'] = 'Not a valid instance id!';
$string['invalidinstanceformat'] = 'Invalid instance ID format';
$string['invalidserverresponse'] = 'Invalid server response';
$string['invalidtransaction'] = 'Invalid transaction';
$string['liveapikeys'] = 'Live Mode API Keys';
$string['liveapikeysdesc'] = 'These keys are used when Live Mode is selected. Live keys start with "pk_live_" and "sk_live_". ⚠️ CAUTION: Live keys process real payments!';
$string['livemode'] = 'Live Mode';
$string['livepublishablekey'] = 'Live Publishable Key';
$string['livepublishablekeydesc'] = 'Your Stripe live publishable key (starts with pk_live_) ⚠️ LIVE KEY';
$string['livesecretkey'] = 'Live Secret Key';
$string['livesecretkeydesc'] = 'Your Stripe live secret key (starts with sk_live_) ⚠️ LIVE KEY';
$string['mailadmins'] = 'Notify admin';
$string['mailstudents'] = 'Notify students';
$string['mailteachers'] = 'Notify teachers';
$string['maxenrolled'] = 'Max enrolled users';
$string['maxenrolled_help'] = 'Specifies the maximum number of users that can stripepayment enrol. 0 means no limit.';
$string['maxenrolledhelp'] = 'Stripe enrolment messages';
$string['maxenrolledreached'] = 'Maximum number of users allowed to stripepayment-enrol was already reached.';
$string['messageprovider:stripepayment_enrolment'] = 'Stripe payment enrolment notifications';
$string['missingresourceid'] = 'Missing resource id';
$string['newenrolment'] = 'New Student Enrollment in {$a}';
$string['notvalidorderdetails'] = 'Not orderdetails valid user id';
$string['off'] = 'off';
$string['paymenterror'] = 'Payment session creation failed.';
$string['paymentmethodnotfound'] = 'Payment method not found. This enrolment instance was created with different API keys. Please contact the administrator for assistance.';
$string['pleasewait'] = 'Please wait...';
$string['pluginname'] = 'Stripe Payment';
$string['pluginnamedesc'] = 'The stripe module allows you to set up paid courses.  If the cost for any course is zero, then students are not asked to pay for entry.  There is a site-wide cost that you set here as a default for the whole site and then a course setting that you can set for each course individually. The course cost overrides the site cost.';
$string['privacy:metadata:enrol_stripepayment'] = 'Information about Stripe transactions for users enrolled via the Stripe Payment enrolment plugin.';
$string['privacy:metadata:enrol_stripepayment:courseid'] = 'The ID of the course being purchased.';
$string['privacy:metadata:enrol_stripepayment:couponid'] = 'The ID of any coupon used in the transaction.';
$string['privacy:metadata:enrol_stripepayment:instanceid'] = 'The ID of the enrolment instance.';
$string['privacy:metadata:enrol_stripepayment:itemname'] = 'The name of the course or item being purchased.';
$string['privacy:metadata:enrol_stripepayment:memo'] = 'Any memo or note associated with the transaction.';
$string['privacy:metadata:enrol_stripepayment:paymentstatus'] = 'The status of the payment.';
$string['privacy:metadata:enrol_stripepayment:paymenttype'] = 'The type of payment made.';
$string['privacy:metadata:enrol_stripepayment:pendingreason'] = 'The reason if the payment is pending.';
$string['privacy:metadata:enrol_stripepayment:price'] = 'The price of the transaction.';
$string['privacy:metadata:enrol_stripepayment:reasoncode'] = 'The reason code for the payment status.';
$string['privacy:metadata:enrol_stripepayment:customeremail'] = 'The email address of the user who receives the payment.';
$string['privacy:metadata:enrol_stripepayment:customerid'] = 'The ID of the user who receives the payment.';
$string['privacy:metadata:enrol_stripepayment:stripe_com'] = 'The Stripe Payment enrolment plugin transmits user data to stripe.com for processing payments.';
$string['privacy:metadata:enrol_stripepayment:stripe_com:email'] = 'User email is transmitted to stripe.com to process the payment.';
$string['privacy:metadata:enrol_stripepayment:timeupdated'] = 'The time the transaction was last updated.';
$string['privacy:metadata:enrol_stripepayment:txnid'] = 'The transaction ID from Stripe.';
$string['privacy:metadata:enrol_stripepayment:userid'] = 'The ID of the user making the payment.';
$string['productdescription'] = 'A moodle course named {$a}';
$string['status'] = 'Allow stripe enrolments';
$string['status_desc'] = 'Allow users to use stripe to enrol into a course by default.';
$string['statusconfigerror'] = '{$mode} MODE - Configuration Error: {$errors}';
$string['statuslive'] = 'LIVE MODE - Real payments will be processed';
$string['statustest'] = 'TEST MODE - Safe for testing';
$string['stripeapierror'] = 'Stripe API error: {$a}';
$string['stripeconfigurationincomplete'] = 'Stripe configuration incomplete';
$string['stripemode'] = 'Stripe Mode';
$string['stripemodedesc'] = 'Select whether to use Test or Live Stripe API keys. Test mode is safe for development and testing. Live mode processes real payments.';
$string['stripemodesettings'] = 'Stripe Mode Settings';
$string['stripemodesettingsdesc'] = 'Configure Live and Test mode API keys and switch between them easily.';
$string['stripepayment:config'] = 'Stripe Payment';
$string['stripepayment:enrol'] = 'Stripe Payment';
$string['stripepayment:manage'] = 'Manage stripepayment';
$string['stripepayment:unenrol'] = 'Unenrol stripepayment';
$string['stripepayment:unenrolself'] = 'Unenrolself stripepayment';
$string['testapikeys'] = 'Test Mode API Keys';
$string['testapikeysdesc'] = 'These keys are used when Test Mode is selected. Test keys start with "pk_test_" and "sk_test_".';
$string['testmode'] = 'Test Mode';
$string['testpublishablekey'] = 'Test Publishable Key';
$string['testpublishablekeydesc'] = 'Your Stripe test publishable key (starts with pk_test_)';
$string['testsecretkey'] = 'Test Secret Key';
$string['testsecretkeydesc'] = 'Your Stripe test secret key (starts with sk_test_)';
$string['totaldue'] = 'Total due';
$string['transactionfailed'] = 'Transaction failed';
$string['transactions'] = 'Stripe transactions';
$string['unenrolselfconfirm'] = 'Do you really want to unenrol yourself from course "{$a}"?';
$string['unknownpaymenterror'] = 'Unknown error occurred during payment';
$string['unknownstripeoperation'] = 'Unknown Stripe operation: {$a}';
$string['warningmodetext'] = 'Changing the mode will hide enrolment instances created in the other mode. Products and prices are linked to specific API keys. If you switch modes and users try to access enrolment instances from the previous mode, they will see a "Payment method not found, contact admin" error. This is normal behavior and instances will reappear when you switch back to the original mode.';
$string['webservicetokenstring'] = 'User token';
$string['welcometocoursetext'] = 'Hello,<br /> You have been successfully enrolled in {$a->course}. We look forward to your learning journey with us.<br /> Best regards, <br /> {$a->sitename} Team';
