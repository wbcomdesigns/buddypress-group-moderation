/**
 * BuddyPress Group Moderation - Admin JavaScript
 */
(function($) {
    'use strict';

    /**
     * Handle group approval action.
     */
    $('.bp-group-approve').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var groupId = button.data('group-id');
        var row = $('#group-' + groupId);
        
        // Disable buttons during processing
        button.prop('disabled', true).text(bpGroupModeration.loadingText);
        row.find('.bp-group-reject').prop('disabled', true);
        
        // Send the AJAX request
        $.ajax({
            url: bpGroupModeration.ajaxUrl,
            type: 'POST',
            data: {
                action: 'bp_group_moderation_handle_group',
                action_type: 'approve',
                group_id: groupId,
                nonce: bpGroupModeration.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    $('#bp-group-moderation-messages').html(
                        '<div class="notice notice-success is-dismissible"><p>' + 
                        response.data.message + 
                        '</p></div>'
                    );
                    
                    // Remove the row
                    row.fadeOut(500, function() {
                        $(this).remove();
                        
                        // Check if table is empty
                        if ($('.bp-group-moderation-table tbody tr').length === 0) {
                            $('.bp-group-moderation-table').replaceWith(
                                '<div class="bp-group-moderation-no-items">' +
                                '<p>No pending groups found.</p>' +
                                '</div>'
                            );
                        }
                    });
                } else {
                    // Show error message
                    $('#bp-group-moderation-messages').html(
                        '<div class="notice notice-error is-dismissible"><p>' + 
                        response.data.message + 
                        '</p></div>'
                    );
                    
                    // Reset buttons
                    button.prop('disabled', false).text(bpGroupModeration.approveText);
                    row.find('.bp-group-reject').prop('disabled', false);
                }
            },
            error: function() {
                // Show error message
                $('#bp-group-moderation-messages').html(
                    '<div class="notice notice-error is-dismissible"><p>' + 
                    'An error occurred while processing the request.' + 
                    '</p></div>'
                );
                
                // Reset buttons
                button.prop('disabled', false).text(bpGroupModeration.approveText);
                row.find('.bp-group-reject').prop('disabled', false);
            }
        });
    });
    
    /**
     * Handle group rejection action.
     */
    $('.bp-group-reject').on('click', function(e) {
        e.preventDefault();
        
        var button = $(this);
        var groupId = button.data('group-id');
        var row = $('#group-' + groupId);
        
        // Confirm the rejection
        if (!confirm(bpGroupModeration.confirmReject)) {
            return;
        }
        
        // Disable buttons during processing
        button.prop('disabled', true).text(bpGroupModeration.loadingText);
        row.find('.bp-group-approve').prop('disabled', true);
        
        // Send the AJAX request
        $.ajax({
            url: bpGroupModeration.ajaxUrl,
            type: 'POST',
            data: {
                action: 'bp_group_moderation_handle_group',
                action_type: 'reject',
                group_id: groupId,
                nonce: bpGroupModeration.nonce
            },
            success: function(response) {
                if (response.success) {
                    // Show success message
                    $('#bp-group-moderation-messages').html(
                        '<div class="notice notice-success is-dismissible"><p>' + 
                        response.data.message + 
                        '</p></div>'
                    );
                    
                    // Remove the row
                    row.fadeOut(500, function() {
                        $(this).remove();
                        
                        // Check if table is empty
                        if ($('.bp-group-moderation-table tbody tr').length === 0) {
                            $('.bp-group-moderation-table').replaceWith(
                                '<div class="bp-group-moderation-no-items">' +
                                '<p>No pending groups found.</p>' +
                                '</div>'
                            );
                        }
                    });
                } else {
                    // Show error message
                    $('#bp-group-moderation-messages').html(
                        '<div class="notice notice-error is-dismissible"><p>' + 
                        response.data.message + 
                        '</p></div>'
                    );
                    
                    // Reset buttons
                    button.prop('disabled', false).text(bpGroupModeration.rejectText);
                    row.find('.bp-group-approve').prop('disabled', false);
                }
            },
            error: function() {
                // Show error message
                $('#bp-group-moderation-messages').html(
                    '<div class="notice notice-error is-dismissible"><p>' + 
                    'An error occurred while processing the request.' + 
                    '</p></div>'
                );
                
                // Reset buttons
                button.prop('disabled', false).text(bpGroupModeration.rejectText);
                row.find('.bp-group-approve').prop('disabled', false);
            }
        });
    });
    
    /**
     * Make notices dismissible.
     */
    $(document).on('click', '.notice-dismiss', function() {
        $(this).closest('.notice').fadeOut(300, function() {
            $(this).remove();
        });
    });

})(jQuery);