/**
 * Track user activity.
 */
(function ($) {
    function sendActivity() {
        $.ajax({
            type: 'POST',
            url: logifyWpActivity.ajax_url,
            data: {
                action: 'track_user_activity',
                nonce: logifyWpActivity.nonce
            },
            success: function (response) {
                // console.log('User activity tracked', response);
            },
            error: function (error) {
                console.error('Error tracking user activity', error);
            }
        });
    }

    $(function () {
        // Send initial activity on page load.
        sendActivity();

        // Send activity every 15 seconds.
        setInterval(sendActivity, 15000);
    });
})(jQuery);
