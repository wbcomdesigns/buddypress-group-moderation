/**
 * BuddyPress Group Moderation - Pending Groups screen.
 */
(function ($) {
	'use strict';

	var l10n = window.bpGroupModeration || {};

	// One notice at a time, text only (never markup from the response).
	function notice(type, text) {
		var $p = $('<p/>').text(text);
		$('#bp-group-moderation-messages').empty().append(
			$('<div/>', { 'class': 'notice notice-' + type, role: 'alert' }).append($p)
		);
	}

	function rowsLeft() {
		return $('.bp-group-moderation-table tbody tr').length;
	}

	function afterRowRemoved() {
		if (rowsLeft() > 0) {
			return;
		}
		if ($('.tablenav-pages .page-numbers').length) {
			// More pending groups on other pages: reload page one to pull them in.
			var url = new URL(window.location.href);
			url.searchParams.delete('paged');
			window.location.assign(url.toString());
			return;
		}
		$('.bp-group-moderation-table, .tablenav.top').remove();
		$('.bp-group-moderation-no-items').prop('hidden', false);
	}

	// Keep the menu badge and the list total in step with the server after each decision.
	function updateCounts(data) {
		if (!data || typeof data.pending !== 'number') {
			return;
		}
		var $badge = $('#adminmenu a[href$="page=bp-pending-groups"] .awaiting-mod');
		if (data.pending > 0) {
			$badge.attr('class', 'awaiting-mod count-' + data.pending).find('.pending-count').text(data.pending);
		} else {
			$badge.remove();
		}
		$('.tablenav-pages .displaying-num').text(data.pendingText);
	}

	function setBusy($row, busy) {
		$row.find('button').prop('disabled', busy);
		$row.attr('aria-busy', busy ? 'true' : 'false');
	}

	function handleGroupAction($row, actionType, reason) {
		var groupId = $row.attr('id').replace('group-', '');
		var $status = $row.find('.bp-group-' + (actionType === 'approve' ? 'approve' : 'reject-confirm-yes'));
		var label = $status.text();

		setBusy($row, true);
		$status.text(l10n.loadingText);

		return $.post(l10n.ajaxUrl, {
			action: 'bp_group_moderation_handle_group',
			action_type: actionType,
			group_id: groupId,
			reason: reason || '',
			nonce: l10n.nonce
		}).done(function (response) {
			if (response && response.success) {
				notice('success', response.data.message);
				updateCounts(response.data);
				$row.fadeOut(300, function () {
					$(this).remove();
					afterRowRemoved();
				});
				return;
			}
			notice('error', (response && response.data && response.data.message) || l10n.errorText);
			$status.text(label);
			setBusy($row, false);
		}).fail(function (xhr) {
			var json = xhr && xhr.responseJSON;
			notice('error', (json && json.data && json.data.message) || l10n.errorText);
			$status.text(label);
			setBusy($row, false);
		});
	}

	$(document).on('click', '.bp-group-approve', function (e) {
		e.preventDefault();
		handleGroupAction($(this).closest('tr'), 'approve');
	});

	// Bulk approve: the per-row approve for every ticked row, one after another.
	$(document).on('click', '.bp-group-approve-selected', function (e) {
		e.preventDefault();
		var $rows = $('.bp-group-moderation-table .bp-group-select:checked').closest('tr');
		if (!$rows.length) {
			notice('warning', l10n.selectText);
			return;
		}
		$rows.toArray().reduce(function (chain, row) {
			return chain.then(function () {
				return handleGroupAction($(row), 'approve');
			}, function () {
				return handleGroupAction($(row), 'approve');
			});
		}, $.Deferred().resolve().promise());
	});

	// Reject opens an inline confirm with an optional reason.
	$(document).on('click', '.bp-group-reject', function (e) {
		e.preventDefault();
		var $row = $(this).closest('tr');
		$row.find('.bp-group-reject-confirm').prop('hidden', false);
		$(this).attr('aria-expanded', 'true');
		$row.find('.bp-group-reject-reason').trigger('focus');
	});

	$(document).on('click', '.bp-group-reject-cancel', function (e) {
		e.preventDefault();
		var $row = $(this).closest('tr');
		$row.find('.bp-group-reject-confirm').prop('hidden', true);
		$row.find('.bp-group-reject').attr('aria-expanded', 'false').trigger('focus');
	});

	$(document).on('keydown', '.bp-group-reject-confirm', function (e) {
		if (e.key === 'Escape') {
			$(this).find('.bp-group-reject-cancel').trigger('click');
		}
	});

	$(document).on('click', '.bp-group-reject-confirm-yes', function (e) {
		e.preventDefault();
		var $row = $(this).closest('tr');
		handleGroupAction($row, 'reject', $row.find('.bp-group-reject-reason').val());
	});
})(jQuery);
