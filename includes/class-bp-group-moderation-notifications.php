<?php
/**
 * Notifications class for BuddyPress Group Moderation.
 *
 * @package BuddyPress_Group_Moderation
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for handling BuddyPress notifications.
 */
class BP_Group_Moderation_Notifications {

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

		add_action( 'bp_init',  array( $this, 'bp_group_moderation_check_notifications_component' ) );
		
	}

	public function bp_group_moderation_check_notifications_component() {
		if ( ! function_exists( 'bp_is_active' ) || ! bp_is_active( 'notifications' ) ) {
			return;
		}
		// Register notification filters.
		add_filter( 'bp_notifications_get_registered_components', array( $this, 'bp_group_moderation_register_notifications_component' ) );
		add_filter( 'bp_groups_new_group_pending_notification', array( $this, 'bp_group_moderation_format_new_group_pending_notifications' ), 10, 5 );
		add_filter( 'bp_groups_group_approved_notification', array( $this, 'bp_group_moderation_format_group_approved_notifications' ), 10, 5 );
		add_filter( 'bp_groups_group_rejected_notification', array( $this, 'bp_group_moderation_format_group_rejected_notifications' ), 10, 5 );
		add_action( 'template_redirect', array( $this, 'bp_group_moderation_mark_read_single_notification' ) );
	}

	/**
	 * Register our component to the notifications component array.
	 *
	 * @param array $component_names Array of component names.
	 * @return array
	 */
	public function bp_group_moderation_register_notifications_component( $component_names = array() ) {
		// Ensure $component_names is an array
		if ( ! is_array( $component_names ) ) {
			$component_names = array();
		}
		
		// Add our component to the registered components.
		// Use 'groups' component for broader compatibility.
		if ( ! in_array( 'groups', $component_names ) ) {
			$component_names[] = 'groups';
		}
		
		return $component_names;
	}

		/**
	 * Formats notifications for pending group approvals.
	 *
	 * This callback formats the notification text and link shown to admins when
	 * one or more new BuddyPress groups are pending approval. The output is used 
	 * by the 'bp_group_moderation_format_new_group_pending_notifications' filter.
	 *
	 * @param string $action            Default notification text.
	 * @param int    $item_id           Group ID (primary item).
	 * @param int    $secondary_item_id Not used in this context.
	 * @param int    $total_items       Number of pending groups.
	 * @param string $format            Desired format: 'string' or 'array'.
	 *
	 * @return string|array Modified notification content (string or array format).
	 */
	public function bp_group_moderation_format_new_group_pending_notifications( $action, $item_id, $secondary_item_id, $total_items, $format ) {

		$group = groups_get_group( $item_id );
		
		if ( ! $group ) {
			return $action;
		}

		$notification_id =  0;

		if( function_exists( 'bp_is_notifications_component' ) && bp_is_notifications_component() ) { 
			$notification_id = function_exists( 'bp_get_the_notification_id' ) ? bp_get_the_notification_id() : 0;
		}

		if( empty( $group->name ) ) { 
			$member_group_name = get_usermeta( $secondary_item_id, 'bp_grp_moderation_rejected_group_'.$item_id, true );
		} else {
			$member_group_name = $group->name;
		}


		$text = (1 === $total_items)
			? sprintf(__('New group "%s" is pending approval', 'bp-group-moderation'), $member_group_name )
			: sprintf(__('%d new groups are pending approval', 'bp-group-moderation'), $total_items);
		
		$link = admin_url( 'admin.php?page=bp-pending-groups' );
		
		if( $notification_id ) {
			$link = add_query_arg( array( 'id' => $notification_id, 'action' => 'new_group_pending' ,'_wp_nonce' => wp_create_nonce( 'bp_group_moderation_notification_nonce' )  ), $link );
		} else {
			$link = add_query_arg( array( 'action' => 'new_group_pending' ,'_wp_nonce' => wp_create_nonce( 'bp_group_moderation_notification_nonce' )  ), $link );
		}

		
		
		if ( empty( $text ) || empty( $link ) ) {
			return $action;
		}

		if ( 'string' === $format ) {
			return '<a href="' . esc_url( $link ) . '">' . esc_html( $text ) . '</a>';
		}
	
		return array(
			'text' => $text,
			'link' => $link,
		);

	}

	/**
	 * Formats the BuddyPress group moderation notification for approved groups.
	 *
	 * This callback formats the notification text and link shown to admins when
	 * one or more new BuddyPress groups are approved. The output is used 
	 * by the 'bp_groups_group_approved_notification' filter.
	 *
	 * @param string $action            Default notification text.
	 * @param int    $item_id           Group ID (primary item).
	 * @param int    $secondary_item_id Not used in this context.
	 * @param int    $total_items       Number of approved groups.
	 * @param string $format            Desired format: 'string' or 'array'.
	 *
	 * @return string|array Modified notification content (string or array format).
	 */
	public function bp_group_moderation_format_group_approved_notifications( $action, $item_id, $secondary_item_id, $total_items, $format ) {

		$group = groups_get_group( $item_id );
		
		if ( ! $group ) {
			return $action;
		}

		$notification_id =  0;

		if( function_exists( 'bp_is_notifications_component' ) && bp_is_notifications_component() ) { 
			$notification_id = function_exists( 'bp_get_the_notification_id' ) ? bp_get_the_notification_id() : 0;
		}

		$text = sprintf( __( 'Your group "%s" has been approved!', 'bp-group-moderation' ), $group->name );
		$link = bp_get_group_url( $group );

		if( $notification_id ) {
			$link = add_query_arg( array( 'id' => $notification_id, 'action' => 'group_approved' ,'_wp_nonce' => wp_create_nonce( 'bp_group_moderation_notification_nonce' )  ), $link );
		} else {
			$link = add_query_arg( array( 'action' => 'group_approved' ,'_wp_nonce' => wp_create_nonce( 'bp_group_moderation_notification_nonce' )  ), $link );
		}
		if ( empty( $text ) || empty( $link ) ) {
			return $action;
		}

		if ( 'string' === $format ) {
			return '<a href="' . esc_url( $link ) . '">' . esc_html( $text ) . '</a>';
		}
	
		return array(
			'text' => $text,
			'link' => $link,
		);
		
	}

	/**
	 * Formats the BuddyPress group moderation notification for rejected groups.
	 *
	 * This callback formats the notification text and link shown to admins when
	 * one or more new BuddyPress groups are not approved. The output is used 
	 * by the 'bp_groups_group_rejected_notification' filter.
	 *
	 * @param string $action            Default notification text.
	 * @param int    $item_id           Group ID (primary item).
	 * @param int    $secondary_item_id Not used in this context.
	 * @param int    $total_items       Number of rejected groups.
	 * @param string $format            Desired format: 'string' or 'array'.
	 *
	 * @return string|array Modified notification content (string or array format).
	 */
	public function bp_group_moderation_format_group_rejected_notifications( $action, $item_id, $secondary_item_id, $total_items, $format ) {
		
		$group = groups_get_group( $item_id );
	
		if ( !$group || empty( $item_id ) ) {
			return $action;
		}

		$user_id = bp_loggedin_user_id();
		$group_name = get_usermeta( $user_id, 'bp_grp_moderation_rejected_group_'.$item_id, true );
		
		$notification_id =  0;

		if( function_exists( 'bp_is_notifications_component' ) && bp_is_notifications_component() ) { 
			$notification_id = function_exists( 'bp_get_the_notification_id' ) ? bp_get_the_notification_id() : 0;
		}
		
		$text = sprintf(__( 'Your group "%s" was not approved by site administrators.', 'bp-group-moderation' ), $group_name);
		$link = bp_loggedin_user_domain() . 'notifications/';

		if( $notification_id ) {
			$link = add_query_arg( array( 'id' => $notification_id, 'action' => 'group_rejected' ,'_wp_nonce' => wp_create_nonce( 'bp_group_moderation_notification_nonce' )  ), $link );
		} else {
			$link = add_query_arg( array( 'action' => 'group_rejected' ,'_wp_nonce' => wp_create_nonce( 'bp_group_moderation_notification_nonce' )  ), $link );
		}

		if ( empty( $text ) || empty( $link ) ) {
			return $action;
		}

		if ( 'string' === $format ) {
			return '<a href="' . esc_url( $link ) . '">' . esc_html( $text ) . '</a>';
		}
	
		return array(
			'text' => $text,
			'link' => $link
		);
		
	}


	/**
	 * Marks notifications as read.
	 * @since 1.0.0
	 */
	public function bp_group_moderation_mark_read_single_notification(){
		
		if( ( isset( $_GET[ 'action' ] ) || isset( $_GET[ 'id' ] ) )  && isset( $_GET['_wp_nonce'] ) ) {

			$nonce_value     = !empty( $_GET['_wp_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wp_nonce'] ) ) : ''; 
			$notification_id = !empty( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : 0; 

			$notification_component = !empty( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : ''; 

			if(  ! wp_verify_nonce( $nonce_value, 'bp_group_moderation_notification_nonce'  ) ) { 
				return;
			}
			
			if ( class_exists('BP_Notifications_Notification') ) {

				if( empty( $notification_id ) && !empty( $notification_component )) {
							
					bp_notifications_mark_notifications_by_type( bp_loggedin_user_id(), 'groups' , $notification_component );
				
				} elseif( !empty( $notification_id ) ) {
									
					BP_Notifications_Notification::update(
						array( 'is_new' => 0 ),
						array( 'id'     => $notification_id )
					);
				}	
			}
		}
			
	}

}