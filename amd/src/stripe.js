define(['jquery', 'core/ajax'
    ],
    function($, ajax
    ) {
    return {
        debug: function(paymentintendsca_link, publishablekey, secret_key, courseid, amount, currency, description, couponid, user_id, instance_id, please_wait_string, buy_now_string) {
            var buyBtn = document.getElementById('payButton');
            var responseContainer = document.getElementById('paymentResponse');

            // Handle any errors returned from Checkout
            var handleResult = function (result) {
                if (result.error) {
                    responseContainer.innerHTML = '<p>'+result.error.message+'</p>';
                }
                buyBtn.disabled = false;
                buyBtn.textContent = buy_now_string;
            };

            // Specify Stripe publishable key to initialize Stripe.js
            var stripe = Stripe(publishablekey);

            buyBtn.addEventListener("click", function (evt) {
                buyBtn.disabled = true;
                buyBtn.textContent = please_wait_string;
                var promises = ajax.call([{
                    methodname: 'moodle_stripepayment_stripe_js_settings',
                    args: { paymentintendsca_link: paymentintendsca_link, publishablekey: publishablekey, secret_key: secret_key, courseid: courseid, amount: amount, currency: currency, description: description, couponid: couponid, user_id: user_id, instance_id: instance_id},
                }]);
                promises[0].then(function(data) {
                    if(data.status) {
                        stripe.redirectToCheckout({
                            sessionId: data.status,
                        }).then(handleResult);
                    } else {
                        handleResult(data);
                    }

                }).fail(function(ex) { // do something with the exception 
                   handleResult(ex);
                });
            });
        }
    };
});