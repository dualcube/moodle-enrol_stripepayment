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
 * Utility class for Stripe payment plugin
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2025 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_stripepayment;


use context_course;
use context_system;
use core\exception\moodle_exception;
use core\lang_string;
use core_user;
use moodle_url;
use stdClass;

/**
 * Utility class for Stripe payment plugin
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2025 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class util {
    /**
     * Get the enrol_stripepayment plugin instance.
     *
     * @return \enrol_stripepayment_plugin
     */
    public static function get_core() {
        return enrol_get_plugin('stripepayment');
    }

    /**
     * Create a link to a URL with optional text
     *
     * @param moodle_url|string $url The URL to link to
     * @param string|null $text The text to display (optional)
     * @return string The HTML link
     */
    public static function generate_link_html($url, ?string $text = null) {
        // If no text is provided, default to "from here" string.
        if ($text === null) {
            $text = get_string('fromhere', 'enrol_stripepayment');
        }

        return '<a href="' . $url . '" target="_blank">' . $text . '</a>';
    }

    /**
     * Get the message to display when web services are not set up.
     *
     * @param string $for The entity for which the message is being displayed
     * @return string The message
     */
    public static function get_webservice_setup_message($for) {

        // Predefined URLs.
        $webservicesoverview = new moodle_url('/admin/search.php', ['query' => 'enablewebservices']);
        $restweblink = new moodle_url('/admin/settings.php', ['section' => 'webserviceprotocols']);
        $createtoken = new moodle_url('/admin/webservice/tokens.php');

        return
            get_string('enablewebservicesfirst', 'enrol_stripepayment') . ' ' .
            get_string('enabledrestprotocol', 'enrol_stripepayment') . ' ' .
            self::generate_link_html($webservicesoverview) . ' . ' .

            get_string('createusertoken', 'enrol_stripepayment') . ' ' .
            self::generate_link_html($createtoken) . ' . ' .

            get_string('enabledrestprotocol', 'enrol_stripepaymentpro', $for) . ' ' .
            self::generate_link_html($restweblink);
    }

    /**
     * Lists all currencies available for plugin.
     * @return array
     */
    public static function get_currencies() {
        // See https://www.stripe.com/cgi-bin/webscr?cmd=p/sell/mc/mc_intro-outside,
        // 3-character ISO-4217: https://cms.stripe.com/us/cgi-bin/?cmd=
        // _render-content&content_ID=developer/e_howto_api_currency_codes.
        $codes = [
            'USD', 'AED', 'AFN', 'ALL', 'AMD', 'ANG', 'AOA', 'ARS', 'AUD', 'AWG', 'AZN', 'BAM', 'BBD', 'BDT', 'BGN', 'BIF', 'BMD',
            'BND', 'BOB', 'BRL', 'BSD', 'BWP', 'BZD', 'CAD', 'CDF', 'CHF', 'CLP', 'CNY', 'COP', 'CRC', 'CVE', 'CZK', 'DJF', 'DKK',
            'DOP', 'DZD', 'EGP', 'ETB', 'EUR', 'FJD', 'FKP', 'GBP', 'GEL', 'GIP', 'GMD', 'GNF', 'GTQ', 'GYD', 'HKD', 'HNL', 'HRK',
            'HTG', 'HUF', 'IDR', 'ILS', 'INR', 'ISK', 'JMD', 'JPY', 'KES', 'KGS', 'KHR', 'KMF', 'KRW', 'KYD', 'KZT', 'LAK', 'LBP',
            'LKR', 'LRD', 'LSL', 'MAD', 'MDL', 'MGA', 'MKD', 'MMK', 'MNT', 'MOP', 'MRO', 'MUR', 'MVR', 'MWK', 'MXN', 'MYR', 'MZN',
            'NAD', 'NGN', 'NIO', 'NOK', 'NPR', 'NZD', 'PAB', 'PEN', 'PGK', 'PHP', 'PKR', 'PLN', 'PYG', 'QAR', 'RON', 'RSD', 'RUB',
            'RWF', 'SAR', 'SBD', 'SCR', 'SEK', 'SGD', 'SHP', 'SLL', 'SOS', 'SRD', 'STD', 'SZL', 'THB', 'TJS', 'TOP', 'TRY', 'TTD',
            'TWD', 'TZS', 'UAH', 'UGX', 'UYU', 'UZS', 'VND', 'VUV', 'WST', 'XAF', 'XCD', 'XOF', 'XPF', 'YER', 'ZAR',
        ];
        $currencies = [];
        foreach ($codes as $c) {
            $currencies[$c] = new lang_string($c, 'core_currencies');
        }
        return $currencies;
    }

    /**
     * Return an array of valid options for the status.
     *
     * @return array
     */
    public static function get_status_options() {
        return [
            ENROL_INSTANCE_ENABLED => get_string('yes'),
            ENROL_INSTANCE_DISABLED => get_string('no'),
        ];
    }

    /**
     * Creates can stripepayament enrol.
     *
     * @param stdClass $instance enrol instance
     * @return bool html text, usually a form in a text box
     */
    public static function can_more_user_enrol(stdClass $instance) {
        global $DB;
        if ($instance->customint3 > 0) {
            // Max enrol limit specified.
            $count = $DB->count_records('user_enrolments', ['enrolid' => $instance->id]);
            if ($count >= $instance->customint3) {
                // Bad luck, no more stripepayment enrolments here.
                return false;
            }
        }
        return true;
    }

    /**
     * Get the current Stripe mode (test or live) - NEW METHOD.
     *
     * @return string 'test' or 'live'
     */
    public static function get_stripe_mode() {
        return get_config('enrol_stripepayment', 'stripemode') ?: 'test'; // Default to test mode for safety.
    }

    /**
     * Get the appropriate API keys based on current mode - NEW METHOD.
     *
     * @return array Array with 'publishable', 'secret', and 'mode' keys
     */
    public static function get_current_api_keys() {
        $mode = self::get_stripe_mode();

        if ($mode === 'live') {
            $publishable = get_config('enrol_stripepayment', 'livepublishablekey');
            $secret = get_config('enrol_stripepayment', 'livesecretkey');
        } else {
            $publishable = get_config('enrol_stripepayment', 'testpublishablekey');
            $secret = get_config('enrol_stripepayment', 'testsecretkey');
        }

        return [
            'publishable' => $publishable,
            'secret' => $secret,
            'mode' => $mode,
        ];
    }

    /**
     * Get the current secret key based on mode - NEW METHOD.
     *
     * @return string The appropriate secret key
     */
    public static function get_current_secret_key() {
        $keys = self::get_current_api_keys();
        return $keys['secret'];
    }

    /**
     * Validate API keys for the current mode.
     *
     * @return array Array with 'valid' boolean and 'errors' array
     */
    public static function validate_current_api_keys() {
        $keys = self::get_current_api_keys();
        $errors = [];

        if (empty($keys['secret'])) {
            $errors[] = get_string('errormissingsecretkey', 'enrol_stripepayment', $keys['mode']);
        }

        if (empty($keys['publishable'])) {
            $errors[] = get_string('errormissingpublishablekey', 'enrol_stripepayment', $keys['mode']);
        }

        // Validate key format.
        if (strpos($keys['secret'], $keys['mode'] === 'live' ? 'sk_live_' : 'sk_test_') !== 0) {
            $errors[] = get_string('errorinvalidsecretkeyformat', 'enrol_stripepayment', $keys['mode']);
        }

        if (strpos($keys['publishable'], $keys['mode'] === 'live' ? 'pk_live_' : 'pk_test_') !== 0) {
            $errors[] = get_string('errorinvalidpublishablekeyformat', 'enrol_stripepayment', $keys['mode']);
        }

        return [
            'valid' => empty($errors),
        ];
    }

    /**
     * Get mode status display text - NEW METHOD.
     *
     * @return string HTML formatted status text
     */
    public static function get_mode_status_display() {
        $mode = self::get_stripe_mode();
        $validation = self::validate_current_api_keys();

        // Load language strings: moodle_stripepaymentpro.php (lang/en/)
        // 'status_live', 'status_test', 'status_config_error'.
        if (!$validation['valid']) {
            $messagestr = get_string(
                'statusconfigerror',
                'enrol_stripepayment',
                ['mode' => strtoupper($mode), 'errors' => implode(', ', $validation['errors'] ?? [])]
            );
            $color = '#d32f2f';
            $icon = '⚠️';
        } else if ($mode === 'live') {
            $messagestr = get_string('statuslive', 'enrol_stripepayment');
            $color = '#d32f2f';
            $icon = '🔴';
        } else {
            $messagestr = get_string('statustest', 'enrol_stripepayment');
            $color = '#388e3c';
            $icon = '🟢';
        }

        return "<span style=\"color: {$color}; font-weight: bold;\">{$icon} {$messagestr}</span>";
    }

    /**
     * Make a cURL request to Stripe API with operation-based logic.
     *
     * @param string      $operation   Operation key that maps to a Stripe route (e.g., 'coupon_retrieve', 'subscription_create')
     * @param string|null $resourceid  Optional Stripe resource ID (used when endpoint requires ID)
     * @param array|null  $data        POST or query parameters sent to Stripe (depending on endpoint method)
     * @return array Stripe API response decoded as associative array.
     * @throws moodle_exception If a cURL error occurs, Stripe returns a non-2xx response, or JSON decoding fails.
     */
    public static function stripe_api_request($operation, $resourceid = null, $data = null) {
        $secretkey = self::get_current_secret_key();
        // Validate Stripe configuration.
        if (empty($secretkey)) {
            throw new moodle_exception('stripeconfigurationincomplete', 'enrol_stripepayment');
        }
        $endpointinfo = static::get_stripe_endpoint($operation, $resourceid);
        $method = $endpointinfo['method'];
        $url = 'https://api.stripe.com/v1/' . $endpointinfo['endpoint'];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_USERPWD, $secretkey . ':');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/x-www-form-urlencoded',
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        } else if ($method === 'GET') {
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($data));
            }
        } else if ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlerror = curl_error($ch);

        if ($curlerror) {
            throw new moodle_exception('crlerror', 'enrol_stripepayment', '', $curlerror);
        }

        $decoded = json_decode($response, true);

        // Any non-200 HTTP response → throw exception.
        if ($httpcode !== 200) {
            $errmsg = isset($decoded['error']['message'])
                ? $decoded['error']['message']
                : 'Unknown Stripe API error';

            throw new moodle_exception('stripeapierror', 'enrol_stripepayment', '', $errmsg);
        }

        // Success → return decoded response.
        return $decoded;
    }

    /**
     * Returns a list of Stripe API routes used by this plugin.
     *
     * @return array<string, array{
     *     method: string,
     *     path: string,
     *     needs_id: bool,
     *     message?: string
     * }>
     */
    public static function routes() {
        return [
            'coupon_retrieve' => [
                'method'   => 'GET',
                'path'     => 'coupons/',
                'needs_id' => true,
                'message'  => get_string('errorcouponidrequired', 'enrol_stripepayment'),
            ],
            'customer_retrieve' => [
                'method'   => 'GET',
                'path'     => 'customers/',
                'needs_id' => true,
                'message'  => get_string('errorcustomeridrequired', 'enrol_stripepayment'),
            ],
            'customer_list' => [
                'method'   => 'GET',
                'path'     => 'customers',
                'needs_id' => false,
            ],
            'customer_create' => [
                'method'   => 'POST',
                'path'     => 'customers',
                'needs_id' => false,
            ],
            'checkout_session_create' => [
                'method'   => 'POST',
                'path'     => 'checkout/sessions',
                'needs_id' => false,
            ],
            'checkout_session_retrieve' => [
                'method'   => 'GET',
                'path'     => 'checkout/sessions/',
                'needs_id' => true,
                'message'  => get_string('errorsessionidrequired', 'enrol_stripepayment'),
            ],
            'payment_intent_retrieve' => [
                'method'   => 'GET',
                'path'     => 'payment_intents/',
                'needs_id' => true,
                'message'  => get_string('errorpaymentintentidrequired', 'enrol_stripepayment'),
            ],
        ];
    }

    /**
     * Make a cURL request to Stripe API with operation-based logic
     *
     * @param string $operation API operation type
     * @param string|null $resourceid Resource ID for specific operations
     * @return array Response data
     * @throws moodle_exception
     */
    public static function get_stripe_endpoint($operation, $resourceid = null) {
        // HTTP method must be the first element.
        $routes = static::routes();

        if (!isset($routes[$operation])) {
            throw new moodle_exception('unknownstripeoperation', 'enrol_stripepayment', '', $operation);
        }

        $route = $routes[$operation];

        if ($route['needs_id'] && !$resourceid) {
            throw new moodle_exception('missingresourceid', 'enrol_stripepayment', '', $route['message']);
        }

        return [
            'method'   => $route['method'],
            'endpoint' => $route['needs_id'] ? $route['path'] . $resourceid : $route['path'],
        ];
    }

    /**
     * Get stripe amount
     *
     * @param float $cost
     * @param string $currency
     * @param bool $reverse
     * @return float
     */
    public static function get_stripe_amount($cost, $currency, $reverse = false) {
        $nodecimalcurrencies = ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg',
            'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf',
        ];
        if (!$currency) {
            $currency = 'USD';
        }
        if (in_array(strtolower($currency), $nodecimalcurrencies)) {
            return abs($cost);
        } else {
            if ($reverse) {
                return abs((float) $cost / 100);
            } else {
                return abs((float) $cost * 100);
            }
        }
    }

    /**
     * Get minimum amount for currency
     *
     * @param string $currency
     * @return float
     */
    public static function minamount($currency) {
        $minamount = [
            'USD' => 0.5, 'AED' => 2.0, 'AUD' => 0.5, 'BGN' => 1.0, 'BRL' => 0.5,
            'CAD' => 0.5, 'CHF' => 0.5, 'CZK' => 15.0, 'DKK' => 2.5, 'EUR' => 0.5,
            'GBP' => 0.3, 'HKD' => 4.0, 'HUF' => 175.0, 'INR' => 0.5, 'JPY' => 50,
            'MXN' => 10, 'MYR' => 2, 'NOK' => 3.0, 'NZD' => 0.5, 'PLN' => 2.0,
            'RON' => 2.0, 'SEK' => 3.0, 'SGD' => 0.5, 'THB' => 10,
        ];
        $minamount = $minamount[$currency] ?? 0.5;
        return $minamount;
    }

    /**
     * send error message to admin using Message API
     * @param string  $subject
     * @param array $data
     */
    public static function message_stripepayment_error_to_admin($subject, $data) {
        global $PAGE;
        $PAGE->set_context(context_system::instance());

        $admin = get_admin();
        $site = get_site();
        $messagebody = $site->fullname . ": " . get_string('transactionfailed', 'enrol_stripepayment') . "\n\n";
        foreach ($data as $key => $value) {
            $messagebody .= s($key) . " => " . s($value) . "\n";
        }
        $messagesubject = get_string('stripeapierror', 'enrol_stripepayment', $subject);
        $fullmessage = $messagebody;
        $fullmessagehtml = '<p>' . nl2br(s($messagebody)) . '</p>';
        self::send_message(
            $site,
            core_user::get_noreply_user(),
            $admin,
            $messagesubject,
            'Site administration',
            'enrol_stripepayment',
            $fullmessage,
            $fullmessagehtml
        );
    }

    /**
     * Send message to user
     *
     * @param stdClass $course Course object
     * @param stdClass $userfrom User sending the message
     * @param mixed $userto User(s) receiving the message
     * @param string $subject Message subject
     * @param string $contexturlname Order details
     * @param string $shortname Course shortname
     * @param string $fullmessage Full message text
     * @param string $fullmessagehtml Full message HTML
     * @return void
     */
    public static function send_message(
        $course,
        $userfrom,
        $userto,
        $subject,
        $contexturlname,
        $shortname,
        $fullmessage,
        $fullmessagehtml
    ) {
        $recipients = is_array($userto) ? $userto : [$userto];
        foreach ($recipients as $recipient) {
            $message = new \core\message\message();
            $message->courseid = $course->id;
            $message->component = $shortname;
            $message->name = $shortname;
            $message->userfrom = $userfrom;
            $message->userto = $recipient;
            $message->subject = $subject;
            $message->fullmessage = $fullmessage;
            $message->fullmessageformat = FORMAT_PLAIN;
            $message->fullmessagehtml = $fullmessagehtml;
            $message->smallmessage = get_string('newenrolment', 'enrol_stripepayment', $shortname);
            $message->notification = 1;
            $message->contexturl = new \core\url('/course/view.php', ['id' => $course->id]);
            $message->contexturlname = $contexturlname;

            if (!message_send($message)) {
                debugging("Failed to send stripepayment enrolment notification to user: {$recipient->id}", DEBUG_DEVELOPER);
            }
        }
    }

    /**
     * Send enrollment notifications to students, teachers, and admins
     * @param stdClass $course The course object
     * @param stdClass $context The course context
     * @param stdClass $user The enrolled user
     * @param object $plugin The enrollment plugin instance
     */
    public static function send_enrollment_notifications($course, $context, $user, $plugin) {
        // Get teacher.
        if (
            $users = get_users_by_capability(
                $context,
                'moodle/course:update',
                'u.*',
                'u.id ASC',
                '',
                '',
                '',
                '',
                false,
                true
            )
        ) {
            $users = sort_by_roleassignment_authority($users, $context);
            $teacher = array_shift($users);
        } else {
            $teacher = false;
        }

        // Notification settings.
        $mailstudents = $plugin->get_config('mailstudents');
        $mailteachers = $plugin->get_config('mailteachers');
        $mailadmins   = $plugin->get_config('mailadmins');

        // Common data.
        $shortname = format_string($course->shortname, true, ['context' => $context]);
        $sitename = new moodle_url('/');

        $adminsubject = get_string(
            "enrolmentnew",
            'enrol_stripepayment',
            ['username' => fullname($user), 'course' => $course->fullname],
        );
        $adminmessage = get_string(
            'adminmessage',
            'enrol_stripepayment',
            ['username' => fullname($user), 'course' => $course->fullname, 'sitename' => $sitename],
        );

        // Map notification rules.
        $notifications = [
            'students' => [
                'enabled' => !empty($mailstudents),
                'recipient' => $user,
                'from' => empty($teacher) ? core_user::get_noreply_user() : $teacher,
                'subject' => get_string("enrolmentuser", 'enrol_stripepayment', $shortname),
                'message' => get_string(
                    'welcometocoursetext',
                    'enrol_stripepayment',
                    ['course' => $course->fullname, 'sitename' => $sitename],
                ),
            ],
            'teachers' => [
                'enabled' => !empty($mailteachers) && !empty($teacher),
                'recipient' => $teacher,
                'from' => $user,
                'subject' => $adminsubject,
                'message' => $adminmessage,
            ],
            'admins' => [
                'enabled' => !empty($mailadmins),
                'recipient' => get_admins(),
                'from' => $user,
                'subject' => $adminsubject,
                'message' => $adminmessage,
            ],
        ];

        // Loop and send messages.
        foreach ($notifications as $notify) {
            if (!$notify['enabled']) {
                continue;
            }

            $fullmessage = $notify['message'];
            $fullmessagehtml = '<p>' . $fullmessage . '</p>';

            self::send_message(
                $course,
                $notify['from'],
                $notify['recipient'],
                $notify['subject'],
                $course->fullname,
                $shortname,
                $fullmessage,
                $fullmessagehtml
            );
        }
    }
}
