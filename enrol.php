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
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/enrollib.php');
require_once('Stripe/init.php');
// get currency symbal
$PAGE->requires->css('/enrol/stripepayment/style.css', true);
$currency_symbol = enrol_get_plugin('stripepayment')->show_currency_symbol(strtolower($instance->currency));
$plugin = enrol_get_plugin('stripepayment');
$enable_coupon_section = !empty($plugin->get_config('enable_coupon_section')) ? true : false;
$dataa = optional_param('data', null, PARAM_RAW);
?>
<script src="https://js.stripe.com/v3/"></script>
<div class="strip-wrap">
    <div class="stripe-left" align="center">
        <div class="stripe-img">
            <img src="<?php echo $CFG->wwwroot; ?>/enrol/stripepayment/pix/stripe-payment.png">
        </div>
    </div>
    <div class="stripe-right">
        <p class='stripe-dclr'><?php print_string("paymentrequired") ?></p>
        <?php if ($enable_coupon_section) { ?>
            <div class="couponcode-wrap">
                <span class="couponcode-text"> <?php echo get_string("couponcode", "enrol_stripepayment"); ?>: </span>
                <p class="stripe-cupon-input">
                    <input type=text id="coupon" />
                    <button id="apply" class="stripe-cupon-apply"><?php echo get_string("applycode", "enrol_stripepayment"); ?></button>
                </p>
            </div>
            <div id="new_coupon"></div>
        <?php } ?>
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
            ?>
                <div class='stripe-line-left'><?php echo get_string("couponapplied", "enrol_stripepayment") . ":<span> - {$currency_symbol}{$discount} off </span>"; ?></div>
            <?php } ?>
            <div id="reload">
                <div id="new_coupon"></div>
                <?php
                $couponid = 0;
                if (isset($dataa)) {
                    $cost = $dataa;
                    $couponid = required_param('coupon_id', PARAM_RAW);
                }
                $amount = enrol_get_plugin('stripepayment')->get_stripe_amount($cost, $instance->currency, false);
                echo $enable_coupon_section ? "<div class='stripe-line-left'> " . get_string("final_cost", "enrol_stripepayment") . ": <span> $currency_symbol$cost <span></div>" : '';
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