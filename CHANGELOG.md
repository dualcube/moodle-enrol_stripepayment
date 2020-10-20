##Stripe Payment Moodle Plugin Change Log

3.1 (2020102001)
	* Showing the Stripe error code on unsuccessful payment
	* Fixing the issue that payment are done Twice
	* Fixing the issue of Secret Key leak on JS

3.0 (2020080600)
	* 100+ currencies are added
	* 100% discount coupon error fixed: If the discount coupon waves off the actual price, making it a free product, self enrolment method will be activated for the user to use and proceed
	* Card Information input fields are hard to spot: Fixed
	* Stripe Receipt: Code added.
		You can also opt for automatic receipts to be sent upon any completed transaction.
		To do this;
		Log in to your Stripe dashboard
		Go to 'Business Settings' > 'Email Receipts'
		Tick the boxes for 'Successful Payments'
		Click 'Save'
		Note: Not applicable for sandbox accounts.
	* Automatic false transaction request generated each time the Stripe Payment page reloads : Fixed
	* Something else happened, completely unrelated to Stripe - fixed
	* Mail not send - fixed

2.0 (2019121201)

1.0 (2015112807)