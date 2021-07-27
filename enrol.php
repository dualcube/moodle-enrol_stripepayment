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

$_SESSION['description'] = $coursefullname;
$_SESSION['courseid'] = $course->id;
$_SESSION['instance_id'] = $instance->id;         
$_SESSION['user_id'] = $USER->id;
$_SESSION['currency'] = $instance->currency;
$currency_symbol = show_currency_symbol( strtolower($instance->currency) );

function get_stripe_amount($cost, $currency, $reverse) {
    $nodecimalcurrencies = array("bif", "clp", "djf", "gnf", "jpy", "kmf", "krw", "mga", "pyg",
        "rwf", "ugx", "vnd", "vuv", "xaf", "xof", "xpf");
    if (!$currency) {
        $currency = 'USD';
    }
    if (in_array(strtolower($currency), $nodecimalcurrencies)) {
        return abs($cost);
    } else {
        if ($reverse) {
            return abs( (float) $cost / 100);
        } else {
            return abs( (float) $cost * 100);
        }
    }
}
?>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
<script>
$(document).ready(function() {
    $("#apply").click(function() {
    var coupon_id_name = $("#coupon").val();
    $.post("<?php echo $CFG->wwwroot; ?>/enrol/stripepayment/validate-coupon.php",
    {coupon_id: coupon_id_name, courseid: '<?php echo $course->id; ?>'}, function(data, status) {
       if(data == 'wrong') {
         $("#coupon").focus();
         $("#new_coupon").html('<p style="color:red;"><b><?php echo get_string("invalidcouponcode", "enrol_stripepayment"); ?>
         </b></p>');
       } else {
         $("#form_data_new_data").attr("value", data);
         $("#form_data_new_coupon_id").attr("value", coupon_id_name);
         $( "#form_data_new" ).submit();
        
         $("#reload").load(location.href + " #reload");
        
         $("#coupon_id").attr("value", coupon_id_name);
         $(".coupon_id").val(coupon_id_name);
         if(data == 0.00) {
             $('#amountgreaterzero').css("display", "none");
             $('#amountequalzero').css("display", "block");
         } else {
             $('#amountgreaterzero').css("display", "block");
             $('#amountequalzero').css("display", "none");
         }
       }
    });
  });
});
</script>
<div align="center">
    <div class="stripe-img">
        <img src="<?php echo $CFG->wwwroot; ?>/enrol/stripepayment/pix/stripe.png"></div>
        <p><?php print_string("paymentrequired") ?></p>
        <p><b><?php echo get_string("cost").": {$currency_symbol}{$cost}"; ?></b></p>
        <div class="couponcode-wrap">
            <span class="couponcode-text"> <?php echo get_string("couponcode", "enrol_stripepayment"); ?>: </span>
            <input type=text id="coupon"/>
            <button id="apply"><?php echo get_string("applycode", "enrol_stripepayment"); ?></button>
        </div>

        <form id="form_data_new" action="" method="post">
            <input id="form_data_new_data" type="hidden" name="data" value="" />
            <input id="form_data_new_coupon_id" type="hidden" name="coupon_id" value="" />
        </form>
        <div id="reload">
            <div id="new_coupon" style="margin-bottom:10px;"></div>
<?php
require('Stripe/init.php');
$couponid = 0;
$dataa = optional_param('data', null, PARAM_RAW);
if ( isset($dataa) ) {
    $cost = $dataa;
    $couponid = required_param('coupon_id', PARAM_RAW);
}
$_SESSION['coupon_id'] = $couponid;
$_SESSION['amount'] = get_stripe_amount($cost, $_SESSION['currency'], false);
$final_cost_text = get_string("final_cost", "enrol_stripepayment");
echo "<p><b> $final_cost_text : $currency_symbol$cost </b></p>";

$costvalue = str_replace(".", "", $cost);
if ($costvalue == 000) {  ?>
<div id="amountequalzero">
  <button id="card-button-zero">
    <?php echo get_string("enrol_now", "enrol_stripepayment"); ?>
  </button>
</div>
<br>

<script>
  $(document.body).on('click', '#card-button-zero' ,function(){
    var cost = "<?php echo str_replace(".", "", $cost); ?>";
    if (cost == 000) {
      document.getElementById("stripeformfree").submit();
    }
  });
</script>

<?php } else { ?>
<script src="https://js.stripe.com/v3/"></script>
<div id="paymentResponse"></div>
<div id="buynow">
    <button class="stripe-button" id="payButton"><?php echo get_string("buy_now", "enrol_stripepayment"); ?></button>
</div>
<script>
var buyBtn = document.getElementById('payButton');
var responseContainer = document.getElementById('paymentResponse');
    
// Create a Checkout Session with the selected product
var createCheckoutSession = function (stripe) {
    return fetch("<?php echo $CFG->wwwroot; ?>/enrol/stripepayment/paymentintendsca.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            checkoutSession: 1,
        }),
    }).then(function (result) {
        return result.json();
    });
};

// Handle any errors returned from Checkout
var handleResult = function (result) {
    if (result.error) {
        responseContainer.innerHTML = '<p>'+result.error.message+'</p>';
    }
    buyBtn.disabled = false;
    buyBtn.textContent = 'Buy Now';
};

// Specify Stripe publishable key to initialize Stripe.js
var stripe = Stripe('<?php echo $publishablekey; ?>');

buyBtn.addEventListener("click", function (evt) {
    buyBtn.disabled = true;
    buyBtn.textContent = 'Please wait...';
    createCheckoutSession().then(function (data) {
        if(data.sessionId) {
            stripe.redirectToCheckout({
                sessionId: data.sessionId,
            }).then(handleResult);
        } else {
            handleResult(data);
        }
    });
});
</script>

<?php } ?>

<form id="stripeform" action="<?php echo "$CFG->wwwroot/enrol/stripepayment/charge.php"?>" method="post">
<input id="coupon_id" type="hidden" name="coupon_id" value="<?php p($couponid) ?>" class="coupon_id" />
<input type="hidden" name="cmd" value="_xclick" />
<input type="hidden" name="charset" value="utf-8" />
<input type="hidden" name="item_name" value="<?php p($coursefullname) ?>" />
<input type="hidden" name="item_number" value="<?php p($courseshortname) ?>" />
<input type="hidden" name="quantity" value="1" />
<input type="hidden" name="on0" value="<?php print_string("user") ?>" />
<input type="hidden" name="os0" value="<?php p($userfullname) ?>" />
<input type="hidden" name="custom" value="<?php echo "{$USER->id}-{$course->id}-{$instance->id}" ?>" />
<input type="hidden" name="currency_code" value="<?php p($instance->currency) ?>" />
<input type="hidden" name="amount" value="<?php p($cost) ?>" />
<input type="hidden" name="for_auction" value="false" />
<input type="hidden" name="no_note" value="1" />
<input type="hidden" name="no_shipping" value="1" />
<input type="hidden" name="rm" value="2" />
<input type="hidden" name="cbt" value="<?php print_string("continuetocourse") ?>" />
<input type="hidden" name="first_name" value="<?php p($userfirstname) ?>" />
<input type="hidden" name="last_name" value="<?php p($userlastname) ?>" />
<input type="hidden" name="address" value="<?php p($useraddress) ?>" />
<input type="hidden" name="city" value="<?php p($usercity) ?>" />
<input type="hidden" name="email" value="<?php p($USER->email) ?>" />
<input type="hidden" name="country" value="<?php p($USER->country) ?>" />
<input id="cardholder-name" name="cname" type="hidden" value="<?php echo $userfullname; ?>">
<input id="cardholder-email" type="hidden" name="email" value="<?php p($USER->email) ?>" />
<input id="sessionID" name="sessionID" type="hidden" value="">
</form>

<form id="stripeformfree" action="<?php
echo "$CFG->wwwroot/enrol/stripepayment/free_enrol.php"?>" method="post">
<input type="hidden" name="coupon_id" value="<?php p($couponid) ?>" class="coupon_id" />
<input type="hidden" name="cmd" value="_xclick" />
<input type="hidden" name="charset" value="utf-8" />
<input type="hidden" name="item_name" value="<?php p($coursefullname) ?>" />
<input type="hidden" name="item_number" value="<?php p($courseshortname) ?>" />
<input type="hidden" name="quantity" value="1" />
<input type="hidden" name="on0" value="<?php print_string("user") ?>" />
<input type="hidden" name="os0" value="<?php p($userfullname) ?>" />
<input type="hidden" name="custom" value="<?php echo "{$USER->id}-{$course->id}-{$instance->id}" ?>" />
<input type="hidden" name="currency_code" value="<?php p($instance->currency) ?>" />
<input type="hidden" name="amount" value="<?php p($cost) ?>" />
<input type="hidden" name="for_auction" value="false" />
<input type="hidden" name="no_note" value="1" />
<input type="hidden" name="no_shipping" value="1" />
<input type="hidden" name="rm" value="2" />
<input type="hidden" name="cbt" value="<?php print_string("continuetocourse") ?>" />
<input type="hidden" name="first_name" value="<?php p($userfirstname) ?>" />
<input type="hidden" name="last_name" value="<?php p($userlastname) ?>" />
<input type="hidden" name="address" value="<?php p($useraddress) ?>" />
<input type="hidden" name="city" value="<?php p($usercity) ?>" />
<input type="hidden" name="email" value="<?php p($USER->email) ?>" />
<input type="hidden" name="country" value="<?php p($USER->country) ?>" />

</form>

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
</style>