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
<div class="couponcode-wrap">
<span class="couponcode-text"> <?php echo get_string("couponcode", "enrol_stripepayment"); ?>: </span> <input type=text id="coupon"/>
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

echo "<p><b> Final Cost : $instance->currency $cost </b></p>";

?>

<?php $costvalue = str_replace(".", "", $cost);
if ($costvalue == 000) {  ?>
<div id="amountequalzero">
  <button id="card-button-zero">
    Enrol Now
  </button>
</div>
<br>

<script type="text/javascript">
  $(document.body).on('click', '#card-button-zero' ,function(){
    var cost = "<?php echo str_replace(".", "", $cost); ?>";
    if (cost == 000) {
      document.getElementById("stripeformfree").submit();
    }
  });
</script>

<?php } else { ?>
<script src="https://js.stripe.com/v3/"></script>

<!-- placeholder for Elements -->

<div id="amountgreaterzero">
    <strong>
  <div id="card-element"></div> <br>
  <button id="card-button">
    Submit Payment
  </button>
  <div id="transaction-status">
    <center> Your transaction is processing. Please wait... </center>
  </div>
  </strong>
</div>

<script type="text/javascript">
    var stripe = Stripe('<?php echo $publishablekey; ?>');
    var elements = stripe.elements();
    var style = {
      base: {
        fontSize:'15px',
        color:'#000',
        '::placeholder': {
          color: '#000',
        }
      },
    };

    var cardElement = elements.create('card', {style: style});
    cardElement.mount('#card-element');
    var cardholderName = "<?php echo $userfullname; ?>";
    var emailId = "<?php echo $USER->email; ?>";
    var cardButton = document.getElementById('card-button');
    var status = 0;
    var postal = null;
    
    cardElement.addEventListener('change', function(event) {

      postalCode = event.value['postalCode'];

    });

    cardButton.addEventListener('click', function(event) {

      if (event.error) {
          status = 0;
      } else {
          status = 1;
      }

      if (status == 0 || status == null) {
         $("#transaction-status").css("display", "none");
      } else {
         $("#transaction-status").css("display", "block");
      
         $.ajax({

          url: "<?php echo $CFG->wwwroot; ?>/enrol/stripepayment/paymentintendsca.php",
          method: 'POST',
          data: {
              'secretkey' : "<?php echo $this->get_config('secretkey'); ?>",
              'amount' : "<?php echo str_replace(".", "", $cost); ?>",
              'currency' : "<?php echo strtolower($instance->currency); ?>",
              'description' : "<?php echo 'Enrolment charge for '.$coursefullname; ?>",
              'courseid' : "<?php echo $course->id; ?>",
              'receiptemail' : emailId,
          },

          success: function(data) {
            var clientSecret = data;

            stripe.handleCardPayment(
              clientSecret, cardElement,
              {
                payment_method_data: {
                  billing_details: {name: cardholderName,email: emailId}
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
          },

          error: function() {
            $("#transaction-status").html("<center> Sorry! Your transaction is failed. </center>");
          },
                            
        });
      
      }
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
<input id="auth" name="auth[]" type="hidden" value="">
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
button#apply, button#card-button, button#card-button-zero{
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
</style>