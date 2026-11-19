<?php
/**
 * General functions used across the plugin.
 *
 * @package BuddyPress_Group_Moderation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'bp_group_moderation_fetch_settings' ) ) {
	/**
	 * Read one General setting. A key that was never saved reads its default.
	 *
	 * @since 1.0.0
	 * @since 1.0.1 Defaults live here, so they hold however the plugin was activated.
	 *
	 * @param string $setting_key Setting key.
	 * @return bool
	 */
	function bp_group_moderation_fetch_settings( $setting_key ) {
		$defaults = array(
			'bp_group_moderation_auto_approve_admin'       => true,
			'bp_group_moderation_send_emails'              => true,
			'bp_group_moderation_delete_data_on_uninstall' => false,
		);

		$settings = get_option( 'bp_group_moderation_general_settings', array() );
		$settings = wp_parse_args( is_array( $settings ) ? $settings : array(), $defaults );

		return ! empty( $settings[ $setting_key ] );
	}
}
