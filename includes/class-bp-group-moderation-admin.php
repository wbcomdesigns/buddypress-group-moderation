<?php
/**
 * Admin class for BuddyPress Group Moderation.
 *
 * @package BuddyPress_Group_Moderation
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for admin functionality.
 */
class BP_Group_Moderation_Admin {

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Get the singleton instance of this class.
	 *
	 * @return object A single instance of this class.
	 */
	public static function get_instance() {
		// If the single instance hasn't been set, set it now.
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the class.
	 */
	private function __construct() {
		// Admin hooks.
		add_action( 'bp_admin_menu', array( $this, 'bp_group_moderation_add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'bp_group_moderation_admin_enqueue_scripts' ) );

		// AJAX handlers.
		add_action( 'wp_ajax_bp_group_moderation_handle_group', array( $this, 'bp_group_moderation_ajax_handle_group' ) );
	}

	/**
	 * Add admin menu for managing pending groups.
	 */
	public function bp_group_moderation_add_admin_menu() {
		$pending_count = current_user_can( 'manage_options' ) ? count( BP_Group_Moderation::bp_group_moderation_get_pending_group_ids() ) : 0;
		$menu_title    = __( 'Pending Groups', 'buddypress-group-moderation' );
		if ( $pending_count ) {
			$menu_title .= sprintf( ' <span class="awaiting-mod count-%1$d"><span class="pending-count">%2$s</span></span>', $pending_count, number_format_i18n( $pending_count ) );
		}

		add_submenu_page(
			'bp-groups',
			__( 'Pending Groups', 'buddypress-group-moderation' ),
			$menu_title,
			'manage_options',
			'bp-pending-groups',
			array( $this, 'bp_group_moderation_admin_page_content' )
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook The current admin page.
	 */
	public function bp_group_moderation_admin_enqueue_scripts( $hook ) {
		if ( strpos( $hook, 'bp-pending-groups' ) === false && strpos( $hook, 'bp-group-moderation' ) === false && 'toplevel_page_wbcomplugins' !== $hook ) {
			return;
		}

		$rtl_css = is_rtl() ? '-rtl' : '';

		if ( defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ) {
			$css_extension = '.css';
			$js_extension  = '.js';
		} else {
			$css_extension = '.min.css';
			$js_extension  = '.min.js';
		}

		wp_enqueue_style( 'bp-group-moderation-admin', BP_GROUP_MODERATION_PLUGIN_URL . 'assets/css' . $rtl_css . '/bp-group-moderation-admin' . $css_extension, array(), BP_GROUP_MODERATION_VERSION );

		wp_enqueue_script(
			'bp-group-moderation-admin',
			BP_GROUP_MODERATION_PLUGIN_URL . 'assets/js/bp-group-moderation-admin' . $js_extension,
			array( 'jquery' ),
			BP_GROUP_MODERATION_VERSION,
			true
		);

		wp_localize_script(
			'bp-group-moderation-admin',
			'bpGroupModeration',
			array(
				'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
				'nonce'       => wp_create_nonce( 'bp_group_moderation_nonce' ),
				'loadingText' => __( 'Processing...', 'buddypress-group-moderation' ),
				'errorText'   => __( 'The request could not be completed. Reload the page and try again.', 'buddypress-group-moderation' ),
				'selectText'  => __( 'Tick at least one group to approve.', 'buddypress-group-moderation' ),
			)
		);
	}

	/**
	 * Handle AJAX requests for group moderation actions.
	 */
	public function bp_group_moderation_ajax_handle_group() {
		check_ajax_referer( 'bp_group_moderation_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'buddypress-group-moderation' ) ), 403 );
		}

		$group_id = isset( $_POST['group_id'] ) ? absint( $_POST['group_id'] ) : 0;
		$action   = isset( $_POST['action_type'] ) ? sanitize_key( wp_unslash( $_POST['action_type'] ) ) : '';
		$reason   = isset( $_POST['reason'] ) ? mb_substr( sanitize_textarea_field( wp_unslash( $_POST['reason'] ) ), 0, 500 ) : '';

		if ( ! $group_id || ! in_array( $action, array( 'approve', 'reject' ), true ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'buddypress-group-moderation' ) ), 400 );
		}

		// Another admin may have handled it already, or the group was deleted.
		if ( ! BP_Group_Moderation::bp_group_moderation_is_pending( $group_id ) ) {
			wp_send_json_success(
				array(
					'message' => __( 'This group was already approved or rejected.', 'buddypress-group-moderation' ),
					'handled' => true,
				)
			);
		}

		if ( 'approve' === $action ) {
			$result  = $this->bp_group_moderation_approve_group( $group_id );
			$message = __( 'Group approved successfully.', 'buddypress-group-moderation' );
		} else {
			$result  = $this->bp_group_moderation_reject_group( $group_id, $reason );
			$message = __( 'Group has been rejected.', 'buddypress-group-moderation' );
		}

		if ( $result ) {
			wp_send_json_success( array( 'message' => $message ) );
		}

		wp_send_json_error( array( 'message' => __( 'An error occurred while processing the request.', 'buddypress-group-moderation' ) ), 500 );
	}

	/**
	 * Approve a pending group: it gets the type its creator asked for.
	 *
	 * @param int $group_id The group ID.
	 * @return bool Success status.
	 */
	public function bp_group_moderation_approve_group( $group_id ) {
		$group = groups_get_group( $group_id );
		if ( empty( $group->id ) ) {
			return false;
		}

		$requested = groups_get_groupmeta( $group->id, 'requested_status', true );

		// Clear the pending flag first, so the save guard lets the new status through.
		groups_delete_groupmeta( $group->id, 'approval_status' );
		BP_Group_Moderation::bp_group_moderation_flush_pending_ids();

		if ( '' !== BP_Group_Moderation::bp_group_moderation_status_label( $requested ) && $requested !== $group->status ) {
			$group->status = $requested;
			if ( ! $group->save() ) {
				groups_update_groupmeta( $group->id, 'approval_status', 'pending' );
				BP_Group_Moderation::bp_group_moderation_flush_pending_ids();
				return false;
			}
		}

		// The admins' "pending approval" notifications are out of date now.
		if ( bp_is_active( 'notifications' ) ) {
			bp_notifications_delete_all_notifications_by_type( $group->id, 'groups', 'new_group_pending' );
		}

		$this->bp_group_moderation_notify_user_of_group_decision( $group, 'approved' );

		return true;
	}

	/**
	 * Reject a pending group: the group is deleted and its creator is told why.
	 *
	 * @param int    $group_id The group ID.
	 * @param string $reason   Optional reason for the creator.
	 * @return bool Success status.
	 */
	public function bp_group_moderation_reject_group( $group_id, $reason = '' ) {
		$group = groups_get_group( $group_id );
		if ( empty( $group->id ) ) {
			return false;
		}

		// The notification outlives the group, so keep its name for the notification text.
		if ( $group->creator_id ) {
			update_user_meta( $group->creator_id, 'bp_grp_moderation_rejected_group_' . $group->id, $group->name );
		}

		if ( ! groups_delete_group( $group->id ) ) {
			return false;
		}
		BP_Group_Moderation::bp_group_moderation_flush_pending_ids();

		// After the delete, which removes every notification about the group, so this one stays.
		$this->bp_group_moderation_notify_user_of_group_decision( $group, 'rejected', $reason );

		return true;
	}

	/**
	 * Notify a user about their group's approval status.
	 *
	 * @param BP_Groups_Group $group    The group object.
	 * @param string          $decision 'approved' or 'rejected'.
	 * @param string          $reason   Optional rejection reason, sent in the email.
	 */
	public function bp_group_moderation_notify_user_of_group_decision( $group, $decision, $reason = '' ) {
		if ( ! in_array( $decision, array( 'approved', 'rejected' ), true ) ) {
			return;
		}

		$creator_id = (int) $group->creator_id;

		if ( bp_is_active( 'notifications' ) ) {
			bp_notifications_add_notification(
				array(
					'user_id'           => $creator_id,
					'item_id'           => $group->id,
					'secondary_item_id' => $creator_id,
					'component_name'    => 'groups',
					'component_action'  => 'group_' . $decision,
					'date_notified'     => bp_core_current_time(),
					'is_new'            => 1,
				)
			);
		}

		$creator = get_userdata( $creator_id );
		if ( ! $creator || ! bp_group_moderation_fetch_settings( 'bp_group_moderation_send_emails' ) ) {
			return;
		}

		if ( 'approved' === $decision ) {
			/* translators: %s: group name. */
			$subject = sprintf( __( 'Your Group Has Been Approved : %s', 'buddypress-group-moderation' ), $group->name );
			$message = sprintf(
				/* translators: 1: member name, 2: group name, 3: group URL, 4: group URL as text, 5: site name. */
				__(
					"<p><strong>Dear %1\$s</strong></p>
					<p>We’re happy to let you know that your group, <strong>“%2\$s”</strong>, has been reviewed and approved by our moderation team!</p>
					<p>Your group is now live and visible to the community. You can start inviting members, sharing updates, and building discussions right away.</p>
					<p><strong>View your group : </strong></p>
					<p><a href='%3\$s'>%4\$s</a></p>
					<p>Thank you for contributing to our community. If you have any questions or need help managing your group, feel free to reach out to us.</p>
					<p><strong>Warm Regards,</strong></p>
					<p><strong>The %5\$s Team</strong></p>",
					'buddypress-group-moderation'
				),
				esc_html( bp_core_get_user_displayname( $creator_id ) ),
				esc_html( $group->name ),
				esc_url( BP_Group_Moderation::bp_group_moderation_get_group_url( $group ) ),
				esc_html( BP_Group_Moderation::bp_group_moderation_get_group_url( $group ) ),
				esc_html( get_bloginfo( 'name' ) )
			);
		} else {
			$reason_html = '';
			if ( '' !== $reason ) {
				/* translators: %s: the administrator's reason for rejecting the group. */
				$reason_html = '<p>' . sprintf( __( '<strong>Reason from the moderation team:</strong> %s', 'buddypress-group-moderation' ), nl2br( esc_html( $reason ) ) ) . '</p>';
			}

			/* translators: %s: group name. */
			$subject = sprintf( __( 'Group Submission Not Approved : %s', 'buddypress-group-moderation' ), $group->name );
			$message = sprintf(
				/* translators: 1: member name, 2: group name, 3: reason paragraph (may be empty), 4: site name. */
				__(
					'<p><strong>Dear %1$s</strong></p>
					<p>Thank you for submitting your group, <strong>“%2$s”</strong>, to our community platform.</p>
					<p>After a thorough review, we regret to inform you that your group submission has not been approved by our moderation team at this time.</p>
					%3$s
					<p>If you would like feedback on your submission or wish to explore how it could be revised to meet our community guidelines, please feel free to contact our support team.</p>
					<p>We truly appreciate your involvement and hope you’ll continue to be an engaged member of our community.</p>
					<p><strong>Warm regards,</strong></p>
					<p><strong>The %4$s Team</strong></p>',
					'buddypress-group-moderation'
				),
				esc_html( bp_core_get_user_displayname( $creator_id ) ),
				esc_html( $group->name ),
				$reason_html,
				esc_html( get_bloginfo( 'name' ) )
			);
		}

		$subject = apply_filters( 'bp_group_moderation_group_decision_mail_subject', $subject );
		$message = apply_filters( 'bp_group_moderation_group_decision_mail', $message, $group, $decision, $reason );

		wp_mail( $creator->user_email, $subject, $message, array( 'Content-Type: text/html; charset=UTF-8' ) );
	}

	/**
	 * Render the admin page content for managing pending groups.
	 */
	public function bp_group_moderation_admin_page_content() {
		// Opened from a notification: mark it read (ownership checked there). Never blocks the page.
		BP_Group_Moderation_Notifications::bp_group_moderation_mark_read_from_request();

		$per_page       = 20;
		$paged          = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page number.
		$pending        = $this->bp_group_moderation_get_pending_groups( $paged, $per_page );
		$pending_groups = $pending['groups'];

		// One users query for the page instead of get_userdata() per row.
		cache_users( wp_list_pluck( $pending_groups, 'creator_id' ) );
		$reject_help = bp_group_moderation_fetch_settings( 'bp_group_moderation_send_emails' )
			? __( 'The group is deleted. This cannot be undone. The reason is sent in the email to the creator.', 'buddypress-group-moderation' )
			: __( 'The group is deleted. This cannot be undone. Email notifications are off, so the creator only gets the on-site notification, without the reason.', 'buddypress-group-moderation' );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Pending Groups', 'buddypress-group-moderation' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bp-group-moderation' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Settings', 'buddypress-group-moderation' ); ?></a>
			<hr class="wp-header-end">

			<div id="bp-group-moderation-messages" aria-live="polite"></div>

				<?php if ( ! empty( $pending_groups ) ) : ?>
					<div class="tablenav top">
						<div class="alignleft actions bulkactions">
							<button type="button" class="button bp-group-approve-selected"><?php esc_html_e( 'Approve selected', 'buddypress-group-moderation' ); ?></button>
						</div>
						<div class="tablenav-pages">
							<span class="displaying-num">
								<?php
								/* translators: %s: number of pending groups. */
								echo esc_html( sprintf( _n( '%s pending group', '%s pending groups', $pending['total'], 'buddypress-group-moderation' ), number_format_i18n( $pending['total'] ) ) );
								?>
							</span>
							<?php
							echo wp_kses_post(
								(string) paginate_links(
									array(
										'base'      => add_query_arg( 'paged', '%#%' ),
										'format'    => '',
										'current'   => $paged,
										'total'     => (int) ceil( $pending['total'] / $per_page ),
										'prev_text' => '&lsaquo;',
										'next_text' => '&rsaquo;',
									)
								)
							);
							?>
						</div>
					</div>

					<table class="wp-list-table widefat fixed striped bp-group-moderation-table">
						<thead>
							<tr>
								<td class="manage-column column-cb check-column"><input type="checkbox" aria-label="<?php esc_attr_e( 'Select all pending groups', 'buddypress-group-moderation' ); ?>"></td>
								<th scope="col"><?php esc_html_e( 'Group Name', 'buddypress-group-moderation' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Creator', 'buddypress-group-moderation' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Created', 'buddypress-group-moderation' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Requested Type', 'buddypress-group-moderation' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Actions', 'buddypress-group-moderation' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							foreach ( $pending_groups as $group ) :
								$creator   = get_userdata( $group->creator_id );
								$requested = BP_Group_Moderation::bp_group_moderation_status_label( groups_get_groupmeta( $group->id, 'requested_status', true ) );
								?>
							<tr id="group-<?php echo esc_attr( $group->id ); ?>">
								<th scope="row" class="check-column"><input type="checkbox" class="bp-group-select" value="<?php echo esc_attr( $group->id ); ?>" aria-label="<?php echo esc_attr( $group->name ); ?>"></th>
								<td data-label="<?php esc_attr_e( 'Group Name', 'buddypress-group-moderation' ); ?>">
									<a href="<?php echo esc_url( BP_Group_Moderation::bp_group_moderation_get_group_url( $group ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $group->name ); ?></a>
								</td>
								<td data-label="<?php esc_attr_e( 'Creator', 'buddypress-group-moderation' ); ?>">
									<?php if ( $creator ) : ?>
										<a href="<?php echo esc_url( bp_members_get_user_url( $group->creator_id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $creator->display_name ); ?></a>
									<?php else : ?>
										<?php esc_html_e( 'Deleted user', 'buddypress-group-moderation' ); ?>
									<?php endif; ?>
								</td>
								<td data-label="<?php esc_attr_e( 'Created', 'buddypress-group-moderation' ); ?>"><?php echo esc_html( bp_core_time_since( $group->date_created ) ); ?></td>
								<td data-label="<?php esc_attr_e( 'Requested Type', 'buddypress-group-moderation' ); ?>"><?php echo esc_html( $requested ); ?></td>
								<td data-label="<?php esc_attr_e( 'Actions', 'buddypress-group-moderation' ); ?>">
									<div class="bp-group-moderation-actions">
										<button type="button" class="button button-primary bp-group-approve" data-group-id="<?php echo esc_attr( $group->id ); ?>">
											<?php esc_html_e( 'Approve', 'buddypress-group-moderation' ); ?>
										</button>
										<button type="button" class="button bp-group-reject" data-group-id="<?php echo esc_attr( $group->id ); ?>" aria-expanded="false" aria-controls="bp-group-reject-confirm-<?php echo esc_attr( $group->id ); ?>">
											<?php esc_html_e( 'Reject', 'buddypress-group-moderation' ); ?>
										</button>
									</div>
									<div class="bp-group-reject-confirm" id="bp-group-reject-confirm-<?php echo esc_attr( $group->id ); ?>" hidden>
										<label for="bp-group-reject-reason-<?php echo esc_attr( $group->id ); ?>"><?php esc_html_e( 'Reason for the creator (optional)', 'buddypress-group-moderation' ); ?></label>
										<textarea id="bp-group-reject-reason-<?php echo esc_attr( $group->id ); ?>" class="bp-group-reject-reason" rows="2" maxlength="500"></textarea>
										<p class="description"><?php echo esc_html( $reject_help ); ?></p>
										<div class="bp-group-moderation-actions">
											<button type="button" class="button bp-group-reject-confirm-yes" data-group-id="<?php echo esc_attr( $group->id ); ?>"><?php esc_html_e( 'Reject and delete group', 'buddypress-group-moderation' ); ?></button>
											<button type="button" class="button-link bp-group-reject-cancel"><?php esc_html_e( 'Cancel', 'buddypress-group-moderation' ); ?></button>
										</div>
									</div>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
				<div class="bp-group-moderation-no-items"<?php echo empty( $pending_groups ) ? '' : ' hidden'; ?>>
					<p><?php esc_html_e( 'No pending groups found.', 'buddypress-group-moderation' ); ?></p>
				</div>
		</div>
		<?php
	}

	/**
	 * Get one page of pending groups, newest first.
	 *
	 * @param int $page     Page number, 1-based.
	 * @param int $per_page Groups per page.
	 * @return array { groups: BP_Groups_Group[], total: int }
	 */
	public function bp_group_moderation_get_pending_groups( $page = 1, $per_page = 20 ) {
		$result = groups_get_groups(
			array(
				'show_hidden' => true,
				'type'        => 'newest',
				'per_page'    => $per_page,
				'page'        => $page,
				'meta_query'  => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- approval_status is the plugin's only pending flag.
					array(
						'key'   => 'approval_status',
						'value' => 'pending',
					),
				),
			)
		);

		return array(
			'groups' => (array) $result['groups'],
			'total'  => (int) $result['total'],
		);
	}
}