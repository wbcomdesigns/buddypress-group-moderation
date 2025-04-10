<?php
/**
 * Main class for BuddyPress Group Moderation functionality.
 *
 * @package BuddyPress_Group_Moderation
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main class for BuddyPress Group Moderation plugin.
 */
class BP_Group_Moderation {

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Initialize the plugin.
	 */
	private function __construct() {
		// Load text domain.
		add_action( 'bp_init', array( $this, 'bp_group_moderation_load_plugin_textdomain' ) );
		add_action( 'bp_loaded', array( $this, 'bp_group_moderation_init' ) );
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
	 * Get group URL in a backward-compatible way.
	 *
	 * @param BP_Groups_Group $group The group object.
	 * @return string
	 */
	protected static function bp_group_moderation_get_group_url( $group ) {
		if ( function_exists( 'bp_get_group_url' ) ) {
			return bp_get_group_url( $group );
		} elseif ( function_exists( 'bp_get_group_permalink' ) ) {
			return bp_get_group_permalink( $group );
		}
		return '';
	}

	/**
	 * Load the plugin text domain for translation.
	 */
	public function bp_group_moderation_load_plugin_textdomain() {
		load_plugin_textdomain( 'bp-group-moderation', false, dirname( plugin_basename( BP_GROUP_MODERATION_PLUGIN_FILE ) ) . '/languages/' );
	}

	/**
	 * Initialize the plugin components.
	 */
	public function bp_group_moderation_init() {

		// Group creation hooks - Catch group creation in multiple stages
		add_action( 'groups_create_group', array( $this, 'bp_group_moderation_catch_new_group' ), 10, 3 );
		add_action( 'groups_created_group', array( $this, 'bp_group_moderation_early_catch_new_group' ), 1, 2 );
		add_action( 'groups_group_after_save', array( $this, 'bp_group_moderation_set_group_to_pending' ), 20 );
		
		// Intercept group status changes directly
		add_filter( 'bp_group_status_change', array( $this, 'bp_group_moderation_intercept_status_change' ), 10, 3 );
		
		// Filter group queries to hide pending groups
		add_filter( 'bp_groups_get_groups', array( $this, 'bp_group_moderation_filter_pending_groups' ), 10, 2 );
		add_filter( 'bp_activity_get', array( $this, 'bp_group_moderation_filter_pending_groups_activity' ), 10, 1 );
		
		// Display hooks.
		add_action( 'bp_before_group_header', array( $this, 'bp_group_moderation_display_pending_notice' ) );

		// Schedule regular checks for pending groups that should be hidden
		add_action( 'bp_group_moderation_hourly_check', array( $this, 'bp_group_moderation_check_pending_groups_status' ) );
		if ( ! wp_next_scheduled( 'bp_group_moderation_hourly_check' ) ) {
			wp_schedule_event( time(), 'hourly', 'bp_group_moderation_hourly_check' );
		}

		// Add hook for direct database access
		add_action( 'bp_group_moderation_verify_status', array( $this, 'bp_group_moderation_verify_group_status' ) );
		
		// Debug hook for admin users - add a button to test the function
		if ( current_user_can('manage_options') && bp_is_group() ) {
			add_action( 'bp_before_group_header', array( $this, 'bp_group_moderation_add_admin_test_buttons' ) );
		}
		
		// Handle admin actions
		add_action( 'bp_actions', array( $this, 'bp_group_moderation_handle_admin_test_actions' ) );
	}

	/**
	 * Intercept status changes and ensure pending groups stay hidden
	 *
	 * @param string $new_status The new status.
	 * @param string $old_status The old status.
	 * @param object $group The group object.
	 * @return string The status to use
	 */
	public function bp_group_moderation_intercept_status_change( $new_status, $old_status, $group ) {
		// Check if this group is pending
		$approval_status = groups_get_groupmeta( $group->id, 'approval_status', true );
		
		if ( 'pending' === $approval_status ) {
			// Force pending groups to stay hidden regardless of status changes
			if ( defined('WP_DEBUG') && WP_DEBUG ) {
				error_log( 'BP Group Moderation: Intercepted status change for group ' . $group->id . ' from ' . $old_status . ' to ' . $new_status . '. Forcing to hidden.' );
			}
			
			// Store the requested status if not already stored
			$requested_status = groups_get_groupmeta( $group->id, 'requested_status', true );
			if ( empty( $requested_status ) ) {
				groups_update_groupmeta( $group->id, 'requested_status', $new_status );
			}
			
			return 'hidden';
		}
		
		return $new_status;
	}
	
	/**
	 * Early catch for newly created groups
	 *
	 * @param int $group_id The group ID.
	 * @param BP_Groups_Group $group The group object.
	 */
	public function bp_group_moderation_early_catch_new_group( $group_id, $group ) {
		if ( defined('WP_DEBUG') && WP_DEBUG ) {
			error_log( 'BP Group Moderation: Early catch new group - ID: ' . $group_id );
		}
		
		// Set the is_new flag explicitly
		$group->is_new = true;
		
		// Mark this as a new group in a temp transient (helps handle race conditions)
		set_transient( 'bp_new_group_' . $group_id, true, 300 ); // 5 minutes expiration
		
		// Apply immediately and schedule a check
		$this->process_new_group($group);
		wp_schedule_single_event( time() + 5, 'bp_group_moderation_verify_status', array( $group_id ) );
	}
	
	/**
	 * Explicitly handle new groups from the groups_create_group action.
	 *
	 * @param int $group_id The group ID.
	 * @param BP_Groups_Member $member The member object.
	 * @param BP_Groups_Group $group The group object.
	 */
	public function bp_group_moderation_catch_new_group( $group_id, $member, $group ) {
		if ( defined('WP_DEBUG') && WP_DEBUG ) {
			error_log( 'BP Group Moderation: Catch new group - ID: ' . $group_id );
		}
		
		// Set the is_new flag explicitly
		$group->is_new = true;
		
		// Mark this as a new group
		set_transient( 'bp_new_group_' . $group_id, true, 300 ); // 5 minutes expiration
		
		// Apply immediately and schedule a check
		$this->process_new_group($group);
		wp_schedule_single_event( time() + 5, 'bp_group_moderation_verify_status', array( $group_id ) );
	}

	/**
	 * Set a newly created group to pending status.
	 *
	 * @param BP_Groups_Group $group The group object.
	 */
	public function bp_group_moderation_set_group_to_pending( $group ) {
		if ( defined('WP_DEBUG') && WP_DEBUG ) {
			error_log( 'BP Group Moderation: Processing group - ID: ' . $group->id . ', Status: ' . $group->status );
		}
		
		// Check if this is a new group based on multiple criteria
		$is_new_group = false;
		
		// Check multiple ways to determine if this is a new group
		if ( !empty( $group->is_new ) || 
			 (isset($group->date_created) && strtotime($group->date_created) > (time() - 300)) ||
			 get_transient( 'bp_new_group_' . $group->id ) ) {
			$is_new_group = true;
		}
		
		// Only process new groups
		if ( !$is_new_group ) {
			// Could be an existing pending group that needs status verified
			$this->check_pending_status($group);
			return;
		}
		
		$this->process_new_group($group);
	}
	
	/**
	 * Process a new group and apply moderation settings
	 *
	 * @param BP_Groups_Group $group The group object.
	 */
	private function process_new_group( $group ) {
		// Check if this group already has approval status set
		$existing_approval = groups_get_groupmeta( $group->id, 'approval_status', true );
		if ( !empty( $existing_approval ) ) {
			// If it's pending but not hidden, force it to hidden
			if ( 'pending' === $existing_approval && 'hidden' !== $group->status ) {
				$this->force_hidden_status($group->id);
			}
			return;
		}
		
		// Check if we should auto-approve admin-created groups
		$auto_approve_admin = get_option( 'bp_group_moderation_auto_approve_admin', true );
		if ( $auto_approve_admin ) {
			// Get the creator of the group
			$creator_id = $group->creator_id;
			$is_admin = user_can( $creator_id, 'manage_options' );
			
			if ( $is_admin ) {
				if ( defined('WP_DEBUG') && WP_DEBUG ) {
					error_log( 'BP Group Moderation: Skipping moderation for admin-created group ' . $group->id );
				}
				return;
			}
		}
		
		// Store the original requested status
		$original_status = $group->status;
		groups_update_groupmeta( $group->id, 'requested_status', $original_status );
		if ( defined('WP_DEBUG') && WP_DEBUG ) {
			error_log( 'BP Group Moderation: Stored original status: ' . $original_status );
		}
		
		// Set approval status to pending
		groups_update_groupmeta( $group->id, 'approval_status', 'pending' );
		
		// Force the group to hidden status
		$this->force_hidden_status($group->id);
		
		// Notify administrators.
		$this->notify_admins_of_pending_group( $group->id );
	}
	
	/**
	 * Force a group to hidden status using multiple methods
	 *
	 * @param int $group_id The group ID.
	 */
	private function force_hidden_status( $group_id ) {
		// Get fresh group object
		$group = groups_get_group( $group_id );
		
		// Try BP's standard method first
		$group->status = 'hidden';
		$result = $group->save();
		
		if ( defined('WP_DEBUG') && WP_DEBUG ) {
			error_log( 'BP Group Moderation: Set group to hidden status. Save result: ' . ($result ? 'Success' : 'Failed') );
		}
		
		// Double-check it worked and use direct DB access if needed
		$updated_group = groups_get_group( $group_id );
		if ( 'hidden' !== $updated_group->status ) {
			if ( defined('WP_DEBUG') && WP_DEBUG ) {
				error_log( 'BP Group Moderation: Group status not set to hidden properly. Using direct database update.' );
			}
			
			// Use direct database update
			global $wpdb, $bp;
			$wpdb->update(
				$bp->groups->table_name,
				array( 'status' => 'hidden' ),
				array( 'id' => $group_id ),
				array( '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Check and fix pending group status if needed
	 *
	 * @param BP_Groups_Group $group The group object.
	 */
	private function check_pending_status( $group ) {
		$approval_status = groups_get_groupmeta( $group->id, 'approval_status', true );
		
		if ( 'pending' === $approval_status && 'hidden' !== $group->status ) {
			if ( defined('WP_DEBUG') && WP_DEBUG ) {
				error_log( 'BP Group Moderation: Found pending group ' . $group->id . ' not hidden, fixing...' );
			}
			
			$this->force_hidden_status( $group->id );
		}
	}
	
	/**
	 * Verify and fix group status if needed (run after a short delay)
	 *
	 * @param int $group_id The group ID.
	 */
	public function bp_group_moderation_verify_group_status( $group_id ) {
		$group = groups_get_group( $group_id );
		$approval_status = groups_get_groupmeta( $group_id, 'approval_status', true );
		
		if ( 'pending' === $approval_status && 'hidden' !== $group->status ) {
			if ( defined('WP_DEBUG') && WP_DEBUG ) {
				error_log( 'BP Group Moderation: Delayed check - Group ' . $group_id . ' is pending but not hidden, fixing...' );
			}
			
			$this->force_hidden_status( $group_id );
		}
	}
	
	/**
	 * Scheduled task to check all pending groups and ensure they're hidden
	 */
	public function bp_group_moderation_check_pending_groups_status() {
		global $wpdb, $bp;
		
		// Get all pending group IDs
		$pending_group_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT g.id FROM {$bp->groups->table_name} g
			INNER JOIN {$bp->groups->table_name_groupmeta} m ON g.id = m.group_id
			WHERE m.meta_key = %s AND m.meta_value = %s",
			'approval_status',
			'pending'
		) );
		
		if ( empty( $pending_group_ids ) ) {
			return;
		}
		
		foreach ( $pending_group_ids as $group_id ) {
			$group = groups_get_group( $group_id );
			
			if ( 'hidden' !== $group->status ) {
				if ( defined('WP_DEBUG') && WP_DEBUG ) {
					error_log( 'BP Group Moderation: Scheduled check - Group ' . $group_id . ' is pending but not hidden, fixing...' );
				}
				
				$this->force_hidden_status( $group_id );
			}
		}
	}

	/**
	 * Notify administrators about a pending group.
	 *
	 * @param int $group_id The group ID.
	 */
	public function notify_admins_of_pending_group( $group_id ) {
		$group = groups_get_group( $group_id );
		
		// Get site administrators.
		$admin_ids = get_users( array(
			'role'    => 'administrator',
			'fields'  => 'ID',
		) );
		
		$group_url = self::bp_group_moderation_get_group_url( $group );
		$admin_url = admin_url( 'admin.php?page=bp-pending-groups' );
		
		$subject = sprintf( __( 'New Group Pending Approval: %s', 'bp-group-moderation' ), $group->name );
		
		$message = sprintf(
			__( 'A new group "%1$s" has been created by %2$s and is pending approval.

You can approve or reject this group from the admin dashboard: %3$s

View the group: %4$s', 'bp-group-moderation' ),
			$group->name,
			bp_core_get_user_displayname( $group->creator_id ),
			$admin_url,
			$group_url
		);
		
		// Send notifications to all admins.
		foreach ( $admin_ids as $admin_id ) {
			// Add BuddyPress notification if available.
			if ( bp_is_active( 'notifications' ) ) {
				bp_notifications_add_notification( array(
					'user_id'           => $admin_id,
					'item_id'           => $group_id,
					'component_name'    => 'groups',
					'component_action'  => 'new_group_pending',
					'date_notified'     => bp_core_current_time(),
					'is_new'            => 1,
				) );
			}
			
			// Send email notification if enabled.
			$send_email = get_option( 'bp_group_moderation_send_emails', true );
			if ( $send_email ) {
				$admin_user = get_userdata( $admin_id );
				wp_mail( $admin_user->user_email, $subject, $message );
			}
		}
	}

	/**
	 * Filter groups in directory listings to hide pending groups.
	 *
	 * @param array $groups Array of group objects.
	 * @param array $args   Array of arguments.
	 * @return array Modified array of group objects.
	 */
	public function bp_group_moderation_filter_pending_groups( $groups, $args ) {
		// Don't filter for admins.
		if ( current_user_can( 'manage_options' ) ) {
			return $groups;
		}
		
		// Filter out pending groups for regular users.
		foreach ( $groups['groups'] as $key => $group ) {
			$approval_status = groups_get_groupmeta( $group->id, 'approval_status', true );
			
			// If group is pending and user is not the creator, remove it.
			if ( 'pending' === $approval_status ) {
				if ( ! groups_is_user_admin( bp_loggedin_user_id(), $group->id ) ) {
					unset( $groups['groups'][ $key ] );
					$groups['total'] = $groups['total'] - 1;
				}
			}
		}
		
		// Re-index the array.
		$groups['groups'] = array_values( $groups['groups'] );
		
		return $groups;
	}

	/**
	 * Filter group activities to hide activities from pending groups.
	 *
	 * @param array $args Array of arguments.
	 * @return array Modified array of arguments.
	 */
	public function bp_group_moderation_filter_pending_groups_activity( $args ) {
		// Don't filter for admins.
		if ( current_user_can( 'manage_options' ) || ! bp_is_active( 'activity' ) ) {
			return $args;
		}
		
		global $wpdb, $bp;
		
		// Get all pending group IDs.
		$pending_group_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT g.id FROM {$bp->groups->table_name} g
			INNER JOIN {$bp->groups->table_name_groupmeta} m ON g.id = m.group_id
			WHERE m.meta_key = %s AND m.meta_value = %s",
			'approval_status',
			'pending'
		) );
		
		if ( ! empty( $pending_group_ids ) ) {
			// Format for SQL query.
			$pending_ids_str = implode( ',', array_map( 'absint', $pending_group_ids ) );
			
			// Add WHERE clause to exclude activities from pending groups.
			if ( ! empty( $args['where'] ) ) {
				$args['where'] .= " AND item_id NOT IN ({$pending_ids_str}) ";
			} else {
				$args['where'] = " item_id NOT IN ({$pending_ids_str}) ";
			}
		}
		
		return $args;
	}

	/**
	 * Display a notice for pending groups.
	 */
	public function bp_group_moderation_display_pending_notice() {
		if ( ! bp_is_group() ) {
			return;
		}
		
		$group_id = bp_get_current_group_id();
		$approval_status = groups_get_groupmeta( $group_id, 'approval_status', true );
		$requested_status = groups_get_groupmeta( $group_id, 'requested_status', true );
		
		if ( current_user_can( 'manage_options' ) && defined('WP_DEBUG') && WP_DEBUG ) {
			?>
			<div class="bp-feedback info">
				<span class="bp-icon" aria-hidden="true"></span>
				<p>
					<?php 
					echo sprintf(
						esc_html__( 'Debug Info: Group ID: %d, Approval Status: %s, Requested Status: %s', 'bp-group-moderation' ),
						esc_html( $group_id ), 
						esc_html( $approval_status ? $approval_status : 'Not set' ),
						esc_html( $requested_status ? $requested_status : 'Not set' )
					); 
					?>
				</p>
			</div>
			<?php
		}
		
		if ( 'pending' === $approval_status ) {
			// Ensure this group is hidden regardless of its current status
			$group = groups_get_group( $group_id );
			if ( 'hidden' !== $group->status ) {
				$this->force_hidden_status( $group_id );
			}
			
			// Show to group admins and site admins.
			if ( groups_is_user_admin( bp_loggedin_user_id(), $group_id ) || current_user_can( 'manage_options' ) ) {
				?>
				<div class="bp-feedback warning">
					<span class="bp-icon" aria-hidden="true"></span>
					<p><?php esc_html_e( 'This group is pending approval by a site administrator. Some features may be limited until approval.', 'bp-group-moderation' ); ?></p>
				</div>
				<?php
			}
		}
	}

	/**
	 * Add admin test buttons for debugging.
	 */
	public function bp_group_moderation_add_admin_test_buttons() {
		$group_id = bp_get_current_group_id();
		?>
		<div class="bp-feedback bp-group-moderation-admin-tools" style="margin-bottom: 15px; background: #f0f0f0; border: 1px solid #ccc; padding: 10px; border-radius: 4px;">
			<h4><?php esc_html_e( 'Group Moderation Admin Tools', 'bp-group-moderation' ); ?></h4>
			<p>
				<!-- Generate secure admin action URLs for group moderation.
				Each link includes a nonce ('_wpnonce') created with a unique action name per group.
				This protects against CSRF when the links are clicked. -->
				<a href="<?php echo esc_url( add_query_arg( array(
						'bp-group-mod-action' => 'set-pending',
						'_wpnonce'            => wp_create_nonce( 'bp_group_mod_action_' . $group_id ),
					), self::bp_group_moderation_get_group_url( groups_get_group( $group_id ) ) ) ); ?>" class="button">
					<?php esc_html_e( 'Set as Pending', 'bp-group-moderation' ); ?>
				</a>				

				<a href="<?php echo esc_url( add_query_arg( array(
					'bp-group-mod-action' => 'clear-pending',
					'_wpnonce'            => wp_create_nonce( 'bp_group_mod_action_' . $group_id ),
				), self::bp_group_moderation_get_group_url( groups_get_group( $group_id ) ) ) ); ?>" class="button">
					<?php esc_html_e( 'Clear Pending Status', 'bp-group-moderation' ); ?>
				</a>

				<a href="<?php echo esc_url( add_query_arg( array(
					'bp-group-mod-action' => 'view-debug',
					'_wpnonce'            => wp_create_nonce( 'bp_group_mod_action_' . $group_id ),
				), self::bp_group_moderation_get_group_url( groups_get_group( $group_id ) ) ) ); ?>" class="button">
					<?php esc_html_e( 'View Group Debug Info', 'bp-group-moderation' ); ?>
				</a>

			</p>
		</div>
		<?php
	}
	
	/**
	 * Handle admin test actions.
	 */
	public function bp_group_moderation_handle_admin_test_actions() {
		if ( !bp_is_group() || !current_user_can('manage_options') || empty($_GET['bp-group-mod-action']) ) {
			return;
		}

		$group_id = bp_get_current_group_id();

		// Verify the nonce for security to prevent CSRF attacks.
		// wp_unslash is used to remove slashes added by WordPress, and sanitize_text_field ensures clean input.
		if ( empty( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'bp_group_mod_action_' . $group_id ) ) {
			bp_core_add_message( __( 'Security check failed. Please try again.', 'bp-group-moderation' ), 'error' );
			bp_core_redirect( bp_get_group_permalink( groups_get_group( $group_id ) ) );
			exit;
		}
		$action = sanitize_text_field( wp_unslash( $_GET['bp-group-mod-action'] ) );		
		
		$group = groups_get_group( $group_id );
		
		if ( $action === 'set-pending' ) {
			// Store the current status as the requested status
			groups_update_groupmeta( $group_id, 'requested_status', $group->status );
			
			// Set the approval status to pending
			groups_update_groupmeta( $group_id, 'approval_status', 'pending' );
			
			// Set to hidden while pending
			$this->force_hidden_status( $group_id );
			
			bp_core_add_message( __( 'Group has been set to pending status.', 'bp-group-moderation' ), 'success' );
		}
		elseif ( $action === 'clear-pending' ) {
			// Get the requested status (if any)
			$requested_status = groups_get_groupmeta( $group_id, 'requested_status', true );
			
			// Remove the pending flag
			groups_delete_groupmeta( $group_id, 'approval_status' );
			
			// If there was a requested status, restore it
			if ( !empty($requested_status) ) {
				$group->status = $requested_status;
				$group->save();
				groups_delete_groupmeta( $group_id, 'requested_status' );
			}
		
			bp_core_add_message( __( 'Pending status has been cleared.', 'bp-group-moderation' ), 'success' );
		}
		elseif ( $action === 'view-debug' ) {
			// Get all group meta
			global $wpdb, $bp;
			$meta_table = $bp->groups->table_name_groupmeta;
			$meta_data = $wpdb->get_results( $wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$meta_table} WHERE group_id = %d",
				$group_id
			));
			
			// Display the debug info
			echo '<div class="bp-feedback">';
			echo '<h3>Group Debug Information</h3>';
			echo '<p><strong>Group ID:</strong> ' . esc_html( $group_id ) . '</p>';
			echo '<p><strong>Group Status:</strong> ' . esc_html( $group->status ) . '</p>';
			echo '<p><strong>Created:</strong> ' . esc_html( $group->date_created ) . '</p>';
			echo '<p><strong>Creator ID:</strong> ' . esc_html( $group->creator_id ) . '</p>';
			echo '<p><strong>Description:</strong> ' . esc_html( $group->description ) . '</p>';
			echo '<p><strong>Group Meta:</strong></p>';
			echo '<ul>';
			foreach ( $meta_data as $meta ) {
				echo '<li><strong>' . esc_html($meta->meta_key) . ':</strong> ' . esc_html($meta->meta_value) . '</li>';
			}
			echo '</ul>';
			echo '</div>';
		}
		
		// Refresh the page without the query arg
		bp_core_redirect( remove_query_arg( 'bp-group-mod-action', self::bp_group_moderation_get_group_url( $group ) ) );
		exit;
	}

	/**
	 * Approve a pending group.
	 *
	 * @param int $group_id The group ID.
	 * @return bool Success status.
	 */
	public function approve_group( $group_id ) {
		// Get the originally requested status
		$requested_status = groups_get_groupmeta( $group_id, 'requested_status', true );
		
		// Get the group object
		$group = groups_get_group( $group_id );
		
		// Update the group to the requested status
		$group->status = $requested_status;
		$result = $group->save();
		
		if ( $result ) {
			// Remove the pending flag
			groups_delete_groupmeta( $group_id, 'approval_status' );
			
			// Notify the group creator
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
		// Get group info before deletion
		$group = groups_get_group( $group_id );
		
		// Notify the creator before deleting the group
		$this->notify_user_of_group_decision( $group, 'rejected' );
		
		// Delete the group
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
					self::bp_group_moderation_get_group_url( $group )
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
}