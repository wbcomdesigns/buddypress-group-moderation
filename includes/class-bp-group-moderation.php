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
		
		// Filter group queries.
		add_filter( 'bp_groups_get_groups', array( $this, 'filter_pending_groups' ), 10, 2 );
		add_filter( 'bp_activity_get', array( $this, 'filter_pending_groups_activity' ), 10, 1 );
		
		// Display hooks.
		add_action( 'bp_before_group_header', array( $this, 'display_pending_notice' ) );
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
		// Skip for site admins if configured to do so.
		$auto_approve_admin = get_option( 'bp_group_moderation_auto_approve_admin', true );
		if ( $auto_approve_admin && current_user_can( 'manage_options' ) ) {
			return;
		}
		
		// Only set to pending for newly created groups.
		if ( $group->is_new ) {
			// Store the original requested status.
			$original_status = $group->status;
			groups_update_groupmeta( $group->id, 'requested_status', $original_status );
			
			// Set approval status to pending.
			groups_update_groupmeta( $group->id, 'approval_status', 'pending' );
			
			// Optionally set to hidden while pending (based on settings).
			$hide_pending = get_option( 'bp_group_moderation_hide_pending', true );
			if ( $hide_pending ) {
				// Update the visibility to hidden.
				$group->status = 'hidden';
				$group->save();
			}
			
			// Notify administrators.
			$this->notify_admins_of_pending_group( $group->id );
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
}