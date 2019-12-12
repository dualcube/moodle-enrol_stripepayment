
<div class="mdl-align">
<h3><?php echo $instancename; ?></h3>
<p><?php echo $message; ?></p>
<p><b><?php echo get_string("cost").": {$instance->currency} {$localisedcost}"; ?></b></p>
<div>

<?php
global $USER;

include('Stripe/init.php');

// Set your secret key: remember to change this to your live secret key in production
// See your keys here: https://dashboard.stripe.com/account/apikeys
\Stripe\Stripe::setApiKey($this->get_config('secretkey'));
// \Stripe\Stripe::setApiKey('sk_test_pOjOyJ2E2bqMkmTBNrpuEEin00vpcSBqh9');

$intent = \Stripe\PaymentIntent::create([
    'amount' => str_replace(".", "", $cost),
    'currency' => strtolower($instance->currency),
    'setup_future_usage' => 'off_session',
    'description' => 'Enrolment charge for '.$coursefullname,
]);

?>

<script src="https://js.stripe.com/v3/"></script>

<!--html-->

<!--<label>Name</label>-->

<!-- placeholder for Elements -->
<br> <div id="card-element"></div> <br>
<button id="card-button" data-secret="<?= $intent->client_secret ?>">
  Submit Payment
</button>

<form id="form_charge" action="<?php echo "$CFG->wwwroot/enrol/stripepayment/charge.php"?>" method="post">
    <input type="hidden" name="cmd" value="_xclick" />
    <input type="hidden" name="charset" value="utf-8" />
    <input id="item_name_course" type="hidden" name="item_name" value="<?php p($coursefullname) ?>" />
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
    <input id="cardholder-email" type="hidden" name="email" value="<?php p($USER->email) ?>" />
    <input type="hidden" name="country" value="<?php p($USER->country) ?>" />
    <input type="hidden" name="publishablekey" value="<?php p($publishablekey) ?>" />
        
    <input id="cardholder-name" name="cname" type="hidden" value="<?php echo $userfullname; ?>">
    <input id="auth" name="auth[]" type="hidden" value="">

</form>

<script src="https://cdnjs.cloudflare.com/ajax/libs/blueimp-md5/2.12.0/js/md5.min.js"></script>
<script>

	var stripe = Stripe('<?php echo $publishablekey; ?>');
    // var stripe = Stripe('pk_test_qB1wCObYFcSgYiPCnjv853QM00ae22h6OD');
    
    var elements = stripe.elements();
    var cardElement = elements.create('card');
    cardElement.mount('#card-element');
    var cardholderName = document.getElementById('cardholder-name');
    // var cardholderName = '<?php echo $userfullname; ?>';
    var emailId = document.getElementById('cardholder-email');
    var courseName = document.getElementById('item_name_course');
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
          var uid = <?php echo $USER->id; ?>;
          document.getElementById("auth").value = JSON.stringify(result[0][1]);
          document.getElementById("form_charge").submit();
        }
      });
    });

</script>