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
require_once($CFG->libdir.'/enrollib.php');
require_once('Stripe/init.php');
require_once('Stripe/version3api.php');
// get currency symbal
$currency_symbol = enrol_get_plugin('stripepayment')->show_currency_symbol( strtolower($instance->currency) );
$plugin = enrol_get_plugin('stripepayment');
$enable_coupon_section = !empty($plugin->get_config('enable_coupon_section')) ? true : false;
?>
<div align="center">
    <div class="stripe-img">
        <img src="<?php echo $CFG->wwwroot; ?>/enrol/stripepayment/pix/stripe.png"></div>
        <p><?php print_string("paymentrequired") ?></p>
        <p><b><?php echo get_string("cost").": {$currency_symbol}{$cost}"; ?></b></p>
        <?php if ($enable_coupon_section) { ?>
            <div class="couponcode-wrap">
                <span class="couponcode-text"> <?php echo get_string("couponcode", "enrol_stripepayment"); ?>: </span>
                <input type=text id="coupon"/>
                <button id="apply"><?php echo get_string("applycode", "enrol_stripepayment"); ?></button>
            </div>
        <?php } ?>
        <form id="form_data_new" action="" method="post">
            <input id="form_data_new_data" type="hidden" name="data" value="" />
            <input id="form_data_new_coupon_id" type="hidden" name="coupon_id" value="" />
        </form>
        <div id="reload">
            <div id="new_coupon"></div>
<?php

$couponid = 0;
$dataa = optional_param('data', null, PARAM_RAW);
if ( isset($dataa) ) {
    $cost = $dataa;
    $couponid = required_param('coupon_id', PARAM_RAW);
}
$amount = enrol_get_plugin('stripepayment')->get_stripe_amount($cost, $instance->currency, false);
echo $enable_coupon_section ? "<p><b> ". get_string("final_cost", "enrol_stripepayment") ." : $currency_symbol$cost </b></p>" : '';

$costvalue = str_replace(".", "", $cost);
if ($costvalue == 000) {  ?>
<div id="amountequalzero">
  <button id="card-button-zero">
    <?php echo get_string("enrol_now", "enrol_stripepayment"); ?>
  </button>
</div>
<br>
<?php } else { ?>
<div id="paymentResponse"></div>
<div id="buynow">
    <button class="stripe-button" id="payButton"><?php echo get_string("buy_now", "enrol_stripepayment"); ?></button>
</div>
<?php } ?>

 <?php $PAGE->requires->js_call_amd('enrol_stripepayment/stripe_payment', 'stripe_payment', array($publishablekey, $plugin->get_config('secretkey'), $course->id, $amount, $instance->currency, $coursefullname, $couponid, $USER->id, $instance->id, get_string("please_wait", "enrol_stripepayment"), get_string("buy_now", "enrol_stripepayment"), $plugin->get_config('cost'), $cost, $USER->email, get_string("invalidcouponcode", "enrol_stripepayment"))); ?>

    </div>
</div>

<style>
.couponcode-wrap {
    display: flex;
    justify-content: center;
    align-items: center;
}
.couponcode-wrap .couponcode-text{
    font-size:14px;
}
.couponcode-wrap input#coupon{
    margin: 0 6px;
}
div#transaction-status, div#transaction-status-zero {
    margin: 15px;
    background: antiquewhite;
    color: chocolate;
    display: none;
}
.CardField-input-wrapper{ overflow: inherit;} 
.coursebox .content .summary{width:100%}
button#apply, button#payButton, button#card-button-zero{
   color: #fff;
   background-color: #1177d1;
   border: 1px solid #1177d1;
   padding: 5px 10px;
   font-size: 13px;
}
input#coupon {
   border: 1px dashed #a2a2a2;
   padding: 3px 5px;
}
p{ text-align:left;}
.stripe-img img{width:130px;}
body#page-enrol-index #region-main .generalbox.info{
 width: 100%;
 box-shadow: none;
}
body#page-enrol-index #region-main .generalbox .card a img{
   max-width: 458px;
   height: 300px;
   padding: 0;
   box-shadow: 0 0 10px #b0afaf;
}
#page-enrol-index .access-btn{
 display: none;
}
body#page-enrol-index #region-main .generalbox:last-of-type {
   width: 468px;
   padding-left: 2rem;
   padding-right: 2rem;
   margin: 0 auto;
   float: left;
   box-shadow: 0 0 10px #ccc;
   clear: both;
   padding-bottom:30px !Important;
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
.StripeElement input[placeholder], [placeholder], *[placeholder] {
    color: red !important;
}
@media (min-width: 200px) and (max-width: 700px) {
#region-main{
    padding:0;
}   
.generalbox {
   width: 300px;} 
body#page-enrol-index #region-main .generalbox:last-of-type{
 width: 320px;
 margin: 0 auto;
 float: none;
} 
#page-enrol-index p{
 text-align: center;
} 
#apply{
 margin-top: 10px;
}
#coupon{
 margin-top:10px;
}
#page-enrol-index #region-main-box .card-title{
 text-align: center;
}
#page-enrol-index #region-main-box .card-title:before, #page-enrol-index #region-main-box .card-title:after{
 display: none;
}
.couponcode-wrap { display: block;
}
}
#region-main h2 { display:none; }
.enrolmenticons { display: none;}
#new_coupon {margin-bottom:10px;}
</style>