/**
 * BuddyPress Group Moderation - Admin JavaScript
 */
(function($) {
    'use strict';

    // Debugging function
    function log(message, data = null) {
        if (window.console && window.console.log) {
            console.log('BP Group Moderation: ' + message);
            if (data !== null) {
                console.log(data);
            }
        }
    }

    // Common function for handling group actions
    function handleGroupAction(button, actionType) {
        var groupId = button.data('group-id');
        var row = $('#group-' + groupId);

        log('Action initiated: ' + actionType + ' for group ' + groupId);

        // Construct the payload
        var ajaxPayload = {
            action: 'bp_group_moderation_handle_group',
            action_type: actionType,
            group_id: groupId,
            nonce: bpGroupModeration.nonce
        };

        // Log full request details
        log('AJAX URL:', bpGroupModeration.ajaxUrl);
        log('AJAX Payload:', ajaxPayload);

        // Disable buttons during processing
        button.prop('disabled', true).text(bpGroupModeration.loadingText);
        row.find('.bp-group-' + (actionType === 'approve' ? 'reject' : 'approve')).prop('disabled', true);

        // Send the AJAX request
        $.ajax({
            url: bpGroupModeration.ajaxUrl,
            type: 'POST',
            data: ajaxPayload,
            success: function(response) {
                log('AJAX Success:', response);

                if (response.success) {
                    $('#bp-group-moderation-messages').html(
                        '<div class="notice notice-success is-dismissible"><p>' +
                        response.data.message +
                        '</p></div>'
                    );

                    row.fadeOut(500, function() {
                        $(this).remove();

                        if ($('.bp-group-moderation-table tbody tr').length === 0) {
                            $('.bp-group-moderation-table').replaceWith(
                                '<div class="bp-group-moderation-no-items">' +
                                '<p>No pending groups found.</p>' +
                                '</div>'
                            );
                        }
                    });
                } else {
                    $('#bp-group-moderation-messages').html(
                        '<div class="notice notice-error is-dismissible"><p>' +
                        response.data.message +
                        '</p></div>'
                    );

                    button.prop('disabled', false).text(
                        actionType === 'approve'
                            ? bpGroupModeration.approveText
                            : bpGroupModeration.rejectText
                    );
                    row.find('.bp-group-' + (actionType === 'approve' ? 'reject' : 'approve')).prop('disabled', false);
                }
            },
            error: function(xhr, status, error) {
                log('AJAX Error: ' + status + ' - ' + error);
                log('AJAX Response Text:', xhr.responseText);

                $('#bp-group-moderation-messages').html(
                    '<div class="notice notice-error is-dismissible"><p>' +
                    'An error occurred while processing the request: ' + error +
                    '</p><pre>' + xhr.responseText + '</pre></div>'
                );

                button.prop('disabled', false).text(
                    actionType === 'approve'
                        ? bpGroupModeration.approveText
                        : bpGroupModeration.rejectText
                );
                row.find('.bp-group-' + (actionType === 'approve' ? 'reject' : 'approve')).prop('disabled', false);
            }
        });
    }

    // Use event delegation for approve button
    $(document).on('click', '.bp-group-approve', function(e) {
        e.preventDefault();
        handleGroupAction($(this), 'approve');
    });

    // Use event delegation for reject button
    $(document).on('click', '.bp-group-reject', function(e) {
        e.preventDefault();

        if (!confirm(bpGroupModeration.confirmReject)) {
            return;
        }

        handleGroupAction($(this), 'reject');
    });

    // Make notices dismissible
    $(document).on('click', '.notice-dismiss', function() {
        $(this).closest('.notice').fadeOut(300, function() {
            $(this).remove();
        });
    });

    // Log initialization
    log('Admin JavaScript Initialized');
    log('Localized Object:', bpGroupModeration);

    // FAQ Accordion  
    var bpolls_elmt = document.getElementsByClassName( "wbcom-faq-accordion" );
    var k;
    var bpolls_elmt_len = bpolls_elmt.length;
    for (k = 0; k < bpolls_elmt_len; k++) {
        bpolls_elmt[k].onclick = function() {
        this.classList.toggle( "active" );
        var panel = this.nextElementSibling;
        if (panel.style.maxHeight) {
            panel.style.maxHeight = null;
        } else {
            panel.style.maxHeight = panel.scrollHeight + "px";
        }
        }
    }

})(jQuery);
