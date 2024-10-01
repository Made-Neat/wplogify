jQuery(($) => {

    // When an event row is cicked in the dashboard widget then open up the log page with that
    // event's details shown.
    $('.logify-wp-event-row').on('click', function (e) {
        let eventId = $(this).data('event-id');
        location.href = `/wp-admin/admin.php?page=logify-wp&event_id=${eventId}`;
    });

});
