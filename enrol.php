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
 * @author     DualCube <admin@dualcube.com>
 * @copyright  2019 Dualcube Team(https://dualcube.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
// Disable moodle specific debug messages and any errors in output,
// comment out when debugging or better look into error log!
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/enrollib.php');
require_once('Stripe/init.php');
// get currency symbal
$currency_symbol = enrol_get_plugin('stripepayment')->show_currency_symbol(strtolower($instance->currency));
$plugin = enrol_get_plugin('stripepayment');
$enable_coupon_section = !empty($plugin->get_config('enable_coupon_section')) ? true : false;
$dataa = optional_param('data', null, PARAM_RAW);
$enrolbtncolor = $plugin->get_config('enrolbtncolor');
?>
<script src="https://js.stripe.com/v3/"></script>
<div class="strip-wrap">
    <div class="stripe-right">
        <p class='stripe-dclr'><?php print_string("paymentrequired") ?></p>
        <div class="stripe-img">
            <img src="<?php echo $CFG->wwwroot; ?>/enrol/stripepayment/pix/stripe-payment.png">
        </div>
        <form id="form_data_new" action="" method="post">
            <input id="form_data_new_data" type="hidden" name="data" value="" />
            <input id="form_data_new_coupon_id" type="hidden" name="coupon_id" value="" />
        </form>
        <div class="paydetail">
            <div class="stripe-line-row">
                <div class="stripe-line-left"><?php echo get_string("cost") . ":<span> {$currency_symbol}{$cost}</span>"; ?></div>
            </div>
            <?php if (isset($dataa)) if ($cost > $dataa) {
                (float)$discount = $cost - $dataa;
                $couponid = required_param('coupon_id', PARAM_RAW);
            ?>
                <div class='stripe-line-left'><?php echo get_string("couponapplied", "enrol_stripepayment") . ":<span> - {$currency_symbol}{$discount} [<i>{$couponid}</i>] </span>"; ?></div>
            <?php } ?>
            <div id="reload">
                <?php
                $couponid = 0;
                if (isset($dataa)) {
                    $cost = $dataa;
                    $couponid = required_param('coupon_id', PARAM_RAW);
                }
                $amount = enrol_get_plugin('stripepayment')->get_stripe_amount($cost, $instance->currency, false);
                echo $enable_coupon_section ? "<div class='stripe-line-left'> " . get_string("final_cost", "enrol_stripepayment") . ": <span> $currency_symbol$cost <span></div>" : '';
                if ($enable_coupon_section) { ?>
                    <div class="couponcode-wrap">
                        <span class="couponcode-text"> <?php echo get_string("couponcodedescription", "enrol_stripepayment"); ?></span>
                        <p class="stripe-cupon-input">
                            <input type=text id="coupon" />
                            <button id="apply" class="stripe-cupon-apply"><?php echo get_string("applycode", "enrol_stripepayment"); ?></button>
                        </p>
                        <div id="new_coupon"></div>
                    </div>
                <?php }
                $costvalue = str_replace(".", "", $cost);
                if ($costvalue == 000) {  ?>
                    <div id="amountequalzero" class="stripe-buy-btn">
                        <button id="card-button-zero">
                            <?php echo get_string("enrol_now", "enrol_stripepayment"); ?>
                        </button>
                    </div>
                <?php } else { ?>
                    <div id="paymentResponse" class="stripe-buy-btn">
                        <div id="buynow">
                            <button class="stripe-button" id="payButton"><?php echo get_string("buy_now", "enrol_stripepayment"); ?></button>
                        </div>
                    <?php } ?>
                    </div>
                    <?php $PAGE->requires->js_call_amd('enrol_stripepayment/stripe_payment', 'stripe_payment', array($publishablekey, $plugin->get_config('secretkey'), $course->id, $amount, $instance->currency, $coursefullname, $couponid, $USER->id, $instance->id, get_string("please_wait", "enrol_stripepayment"), get_string("buy_now", "enrol_stripepayment"), $plugin->get_config('cost'), $cost, $USER->email, get_string("invalidcouponcode", "enrol_stripepayment"))); ?>
            </div>
        </div>
    </div>
    <style>
        .generalbox {
            margin: auto;
        }

        .strip-wrap {
            margin: auto;
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 2.5rem;
            border-top: 0.06rem solid #eee;
            border-bottom: 0.06rem solid #eee;
            padding: 3rem 0;
        }

        .stripe-right {
            flex: auto;
        }

        .stripe-img {
            height: 68px;
            margin: 0rem auto 1.5rem;
            border-bottom: 0.06rem solid #eee;
            width: 48%;
            padding: 0.25rem 0;
        }

        .couponcode-wrap {
            padding: 1rem 0 1.5rem 0;
            border-top: 0.06rem solid #eee;
            margin: 1.25rem auto 0rem;
            width: 48%;
        }

        .stripe-line-left {
            display: flex;
            justify-content: space-between;
            font-weight: 600;
            width: 48%;
            margin: 0.5rem auto;
        }

        .stripe-line-left span {
            font-weight: 700;
        }

        .stripe-buy-btn {
            text-align: center;
            padding: 1.5rem 0 0;
            margin: 0rem auto 0;
            border-top: 0.06rem solid #eee;
            width: 52%;
        }

        .stripe-cupon-input input#coupon {
            border-radius: 1rem;
        }

        .stripe-cupon-apply#apply {
            border-radius: 1rem;
            background: #0a2540;
        }

        .stripe-dclr {
            margin: 0 auto;
            border-bottom: 0.06rem solid #eee;
            padding-bottom: 1rem;
            text-align: center;
            font-size: 1rem;
            width: 48%;
            font-weight: 500;
        }

        .stripe-cupon-input {
            margin: 0;
        }

        .couponcode-wrap .couponcode-text {
            font-size: 14px;
            margin: 0 0 1rem;
            display: block;
        }

        #new_coupon b {
            font-weight: 400;
        }

        .couponcode-wrap input#coupon {
            margin: 0 4px 4px 0;
        }

        div#new_coupon p {
            margin: 0 0 -21px;
            padding: 5px;
        }

        div#transaction-status,
        div#transaction-status-zero {
            margin: 15px;
            background: antiquewhite;
            color: chocolate;
            display: none;
        }

        .CardField-input-wrapper {
            overflow: inherit;
        }

        .coursebox .content .summary {
            width: 100%
        }

        button#apply {
            color: #fff;
            background-color: <?php echo $enrolbtncolor; ?>;
            border: 0;
            padding: 5px 16px;
            border-radius: 0.5rem;
            font-size: 13px;
        }

        button#payButton,
        button#card-button-zero {
            color: #fff;
            background-color: <?php echo $enrolbtncolor; ?>;
            border: 0;
            padding: 5px 32px;
            border-radius: 0.25rem;
            font-size: 13px;
            box-shadow: 0 0.125rem 0.25rem #645cff2e;
            width: 100%;
        }

        input#coupon {
            border: 1px dashed #a2a2a2;
            padding: 3px 14px;
        }

        p {
            text-align: left;
        }

        .stripe-img img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            object-position: center;
        }

        body#page-enrol-index #region-main .generalbox.info {
            width: 100%;
            box-shadow: none;
        }

        body#page-enrol-index #region-main .generalbox .card a img {
            max-width: 458px;
            height: 300px;
            padding: 0;
            box-shadow: 0 0 10px #b0afaf;
        }

        #page-enrol-index .access-btn {
            display: none;
        }

        .payment-left p {
            text-align: center;
        }

        #page-enrol-index #region-main-box .card-title {
            position: relative;
            line-height: 59px;
            font-size: 2rem;
            text-transform: capitalize;
        }

        .StripeElement {
            padding: 15px;
            border: 1px solid #e9ebec;
            background: #f9f9f9;
            box-shadow: 0 10px 6px -4px #d4d2d2;
        }

        .StripeElement input[placeholder],
        [placeholder],
        *[placeholder] {
            color: red !important;
        }

        @media (min-width: 200px) and (max-width: 700px) {

            .stripe-img,
            .stripe-dclr,
            .stripe-line-left,
            .couponcode-wrap,
            .stripe-buy-btn {
                width: 100%;
            }

            .stripe-left {
                width: 100%;
            }

            #region-main {
                padding: 0;
            }

            .generalbox {
                width: 300px;
            }

            body#page-enrol-index #region-main .generalbox:last-of-type {
                width: 320px;
                margin: 0 auto;
                float: none;
            }

            #page-enrol-index p {
                text-align: center;
            }

            #apply {
                margin-top: 10px;
            }

            #coupon {
                margin-top: 10px;
            }

            #page-enrol-index #region-main-box .card-title {
                text-align: center;
            }

            #page-enrol-index #region-main-box .card-title:before,
            #page-enrol-index #region-main-box .card-title:after {
                display: none;
            }

            .couponcode-wrap {
                display: block;
            }
        }

        #region-main h2 {
            display: none;
        }

        .enrolmenticons {
            display: none;
        }

        #new_coupon {
            margin-bottom: 10px;
        }

        button#final-payment-button {
            line-height: 1;
        }
    </style>