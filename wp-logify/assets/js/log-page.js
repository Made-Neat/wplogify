jQuery(($) => {
    let table = $('#wp-logify-activity-log').DataTable({
        processing: true,
        language: {
            processing: 'Please wait...'
        },
        serverSide: true,
        ajax: {
            url: wpLogifyLogPage.ajaxurl,
            type: "POST",
            data: (d) => {
                d.action = 'wp_logify_fetch_logs';
                console.log('Sending AJAX request with data:', d);
            },
            error: (xhr, error, code) => {
                console.log('AJAX error:', error);
                console.log('AJAX error code:', code);
            }
        },
        columns: [
            {
                data: "ID",
                width: '70px'
            },
            { data: "date_time" },
            { data: "user" },
            { data: "user_ip" },
            { data: "event_type" },
            { data: "object" },
            {
                className: 'details-control',
                orderable: false,
                defaultContent: '<span class="wp-logify-show-details">Show</span>',
                width: '100px'
            }
        ],
        order: [[1, 'desc']],
        searching: true,
        paging: true,
        lengthChange: false,
        // Get the page length from the per_page screen option.
        pageLength: +$('#wp_logify_events_per_page').val()
    });

    // Custom search box
    $('#wp-logify-search-box').on('keyup', function () {
        table.search(this.value).draw();
    });

    // Add event listener for opening and closing details
    table.on('click', 'tr', function (e) {
        // Ignore link clicks.
        if ($(e.target).is('a, a *')) {
            return;
        }

        // Get the row.
        let tr = $(this);

        // Ignore clicks on the header row.
        if (tr.parent().is('thead')) {
            return;
        }

        let row = table.row(tr);

        if (row.child.isShown()) {
            // This row is already open - close it.
            row.child.hide();
            tr.find('td.details-control span').text('Show');
        }
        else {
            // Open this row.
            row.child(row.data().details, 'details-row').show();
            tr.find('td.details-control span').text('Hide');
        }
    });

    // Fix search box position.
    $('.dt-search').parent().removeClass('dt-end');

    // Custom search input handler
    $('#dt-search-0').unbind().on('keyup', function (e) {
        var searchTerm = this.value;

        // Check if the length of the search term is at least 3 characters, or none.
        if (searchTerm.length >= 3 || searchTerm.length === 0) {
            table.search(searchTerm).draw();
        }
    });
});
