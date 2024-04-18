##Stripe Payment Moodle Plugin Change Log
3.4.6 (2024041800)
 * Fixed the stripe checkout page displays the last email address of the customer.  
  
3.4.5 (2024030600)
 * Fixed purchases with coupon getting redeemed twice.

3.4.4 (2023101400)
 * Fixed cost in float number #133.
 * Fived UI #133.
 * Added Metadata attached with stripe

3.4.3 (2023101000)
* Added Moodle 4.3 supports.

3.4.2 (2023060200)
 * Fixed Missing Instance name #124.
 * Fixed Free enrol - coupon id and user id arguments swapped #125.
 * Fixed Successful Enrolment - Exception Error Message (stdClass undefined) #126.
 * Added Script library 10.14.0.

3.4.1 (2023042500)
 * Added compatibility of Moodle 4.2+.
 * Fixed compatibility issue with 3.9+ #122.


3.4.0 (2023042200)
 * Added Token fild required issue #120.
 * Code optimized.

3.3.5 (2023022500)
 * Added compatibility of Moodle 4.1+.
 * Added Script library 10.7.0.
 * Fixed Chackout error.
 * Fixed Expired Coupon Faild silently.
 * Fixed Payment description no stripe dashboard not shows.
 * Fixed coupon apply resister in Stripe.
 * Fixed internal error.

3.3.4 (2022070702)
 * Added compatibility of Moodle 4.0.1+.
 * Fixed error on 100% counpon discount.
 * Fixed internal error.
 
3.3.3 (2022050602)
 * Added compatibility of Moodle 4.0+.

3.3.2 (2022032402)
 * Added compatibility of Moodle 3.11.6+.
 * Fixed error on the plugin settings page.

3.3.1 (2021111802)
 * Fixed 100% discount issue #89.
 * Fixed New error upon adding the plugin #86
 * Fixed error on the plugin settings page.
 * Added Some Country on the list
 
3.3.0 (2021080601)
 * Make the plugin Moodle Stanard
 * Fixed the plugin settings page.
 
3.2.1 (2020121401)
 * Fixed the issue of wrong variable causing critical bug
 * Fixed the issue of full price taken by stripe upon payment using a coupon.

3.2 (202010270200)
 * Fixing the issue of payments record not getting through stripe dashboard
 * Changed the Course Stripe Enrolment Setting 

3.1 (2020102001)
 * Showing the Stripe error code on unsuccessful payment
 * Fixing the issue that payment are done Twice
 * Fixing the issue of Secret Key leak on JS

3.0 (2020080600)
 * 100+ currencies are added
 * 100% discount coupon error fixed: If the discount coupon waves off the actual price, making it a free product, self enrolment method will be activated for the user to use and proceed
 * Card Information input fields are hard to spot: Fixed
 * Stripe Receipt: Code added. You can also opt for automatic receipts to be sent upon any completed transaction. To do this => Log in to your Stripe dashboard => Go to 'Business Settings' > 'Email Receipts' => Tick the boxes for 'Successful Payments' => Click 'Save'. Note: Not applicable for sandbox accounts.
 * Automatic false transaction request generated each time the Stripe Payment page reloads : Fixed
 * Something else happened, completely unrelated to Stripe - fixed
 * Mail not send - fixed

2.0 (2019121201)

1.0 (2015112807)
