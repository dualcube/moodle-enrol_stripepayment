define(['jquery', 'core/ajax'
    ],
    function($, ajax
    ) {
    return {
        debug: function(cost, couponid, user_id, course_id, instance_id, email) {
            $('#card-button-zero').click(function () {
                var cost = cost;
                var promises = ajax.call([{
                    methodname: 'mod_testtest_updatesettings',
                    args: { cost: cost, couponid: couponid, user_id: user_id, course_id: course_id, instance_id: instance_id, email: email},
                }]);
            });
        }
    };
});