/**
 * BuddyPress Group Moderation - Admin JavaScript
 */
(function($) {
    'use strict';

    // Common function for handling group actions
    function handleGroupAction(button, actionType) {
        var groupId = button.data('group-id');
        var row = $('#group-' + groupId);


        // Construct the payload
        var ajaxPayload = {
            action: 'bp_group_moderation_handle_group',
            action_type: actionType,
            group_id: groupId,
            nonce: bpGroupModeration.nonce
        };


        // Disable buttons during processing
        button.prop('disabled', true).text(bpGroupModeration.loadingText);
        row.find('.bp-group-' + (actionType === 'approve' ? 'reject' : 'approve')).prop('disabled', true);

        // Send the AJAX request
        $.ajax({
            url: bpGroupModeration.ajaxUrl,
            type: 'POST',
            data: ajaxPayload,
            success: function(response) {

                if (response.success) {
                    $('#bp-group-moderation-messages').html(
                        '<div class="notice notice-success is-dismissible"><p>' +
                        response.data.message +
                        '</p></div>'
                    );

                    row.fadeOut(500, function() {
                        $(this).remove();

                        if ($('.bp-group-moderation-table tbody tr').length === 0 && $('.tablenav-pages .page-numbers').length) {
                            // More pending groups on other pages: reload to pull the next batch in.
                            window.location.reload();
                        } else if ($('.bp-group-moderation-table tbody tr').length === 0) {
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

    // Bulk approve: run the existing per-row approve for every ticked row.
    $(document).on('click', '.bp-group-approve-selected', function(e) {
        e.preventDefault();
        $('.bp-group-moderation-table .bp-group-select:checked').each(function() {
            handleGroupAction($('#group-' + $(this).val()).find('.bp-group-approve'), 'approve');
        });
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

})(jQuery);
