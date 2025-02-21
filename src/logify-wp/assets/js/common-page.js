jQuery(($) => {
    // Initialize the modal with TinyMCE integration
    $('#edit-note-modal').dialog({
        autoOpen: false,
        modal: true,
        width: 500,
        open: function () {
            // Initialize TinyMCE if not already initialized
            if (!tinyMCE.get('edit-note-content')) {
                wp.editor.initialize('edit-note-content', {
                    tinymce: {
                        toolbar1: 'bold italic bullist numlist link undo redo',
                        plugins: 'link lists',
                        menubar: false,
                        default_link_target: "_blank", // Links open in a new tab
                    },
                    quicktags: true,
                });

                // Remove "required" attribute from the hidden textarea
                $('#edit-note-content').removeAttr('required');
            }
        },
        close: function () {
            // Remove TinyMCE instance on modal close
            if (tinyMCE.get('edit-note-content')) {
                wp.editor.remove('edit-note-content');
            }
        },
    });

    // Function to open and prepare the modal
    function openNoteModal(noteId = '', eventId = '', noteContent = '') {
        // Set note and event IDs
        $('#edit-note-id').val(noteId);
        $('#edit-event-id').val(eventId);

        // Set content for the editor or fallback to textarea
        if (tinyMCE.get('edit-note-content')) {
            tinyMCE.get('edit-note-content').setContent(noteContent);
            setTimeout(() => tinyMCE.get('edit-note-content').focus(), 500); // Focus TinyMCE editor
        } else {
            $('#edit-note-content').val(noteContent); // Set textarea content
            setTimeout(() => tinyMCE.get('edit-note-content').focus(), 500); // Focus TinyMCE editor
        }

        // Open the modal dialog
        $('#edit-note-modal').dialog('open');
    }

    // Open modal for adding a new note
    $('#logify-wp-add-note').on('click', function () {
        openNoteModal(); // No IDs or content for a new note
    });

    // Open modal for editing an existing note
    $('#logify-wp-activity-log').on('click', '.edit-note-link', function (e) {
        e.preventDefault();

        // Extract data attributes from the clicked element
        const noteId = $(this).data('note-id') || '';
        const eventId = $(this).data('event-id') || '';
        const noteContent = $(this).data('note-content') || '';

        openNoteModal(noteId, eventId, noteContent); // Populate modal with existing note data
    });

});
