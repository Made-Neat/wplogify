jQuery(($) => {

    $('.wp-logify-event-row').on('click', function (e) {
        let eventId = $(this).data('event-id');
        location.href = `/wp-admin/admin.php?page=wp-logify&event_id=${eventId}`;
    });

});
