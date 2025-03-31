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
	 * Initialize the class.
	 */
	private function __construct() {
		// Admin hooks.
		add_action( 'bp_admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		
		// AJAX handlers.
		add_action( 'wp_ajax_bp_group_moderation_handle_group', array( $this, 'ajax_handle_group' ) );
	}

	/**
	 * Return an instance of this class.
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
	 * Add admin menu for managing pending groups.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'bp-groups',
			__( 'Pending Groups', 'bp-group-moderation' ),
			__( 'Pending Groups', 'bp-group-moderation' ),
			'manage_options',
			'bp-pending-groups',
			array( $this, 'admin_page_content' )
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook The current admin page.
	 */
	public function admin_enqueue_scripts( $hook ) {
		if ( 'buddypress_page_bp-pending-groups' !== $hook ) {
			return;
		}
		
		wp_enqueue_style( 'bp-group-moderation-admin', BP_GROUP_MODERATION_PLUGIN_URL . 'assets/css/admin.css', array(), BP_GROUP_MODERATION_VERSION );
		wp_enqueue_script( 'bp-group-moderation-admin', BP_GROUP_MODERATION_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), BP_GROUP_MODERATION_VERSION, true );
		
		wp_localize_script( 'bp-group-moderation-admin', 'bpGroupModeration', array(
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'bp-group-moderation-nonce' ),
			'approveText'   => __( 'Approve', 'bp-group-moderation' ),
			'rejectText'    => __( 'Reject', 'bp-group-moderation' ),
			'confirmReject' => __( 'Are you sure you want to reject this group? This action cannot be undone.', 'bp-group-moderation' ),
			'loadingText'   => __( 'Processing...', 'bp-group-moderation' ),
		) );
	}

	/**
	 * Handle AJAX requests for group moderation actions.
	 */
	public function ajax_handle_group() {
		// Verify nonce.
		check_ajax_referer( 'bp-group-moderation-nonce', 'nonce' );
		
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'bp-group-moderation' ) ) );
		}
		
		$group_id = isset( $_POST['group_id'] ) ? intval( $_POST['group_id'] ) : 0;
		$action = isset( $_POST['action_type'] ) ? sanitize_text_field( $_POST['action_type'] ) : '';
		
		if ( ! $group_id || ! in_array( $action, array( 'approve', 'reject' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'bp-group-moderation' ) ) );
		}
		
		$result = false;
		$message = '';
		
		// Process the action.
		if ( 'approve' === $action ) {
			$result = $this->approve_group( $group_id );
			$message = __( 'Group has been approved successfully.', 'bp-group-moderation' );
		} elseif ( 'reject' === $action ) {
			$result = $this->reject_group( $group_id );
			$message = __( 'Group has been rejected.', 'bp-group-moderation' );
		}
		
		if ( $result ) {
			wp_send_json_success( array( 'message' => $message ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'An error occurred while processing the request.', 'bp-group-moderation' ) ) );
		}
	}

	/**
	 * Approve a pending group.
	 *
	 * @param int $group_id The group ID.
	 * @return bool Success status.
	 */
	public function approve_group( $group_id ) {
		// Get the originally requested status.
		$requested_status = groups_get_groupmeta( $group_id, 'requested_status', true );
		
		// Get the group object.
		$group = groups_get_group( $group_id );
		
		if ( ! $group ) {
			return false;
		}
		
		// Update the group to the requested status.
		$group->status = $requested_status;
		$result = $group->save();
		
		if ( $result ) {
			// Remove the pending flag.
			groups_delete_groupmeta( $group_id, 'approval_status' );
			
			// Notify the group creator.
			$this->notify_user_of_group_decision( $group, 'approved' );
			
			return true;
		}
		
		return false;
	}

	/**
	 * Reject a pending group.
	 *
	 * @param int $group_id The group ID.
	 * @return bool Success status.
	 */
	public function reject_group( $group_id ) {
		// Get group info before deletion.
		$group = groups_get_group( $group_id );
		
		if ( ! $group ) {
			return false;
		}
		
		$creator_id = $group->creator_id;
		$group_name = $group->name;
		
		// Notify the creator before deleting the group.
		$this->notify_user_of_group_decision( $group, 'rejected' );
		
		// Delete the group.
		$result = groups_delete_group( $group_id );
		
		return $result;
	}

	/**
	 * Notify a user about their group's approval status.
	 *
	 * @param BP_Groups_Group $group The group object.
	 * @param string          $decision 'approved' or 'rejected'.
	 */
	public function notify_user_of_group_decision( $group, $decision ) {
		if ( ! in_array( $decision, array( 'approved', 'rejected' ) ) ) {
			return;
		}
		
		$creator_id = $group->creator_id;
		$group_name = $group->name;
		$group_id = $group->id;
		
		// Add BuddyPress notification.
		if ( bp_is_active( 'notifications' ) ) {
			bp_notifications_add_notification( array(
				'user_id'           => $creator_id,
				'item_id'           => $group_id,
				'component_name'    => 'groups',
				'component_action'  => 'group_' . $decision,
				'date_notified'     => bp_core_current_time(),
				'is_new'            => 1,
			) );
		}
		
		// Send email notification if enabled.
		$send_email = get_option( 'bp_group_moderation_send_emails', true );
		if ( $send_email ) {
			$creator = get_userdata( $creator_id );
			
			if ( 'approved' === $decision ) {
				$subject = sprintf( __( 'Your group "%s" has been approved', 'bp-group-moderation' ), $group_name );
				$message = sprintf(
					__( 'Good news! Your group "%1$s" has been approved by a site administrator and is now live with your requested visibility setting.

Visit your group: %2$s', 'bp-group-moderation' ),
					$group_name,
					bp_get_group_permalink( $group )
				);
			} else {
				$subject = sprintf( __( 'Your group "%s" was not approved', 'bp-group-moderation' ), $group_name );
				$message = sprintf(
					__( 'We\'re sorry, but your group "%s" has not been approved by the site administrators.

If you have questions about this decision, please contact the site administrators.', 'bp-group-moderation' ),
					$group_name
				);
			}
			
			wp_mail( $creator->user_email, $subject, $message );
		}
	}

	/**
	 * Render the admin page content for managing pending groups.
	 */
	public function admin_page_content() {
		// Get pending groups.
		$pending_groups = $this->get_pending_groups();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Pending Groups', 'bp-group-moderation' ); ?></h1>
			
			<div class="bp-group-moderation-settings">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bp-pending-groups&view=settings' ) ); ?>" class="button">
					<?php esc_html_e( 'Settings', 'bp-group-moderation' ); ?>
				</a>
			</div>
			
			<?php if ( isset( $_GET['view'] ) && 'settings' === $_GET['view'] ) : ?>
				<?php $this->render_settings_page(); ?>
			<?php else : ?>
				<?php if ( ! empty( $pending_groups ) ) : ?>
					<div id="bp-group-moderation-messages"></div>
					
					<table class="wp-list-table widefat fixed striped bp-group-moderation-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Group Name', 'bp-group-moderation' ); ?></th>
								<th><?php esc_html_e( 'Creator', 'bp-group-moderation' ); ?></th>
								<th><?php esc_html_e( 'Created', 'bp-group-moderation' ); ?></th>
								<th><?php esc_html_e( 'Requested Type', 'bp-group-moderation' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'bp-group-moderation' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $pending_groups as $group ) : 
								$creator = get_userdata( $group->creator_id );
								$requested_status = groups_get_groupmeta( $group->id, 'requested_status', true );
							?>
							<tr id="group-<?php echo esc_attr( $group->id ); ?>">
								<td>
									<a href="<?php echo esc_url( bp_get_group_permalink( $group ) ); ?>" target="_blank">
										<?php echo esc_html( $group->name ); ?>
									</a>
								</td>
								<td>
									<a href="<?php echo esc_url( bp_core_get_user_domain( $group->creator_id ) ); ?>" target="_blank">
										<?php echo esc_html( $creator->display_name ); ?>
									</a>
								</td>
								<td><?php echo esc_html( bp_core_time_since( $group->date_created ) ); ?></td>
								<td><?php echo esc_html( ucfirst( $requested_status ) ); ?></td>
								<td>
									<div class="bp-group-moderation-actions">
										<button class="button button-primary bp-group-approve" data-group-id="<?php echo esc_attr( $group->id ); ?>">
											<?php esc_html_e( 'Approve', 'bp-group-moderation' ); ?>
										</button>
										<button class="button bp-group-reject" data-group-id="<?php echo esc_attr( $group->id ); ?>">
											<?php esc_html_e( 'Reject', 'bp-group-moderation' ); ?>
										</button>
									</div>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<div class="bp-group-moderation-no-items">
						<p><?php esc_html_e( 'No pending groups found.', 'bp-group-moderation' ); ?></p>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render the settings page.
	 */
	public function render_settings_page() {
		// Process settings save.
		if ( isset( $_POST['bp_group_moderation_save_settings'] ) && check_admin_referer( 'bp_group_moderation_settings' ) ) {
			$auto_approve_admin = isset( $_POST['bp_group_moderation_auto_approve_admin'] ) ? 1 : 0;
			$hide_pending = isset( $_POST['bp_group_moderation_hide_pending'] ) ? 1 : 0;
			$send_emails = isset( $_POST['bp_group_moderation_send_emails'] ) ? 1 : 0;
			
			update_option( 'bp_group_moderation_auto_approve_admin', $auto_approve_admin );
			update_option( 'bp_group_moderation_hide_pending', $hide_pending );
			update_option( 'bp_group_moderation_send_emails', $send_emails );
			
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Settings saved successfully.', 'bp-group-moderation' ); ?></p>
			</div>
			<?php
		}
		
		// Get current settings.
		$auto_approve_admin = get_option( 'bp_group_moderation_auto_approve_admin', true );
		$hide_pending = get_option( 'bp_group_moderation_hide_pending', true );
		$send_emails = get_option( 'bp_group_moderation_send_emails', true );
		
		?>
		<div class="bp-group-moderation-settings-form">
			<h2><?php esc_html_e( 'Group Moderation Settings', 'bp-group-moderation' ); ?></h2>
			
			<form method="post" action="">
				<?php wp_nonce_field( 'bp_group_moderation_settings' ); ?>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="bp_group_moderation_auto_approve_admin">
								<?php esc_html_e( 'Auto-approve admin groups', 'bp-group-moderation' ); ?>
							</label>
						</th>
						<td>
							<input type="checkbox" id="bp_group_moderation_auto_approve_admin" name="bp_group_moderation_auto_approve_admin" value="1" <?php checked( $auto_approve_admin ); ?> />
							<p class="description"><?php esc_html_e( 'Automatically approve groups created by site administrators.', 'bp-group-moderation' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="bp_group_moderation_hide_pending">
								<?php esc_html_e( 'Hide pending groups', 'bp-group-moderation' ); ?>
							</label>
						</th>
						<td>
							<input type="checkbox" id="bp_group_moderation_hide_pending" name="bp_group_moderation_hide_pending" value="1" <?php checked( $hide_pending ); ?> />
							<p class="description"><?php esc_html_e( 'Set pending groups to hidden status until approved.', 'bp-group-moderation' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="bp_group_moderation_send_emails">
								<?php esc_html_e( 'Send email notifications', 'bp-group-moderation' ); ?>
							</label>
						</th>
						<td>
							<input type="checkbox" id="bp_group_moderation_send_emails" name="bp_group_moderation_send_emails" value="1" <?php checked( $send_emails ); ?> />
							<p class="description"><?php esc_html_e( 'Send email notifications in addition to BuddyPress notifications.', 'bp-group-moderation' ); ?></p>
						</td>
					</tr>
				</table>
				
				<p class="submit">
					<input type="submit" name="bp_group_moderation_save_settings" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'bp-group-moderation' ); ?>" />
				</p>
			</form>
		</div>
		
		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bp-pending-groups' ) ); ?>" class="button">
				<?php esc_html_e( '&laquo; Back to Pending Groups', 'bp-group-moderation' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Get all pending groups.
	 *
	 * @return array Array of group objects.
	 */
	public function get_pending_groups() {
		global $wpdb, $bp;
		
		$groups_table = $bp->groups->table_name;
		$meta_table = $bp->groups->table_name_groupmeta;
		
		$query = $wpdb->prepare(
			"SELECT g.* FROM {$groups_table} g
			INNER JOIN {$meta_table} m ON g.id = m.group_id
			WHERE m.meta_key = %s AND m.meta_value = %s
			ORDER BY g.date_created DESC",
			'approval_status',
			'pending'
		);
		
		$results = $wpdb->get_results( $query );
		
		// Convert to BP group objects.
		$groups = array();
		foreach ( $results as $result ) {
			$groups[] = groups_get_group( $result->id );
		}
		
		return $groups;
	}
}