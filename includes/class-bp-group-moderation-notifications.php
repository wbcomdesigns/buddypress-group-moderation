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
	 * Initialize the class.
	 */
	private function __construct() {

		add_action( 'bp_init',  array( $this, 'check_notifications_component' ) );
		
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


	public function check_notifications_component() {
		if ( ! function_exists( 'bp_is_active' ) || ! bp_is_active( 'notifications' ) ) {
			return;
		}
		// Register notification filters.
		add_filter( 'bp_notifications_get_registered_components', array( $this, 'register_notifications_component' ) );
		add_filter( 'bp_groups_new_group_pending_notification', array( $this, 'wbcom_format_new_group_pending_notifications' ), 10, 5 );
		add_filter( 'bp_groups_group_approved_notification', array( $this, 'wbcom_format_group_approved_notifications' ), 10, 5 );
		add_filter( 'bp_groups_group_rejected_notification', array( $this, 'wbcom_format_group_rejected_notifications' ), 10, 5 );
		
	}

	/**
	 * Register our component to the notifications component array.
	 *
	 * @param array $component_names Array of component names.
	 * @return array
	 */
	public function register_notifications_component( $component_names = array() ) {
		// Force $component_names to be an array
		if ( ! is_array( $component_names ) ) {
			$component_names = array();
		}
		
		// Add our component to the registered components.
		// Using 'groups' instead of a custom component for better compatibility.
		if ( ! in_array( 'groups', $component_names ) ) {
			$component_names[] = 'groups';
		}
		
		return $component_names;
	}

		/**
	 * Formats the BuddyPress group moderation notification for pending groups.
	 *
	 * This callback formats the notification text and link shown to admins when
	 * one or more new BuddyPress groups are pending approval. The output is used 
	 * by the 'bp_groups_new_group_pending_notification' filter.
	 *
	 * @param string $action            Default notification text.
	 * @param int    $item_id           Group ID (primary item).
	 * @param int    $secondary_item_id Not used in this context.
	 * @param int    $total_items       Number of pending groups.
	 * @param string $format            Desired format: 'string' or 'array'.
	 *
	 * @return string|array Modified notification content (string or array format).
	 */
	public function wbcom_format_new_group_pending_notifications( $action, $item_id, $secondary_item_id, $total_items, $format ) {
		$group = groups_get_group( $item_id );
	
		if ( ! $group ) {
			return $action;
		}

		$text = (1 === $total_items)
			? sprintf(__('New group "%s" is pending approval', 'bp-group-moderation'), $group->name)
			: sprintf(__('%d new groups are pending approval', 'bp-group-moderation'), $total_items);
		$link = admin_url( 'admin.php?page=bp-pending-groups' );

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
	public function wbcom_format_group_approved_notifications( $action, $item_id, $secondary_item_id, $total_items, $format ) {
		$group = groups_get_group( $item_id );
	
		if ( ! $group ) {
			return $action;
		}

		$text = sprintf( __( 'Your group "%s" has been approved!', 'bp-group-moderation' ), $group->name );
		$link = bp_get_group_permalink( $group );

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
	 * one or more new BuddyPress groups are rejected approval. The output is used 
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
	public function wbcom_format_group_rejected_notifications( $action, $item_id, $secondary_item_id, $total_items, $format ) {
		$group = groups_get_group( $item_id );
	
		if ( ! $group ) {
			return $action;
		}

		$text = __( 'Your group was not approved by site administrators.', 'bp-group-moderation' );
		$link = bp_get_loggedin_user_domain();

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
}