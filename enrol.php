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
?>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js">
</script>
<script>
$(document).ready(function() {
    $("#apply").click(function() {
        var txt = $("#coupon").val();
    $.post("<?php echo $CFG->wwwroot; ?>/enrol/stripepayment/validate-coupon.php",
    {coupon_id: txt, courseid: '<?php echo $course->id; ?>'}, function(data, status) {

       if(data == 'wrong') {
         $("#coupon").focus();
         $("#new_coupon").html('<p style="color:red;"><b><?php echo get_string("invalidcouponcode", "enrol_stripepayment"); ?>
         </b></p>');
       } else {
         
         $("#form_data_new_data").attr("value", data);
         $("#form_data_new_coupon_id").attr("value", txt);
         $( "#form_data_new" ).submit();
        
         $("#reload").load(location.href + " #reload");
        
         $("#coupon_id").attr("value", txt);
         $(".coupon_id").val(txt);
         if(data == 0.00) {
             $('#stripeform').hide();
             $('#stripeformfree').show();
         } else {
             $('#stripeform').show();
             $('#stripeformfree').hide();
         }
       }
    });
  });
});
</script>

<style>
#region-main h2 { display:none; }
.enrolmenticons { display: none;}
</style>

<div align="center">
<div class="stripe-img">
<img src="<?php echo $CFG->wwwroot; ?>/enrol/stripepayment/stripe.png"></div>
<p><?php print_string("paymentrequired") ?></p>
<!-- <p><b><?php echo $instancename; ?></b></p> //-->
<p><b><?php echo get_string("cost").": {$instance->currency} {$cost}"; ?></b></p>

<?php echo get_string("couponcode", "enrol_stripepayment"); ?>: <input type=text id="coupon"/>
<button id="apply"><?php echo get_string("applycode", "enrol_stripepayment"); ?></button>

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

// Set your secret key: remember to change this to your live secret key in production.
// See your keys here: https://dashboard.stripe.com/account/apikeys.
\Stripe\Stripe::setApiKey($this->get_config('secretkey'));

$intent = \Stripe\PaymentIntent::create([
    'amount' => str_replace(".", "", $cost),
    'currency' => strtolower($instance->currency),
    'setup_future_usage' => 'off_session',
    'description' => 'Enrolment charge for '.$coursefullname,
]);

echo "<p><b> Final Cost : $instance->currency $cost </b></p>";

?>
<script src="https://js.stripe.com/v3/"></script>

<!-- placeholder for Elements -->
<br> <div id="card-element"></div> <br>
<button id="card-button" data-secret="<?php echo $intent->client_secret ?>">
  Submit Payment
</button>

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
<input id="auth" name="auth[]" type="hidden" value="">

<script>

    var stripe = Stripe('<?php echo $publishablekey; ?>');
    
    var elements = stripe.elements();
    var cardElement = elements.create('card');
    cardElement.mount('#card-element');
    var cardholderName = document.getElementById('cardholder-name');
    var emailId = document.getElementById('cardholder-email');
    var cardButton = document.getElementById('card-button');
    var clientSecret = cardButton.dataset.secret;
    
    cardButton.addEventListener('click', function(ev) {
    
      stripe.handleCardPayment(
        clientSecret, cardElement,
        {
          payment_method_data: {
            billing_details: {name: cardholderName.value,email: emailId.value}
          }
        }
      ).then(function(result) {
        if (result.error) {
          // Display error.message in your UI.
        } else {
          // The setup has succeeded. Display a success message.
          var result = Object.keys(result).map(function(key) {
              return [Number(key), result[key]];
            });
          document.getElementById("auth").value = JSON.stringify(result[0][1]);
          document.getElementById("stripeform").submit();
        }
      });
    });

</script>
</form>

<form id="stripeformfree" style="display:none;" action="<?php
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
<input type="submit" name="submit" value="<?php echo get_string('enrol', 'enrol_stripepayment'); ?>" />

</form>

</div>
</div>

<style>
.CardField-input-wrapper{ overflow: inherit;} 
.coursebox .content .summary{width:100%}
button#apply, button#card-button{
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
}
#page-enrol-index #region-main-box .card-title {
   position: relative;
   line-height: 59px;
   font-size: 2rem;
   text-transform: capitalize;
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
}
</style>