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


use core\lang_string;
use moodle_url;
use stdClass;

/**
 * Utility class for Stripe payment plugin
 *
 * Stripe API access lives in {@see stripe_client} and enrolment messaging lives in
 * {@see enrolment_notifier} - keeping those concerns out of this class is what keeps it small.
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

            get_string('enabledrestprotocol', 'enrol_stripepayment', $for) . ' ' .
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
     * Convert a decimal cost into the integer amount Stripe expects for the given currency.
     *
     * Stripe amounts are expressed in the smallest currency unit (e.g. cents), except for
     * zero-decimal currencies which Stripe already expects as a whole number.
     *
     * @param float $cost
     * @param string $currency
     * @return float
     */
    public static function to_stripe_amount($cost, $currency) {
        if (self::is_zero_decimal_currency($currency)) {
            return abs($cost);
        }
        return abs((float) $cost * 100);
    }

    /**
     * Convert a Stripe amount (in its smallest currency unit) back into a decimal cost.
     *
     * @param float $amount
     * @param string $currency
     * @return float
     */
    public static function from_stripe_amount($amount, $currency) {
        if (self::is_zero_decimal_currency($currency)) {
            return abs($amount);
        }
        return abs((float) $amount / 100);
    }

    /**
     * Whether Stripe treats the given currency as zero-decimal (it has no fractional unit).
     *
     * @param string $currency
     * @return bool
     */
    private static function is_zero_decimal_currency($currency) {
        $nodecimalcurrencies = ['bif', 'clp', 'djf', 'gnf', 'jpy', 'kmf', 'krw', 'mga', 'pyg',
            'rwf', 'ugx', 'vnd', 'vuv', 'xaf', 'xof', 'xpf',
        ];
        return in_array(strtolower($currency ?: 'USD'), $nodecimalcurrencies);
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
}
