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
		// Load text domain.
		add_action( 'bp_init', array( $this, 'bp_group_moderation_load_plugin_textdomain' ) );
		add_action( 'bp_loaded', array( $this, 'bp_group_moderation_init' ) );
		add_action( 'init', array( $this, 'bp_group_moderation_remove_action_notification' ) );
		add_action( 'bp_after_has_activities_parse_args', array( $this, 'bp_group_moderation_hide_pending_group_activity' ), 10,1 );
	}	

	/**
	 * Function to hide group creation activity till the group is approved by the site administrator.
	 * @param $activity_args Default activity args.
	 * @return $activity_args Modified activity args.
	 * 
	 * @since 1.0.0
	 */
	public function bp_group_moderation_hide_pending_group_activity( $activity_args ) { 
		
		if( ! bp_is_active( 'groups' ) || ! function_exists( 'bp_activity_get_activity_id' ) ) {
			return $activity_args;
		}

		$group_args = array( 
			'status' => array( 'hidden' ),
			'meta_query' => array(array(  
				'key' => 'approval_status',
				'value' => 'pending',
				'compare' => '=='
			))
		);

		$groups = groups_get_groups( $group_args);

		$activity_ids_to_exclude = array();
		$group_create_activity_ids = array();
		$group_cover_activity_ids = array();
		$group_profile_activity_ids = array();
		
		if( !empty( $groups['groups'] ) && is_array( $groups['groups'] ) ) {
			foreach( $groups['groups'] as $group_object ) {
				
				$group_create_activity_id = bp_activity_get_activity_id( 
					array( 
						'item_id' => $group_object->id,
						'type'    => 'created_group'
					)
				);

				$group_cover_activity_id = bp_activity_get_activity_id( 
					array( 
						'item_id' => $group_object->id,
						'type'    => 'new_group_cover_photo'
					)
				);

				$group_profile_activity_id = bp_activity_get_activity_id( 
					array( 
						'item_id' => $group_object->id,
						'type'    => 'new_group_avatar'
					)
				);

				$group_create_activity_ids[] = $group_create_activity_id; 

				if( !empty( $group_cover_activity_id ) ) {
					$group_cover_activity_ids[] = $group_cover_activity_id; 
				}
				
				if( !empty( $group_profile_activity_id ) ){
					$group_profile_activity_ids[] = $group_profile_activity_id; 
				}
				
			}
		}	

		$activity_ids_to_exclude  = array_merge( $group_create_activity_ids, $group_cover_activity_ids, $group_profile_activity_ids );

		$activity_args['exclude'] = $activity_ids_to_exclude;

		return $activity_args;
	}

	/** 
	 * Removes notification deletion hooks for group deletion.
	 * This allows custom handling of notifications when groups are deleted.
	 * @since 1.0.0
	 */
	public function bp_group_moderation_remove_action_notification(){
		
		remove_action( 'groups_delete_group', 'bp_groups_delete_group_delete_all_notifications');
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
		load_plugin_textdomain( 'buddypress-group-moderation', false, dirname( plugin_basename( BP_GROUP_MODERATION_PLUGIN_FILE ) ) . '/languages/' );
	}

	/**
	 * Initialize the plugin components.
	 */
	public function bp_group_moderation_init() {

		// Group creation hooks - Catch group creation in multiple stages
		add_action( 'groups_group_create_complete', array( $this, 'bp_group_moderation_set_group_to_pending' ), 20, 1 );
		
		// Show the creator their group is pending instead of the temporary Hidden type.
		add_filter( 'bp_get_group_type', array( $this, 'bp_group_moderation_pending_group_type' ), 10, 2 );

		// Intercept group status changes directly
		add_filter( 'bp_group_status_change', array( $this, 'bp_group_moderation_intercept_status_change' ), 10, 3 );
		
		// Filter group queries to hide pending groups
		add_filter( 'bp_groups_get_groups', array( $this, 'bp_group_moderation_filter_pending_groups' ), 10, 2 );
		add_filter( 'bp_activity_get', array( $this, 'bp_group_moderation_filter_pending_groups_activity' ), 10, 1 );
		
		// Display hooks.
		if( class_exists( 'Youzify' ) ) {
			add_action( 'bp_before_group_body', array( $this, 'bp_group_moderation_display_pending_notice' ) );
		} else {
			add_action( 'bp_before_group_header', array( $this, 'bp_group_moderation_display_pending_notice' ) );
		}
		
		// Schedule regular checks for pending groups that should be hidden
		add_action( 'bp_group_moderation_hourly_check', array( $this, 'bp_group_moderation_check_pending_groups_status' ) );
		if ( ! wp_next_scheduled( 'bp_group_moderation_hourly_check' ) ) {
			wp_schedule_event( time(), 'hourly', 'bp_group_moderation_hourly_check' );
		}

	}

	/**
	 * Label a pending group by its approval state and the type its creator asked for.
	 *
	 * @param string          $type  Group type label from BuddyPress.
	 * @param BP_Groups_Group $group The group object.
	 * @return string
	 */
	public function bp_group_moderation_pending_group_type( $type, $group ) {
		if ( empty( $group->id ) || 'pending' !== groups_get_groupmeta( $group->id, 'approval_status', true ) ) {
			return $type;
		}

		$labels    = array(
			'public'  => __( 'Public', 'buddypress-group-moderation' ),
			'private' => __( 'Private', 'buddypress-group-moderation' ),
			'hidden'  => __( 'Hidden', 'buddypress-group-moderation' ),
		);
		$requested = groups_get_groupmeta( $group->id, 'requested_status', true );

		if ( ! isset( $labels[ $requested ] ) ) {
			return __( 'Pending approval', 'buddypress-group-moderation' );
		}

		/* translators: %s: group type the creator requested (Public, Private or Hidden). */
		return sprintf( __( 'Pending approval, requested: %s', 'buddypress-group-moderation' ), $labels[ $requested ] );
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
	 * Set a newly created group to pending status.
	 *
	 * @param BP_Groups_Group $group The group object.
	 */
	public function bp_group_moderation_set_group_to_pending( $group_id ) {
		
		$group_obj = groups_get_group( $group_id );

		if( is_object( $group_obj ) && !empty( $group_obj ) ) {

			// Set the is_new flag explicitly
		    $group_obj->is_new = true;
			
			// Apply moderation settings immediately and schedule a status verification check
			$this->bp_group_moderation_process_new_group($group_obj);

		}

	}
	
	/**
	 * Process a new group and apply moderation settings
	 *
	 * @param BP_Groups_Group $group The group object.
	 */
	private function bp_group_moderation_process_new_group( $group ) {
		// Check if this group already has approval status set
		$existing_approval = groups_get_groupmeta( $group->id, 'approval_status', true );
		if ( !empty( $existing_approval ) ) {
			// If it's pending but not hidden, force it to hidden
			if ( 'pending' === $existing_approval && 'hidden' !== $group->status ) {
				$this->bp_group_moderation_force_hidden_status($group->id);
			}
			return;
		}
		
		// Check if we should auto-approve admin-created groups
		$auto_approve_admin = bp_group_moderation_fetch_settings( 'bp_group_moderation_auto_approve_admin' );
		if ( $auto_approve_admin ) {
			// Get the creator of the group
			$creator_id = $group->creator_id;
			$is_admin = user_can( $creator_id, 'manage_options' );
			
			if ( $is_admin ) {
				return;
			}
		}
		
		// Store the original requested status
		$original_status = $group->status;
		groups_update_groupmeta( $group->id, 'requested_status', $original_status );

		// Set approval status to pending
		groups_update_groupmeta( $group->id, 'approval_status', 'pending' );
		
		// Force the group to hidden status
		$this->bp_group_moderation_force_hidden_status($group->id);
		
		// Notify administrators.
		$this->bp_group_moderation_notify_admins_of_pending_group( $group->id );
	}
	
	/**
	 * Force a group to hidden status using multiple methods
	 *
	 * @param int $group_id The group ID.
	 */
	private function bp_group_moderation_force_hidden_status( $group_id ) {
		// Get fresh group object
		$group = groups_get_group( $group_id );
		
		// Try BP's standard method first
		$group->status = 'hidden';
		$group->save();

		// Double-check it worked and use direct DB access if needed
		$updated_group = groups_get_group( $group_id );
		if ( 'hidden' !== $updated_group->status ) {
			
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
	 * Scheduled task to verify all pending groups maintain hidden status. 
	 * Runs on an hourly schedule to repair any status inconsistencies.
	 */
	public function bp_group_moderation_check_pending_groups_status() {
		global $wpdb, $bp;
		
		// Get all pending group IDs
		
		//phpcs:disable
		$pending_group_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT g.id FROM {$bp->groups->table_name} g
			INNER JOIN {$bp->groups->table_name_groupmeta} m ON g.id = m.group_id
			WHERE m.meta_key = %s AND m.meta_value = %s",
			'approval_status',
			'pending'
		) );
		//phpcs:enable
		
		if ( empty( $pending_group_ids ) ) {
			return;
		}
		
		foreach ( $pending_group_ids as $group_id ) {
			$group = groups_get_group( $group_id );
			
			if ( 'hidden' !== $group->status ) {
				
				$this->bp_group_moderation_force_hidden_status( $group_id );
			}
		}
	}

	/**
	 * Notify administrators about a pending group.
	 *
	 * @param int $group_id The group ID.
	 */
	public function bp_group_moderation_notify_admins_of_pending_group( $group_id ) {
		$group = groups_get_group( $group_id );
		
		// Get site administrators.
		$admin_ids = get_users( array(
			'role'    => 'administrator',
			'fields'  => 'ID',
		) );
		
		$group_url = self::bp_group_moderation_get_group_url( $group );
		$admin_url = admin_url( 'admin.php?page=bp-pending-groups' );
		
		$subject = sprintf( __( 'New Group Pending Approval : %s', 'buddypress-group-moderation' ), $group->name );

		$message = sprintf( __( "<p><strong>Hello Admin</strong></p>
			<p>A new group titled “%s” has been created by <strong>“%s”</strong> and is currently <strong>awaiting your approval</strong>.</p>
			<p>Please review the submission to ensure it aligns with the community standards.</p>
			<p><strong>View Group :</strong></p>
			<p><a href='%s'>%s</a></p>
			<p><strong>Approve or Reject :</strong></p>	
			<p><a href='%s'>%s</a></p>	
			<p><strong>Thank you for keeping our community safe and welcoming!<strong></p>" , 'buddypress-group-moderation' ),

			esc_html( $group->name ),
			esc_html( bp_core_get_user_displayname( $group->creator_id ) ),
			esc_html( $group_url ),
			esc_html( $group_url ),
			esc_html( $admin_url ),
			esc_html( $admin_url ),
		);
		
		// Send notifications to all admins.
		foreach ( $admin_ids as $admin_id ) {
			// Add BuddyPress notification if available.
			if ( bp_is_active( 'notifications' ) ) {
				bp_notifications_add_notification( array(
					'user_id'           => $admin_id,
					'item_id'           => $group_id,
					'secondary_item_id' => $group->creator_id,
					'component_name'    => 'groups',
					'component_action'  => 'new_group_pending',
					'date_notified'     => bp_core_current_time(),
					'is_new'            => 1,
				) );
			}
			
			// Send email notification if enabled.
			$send_email = bp_group_moderation_fetch_settings( 'bp_group_moderation_send_emails' );
			if ( $send_email ) {
				$admin_user = get_userdata( $admin_id );
				$headers = array('Content-Type: text/html; charset=UTF-8');

				$subject = apply_filters( 'bp_group_moderation_group_pending_mail_subject', $subject );
				$message = apply_filters( 'bp_group_moderation_group_pending_mail', $message, $group_id, $admin_id );
				wp_mail( $admin_user->user_email, $subject, $message, $headers );
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
		
		// Remove pending groups from listings for non-admin users.
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
		
		//phpcs:disable
		// Get all pending group IDs.
		$pending_group_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT g.id FROM {$bp->groups->table_name} g
			INNER JOIN {$bp->groups->table_name_groupmeta} m ON g.id = m.group_id
			WHERE m.meta_key = %s AND m.meta_value = %s",
			'approval_status',
			'pending'
		) );
		//phpcs:enable

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
		
		$group_id        = bp_get_current_group_id();
		$approval_status = groups_get_groupmeta( $group_id, 'approval_status', true );

		if ( 'pending' === $approval_status ) {
			// Ensure this group is hidden regardless of its current status
			$group = groups_get_group( $group_id );
			if ( 'hidden' !== $group->status ) {
				$this->bp_group_moderation_force_hidden_status( $group_id );
			}
			
			// Show to group admins and site admins.
			if ( groups_is_user_admin( bp_loggedin_user_id(), $group_id ) || current_user_can( 'manage_options' ) ) {
				// Add id when Youzify is active.
				$id_attr = class_exists( 'Youzify' ) ? 'message' : '';
				?>
				<div <?php if ( $id_attr ) echo 'id="' . esc_attr( $id_attr ) . '"'; ?> class="bp-feedback warning info">
					<span class="bp-icon" aria-hidden="true"></span>
					<p><?php esc_html_e( 'Your group is currently awaiting approval from a site administrator. You will be notified once it has been approved.', 'buddypress-group-moderation' ); ?></p>
				</div>
				<?php
			}
		}
	}

}