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
		add_action( 'bp_init', array( $this, 'load_plugin_textdomain' ) );
		add_action( 'bp_loaded', array( $this, 'init' ) );
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
	 * Load the plugin text domain for translation.
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'bp-group-moderation', false, dirname( plugin_basename( BP_GROUP_MODERATION_PLUGIN_FILE ) ) . '/languages/' );
	}

	/**
	 * Initialize the plugin components.
	 */
	public function init() {
		// Check if BuddyPress is active.
		if ( ! function_exists( 'buddypress' ) || ! bp_is_active( 'groups' ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notice_need_buddypress' ) );
			return;
		}

		// Group creation hooks.
		add_action( 'groups_group_after_save', array( $this, 'set_group_to_pending' ) );
		add_action( 'groups_create_group', array( $this, 'catch_new_group' ), 10, 3 );
		
		// Filter group queries.
		add_filter( 'bp_groups_get_groups', array( $this, 'filter_pending_groups' ), 10, 2 );
		add_filter( 'bp_activity_get', array( $this, 'filter_pending_groups_activity' ), 10, 1 );
		
		// Display hooks.
		add_action( 'bp_before_group_header', array( $this, 'display_pending_notice' ) );

		// Debug hook for admin users - add a button to test the function
		if ( current_user_can('manage_options') && bp_is_group() ) {
			add_action( 'bp_before_group_header', array( $this, 'add_admin_test_buttons' ) );
		}
		
		// Handle admin actions
		add_action( 'bp_actions', array( $this, 'handle_admin_test_actions' ) );
	}

	/**
	 * Display admin notice if BuddyPress is not active.
	 */
	public function admin_notice_need_buddypress() {
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e( 'BuddyPress Group Moderation requires BuddyPress with the Groups component activated.', 'bp-group-moderation' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Set a newly created group to pending status.
	 *
	 * @param BP_Groups_Group $group The group object.
	 */
	public function set_group_to_pending( $group ) {
		// Debug the incoming group object (commented out for production)
		/*
		if ( defined('WP_DEBUG') && WP_DEBUG ) {
			error_log( 'BP Group Moderation: Group object - ID: ' . $group->id . ', Status: ' . $group->status . ', Is New: ' . (isset($group->is_new) ? ($group->is_new ? 'Yes' : 'No') : 'Not Set') );
		}
		*/
		
		// Check if this group already has approval status set
		$existing_approval = groups_get_groupmeta( $group->id, 'approval_status', true );
		if ( !empty( $existing_approval ) ) {
			/*
			if ( defined('WP_DEBUG') && WP_DEBUG ) {
				error_log( 'BP Group Moderation: Group ' . $group->id . ' already has approval status: ' . $existing_approval );
			}
			*/
			return;
		}
		
		// Better detection for new groups
		$is_new_group = false;
		
		// Check if the group is marked as new or has been created recently
		if ( !empty( $group->is_new ) || 
			 (isset($group->date_created) && strtotime($group->date_created) > (time() - 300)) ) {
			$is_new_group = true;
		}
		
		// Only process newly created groups
		if ( !$is_new_group ) {
			/*
			if ( defined('WP_DEBUG') && WP_DEBUG ) {
				error_log( 'BP Group Moderation: Group ' . $group->id . ' is not new, skipping moderation' );
			}
			*/
			return;
		}
		
		// Check if we should auto-approve admin-created groups
		$auto_approve_admin = get_option( 'bp_group_moderation_auto_approve_admin', true );
		if ( $auto_approve_admin ) {
			// Get the creator of the group
			$creator_id = $group->creator_id;
			$is_admin = user_can( $creator_id, 'manage_options' );
			
			if ( $is_admin ) {
				/*
				if ( defined('WP_DEBUG') && WP_DEBUG ) {
					error_log( 'BP Group Moderation: Skipping moderation for admin-created group ' . $group->id );
				}
				*/
				return;
			}
		}
		
		// Store the original requested status.
		$original_status = $group->status;
		groups_update_groupmeta( $group->id, 'requested_status', $original_status );
		/*
		if ( defined('WP_DEBUG') && WP_DEBUG ) {
			error_log( 'BP Group Moderation: Stored original status: ' . $original_status );
		}
		*/
		
		// Set approval status to pending.
		groups_update_groupmeta( $group->id, 'approval_status', 'pending' );
		
		// Always set to hidden while pending
		$hide_pending = get_option( 'bp_group_moderation_hide_pending', true );
		if ( $hide_pending ) {
			$group->status = 'hidden';
			$group->save();
			/*
			if ( defined('WP_DEBUG') && WP_DEBUG ) {
				error_log( 'BP Group Moderation: Set group to hidden status' );
			}
			*/
		}
		
		// Notify administrators.
		$this->notify_admins_of_pending_group( $group->id );
		
		/*
		if ( defined('WP_DEBUG') && WP_DEBUG ) {
			error_log( 'BP Group Moderation: Group ' . $group->id . ' set to pending status' );
		}
		*/
	}

	/**
	 * Explicitly handle new groups from the groups_create_group action.
	 *
	 * @param int $group_id The group ID.
	 * @param BP_Groups_Member $member The member object.
	 * @param BP_Groups_Group $group The group object.
	 */
	public function catch_new_group( $group_id, $member, $group ) {
		// Set the is_new flag explicitly
		$group->is_new = true;
		
		// Call set_group_to_pending with properly flagged group
		$this->set_group_to_pending( $group );
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
		
		$group_url = bp_get_group_permalink( $group );
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
	public function filter_pending_groups( $groups, $args ) {
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
	public function filter_pending_groups_activity( $args ) {
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
	public function display_pending_notice() {
		if ( ! bp_is_group() ) {
			return;
		}
		
		$group_id = bp_get_current_group_id();
		$approval_status = groups_get_groupmeta( $group_id, 'approval_status', true );
		$requested_status = groups_get_groupmeta( $group_id, 'requested_status', true );
		
		// Debug information for admins - commented out for production
		/*
		if ( current_user_can( 'manage_options' ) && defined('WP_DEBUG') && WP_DEBUG ) {
			?>
			<div class="bp-feedback info">
				<span class="bp-icon" aria-hidden="true"></span>
				<p>
					<?php 
					echo sprintf(
						'Debug Info: Group ID: %d, Approval Status: %s, Requested Status: %s', 
						$group_id, 
						$approval_status ? $approval_status : 'Not set',
						$requested_status ? $requested_status : 'Not set'
					); 
					?>
				</p>
			</div>
			<?php
		}
		*/
		
		if ( 'pending' === $approval_status ) {
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
	public function add_admin_test_buttons() {
		$group_id = bp_get_current_group_id();
		?>
		<div class="bp-feedback bp-group-moderation-admin-tools" style="margin-bottom: 15px; background: #f0f0f0; border: 1px solid #ccc; padding: 10px; border-radius: 4px;">
			<h4><?php esc_html_e( 'Group Moderation Admin Tools', 'bp-group-moderation' ); ?></h4>
			<p>
				<a href="<?php echo esc_url( add_query_arg( 'bp-group-mod-action', 'set-pending', bp_get_group_permalink( groups_get_group( $group_id ) ) ) ); ?>" class="button">
					<?php esc_html_e( 'Set as Pending', 'bp-group-moderation' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'bp-group-mod-action', 'clear-pending', bp_get_group_permalink( groups_get_group( $group_id ) ) ) ); ?>" class="button">
					<?php esc_html_e( 'Clear Pending Status', 'bp-group-moderation' ); ?>
				</a>
				<a href="<?php echo esc_url( add_query_arg( 'bp-group-mod-action', 'view-debug', bp_get_group_permalink( groups_get_group( $group_id ) ) ) ); ?>" class="button">
					<?php esc_html_e( 'View Group Debug Info', 'bp-group-moderation' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
	
	/**
	 * Handle admin test actions.
	 */
	public function handle_admin_test_actions() {
		if ( !bp_is_group() || !current_user_can('manage_options') || empty($_GET['bp-group-mod-action']) ) {
			return;
		}
		
		$action = sanitize_text_field( $_GET['bp-group-mod-action'] );
		$group_id = bp_get_current_group_id();
		$group = groups_get_group( $group_id );
		
		if ( $action === 'set-pending' ) {
			// Store the current status as the requested status
			groups_update_groupmeta( $group_id, 'requested_status', $group->status );
			
			// Set the approval status to pending
			groups_update_groupmeta( $group_id, 'approval_status', 'pending' );
			
			// Set to hidden while pending
			$group->status = 'hidden';
			$group->save();
			
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
			echo '<p><strong>Group ID:</strong> ' . $group_id . '</p>';
			echo '<p><strong>Group Status:</strong> ' . $group->status . '</p>';
			echo '<p><strong>Created:</strong> ' . $group->date_created . '</p>';
			echo '<p><strong>Creator ID:</strong> ' . $group->creator_id . '</p>';
			echo '<p><strong>Description:</strong> ' . $group->description . '</p>';
			echo '<p><strong>Group Meta:</strong></p>';
			echo '<ul>';
			foreach ( $meta_data as $meta ) {
				echo '<li><strong>' . esc_html($meta->meta_key) . ':</strong> ' . esc_html($meta->meta_value) . '</li>';
			}
			echo '</ul>';
			echo '</div>';
		}
		
		// Refresh the page without the query arg
		bp_core_redirect( remove_query_arg( 'bp-group-mod-action', bp_get_group_permalink( $group ) ) );
		exit;
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
}