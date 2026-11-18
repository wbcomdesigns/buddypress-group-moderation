<?php
/**
 * Uninstall: delete the plugin's settings, and the licence data 1.0.0 stored, only when the site owner opted in
 * (General > Data > Delete settings on uninstall, off by default).
 *
 * @package BuddyPress_Group_Moderation
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

wp_clear_scheduled_hook( 'bp_group_moderation_hourly_check' );

$bgm_settings = get_option( 'bp_group_moderation_general_settings', array() );

if ( is_array( $bgm_settings ) && ! empty( $bgm_settings['bp_group_moderation_delete_data_on_uninstall'] ) ) {
	delete_option( 'bp_group_moderation_general_settings' );
	delete_option( 'edd_wbcom_group_moderation_license_key' );
	delete_option( 'edd_wbcom_group_moderation_license_status' );
	delete_transient( 'edd_wbcom_group_moderation_license_key_data' );
}
