jQuery(($) => {
    // Retrieve the error ID from the URL query string, if present.
    let urlParams = new URLSearchParams(window.location.search);
    const eventIdToExpand = urlParams.get('error_id');

    // Initialize the DataTable instance.
    let eventsTable = $('#logify-wp-activity-log').DataTable({
        processing: true,
        language: {
            processing: 'Please wait...'
        },
        serverSide: true,
        ajax: {
            url: logifyWpLogPage.ajaxUrl, // AJAX request URL.
            type: "POST", // Use POST method for security.
            data: (d) => {
                d.action = 'logify_wp_fetch_errors'; // Action for WordPress AJAX handler.
                d.security = logifyWpLogPage.ajaxNonce; // Nonce for security validation.
                console.log('Sending AJAX request with data:', d); // Debugging log.
            },
            dataType: "json",
            error: (xhr, error, code) => {
                console.log('AJAX error:', error); // Log the error type.
                console.log('AJAX error code:', code); // Log the error code.
            }
        },
        columns: [
            {
                data: "error_id",
                width: '70px' // Set column width.
            },
            {
                data: "error_type",
                width: '200px' // Set column width.
            },
            {
                data: "error_content" // Error content column.
            }
        ],
        order: [[1, 'desc']], // Default sorting on the second column (error_type) in descending order.
        layout: {
            topStart: null,
            topEnd: null,
        },
        paging: true, // Enable pagination.
        lengthChange: false, // Disable page length change option.
        pageLength: +$('#logify_wp_events_per_page').val(), // Set page length dynamically.
        createdRow: (row, data, dataIndex) => {
            const $row = $(row); // Wrap the row in jQuery.
            const eventId = $row.find('td:first-child').text(); // Extract the event ID.
            
            // Add CSS classes and set a data attribute for easy identification.
            $row.addClass('logify-wp-summary-row').attr('data-event-id', eventId);
        },
        drawCallback: function (settings) {
            // If there is no event ID to expand, exit the function.
            if (!eventIdToExpand) {
                return;
            }

            // Find the row with the specified error ID.
            let $row = $(`tr[data-error-id="${eventIdToExpand}"]`);
            if ($row.length) {
                // Expand the corresponding details row.
                showDetailsRow($row);
                
                // Smoothly scroll the expanded row into view.
                $row.get(0).scrollIntoView({ behavior: 'smooth' });
            }
        }
    });

    /**
     * Function to show the details row for a given summary row.
     * 
     * @param {Object} $tr - jQuery object representing the table row.
     */
    let showDetailsRow = ($tr) => {
        let row = eventsTable.row($tr); // Get the DataTable row object.
        let classes = 'logify-wp-details-row shown'; // Define CSS classes for the expanded row.
        
        row.child(row.data().details, classes).show(); // Show the details row.
        $tr.addClass('shown'); // Add 'shown' class to indicate expansion.
    };
});
