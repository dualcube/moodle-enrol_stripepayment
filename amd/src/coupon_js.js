define(['jquery', 'core/ajax'
    ],
    function($, ajax
    ) {
    return {
        debug: function(course_id, secret_key, get_cost_from_plugin, invalid_code_string) {
            $('#apply').click(function () {
                var coupon_id_name = $("#coupon").val();
                var promises = ajax.call([{
                    methodname: 'moodle_stripepayment_couponsettings',
                    args: { coupon_id: coupon_id_name, courseid: course_id, secret_key: secret_key, get_cost_from_plugin: get_cost_from_plugin},
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
        }
    };
});