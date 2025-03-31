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
		// Only if notifications component is active.
		if ( ! bp_is_active( 'notifications' ) ) {
			return;
		}

		// Register notification filters.
		add_filter( 'bp_notifications_get_registered_components', array( $this, 'register_notifications_component' ) );
		add_filter( 'bp_notifications_get_notifications_for_user', array( $this, 'format_notifications' ), 10, 8 );
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
	 * Format the notifications for our component.
	 *
	 * @param string $action            The notification action.
	 * @param int    $item_id           The item ID.
	 * @param int    $secondary_item_id The secondary item ID.
	 * @param int    $total_items       The total number of items to format.
	 * @param string $format            The format to return. 'string' or 'object'.
	 * @param string $component_action  The component action.
	 * @param string $component_name    The component name.
	 * @param int    $id                The notification ID.
	 * @return string|object
	 */
	public function format_notifications( $action, $item_id, $secondary_item_id, $total_items, $format, $component_action, $component_name, $id ) {
		// Only handle groups component notifications.
		if ( 'groups' !== $component_name ) {
			return $action;
		}

		// Handle our specific component actions.
		$group_id = $item_id;
		$group = groups_get_group( $group_id );
		
		if ( ! $group ) {
			return $action;
		}

		// Format based on the component action.
		if ( 'new_group_pending' === $component_action ) {
			if ( 1 === $total_items ) {
				$text = sprintf( __( 'New group "%s" is pending approval', 'bp-group-moderation' ), $group->name );
				$link = admin_url( 'admin.php?page=bp-pending-groups' );
			} else {
				$text = sprintf( __( '%d new groups are pending approval', 'bp-group-moderation' ), $total_items );
				$link = admin_url( 'admin.php?page=bp-pending-groups' );
			}
		} elseif ( 'group_approved' === $component_action ) {
			$text = sprintf( __( 'Your group "%s" has been approved!', 'bp-group-moderation' ), $group->name );
			$link = bp_get_group_permalink( $group );
		} elseif ( 'group_rejected' === $component_action ) {
			$text = __( 'Your group was not approved by site administrators.', 'bp-group-moderation' );
			$link = bp_get_loggedin_user_domain();
		} else {
			return $action;
		}
		
		// WordPress Toolbar.
		if ( 'string' === $format ) {
			$return = '<a href="' . esc_url( $link ) . '">' . esc_html( $text ) . '</a>';
		} else {
			$return = array(
				'text' => $text,
				'link' => $link
			);
		}
		
		return $return;
	}
}