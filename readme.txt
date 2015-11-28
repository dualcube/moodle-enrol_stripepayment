Enrolment in Moodle using Stripe payment gateway for paid courses

This plugin helps admins and webmasters use Stripe as the payment gateway. Stripe is one of the populer payment gateways. This plugin has all the settings for development as well as for production usage. Its easy to install, set up and effective.

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

This completes all the steps from the administrator end. Now registered users can login to the Moodle site and view the course after a successful payment.
