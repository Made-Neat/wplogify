jQuery(($) => {
    let eventsTable = $('#wp-logify-activity-log').DataTable({
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
                data: "event_id",
                width: '70px'
            },
            { data: "when_happened" },
            { data: "display_name" },
            { data: "user_ip" },
            { data: "event_type" },
            { data: "object_name" },
            {
                data: "object_type",
                visible: false
            }
        ],
        order: [[1, 'desc']],
        searching: true,
        paging: true,
        lengthChange: false,
        // Get the page length from the per_page screen option.
        pageLength: +$('#wp_logify_events_per_page').val(),
        createdRow: (row, data, dataIndex) => {
            $(row).addClass('wp-logify-summary-row wp-logify-object-type-' + data.object_type);
        }
    });

    // Custom search box
    $('#wp-logify-search-box').on('keyup', function () {
        eventsTable.search(this.value).draw();
    });

    // Add event listener for opening and closing details
    eventsTable.on('click', 'tr', function (e) {
        // Ignore link clicks.
        if ($(e.target).is('a, a *')) {
            return;
        }

        // Get the row.
        let $tr = $(this);

        // Ignore clicks on the header row.
        if ($tr.parent().is('thead')) {
            return;
        }

        // Check and see which type of row was clicked.
        let isSummaryRow = $tr.hasClass('wp-logify-summary-row');
        let isDetailsRow = $tr.hasClass('wp-logify-details-row');

        // If neither (for example, clicking a row in a details table), bail.
        if (!isSummaryRow && !isDetailsRow) {
            return;
        }

        // Get the datatable row object.
        let row = eventsTable.row($tr);

        if (isSummaryRow) {
            // Summary row clicked.
            // console.log('summary row clicked');
            if (row.child.isShown()) {
                // Remove the shown class from the summary row.
                $tr.removeClass('shown');
                // Hide the child details row.
                row.child.hide();
            }
            else {
                // Add CSS classes to the details row.
                let classes = 'wp-logify-details-row wp-logify-object-type-' + row.data().object_type + ' shown';
                // Show the details row.
                row.child(row.data().details, classes).show();
                // Add the shown class to the summary row.
                $tr.addClass('shown');
            }
        }
        else {
            // Details row clicked.
            // console.log('details row clicked');

            // Remove the shown class from the summary row.
            $tr.prev().removeClass('shown');
            // Hide the details row.
            row.child.hide();
        }
    });

    // Fix search box position.
    $('.dt-search').parent().removeClass('dt-end');

    // Custom search input handler
    $('#dt-search-0').unbind().on('keyup', function (e) {
        var searchTerm = this.value;

        // Check if the length of the search term is at least 3 characters, or none.
        if (searchTerm.length >= 3 || searchTerm.length === 0) {
            eventsTable.search(searchTerm).draw();
        }
    });
});
