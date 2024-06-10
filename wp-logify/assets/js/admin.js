jQuery(($) => {
    console.log('Admin.js loaded'); // Debug: Check if the script is loaded

    let table = $('#wp-logify-activity-log').DataTable({
        "processing": true,
        "language": {
            processing: 'Please wait...'
        },
        "serverSide": true,
        "ajax": {
            "url": wpLogifyAdmin.ajaxurl,
            "type": "POST",
            "data": (d) => {
                d.action = 'wp_logify_fetch_logs';
                console.log('Sending AJAX request with data:', d); // Debug: Check data sent with the request
            },
            "error": (xhr, error, code) => {
                console.log('AJAX error:', error);
                console.log('AJAX error code:', code);
            }
        },
        "columns": [
            { "data": "id" },
            { "data": "date_time" },
            { "data": "user" },
            // { "data": "user_role" },
            { "data": "source_ip" },
            { "data": "event" },
            // { "data": "object" },
            { "data": "details" },
        ],
        "order": [[1, 'desc']],
        "searching": true,
        "paging": true,
        "pageLength": 20,
        "lengthMenu": [10, 20, 50, 100]
    });

    // Custom search box
    $('#wp-logify-search-box').on('keyup', function () {
        table.search(this.value).draw();
    });
});
