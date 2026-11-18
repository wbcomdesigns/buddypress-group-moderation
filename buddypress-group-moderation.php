<?php
/**
 * Plugin Name: BuddyPress Group Moderation
 * Plugin URI: https://wbcomdesigns.com/plugins/buddypress-group-moderation
 * Description: Introduces a moderation system for BuddyPress groups, requiring admin approval for new groups before they become active.
 * Version: 1.0.1
 * Requires PHP: 8.1
 * Author: Wbcom Designs
 * Author URI: https://wbcomdesigns.com
 * Text Domain: buddypress-group-moderation
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
if ( ! defined( 'BP_GROUP_MODERATION_VERSION' ) ) {
	define( 'BP_GROUP_MODERATION_VERSION', '1.0.1' );
}
if ( ! defined( 'BP_GROUP_MODERATION_PLUGIN_FILE' ) ) {
	define( 'BP_GROUP_MODERATION_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'BP_GROUP_MODERATION_PLUGIN_DIR' ) ) {
	define( 'BP_GROUP_MODERATION_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}
if ( ! defined( 'BP_GROUP_MODERATION_PLUGIN_URL' ) ) {
	define( 'BP_GROUP_MODERATION_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
}


// Include required files.
require_once BP_GROUP_MODERATION_PLUGIN_DIR . 'includes/bp-group-moderation-general-functions.php';
require_once BP_GROUP_MODERATION_PLUGIN_DIR . 'includes/class-bp-group-moderation.php';
require_once BP_GROUP_MODERATION_PLUGIN_DIR . 'includes/class-bp-group-moderation-admin.php';
require_once BP_GROUP_MODERATION_PLUGIN_DIR . 'includes/class-bp-group-moderation-notifications.php';
require_once BP_GROUP_MODERATION_PLUGIN_DIR . 'includes/admin/class-bp-group-moderation-settings.php';
/**
 * Plugin updates from the Wbcom store on the built-in free licence key. A partial upload or a
 * security scanner can strip edd-license/; the plugin keeps working without automatic updates.
 */
if ( file_exists( BP_GROUP_MODERATION_PLUGIN_DIR . 'edd-license/edd-plugin-license.php' ) ) {
	require_once BP_GROUP_MODERATION_PLUGIN_DIR . 'edd-license/edd-plugin-license.php';
} else {
	add_action( 'admin_notices', 'bp_group_moderation_license_files_missing_notice' );
}

/**
 * Tell site admins that the update files are missing.
 *
 * @since 1.0.1
 */
function bp_group_moderation_license_files_missing_notice() {
	if ( ! current_user_can( 'update_plugins' ) ) {
		return;
	}
	printf(
		'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
		esc_html__( 'BuddyPress Group Moderation: the update files (edd-license folder) are missing, so automatic updates are off. Please reinstall the plugin from wbcomdesigns.com.', 'buddypress-group-moderation' )
	);
}

/**
 * Whether this site runs BuddyPress (not BuddyBoss) with the Groups component on.
 *
 * @since 1.0.1
 * @return bool
 */
function bp_group_moderation_requirements_met() {
	return function_exists( 'buddypress' ) && function_exists( 'bp_is_active' ) && ! isset( buddypress()->buddyboss ) && bp_is_active( 'groups' );
}

/**
 * Initializes the plugin. Nothing is registered unless BuddyPress runs with the Groups component.
 */
function run_buddypress_group_moderation() {
	if ( bp_group_moderation_requirements_met() ) {
		BP_Group_Moderation::get_instance();
		BP_Group_Moderation_Admin::get_instance();
		BP_Group_Moderation_Notifications::get_instance();
	}
}
add_action( 'bp_include', 'run_buddypress_group_moderation' );

// The settings screen opens whatever the requirements; it explains what is missing instead of a dead end.
( new BP_Group_Moderation_Settings() )->register();

/**
 * Activation hook for the plugin.
 */
function bp_group_moderation_activate() {
	global $wpdb;

	// Deactivation moved this plugin's notifications out of sight; bring every member's back.
	$table = ( function_exists( 'bp_core_get_table_prefix' ) ? bp_core_get_table_prefix() : $wpdb->base_prefix ) . 'bp_notifications';
	if ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET component_name = %s WHERE component_name = %s", 'groups', 'bp_mod_groups' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from BuddyPress.
	}
}
register_activation_hook( __FILE__, 'bp_group_moderation_activate' );

/**
 * Deactivation hook for the plugin.
 *
 * Moves this plugin's notifications to a component nobody formats, so members do not see
 * blank notifications while it is off. Activation moves them back.
 */
function bp_group_moderation_deactivate() {
	global $wpdb;

	$table = ( function_exists( 'bp_core_get_table_prefix' ) ? bp_core_get_table_prefix() : $wpdb->base_prefix ) . 'bp_notifications';
	if ( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "UPDATE {$table} SET component_name = %s WHERE component_name = %s AND component_action IN ( 'new_group_pending', 'group_approved', 'group_rejected' )", 'bp_mod_groups', 'groups' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from BuddyPress.
	}

	// 1.0.0 scheduled an hourly check; make sure nothing is left behind.
	wp_clear_scheduled_hook( 'bp_group_moderation_hourly_check' );
}
register_deactivation_hook( __FILE__, 'bp_group_moderation_deactivate' );

/**
 * Displays an admin notice indicating that BuddyPress and the group component are required.
 * Also printed inside the settings screen, which hides other notices.
 */
function bp_group_moderation_required_plugin_admin_notice() {
	if ( ! current_user_can( 'manage_options' ) || bp_group_moderation_requirements_met() ) {
		return;
	}

	$plugin_name = esc_html__( 'BuddyPress Group Moderation', 'buddypress-group-moderation' );
	$bp_plugin   = esc_html__( 'BuddyPress', 'buddypress-group-moderation' );
	$bp_groups   = esc_html__( 'Groups Component', 'buddypress-group-moderation' );
	$message     = '';
	if ( ! class_exists( 'BuddyPress' ) ) {
		$message = sprintf(
			/* translators: 1: plugin name, 2: BuddyPress. */
			esc_html__( '%1$s requires %2$s to be installed and active.', 'buddypress-group-moderation' ),
			'<strong>' . esc_html( $plugin_name ) . '</strong>',
			'<strong>' . esc_html( $bp_plugin ) . '</strong>'
		);
	} elseif ( isset( buddypress()->buddyboss ) ) {
		$message = sprintf(
			/* translators: 1: plugin name, 2: BuddyPress. */
			esc_html__( '%1$s works with %2$s only and does nothing on BuddyBoss.', 'buddypress-group-moderation' ),
			'<strong>' . esc_html( $plugin_name ) . '</strong>',
			'<strong>' . esc_html( $bp_plugin ) . '</strong>'
		);
	} elseif ( ! bp_is_active( 'groups' ) ) {
		$message = sprintf(
			/* translators: 1: plugin name, 2: Groups Component. */
			esc_html__( '%1$s requires the %2$s to be enabled in the BuddyPress settings.', 'buddypress-group-moderation' ),
			'<strong>' . esc_html( $plugin_name ) . '</strong>',
			'<strong>' . esc_html( $bp_groups ) . '</strong>'
		);
	}

	echo '<div class="notice notice-warning"><p>' . wp_kses_post( $message ) . '</p></div>';
}
add_action( 'admin_notices', 'bp_group_moderation_required_plugin_admin_notice' );

/**
 * Redirects to the plugin settings page after activation.
 *
 * @param string $plugin The plugin slug.
 */
function bp_group_moderation_activation_redirect_settings( $plugin ) {
	if ( is_multisite() ) {
		return;
	}
	if ( plugin_basename( __FILE__ ) === $plugin && bp_group_moderation_requirements_met() ) {
		if ( isset( $_REQUEST['action'] ) && $_REQUEST['action'] === 'activate' && isset( $_REQUEST['plugin'] ) && $_REQUEST['plugin'] === $plugin ) { //phpcs:ignore
			wp_safe_redirect( admin_url( 'admin.php?page=bp-group-moderation' ) );
			exit;
		}
	}
}
add_action( 'activated_plugin', 'bp_group_moderation_activation_redirect_settings' );
