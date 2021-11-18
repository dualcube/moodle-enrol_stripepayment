define(['jquery', 'core/ajax'
    ],
    function($, ajax
    ) {
    return {
        stripe_payment: function(publishablekey, secret_key, courseid, amount, currency, description, couponid, user_id, instance_id, please_wait_string, buy_now_string, get_cost_from_plugin, cost, email, invalid_code_string) {
            // coupon js code
            $('#apply').click(function () {
                var coupon_id_name = $("#coupon").val();
                var promises = ajax.call([{
                    methodname: 'moodle_stripepayment_couponsettings',
                    args: { coupon_id: coupon_id_name, courseid: courseid, secret_key: secret_key, get_cost_from_plugin: get_cost_from_plugin},
                }]);
                promises[0].then(function(data) {
                    $("#form_data_new_data").attr("value", data.status);
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
                }).fail(function(ex) { // do something with the exception 
                    $("#coupon").focus();
                    $("#new_coupon").html('<p style="color:red;"><b>'+ invalid_code_string +'</b></p>');
                });
            });

            // free enrol js
            $('#card-button-zero').click(function () {
                var cost = cost;
                var promises = ajax.call([{
                    methodname: 'stripepayment_free_enrolsettings',
                    args: { cost: cost, couponid: couponid, user_id: user_id, course_id: courseid, instance_id: instance_id, email: email},
                }]);
            });

            // stripe payment code
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
                    args: { secret_key: secret_key, courseid: courseid, amount: amount, currency: currency, description: description, couponid: couponid, user_id: user_id, instance_id: instance_id},
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
