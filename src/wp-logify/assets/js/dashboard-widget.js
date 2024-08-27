jQuery(($) => {

    // When an event row is cicked in the dashboard widget then open up the log page with that
    // event's details shown.
    $('.wp-logify-event-row').on('click', function (e) {
        let eventId = $(this).data('event-id');
        location.href = `/wp-admin/admin.php?page=wp-logify&event_id=${eventId}`;
    });

});
