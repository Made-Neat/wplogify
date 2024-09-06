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
        searching: true,
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

    // Custom search box
    $('#wp-logify-search-box').on('keyup', function () {
        eventsTable.search(this.value).draw();
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
            // console.log('summary row clicked');
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

    // Get the selected object types.
    let getSelectedObjectTypes = () => {
        let objectTypes = [];
        $('#wp-logify-object-type-checkboxes input[type="checkbox"]:checked').each((index, element) => {
            objectTypes.push(element.value);
        });
        return objectTypes;
    }

    // Check if all object types are selected.
    let allObjectTypesSelected = () => {
        return $('#wp-logify-object-type-checkboxes input[type="checkbox"]:not(:checked)').length === 0;
    }

    // Store the selected object types in the cookie and reload the table.
    let updateCookieAndReload = () => {
        // Store the selected object types in the cookie.
        let selectedObjectTypes = getSelectedObjectTypes();
        // console.log(selectedObjectTypes);
        wpCookies.set('object_types', JSON.stringify(selectedObjectTypes), false, false, false, false);

        // Reload the page.
        eventsTable.ajax.reload();
    }

    // Initialize the object type checkboxes.
    let initObjectTypeCheckboxes = () => {
        // Initialize the object type checkboxes.
        let selectedObjectTypes = wpCookies.get('object_types');
        // console.log(selectedObjectTypes);
        $('#wp-logify-object-type-checkboxes input[type="checkbox"]').each((index, element) => {
            $(element).prop('checked', selectedObjectTypes.includes(element.value));
        });

        // Update state of 'All' checkbox to match state of others.
        $('#wp-logify-show-all-events').prop('checked', allObjectTypesSelected());
    };

    // Do it now.
    initObjectTypeCheckboxes();

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
            console.log('All checkbox clicked');

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

        // Update cookies and reload the page.
        updateCookieAndReload();
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

});
