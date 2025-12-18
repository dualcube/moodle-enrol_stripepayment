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
 * External library for stripepayment
 *
 * @package    enrol_stripepayment
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2025 DualCube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


 namespace enrol_stripepayment\external;
 use core\exception\moodle_exception;
 use core_external\external_api;
 use core_external\external_function_parameters;
 use core_external\external_value;
 use core_external\external_single_structure;
 use enrol_stripepayment\util;

 /**
  * External apply coupon for stripepayment
  *
  * @package    enrol_stripepayment
  * @author     DualCube <admin@dualcube.com>
  * @copyright  2025 DualCube Team(https://dualcube.com)
  * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
  */
class apply_coupon extends external_api {
    /**
     * Parameter for couponsettings function
     */
    public static function execute_parameters() {
        return new external_function_parameters(
            [
                'couponid' => new external_value(PARAM_RAW, 'The coupon id to operate on'),
                'instance' => new external_single_structure(
                    [
                        'id' => new external_value(PARAM_INT),
                        'cost' => new external_value(PARAM_INT),
                        'currency' => new external_value(PARAM_RAW),
                        'courseid' => new external_value(PARAM_INT),
                    ]
                ),
            ]
        );
    }

    /**
     * return type of couponsettings functioin
     */
    public static function execute_returns() {
        return new external_single_structure(
            [
                'displaydiscountsection' => new external_value(PARAM_BOOL, 'Whether to show the discount section'),
                'couponname' => new external_value(PARAM_RAW, 'The name of the coupon'),
                'discountamount' => new external_value(PARAM_RAW, 'The amount of the discount'),
                'discountedprice' => new external_value(PARAM_RAW, 'The cost of the course after applying the coupon'),
                'discountmessage' => new external_value(PARAM_RAW, 'The display text for the discount'),
            ]
        );
    }

    /**
     * function for couponsettings with validation
     * @param string $couponid
     * @param object $instance
     * @return array
     */
    public static function execute($couponid, $instance) {
        if (!$instance) {
            throw new moodle_exception('enrollmentinstancenotfound', 'enrol_stripepayment');
        }

        $coupon = self::validate_and_get_coupon($couponid, $instance['id']);
        $discount = self::calculate_discount($coupon, $instance);

        return [
            'displaydiscountsection' => ($discount['discountamount'] > 0),
            'couponname' => $coupon['name'] ?? $couponid,
            'discountamount' => '- ' . $discount['currency'] . ' ' . $discount['discountamount'],
            'discountedprice' => $discount['currency'] . ' ' . $discount['discountedprice'],
            'discountmessage' => $discount['discountmessage'],
        ];
    }

    /**
     * function for validating coupon
     * @param string $couponid
     * @param int $instanceid
     * @return array
     */
    private static function validate_and_get_coupon($couponid, $instanceid) {
        // Input validation.
        if (empty($couponid) || trim($couponid) === '') {
            throw new moodle_exception('couponcodeempty', 'enrol_stripepayment');
        }

        // Validate instanceid.
        if (!is_numeric($instanceid) || $instanceid <= 0) {
            throw new moodle_exception('invalidinstanceformat', 'enrol_stripepayment');
        }

        $coupon = util::stripe_api_request('coupon_retrieve', $couponid);

        // Enhanced coupon validation.
        if (!$coupon || (isset($coupon['valid']) && !$coupon['valid'])) {
            throw new moodle_exception('invalidcoupon', 'enrol_stripepayment');
        }

        // Check if coupon has expired.
        if (isset($coupon['redeem_by']) && $coupon['redeem_by'] < time()) {
            throw new moodle_exception('couponhasexpired', 'enrol_stripepayment');
        }

        // Check if coupon has usage limits.
        if (
            isset($coupon['max_redemptions']) && isset($coupon['times_redeemed'])
            && $coupon['times_redeemed'] >= $coupon['max_redemptions']
        ) {
            throw new moodle_exception('couponlimitexceeded', 'enrol_stripepayment');
        }
        return $coupon;
    }

    /**
     * function for calculating discount
     * @param array $coupon
     * @param object $instance
     * @return array
     */
    private static function calculate_discount($coupon, $instance) {
        $cost = (float)$instance['cost'] > 0 ? (float)$instance['cost'] : (float)util::get_core()->get_config('cost');
        $currency = $instance['currency'] ?: 'USD';
        if (isset($coupon['currency']) && strtoupper($coupon['currency']) !== strtoupper($currency)) {
            throw new moodle_exception('couponcurrencymismatch', 'enrol_stripepayment');
        }
        $discountamount = 0;
        if (isset($coupon['percent_off'])) {
            $discountamount = $cost * ($coupon['percent_off'] / 100);
            $discountmessage = $coupon['percent_off'] . '%' . get_string('off', 'enrol_stripepayment');
        } else if (isset($coupon['amount_off'])) {
            $discountamount = $coupon['amount_off'] / 100;
            $discountmessage = $currency . ' ' . $coupon['amount_off'] / 100 . ' ' . get_string('off', 'enrol_stripepayment');
        } else {
            throw new moodle_exception('invalidcoupontype', 'enrol_stripepayment');
        }
        $discountedprice = $cost - $discountamount;
        $discountedprice = max(0, $discountedprice);
        $discountedprice = format_float($discountedprice, 2, false);
        $discountamount = format_float($discountamount, 2, false);
        $minamount = util::minamount($currency);
        if ($discountedprice > 0 && $discountedprice < $minamount) {
            throw new moodle_exception('couponminimumerror', 'enrol_stripepayment', '', [
                'amount' => $currency . ' ' . number_format($discountedprice, 2),
                'minimum' => $currency . ' ' . number_format($minamount, 2),
            ]);
        }
        return [
            'currency' => $currency,
            'discountamount' => $discountamount,
            'discountedprice' => $discountedprice,
            'discountmessage' => $discountmessage,
        ];
    }
}
