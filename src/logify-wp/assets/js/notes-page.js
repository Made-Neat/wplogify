jQuery(($) => {

    // Get the Notes ID from the query string.
    let urlParams = new URLSearchParams(window.location.search);
    const eventIdToExpand = urlParams.get('note_id');

    // Set up the datatable.
    let eventsTable = $('#logify-wp-activity-log').DataTable({
        processing: true,
        language: {
            processing: 'Please wait...'
        },
        serverSide: true,
        ajax: {
            url: logifyWpLogPage.ajaxUrl,
            type: "POST",
            data: (d) => {
                d.action = 'logify_wp_fetch_notes';
                d.security = logifyWpLogPage.ajaxNonce;
                console.log('Sending AJAX request with data:', d);
            },
            error: (xhr, error, code) => {
                console.log('AJAX error:', error);
                console.log('AJAX error code:', code);
            }
        },
        columns: [
            {
                data: "note_id",
                width: '70px'
            },
            { data: "created_at" },
            { data: "display_name" },
            { data: "short_note" },
            { data: "event_id" },
            {
                data: "edit_link",
                render: (data, type, row) => {
                    return data ? data : ''; // Render the HTML link
                },
                orderable: false, // Prevent sorting on this column
                searchable: false // Prevent searching on this column
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
        pageLength: +$('#logify_wp_events_per_page').val(),
        createdRow: (row, data, dataIndex) => {
            // Get the event ID of this row.
            const $row = $(row);
            const eventId = $row.find('td:first-child').text();

            // Add CSS classes to the tr element, and set the event-id.
            $row.addClass('logify-wp-summary-row').attr('data-event-id', eventId);
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
        let classes = 'logify-wp-details-row shown';
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
        let isSummaryRow = $tr.hasClass('logify-wp-summary-row');
        let isDetailsRow = $tr.hasClass('logify-wp-details-row');

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

    // Setup behavior for the search box.
    $('#logify-wp-search-filter').on('keyup', function () {
        // Remember the search string.
        let searchString = this.value;
        wpCookies.set('search', searchString, false, false, false, false);

        // If there are 3 or more characters in the field, or 0, search.
        if (searchString.length >= 3 || searchString.length === 0) {
            eventsTable.search(searchString);
            eventsTable.ajax.reload();
        }
    });

    // Get the selected object types.
    let getSelectedObjectTypes = () => {
        let objectTypes = {};
        $('#logify-wp-object-type-checkboxes input[type="checkbox"]').each((index, element) => {
            objectTypes[element.value] = element.checked;
        });
        return objectTypes;
    }

    // Check if all object types are selected.
    let allObjectTypesSelected = () => {
        return $('#logify-wp-object-type-checkboxes input[type="checkbox"]:not(:checked)').length === 0;
    }

    // Get a value from a cookie; then, in the specified select list, select the option with the
    // matching value if present, otherwise select the option with the empty string value.
    let selectCookieValue = (selectId, cookieName) => {
        // Get the cookie value.
        let value = wpCookies.get(cookieName);

        // Get the select element.
        let $select = $(selectId);

        // Get the option element with the matching value, if present.
        let $option = typeof value === 'string' ? $select.find(`option[value='${value}']`) : null;

        // Check if the option element with the matching value exists.
        if ($option && $option.length) {
            // If it does, select the post type.
            $option.prop('selected', true);
        } else {
            // If not, select the 'All' option.
            $select.val('');
            // Update the cookie to match.
            wpCookies.set(cookieName, '', false, false, false, false);
        }
    }

    // Initialize the search filters from the cookies.
    let initSearchForm = () => {
        // Search string.
        let search = wpCookies.get('search');
        $('#logify-wp-search-filter').val(search);

        // Object types.
        let selectedObjectTypes = wpCookies.get('object_types');
        if (selectedObjectTypes) {
            selectedObjectTypes = JSON.parse(selectedObjectTypes);
        }
        $('#logify-wp-object-type-checkboxes input[type="checkbox"]').each((index, element) => {
            let checked = (selectedObjectTypes && typeof selectedObjectTypes === 'object'
                && element.value in selectedObjectTypes)
                ? Boolean(selectedObjectTypes[element.value]) : true;
            $(element).prop('checked', checked);
        });
        // Update state of 'All' checkbox to match state of others.
        $('#logify-wp-show-all-events').prop('checked', allObjectTypesSelected());

        // Start date.
        let startDate = wpCookies.get('start_date');
        $('#logify-wp-start-date').val(startDate);
        $('#logify-wp-end-date').datepicker("option", "minDate", startDate);

        // End date.
        let endDate = wpCookies.get('end_date');
        $('#logify-wp-end-date').val(endDate);
        $('#logify-wp-start-date').datepicker("option", "maxDate", endDate);

        // Post type.
        selectCookieValue('#logify-wp-post-type-filter', 'post_type');

        // Taxonomy.
        selectCookieValue('#logify-wp-taxonomy-filter', 'taxonomy');

        // Event type.
        selectCookieValue('#logify-wp-event-type-filter', 'event_type');

        // User.
        selectCookieValue('#logify-wp-user-filter', 'user_id');

        // Role.
        selectCookieValue('#logify-wp-role-filter', 'role');
    };

    // Setup behavior of object type checkboxes.
    $('.logify-wp-object-type-filter-item input').on('change', function (event) {
        // Cancel the default behavior of the checkbox.
        event.preventDefault();

        // Click the surrounding div.
        $(this).parent().click();
    });

    // Setup behavior of object type containers (i.e. the colored divs wrapping the checkbox and label).
    $('.logify-wp-object-type-filter-item').on('click', function (event) {
        // Toggle the checkbox.
        let checkbox = $(this).find('input[type="checkbox"]');
        let isChecked = checkbox.is(':checked');
        checkbox.prop('checked', !isChecked);
        isChecked = !isChecked;

        // If this is the 'All' checkbox, check or uncheck all others.
        if (checkbox.is('#logify-wp-show-all-events')) {
            if (isChecked) {
                // All is checked, so check all others.
                $('#logify-wp-object-type-checkboxes input').prop('checked', true);
            }
            else {
                // Otherwise, uncheck all others.
                $('#logify-wp-object-type-checkboxes input').prop('checked', false);
            }
        }
        else {
            // Update state of 'All' checkbox to match state of others.
            $('#logify-wp-show-all-events').prop('checked', allObjectTypesSelected());
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
            date = $.datepicker.parseDate(logifyWpLogPage.dateFormat, element.value);
            console.log(date);
        } catch (error) {
            date = null;
        }

        return date;
    };

    // Setup the start datepicker.
    let startDatePicker = $('#logify-wp-start-date').datepicker({
        dateFormat: logifyWpLogPage.dateFormat,
        onSelect: function (date) {
            // console.log('start date selected:', date);

            // Update the cookie.
            wpCookies.set('start_date', date, false, false, false, false);

            // Set the min date of the end datepicker.
            endDatePicker.datepicker("option", "minDate", getDate(this));

            // Reload the table.
            eventsTable.ajax.reload();
        }
    });

    // Setup the end datepicker.
    let endDatePicker = $('#logify-wp-end-date').datepicker({
        dateFormat: logifyWpLogPage.dateFormat,
        onSelect: function (date) {
            // console.log('end date selected:', date);

            // Update the cookie.
            wpCookies.set('end_date', date, false, false, false, false);

            // Set the max date of the start datepicker.
            startDatePicker.datepicker("option", "maxDate", getDate(this));

            // Reload the table.
            eventsTable.ajax.reload();
        }
    });

    // Set up the post type selector.
    $('#logify-wp-post-type-filter').on('change', function () {
        // Update the cookie.
        wpCookies.set('post_type', this.value, false, false, false, false);

        // Reload the table.
        eventsTable.ajax.reload();
    });

    // Set up the taxonomy selector.
    $('#logify-wp-taxonomy-filter').on('change', function () {
        // Update the cookie.
        wpCookies.set('taxonomy', this.value, false, false, false, false);

        // Reload the table.
        eventsTable.ajax.reload();
    });

    // Set up the event type selector.
    $('#logify-wp-event-type-filter').on('change', function () {
        // Update the cookie.
        wpCookies.set('event_type', this.value, false, false, false, false);

        // Reload the table.
        eventsTable.ajax.reload();
    });

    // Set up the user selector.
    $('#logify-wp-user-filter').on('change', function () {
        // Update the cookie.
        wpCookies.set('user_id', this.value, false, false, false, false);

        // Reload the table.
        eventsTable.ajax.reload();
    });

    // Set up the role selector.
    $('#logify-wp-role-filter').on('change', function () {
        // Update the cookie.
        wpCookies.set('role', this.value, false, false, false, false);

        // Reload the table.
        eventsTable.ajax.reload();
    });

    // Set up the button to reset search filters.
    $('#logify-wp-reset-filters').on('click', function () {
        // Clear the search text.
        $('#logify-wp-search-filter').val('');
        wpCookies.set('search', '', false, false, false, false);

        // Clear the object type checkboxes.
        $('#logify-wp-show-all-events').prop('checked', true);
        $('#logify-wp-object-type-checkboxes input').prop('checked', true);
        let selectedObjectTypes = getSelectedObjectTypes();
        wpCookies.set('object_types', JSON.stringify(selectedObjectTypes), false, false, false, false);

        // Clear the start date.
        $('#logify-wp-start-date').val('');
        wpCookies.set('start_date', '', false, false, false, false);

        // Clear the end date.
        $('#logify-wp-end-date').val('');
        wpCookies.set('end_date', '', false, false, false, false);

        // Clear the post type.
        $('#logify-wp-post-type-filter').val('');
        wpCookies.set('post_type', '', false, false, false, false);

        // Clear the taxonomy.
        $('#logify-wp-taxonomy-filter').val('');
        wpCookies.set('taxonomy', '', false, false, false, false);

        // Clear the event type.
        $('#logify-wp-event-type-filter').val('');
        wpCookies.set('event_type', '', false, false, false, false);

        // Clear the user.
        $('#logify-wp-user-filter').val('');
        wpCookies.set('user_id', '', false, false, false, false);

        // Clear the role.
        $('#logify-wp-role-filter').val('');
        wpCookies.set('role', '', false, false, false, false);

        // Reload the table.
        eventsTable.search('');
        eventsTable.ajax.reload();
    });

    // Initialize the search form.
    initSearchForm();

    // Open modal for editing an existing note
    $('#notes-table').on('click', '.edit-note', function () {
        const noteId = parseInt($(this).data('id'), 10); // Ensure numeric value
        const noteContent = $(this).data('note');

        $('#edit-note-id').val(noteId);

        if (tinyMCE.get('edit-note-content')) {
            tinyMCE.get('edit-note-content').setContent(noteContent); // Set content in TinyMCE
        } else {
            $('#edit-note-content').val(noteContent); // Fallback for textarea
        }

        $('#edit-note-modal').dialog('open');
    });

    // Submit form to save or update the note
    $('#edit-note-form').on('submit', function (e) {
        e.preventDefault();

        const noteId = $('#edit-note-id').val();
        const noteContent = tinyMCE.get('edit-note-content')
            ? tinyMCE.get('edit-note-content').getContent() // Get TinyMCE content
            : $('#edit-note-content').val(); // Fallback for textarea

        // Validate content before submitting
        if (!noteContent || noteContent.trim() === '') {
            alert('Note content cannot be empty.');
            return;
        }

        $.ajax({
            url: ajaxurl,
            method: 'POST',
            data: {
                action: noteId ? 'logify_update_notes' : 'logify_add_notes', // Choose action based on note ID
                security: logifyWpLogPage.ajaxNonce,
                note_id: noteId,
                note_content: noteContent,
            },
            success: function (response) {
                if (response.success) {
                    // Display success message
                    $('<div class="success notice updated"><p>Note saved successfully!</p></div>')
                        .appendTo('#edit-note-form')
                        .fadeIn()
                        .delay(2000) // Show the message for 2 seconds
                        .fadeOut(2000, function () {
                            $(this).remove(); // Remove the message after fading out
                            eventsTable.ajax.reload();
                            $('#edit-note-modal').dialog('close');
                        });
                    
                } else {
                    // Display error message inline
                    $('<div id="message" class="error notice updated"><p>Failed to save note: ' + response.data.message + '</p></div>')
                        .appendTo('#edit-note-form')
                        .fadeIn()
                        .delay(2000)
                        .fadeOut(500, function () {
                            $(this).remove();
                        });
                }
            },
        });
    });
    
});
