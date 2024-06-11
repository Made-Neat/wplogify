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
            { "data": "source_ip" },
            { "data": "event_type" },
            { className: 'details-control', orderable: false, defaultContent: '<a>Show</a>' }
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

    // Add event listener for opening and closing details
    table.on('click', 'tr', function (e) {
        // Check if the clicked element is a link.
        if ($(e.target).is('a, a *')) {
            return;
        }

        // Get the row.
        let tr = $(this);
        let row = table.row(tr);

        if (row.child.isShown()) {
            // This row is already open - close it.
            row.child.hide();
            tr.find('td.details-controla ').text('Show');
        }
        else {
            // Open this row.
            row.child(row.data().details).show();
            tr.find('td.details-control a').text('Hide');
        }
    });
});
