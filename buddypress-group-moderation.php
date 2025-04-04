<?php
/**
 * Plugin Name: BuddyPress Group Moderation
 * Plugin URI: https://wbcomdesigns.com/plugins/buddypress-group-moderation
 * Description: Adds a moderation system to BuddyPress groups. New groups require admin approval before becoming active.
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
define( 'BP_GROUP_MODERATION_VERSION', '1.0.0' );
define( 'BP_GROUP_MODERATION_PLUGIN_FILE', __FILE__ );
define( 'BP_GROUP_MODERATION_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BP_GROUP_MODERATION_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include required files.
require_once BP_GROUP_MODERATION_PLUGIN_DIR . 'includes/class-bp-group-moderation.php';
require_once BP_GROUP_MODERATION_PLUGIN_DIR . 'includes/class-bp-group-moderation-admin.php';
require_once BP_GROUP_MODERATION_PLUGIN_DIR . 'includes/class-bp-group-moderation-notifications.php';
require_once BP_GROUP_MODERATION_PLUGIN_DIR . 'admin/wbcom/wbcom-admin-settings.php';


/**
 * Initialize the plugin.
 */
function bp_group_moderation_init() {
	BP_Group_Moderation::get_instance();
	BP_Group_Moderation_Admin::get_instance();
	BP_Group_Moderation_Notifications::get_instance();
}
add_action( 'plugins_loaded', 'bp_group_moderation_init' );

/**
 * Activation hook for the plugin.
 */
function bp_group_moderation_activate() {
	// Set default options.
	add_option( 'bp_group_moderation_auto_approve_admin', true );
	add_option( 'bp_group_moderation_hide_pending', true );
	add_option( 'bp_group_moderation_send_emails', true );
}
register_activation_hook( __FILE__, 'bp_group_moderation_activate' );

/**
 * Deactivation hook for the plugin.
 */
function bp_group_moderation_deactivate() {
	// Nothing to do here yet.
}
register_deactivation_hook( __FILE__, 'bp_group_moderation_deactivate' );

/**
 *  Check if buddypress and group component is activate.
 */
function bp_group_moderation_requires_buddypress() {

	if ( ! class_exists( 'BuddyPress' ) || ! bp_is_active( 'groups' ) ) {
		deactivate_plugins( plugin_basename( __FILE__ ) );
		add_action( 'admin_notices', 'bp_group_moderation_required_plugin_admin_notice' );
	}
}

add_action( 'admin_init', 'bp_group_moderation_requires_buddypress' );

/**
 * Admin notice to indicate BuddyPress and group component is required.
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
			esc_html__( '%1$s requires the %2$s to be active in BuddyPress settings.', 'bp-group-moderation' ),
			'<strong>' . esc_html( $plugin_name ) . '</strong>',
			'<strong>' . esc_html( $bp_groups ) . '</strong>'
		);
	}

	echo '<div class="error"><p>' . wp_kses_post( $message ) . '</p></div>';
}