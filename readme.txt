Stripe Payment: 

Introducing the newest offering from Team Dualcube. Stripe Payment!

Now avail the much-awaited coupon functionality while enrolling students in Moodle courses using Stripe payment gateway for paid courses with Strong customer authentication (SCA).

This plugin will help the admins and webmasters to offer their students a percent-off or amount-off discount for the paid courses.


Stripe Payment:


1. Registered users can login to the Moodle site and happily apply the promo codes for a discount before payment. On successful payment, they can access the course.

2. Admins and Webmasters, now, can create, manage and keep track of all promotional codes directly in their Stripe dashboard.

3. Strong customer authentication (SCA) implemented with 4 layers of complex security to comply with EU Revised Directive on Payment Services (PSD2) on payment service providers within the European Economic Area.

4. The first among it's kind to use Payment intent method for Stripe-coupon. 

5. Works with all stable versions of Moodle till v 3.8.1

6. Dedicated support.





Stripe Payment Documentation:



 This plugin has all the settings for development as well as for production usage. Its easy to install, set up and effective.

Creating Merchant Account :

1) Create account at https://stripe.com.

2) Complete your merchant profile details from https://dashboard.stripe.com/account.

3) Now set up secret key and publishers key at https://dashboard.stripe.com/account/apikeys.

4) For test mode use test api keys and for live mode use live api keys.

Now you are done with merchant account set up.

Installation Guidence : 

Login to your moodle site as an “admin user” and follow the steps.

1) Upload the zip package from Site administration > Plugins > Install plugins. Choose Plugin type 'Enrolment method (enrol)'. Upload the ZIP package, check the acknowledgement and install.

2) Go to Enrolments > Manage enrol plugins > Enable 'Stripe' from list

3) Click 'Settings' which will lead to the settings page of the plugin

4) Provide merchant credentials for Stripe. Note that, you will get all the details from your merchant account. Now select the checkbox as per requirement. Save the settings.

5) Select any course from course listing page.

6) Go to Course administration > Users > Enrolment methods > Add method 'Stripe' from the dropdown. Set 'Custom instance name', 'Enrol cost' etc and add the method.

Adding coupons:

Go to your Stripe Dashboard >  Billing > Coupons > Create a coupon.

Type in the coupon’s name: it can be anything and for your reference only.
Type in the Coupon’s ID : This is the Coupon code which your students will need to enter if they want to avail the discount.

Choose Coupon Type: 
1. Percentage discount : offers % off on the course price 
2. Fixed amount discount : Offers a fixed amount off on the course price.

Duration: For duration, when using the value repeating, also specify duration in months as the number of months for which the coupon should repeatedly apply. Otherwise the coupon can be set to apply only to a single invoice or to them all.

Redemption : The max_redemptions and redeem_by values apply to the coupon across every customer you have. For example, you can restrict a coupon to the first 50 customers that use it, or you can make a coupon expire by a certain date. If you do the latter, this only impacts when the coupon can be applied to a customer. If you set a coupon to last forever when used by a customer, but have it expire on January 1st, any customer given that coupon will have that coupon’s discount forever, but no new customers can apply the coupon after January 1st.

If a coupon has a max_redemptions value of 50, it can only be applied among all your customers a total of 50 times, although there’s nothing preventing a single customer from using it multiple times. (You can always use logic on your end to prevent that from occurring.)


 

This completes all the steps from the administrator end. Now registered users can login to the Moodle site and view the course after a successful payment of the discounted price. 




Change log :
* Something else happened, completely unrelated to Stripe - fixed
* Mail not send - fixed
