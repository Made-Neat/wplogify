jQuery(($) => {

    // Get the event ID from the query string.
    let urlParams = new URLSearchParams(window.location.search);
    const eventIdToExpand = urlParams.get('event_id');

    // Set up the datatable.
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
        layout: {
            topStart: null,
            topEnd: null,
        },
        paging: true,
        lengthChange: false,
        // Get the page length from the per_page screen option.
        pageLength: +$('#wp_logify_events_per_page').val(),
        createdRow: (row, data, dataIndex) => {
            // Get the event ID of this row.
            const $row = $(row);
            const eventId = $row.find('td:first-child').text();

            // Add CSS classes to the tr element, and set the event-id.
            $row.addClass('wp-logify-summary-row wp-logify-object-type-' + data.object_type)
                .attr('data-event-id', eventId);
        },
        drawCallback: function (settings) {
            // Check if we need to expand a row.
            if (!eventIdToExpand) {
                return;
            }

            // Find the row with this event ID.
            let $row = $(`tr[data-event-id="${eventIdToExpand}"]`);
            if ($row.length) {
                // Expand the details row.
                showDetailsRow($row);
                // Scroll the summary and details rows into view.
                $row.get(0).scrollIntoView({ behavior: 'smooth' });
            }
        }
    });

    // Show the details row for a given summary row.
    let showDetailsRow = $tr => {
        // Get the datatable row object.
        let row = eventsTable.row($tr);
        // Add CSS classes to the details row.
        let classes = 'wp-logify-details-row wp-logify-object-type-' + row.data().object_type + ' shown';
        // Show the details row.
        row.child(row.data().details, classes).show();
        // Add the shown class to the summary row.
        $tr.addClass('shown');
    };

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
            if (row.child.isShown()) {
                // Remove the shown class from the summary row.
                $tr.removeClass('shown');
                // Hide the child details row.
                row.child.hide();
            }
            else {
                showDetailsRow($tr);
            }
        }
        else {
            // Details row clicked.
            // Remove the shown class from the summary row.
            $tr.prev().removeClass('shown');
            // Hide the details row.
            row.child.hide();
        }
    });

    // Setup behaviour for the search box.
    $('#wp-logify-search-filter').on('keyup', function () {
        // Remember the search string.
        let searchString = this.value;
        wpCookies.set('search', searchString, false, false, false, false);

        // If there are 3 or more characters in the field, or 0, search.
        if (searchString.length >= 3 || searchString.length === 0) {
            eventsTable.search(searchString).draw();
        }
    });

    // Get the selected object types.
    let getSelectedObjectTypes = () => {
        let objectTypes = {};
        $('#wp-logify-object-type-checkboxes input[type="checkbox"]').each((index, element) => {
            objectTypes[element.value] = element.checked;
        });
        return objectTypes;
    }

    // Check if all object types are selected.
    let allObjectTypesSelected = () => {
        return $('#wp-logify-object-type-checkboxes input[type="checkbox"]:not(:checked)').length === 0;
    }

    // Initialize the search filters from the cookies.
    let initSearchForm = () => {
        // Search string.
        let search = wpCookies.get('search');
        $('#wp-logify-search-filter').val(search);

        // Object types.
        let selectedObjectTypes = wpCookies.get('object_types');
        if (selectedObjectTypes) {
            selectedObjectTypes = JSON.parse(selectedObjectTypes);
        }
        $('#wp-logify-object-type-checkboxes input[type="checkbox"]').each((index, element) => {
            let checked = (selectedObjectTypes && typeof selectedObjectTypes === 'object'
                && element.value in selectedObjectTypes) ? selectedObjectTypes[element.value] : true;
            $(element).prop('checked', checked);
        });
        // Update state of 'All' checkbox to match state of others.
        $('#wp-logify-show-all-events').prop('checked', allObjectTypesSelected());

        // Dates.
        let startDate = wpCookies.get('start_date');
        let endDate = wpCookies.get('end_date');
        $('#wp-logify-start-date').val(startDate);
        $('#wp-logify-end-date').val(endDate);

        // Post type.
        let postType = wpCookies.get('post_type');
        $('#wp-logify-post-type-filter').val(postType);

        // Taxonomy.
        let taxonomy = wpCookies.get('taxonomy');
        $('#wp-logify-taxonomy-filter').val(taxonomy);

        // Event type.
        let eventType = wpCookies.get('event_type');
        $('#wp-logify-event-type-filter').val(eventType);

        // User.
        let user_id = wpCookies.get('user_id');
        $('#wp-logify-user-filter').val(user_id);

        // Role.
        let role = wpCookies.get('role');
        $('#wp-logify-role-filter').val(role);
    };

    // Do it now.
    initSearchForm();

    // Setup behaviour of object type checkboxes.
    $('.wp-logify-object-type-filter-item input').on('change', function (event) {
        // Cancel the default behaviour of the checkbox.
        event.preventDefault();

        // Click the surrounding div.
        $(this).parent().click();
    });

    // Setup behaviour of object type containers (i.e. the coloured divs wrapping the checkbox and label).
    $('.wp-logify-object-type-filter-item').on('click', function (event) {
        // Toggle the checkbox.
        let checkbox = $(this).find('input[type="checkbox"]');
        let isChecked = checkbox.is(':checked');
        checkbox.prop('checked', !isChecked);
        isChecked = !isChecked;

        // If this is the 'All' checkbox, check or uncheck all others.
        if (checkbox.is('#wp-logify-show-all-events')) {
            if (isChecked) {
                // All is checked, so check all others.
                $('#wp-logify-object-type-checkboxes input').prop('checked', true);
            }
            else {
                // Otherwise, uncheck all others.
                $('#wp-logify-object-type-checkboxes input').prop('checked', false);
            }
        }
        else {
            // Update state of 'All' checkbox to match state of others.
            $('#wp-logify-show-all-events').prop('checked', allObjectTypesSelected());
        }

        // Update cookie.
        let selectedObjectTypes = getSelectedObjectTypes();
        // console.log(selectedObjectTypes);
        wpCookies.set('object_types', JSON.stringify(selectedObjectTypes), false, false, false, false);

        // Reload the page.
        eventsTable.ajax.reload();
    });

    // Extract the date from a datepicker element.
    let getDate = element => {
        var date;
        try {
            date = $.datepicker.parseDate(wpLogifyLogPage.dateFormat, element.value);
            console.log(date);
        } catch (error) {
            date = null;
        }

        return date;
    };

    // Setup the start datepicker.
    let startDatePicker = $('#wp-logify-start-date').datepicker({
        dateFormat: wpLogifyLogPage.dateFormat,
        onSelect: function (date) {
            console.log('start date selected:', date);
            // Update the cookie.
            wpCookies.set('start_date', date, false, false, false, false);

            // Set the min date of the end datepicker.
            endDatePicker.datepicker("option", "minDate", getDate(this));

            // Reload the table.
            eventsTable.ajax.reload();
        }
    });

    // Setup the end datepicker.
    let endDatePicker = $('#wp-logify-end-date').datepicker({
        dateFormat: wpLogifyLogPage.dateFormat,
        onSelect: function (date) {
            console.log('end date selected:', date);
            // Update the cookie.
            wpCookies.set('end_date', date, false, false, false, false);

            // Set the max date of the start datepicker.
            startDatePicker.datepicker("option", "maxDate", getDate(this));

            // Reload the table.
            eventsTable.ajax.reload();
        }
    });

    // Set up the post type selector.
    $('#wp-logify-post-type-filter').on('change', function () {
        // Update the cookie.
        wpCookies.set('post_type', this.value, false, false, false, false);

        // Reload the table.
        eventsTable.ajax.reload();
    });

    // Set up the taxonomy selector.
    $('#wp-logify-taxonomy-filter').on('change', function () {
        // Update the cookie.
        wpCookies.set('taxonomy', this.value, false, false, false, false);

        // Reload the table.
        eventsTable.ajax.reload();
    });

    // Set up the event type selector.
    $('#wp-logify-event-type-filter').on('change', function () {
        // Update the cookie.
        wpCookies.set('event_type', this.value, false, false, false, false);

        // Reload the table.
        eventsTable.ajax.reload();
    });

    // Set up the user selector.
    $('#wp-logify-user-filter').on('change', function () {
        // Update the cookie.
        wpCookies.set('user_id', this.value, false, false, false, false);

        // Reload the table.
        eventsTable.ajax.reload();
    });

    // Set up the role selector.
    $('#wp-logify-role-filter').on('change', function () {
        // Update the cookie.
        wpCookies.set('role', this.value, false, false, false, false);

        // Reload the table.
        eventsTable.ajax.reload();
    });
});
