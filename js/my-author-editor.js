jQuery(document).ready(function($) {
    // Declared at this scope so they persist and can be re-opened
    var authorPhotoUploader;
    // Removed otherImageUploader

    // Function to initialize TinyMCE editor (if not already initialized)
    function initTinyMCE(selector) {
        if (typeof tinymce !== 'undefined' && tinymce.editors[selector]) {
            tinymce.execCommand('mceRemoveEditor', false, selector);
            tinymce.execCommand('mceAddEditor', false, selector);
        } else if (typeof tinymce !== 'undefined' && !tinymce.editors[selector]) {
            tinymce.execCommand('mceAddEditor', false, selector);
        }
    }

    // Function to load author content via AJAX
    $('#load-author-content-btn').on('click', function(e) {
        e.preventDefault();
        var selectedAuthorId = $('#selected_author_id').val();

        if (!selectedAuthorId) {
            alert(myAuthorEditorAjax.alert_no_author_selected);
            return;
        }

        // Disable elements during load to prevent multiple submissions
        $(this).text('Loading...').prop('disabled', true);
        $('#author-editor-form').find('input, select, button, textarea').not('#load-author-content-btn').prop('disabled', true);
        if (tinymce.get('author_content')) {
            tinymce.get('author_content').setMode('readonly'); // Disable TinyMCE editor
        }


        $.ajax({
            url: myAuthorEditorAjax.ajaxurl,
            type: 'POST',
            data: {
                action: 'my_author_editor_get_author_content',
                nonce: myAuthorEditorAjax.nonce,
                author_id: selectedAuthorId
            },
            success: function(response) {
                if (response.success) {
                    var data = response.data;

                    // Populate form fields
                    $('#author_post_id').val(data.author_post_id || 0); // Important for update logic
                    $('#new_author_name').val(''); // Clear new author name field when existing author is selected
                    $('#author_subtitle').val(data.author_subtitle);

                    // Set content in TinyMCE
                    if (tinymce.get('author_content')) {
                        tinymce.get('author_content').setContent(data.author_content || '');
                        tinymce.get('author_content').setMode('design'); // Re-enable TinyMCE editor
                    } else {
                        $('#author_content').val(data.author_content || '');
                    }

                    // Handle Author Photo
                    if (data.author_photo_id && data.author_photo_url) {
                        $('#author_photo_id').val(data.author_photo_id);
                        $('#author_photo_url').val(data.author_photo_url);
                        $('#author-image-preview img').attr('src', data.author_photo_url);
                        $('#author-image-preview').show();
                    } else {
                        $('#author_photo_id').val(0);
                        $('#author_photo_url').val('');
                        $('#author-image-preview img').attr('src', '');
                        $('#author-image-preview').hide();
                    }

                    // Handle new Footer and Insert Link fields
                    $('#footer_position').val(data.footer_position || '');
                    $('#insert_link').val(data.insert_link || '');

                    // Show message if content not found (e.g., creating new content for an existing author)
                    if (data.message && data.author_post_id === 0) {
                        console.log(data.message);
                    }

                } else {
                    alert(response.data.message || 'Error loading author content. Please try again.');
                    // Clear form fields on error
                    $('#author_post_id').val(0);
                    $('#new_author_name').val('');
                    $('#author_subtitle').val('');
                    if (tinymce.get('author_content')) {
                        tinymce.get('author_content').setContent('');
                    } else {
                        $('#author_content').val('');
                    }
                    $('#author_photo_id').val(0);
                    $('#author_photo_url').val('');
                    $('#author-image-preview img').attr('src', '');
                    $('#author-image-preview').hide();
                    // Clear new footer fields on error
                    $('#footer_position').val('');
                    $('#insert_link').val('');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("AJAX Error: ", textStatus, errorThrown, jqXHR.responseText);
                alert('An error occurred while communicating with the server. Please check the console for details.');
            },
            complete: function() {
                // Re-enable elements
                $('#load-author-content-btn').text('Submit').prop('disabled', false);
                $('#author-editor-form').find('input, select, button, textarea').prop('disabled', false);
                // TinyMCE re-enabled in success block
            }
        });
    });

    // --- Media Uploader for Author Photo (Patterned after your successful Featured Image) ---
    $('.browse-author-image').on('click', function(e) {
        e.preventDefault();

        // If the uploader already exists, open it
        if (authorPhotoUploader) {
            authorPhotoUploader.open();
            return;
        }

        // Extend the wp.media object
        authorPhotoUploader = wp.media({
            title: 'Select Author Photo',
            button: {
                text: 'Use this image'
            },
            multiple: false // Only allow selection of a single image
        });

        // When a file is selected, grab the URL and ID and set it to the input fields
        authorPhotoUploader.on('select', function() {
            var attachment = authorPhotoUploader.state().get('selection').first().toJSON();
            $('#author_photo_url').val(attachment.url);
            $('#author_photo_id').val(attachment.id);
            $('#author-image-preview img').attr('src', attachment.url);
            $('#author-image-preview').show();
        });

        // Open the uploader dialog
        authorPhotoUploader.open();
    });

    // Handle "Remove Image" button click for Author Photo
    $('.remove-author-image').on('click', function(e) {
        e.preventDefault();
        $('#author_photo_url').val('');
        $('#author_photo_id').val('');
        $('#author-image-preview img').attr('src', '');
        $('#author-image-preview').hide();
    });

    // Removed the "Other Image" uploader and its handlers.

    // Handle form submission (for actions like delete, publish, save)
    $('#author-editor-form').on('submit', function(e) {
        // Ensure TinyMCE content is saved back to the textarea before submission
        if (typeof tinyMCE !== 'undefined' && tinyMCE.activeEditor && !tinyMCE.activeEditor.isHidden()) {
            tinyMCE.activeEditor.save();
        }

        var selectedAction = $('#submit_author_action_select').val();
        var authorPostId = $('#author_post_id').val();
        var selectedAuthorId = $('#selected_author_id').val();
        var newAuthorName = $('#new_author_name').val().trim();

        // If no author selected AND no new author name entered, prevent submission
        if (!selectedAuthorId && !newAuthorName) {
            alert('Please select an author or enter a new author name.');
            e.preventDefault();
            return;
        }

        // Confirmation for delete action
        if (selectedAction === 'delete') {
            if (!authorPostId || authorPostId === '0') {
                alert(myAuthorEditorAjax.alert_content_not_found);
                e.preventDefault();
                return;
            }
            if (!confirm(myAuthorEditorAjax.alert_confirm_delete)) {
                e.preventDefault();
            }
        }
    });

    // Initialize TinyMCE for the editor when the page loads
    initTinyMCE('author_content');
});