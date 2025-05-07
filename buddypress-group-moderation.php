<?php
/**
 * Plugin Name: BuddyPress Group Moderation
 * Plugin URI: https://wbcomdesigns.com/plugins/buddypress-group-moderation
 * Description: Introduces a moderation system for BuddyPress groups, requiring admin approval for new groups before they become active.
 * Version: 1.0.0
 * Author: Wbcom Designs
 * Author URI: https://wbcomdesigns.com
 * Text Domain: bp-group-moderation
 * Domain Path: /languages
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * @package BuddyPress_Group_Moderation
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
if( ! defined( 'BP_GROUP_MODERATION_VERSION' ) ) {
	define( 'BP_GROUP_MODERATION_VERSION', '1.0.0' );
}
if( ! defined( 'BP_GROUP_MODERATION_PLUGIN_FILE' ) ) {
	define( 'BP_GROUP_MODERATION_PLUGIN_FILE', __FILE__ );
}
if( ! defined( 'BP_GROUP_MODERATION_PLUGIN_DIR' ) ) {
	define( 'BP_GROUP_MODERATION_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if( ! defined( 'BP_GROUP_MODERATION_PLUGIN_URL' ) ) {
	define( 'BP_GROUP_MODERATION_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}


// Include required files.
include_once BP_GROUP_MODERATION_PLUGIN_DIR . 'includes/bp-group-moderation-general-functions.php';
require_once BP_GROUP_MODERATION_PLUGIN_DIR . 'includes/class-bp-group-moderation.php';
require_once BP_GROUP_MODERATION_PLUGIN_DIR . 'includes/class-bp-group-moderation-admin.php';
require_once BP_GROUP_MODERATION_PLUGIN_DIR . 'includes/class-bp-group-moderation-notifications.php';
require_once BP_GROUP_MODERATION_PLUGIN_DIR . 'admin/wbcom/wbcom-admin-settings.php';


/**
 * Initializes the plugin.
 */
function run_buddypress_group_moderation() {
	if( function_exists( 'bp_is_active' ) && bp_is_active( 'groups' ) ) {
		BP_Group_Moderation::get_instance();
		BP_Group_Moderation_Admin::get_instance();
		BP_Group_Moderation_Notifications::get_instance();
	}
}

/**
 * Checks that BuddyPress/Buddyboss is completely initialized.
 * After that initialize the plugin.
 */
function bp_group_moderation_init() {
	if ( has_action( 'bp_loaded' ) ) {
		add_action( 'bp_include', 'run_buddypress_group_moderation' );
	} elseif ( has_action( 'bbp_loaded' ) ) {
		add_action( 'bbp_includes', 'run_buddypress_group_moderation' );
	}
}
add_action( 'plugins_loaded', 'bp_group_moderation_init' );

/**
 * Activation hook for the plugin.
 */
function bp_group_moderation_activate() {
	
	if( class_exists( 'BuddyPress' ) && bp_is_active( 'groups' ) ) {
		
		global $wpdb;

		$modified_unread_notifications = $wpdb->get_results($wpdb->prepare( "SELECT * FROM {$wpdb->prefix}bp_notifications WHERE `user_id`=%d AND `component_name`=%s", bp_loggedin_user_id(), 'bp_mod_groups' ));

		if( is_array( $modified_unread_notifications ) && !empty( $modified_unread_notifications ) ) {
			$actions = array( 'new_group_pending', 'group_approved', 'group_rejected' );
			foreach( $actions as $action ) { 
				
				$modified_notification_component = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}bp_notifications SET component_name = %s WHERE component_action=%s AND component_name=%s", 'groups', $action, 'bp_mod_groups' ) );
			}
			
		}

		$bp_grp_moderation_settings_array = array(  
			'bp_group_moderation_auto_approve_admin' => true,
			'bp_group_moderation_send_emails'        => true
		);

		// Set default options.	
		add_option( 'bp_group_moderation_general_settings', $bp_grp_moderation_settings_array );
		
	}
}
register_activation_hook( __FILE__, 'bp_group_moderation_activate' );

/**
 * Deactivation hook for the plugin.
 *
 * To avoid blank notification formatting on plugin deactivation,
 * this modifies the notification component of plugin-generated notifications.
 */
function bp_group_moderation_deactivate() {
	
	/**
	 * To avoid blank notification formatting.
	 * On plugin deactivation modified the notification component of plugin generated notifications.
	 * 
	*/
	global $wpdb;
	$actions = array( 'new_group_pending', 'group_approved', 'group_rejected' );
	foreach( $actions as $action ) {
		$update_notification_component_name = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->prefix}bp_notifications SET component_name = %s WHERE component_action=%s", 'bp_mod_groups', $action ) );
	}
}
register_deactivation_hook( __FILE__, 'bp_group_moderation_deactivate' );

/**
 *  Check if BuddyPress and the group component are activated.
 */
function bp_group_moderation_requires_buddypress() {

	if ( ! class_exists( 'BuddyPress' ) || ! bp_is_active( 'groups' ) || ( function_exists('buddypress') && isset( buddypress()->buddyboss ) ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		add_action( 'admin_notices', 'bp_group_moderation_required_plugin_admin_notice' );
	}
}

add_action( 'admin_init', 'bp_group_moderation_requires_buddypress' );

/**
 * Displays an admin notice indicating that BuddyPress and the group component are required.
 */
function bp_group_moderation_required_plugin_admin_notice() {
	$plugin_name = esc_html__( 'BuddyPress Group Moderation', 'bp-group-moderation' );
	$bp_plugin   = esc_html__( 'BuddyPress', 'bp-group-moderation' );
	$bp_groups   = esc_html__( 'Groups Component', 'bp-group-moderation' );
	if ( ! class_exists( 'BuddyPress' ) ) {
		$message = sprintf(
			esc_html__( '%1$s requires %2$s to be installed and active.', 'bp-group-moderation' ),
			'<strong>' . esc_html( $plugin_name ) . '</strong>',
			'<strong>' . esc_html( $bp_plugin ) . '</strong>'
		);
	} elseif ( ! bp_is_active( 'groups' ) ) {
		$message = sprintf(
			esc_html__( '%1$s requires the %2$s to be enabled in the BuddyPress settings.', 'bp-group-moderation' ),
			'<strong>' . esc_html( $plugin_name ) . '</strong>',
			'<strong>' . esc_html( $bp_groups ) . '</strong>'
		);
	}

	echo '<div class="error"><p>' . wp_kses_post( $message ) . '</p></div>';
}

/**
 * Redirects to the plugin settings page after activation.
 *
 * @param string $plugin The plugin slug.
 */
function bp_group_moderation_activation_redirect_settings( $plugin ) {
    if ( is_multisite() ) {
       return;
    }
   if ( plugin_basename( __FILE__ ) === $plugin && class_exists( 'BuddyPress' ) && bp_is_active( 'groups' ) ) {
       if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] === 'activate' && isset( $_REQUEST['plugin'] ) && $_REQUEST['plugin'] === $plugin ) { //phpcs:ignore
           wp_redirect( admin_url( 'admin.php?page=bp-group-moderation' ) );
           exit;
       }
   }
}
add_action( 'activated_plugin', 'bp_group_moderation_activation_redirect_settings' );