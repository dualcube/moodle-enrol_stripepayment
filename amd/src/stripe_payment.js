define(['jquery', 'core/ajax'],
    function ($, ajax) {
        return {
            stripe_payment: function (pluginname, user_id, publishablekey, couponid, instance_id, please_wait_string, buy_now_string, invalid_code_string) {
                // coupon js code
                $('#apply').click(function () {
                    var coupon_id_name = $("#coupon").val();
                    var promises = ajax.call([{
                        methodname: 'moodle_stripepayment_couponsettings',
                        args: { coupon_id: coupon_id_name, instance_id: instance_id},
                    }]);
                    promises[0].then(function (data) {
                        $("#form_data_new_data").attr("value", data.status);
                        $("#form_data_new_coupon_id").attr("value", coupon_id_name);
                        $("#form_data_new").submit();
                        $("#reload").load(location.href + " #reload");
                        $("#coupon_id").attr("value", coupon_id_name);
                        $(".coupon_id").val(coupon_id_name);
                        if (data == 0.00) {
                            $('#amountgreaterzero').css("display", "none");
                            $('#amountequalzero').css("display", "block");
                        } else {
                            $('#amountgreaterzero').css("display", "block");
                            $('#amountequalzero').css("display", "none");
                        }
                    }).fail(function (ex) { // do something with the exception 
                        $("#coupon").focus();
                        $("#new_coupon").html('<p style="color:red;"><b>' + invalid_code_string + '</b></p>');
                    });
                });
                // free enrol js
                var get_card_zero_cost = $('#card-button-zero');
                if (get_card_zero_cost) {
                    get_card_zero_cost.click(function () {
                        var promises = ajax.call([{
                            methodname: 'moodle_stripepayment_free_enrolsettings',
                            args: { user_id:user_id, couponid: couponid, instance_id: instance_id },
                        }]);
                        promises[0].then(function (data) {
                            location.reload();
                        }).fail(function (ex) {
                            location.reload();
                        });
                    });
                }
                // stripe payment code
                var buyBtn = $('#payButton');
                var responseContainer = $('#paymentResponse');
                // Handle any errors returned from Checkout
                var handleResult = function (result) {
                    if (result.error) {
                        responseContainer.html('<p>' + result.error.message + '</p>');
                    }
                    buyBtn.prop('disabled', false);
                    buyBtn.text(buy_now_string);
                };
                // Specify Stripe publishable key to initialize Stripe.js
                var stripe = Stripe(publishablekey);
                if (buyBtn) {
                    buyBtn.click(function () {
                        buyBtn.prop('disabled', true);
                        buyBtn.text(please_wait_string);
                        var promises = ajax.call([{
                            methodname: 'moodle_' + pluginname + '_stripe_js_settings',
                            args: {user_id:user_id, couponid: couponid, instance_id: instance_id },
                        }]);
                        promises[0].then(function (data) {
                            if (data.status) {
                                stripe.redirectToCheckout({
                                    sessionId: data.status,
                                }).then(handleResult);
                            } else {
                                handleResult(data);
                            }
                        }).fail(function (ex) { // do something with the exception
                            handleResult(ex);
                        });
                    });
                }
            }
        };
    });
