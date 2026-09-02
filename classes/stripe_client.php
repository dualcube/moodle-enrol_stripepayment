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
 * Stripe API client for the Stripe payment enrolment plugin.
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2026 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace enrol_stripepayment;

use core\exception\moodle_exception;

/**
 * Stripe API client for the Stripe payment enrolment plugin.
 *
 * Holds the API-key/mode handling and the raw cURL request logic that {@see util} used
 * to carry directly - split out on its own so each class stays focused and small.
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2026 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class stripe_client {
    /**
     * Get the current Stripe mode (test or live).
     *
     * @return string 'test' or 'live'
     */
    public static function get_stripe_mode() {
        return get_config('enrol_stripepayment', 'stripemode') ?: 'test'; // Default to test mode for safety.
    }

    /**
     * Get the appropriate API keys based on current mode.
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
     * Get the current secret key based on mode.
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
     * @return array Array with 'valid' boolean
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
        $keysuffix = $keys['mode'] === 'live' ? 'live_' : 'test_';

        if (strpos($keys['secret'], 'sk_' . $keysuffix) !== 0) {
            $errors[] = get_string('errorinvalidsecretkeyformat', 'enrol_stripepayment', $keys['mode']);
        }

        if (strpos($keys['publishable'], 'pk_' . $keysuffix) !== 0) {
            $errors[] = get_string('errorinvalidpublishablekeyformat', 'enrol_stripepayment', $keys['mode']);
        }

        return [
            'valid' => empty($errors),
        ];
    }

    /**
     * Get mode status display text.
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
        $ch = self::build_curl_handle($endpointinfo, $secretkey, $data);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlerror = curl_error($ch);

        if ($curlerror) {
            throw new moodle_exception('crlerror', 'enrol_stripepayment', '', $curlerror);
        }

        $decoded = json_decode($response, true);

        // Any non-200 HTTP response → throw exception.
        if ($httpcode !== 200) {
            $errmsg = $decoded['error']['message'] ?? 'Unknown Stripe API error';

            throw new moodle_exception('stripeapierror', 'enrol_stripepayment', '', $errmsg);
        }

        // Success → return decoded response.
        return $decoded;
    }

    /**
     * Build the cURL handle for a Stripe API call.
     *
     * @param array $endpointinfo Endpoint 'method' and 'endpoint', as returned by get_stripe_endpoint()
     * @param string $secretkey Stripe secret key used for HTTP basic auth
     * @param array|null $data POST or query parameters sent to Stripe
     * @return \CurlHandle cURL handle ready to be executed
     */
    private static function build_curl_handle($endpointinfo, $secretkey, $data) {
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

        return $ch;
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
     * Resolve the HTTP method and endpoint path for a Stripe operation.
     *
     * @param string $operation API operation type
     * @param string|null $resourceid Resource ID for specific operations
     * @return array Array with 'method' and 'endpoint' keys
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
}
