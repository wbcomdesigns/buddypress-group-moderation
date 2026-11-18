<?php
/**
 * Plugin updates from the Wbcom EDD store on the built-in free licence key.
 *
 * The plugin is free: it ships one shared, pre-activated key, so there is no
 * licence screen, no stored key and no activation step. The store accepts the
 * key for this download because its free licence passthrough lists the
 * BuddyPress Group Moderation download id in wbcom_free_license_download_ids().
 *
 * @package BuddyPress_Group_Moderation
 */

defined( 'ABSPATH' ) || exit;

// The EDD store the updater asks for new versions.
if ( ! defined( 'EDD_GROUP_MODERATION_STORE_URL' ) ) {
	define( 'EDD_GROUP_MODERATION_STORE_URL', 'https://wbcomdesigns.com/' );
}

// The product name, matching the download name in EDD exactly.
if ( ! defined( 'EDD_GROUP_MODERATION_ITEM_NAME' ) ) {
	define( 'EDD_GROUP_MODERATION_ITEM_NAME', 'BuddyPress Group Moderation' );
}

defined( 'EDD_GROUP_MODERATION_FREE_LICENSE_KEY' ) || define( 'EDD_GROUP_MODERATION_FREE_LICENSE_KEY', '' ); // set to the free blanket licence key from the EDD store before building the release zip.

if ( ! class_exists( 'EDD_GROUP_MODERATION_PLUGIN_UPDATER' ) ) {
	include __DIR__ . '/edd_group_moderation_plugin_updater.php';
}

/**
 * Register the updater with the built-in key.
 *
 * Runs on init, not admin_init, so cron and WP-CLI update checks are covered too.
 * Without a key it registers nothing, so no store request is made.
 */
function edd_group_moderation_plugin_updater() {
	if ( '' === EDD_GROUP_MODERATION_FREE_LICENSE_KEY ) {
		return;
	}

	new EDD_GROUP_MODERATION_PLUGIN_UPDATER(
		EDD_GROUP_MODERATION_STORE_URL,
		BP_GROUP_MODERATION_PLUGIN_FILE,
		array(
			'version'   => BP_GROUP_MODERATION_VERSION,
			'license'   => EDD_GROUP_MODERATION_FREE_LICENSE_KEY,
			'item_name' => EDD_GROUP_MODERATION_ITEM_NAME,
			'author'    => 'wbcomdesigns',
			'url'       => home_url(),
		)
	);
}
add_action( 'init', 'edd_group_moderation_plugin_updater' );

/**
 * Delete the licence key, status and cached check stored by 1.0.0; the built-in key replaces them.
 *
 * Gated on the old options still existing, so it runs once and needs no version option.
 */
function edd_group_moderation_remove_legacy_licence_data() {
	if ( false === get_option( 'edd_wbcom_group_moderation_license_key' ) && false === get_option( 'edd_wbcom_group_moderation_license_status' ) ) {
		return;
	}

	delete_option( 'edd_wbcom_group_moderation_license_key' );
	delete_option( 'edd_wbcom_group_moderation_license_status' );
	delete_transient( 'edd_wbcom_group_moderation_license_key_data' );
}
add_action( 'admin_init', 'edd_group_moderation_remove_legacy_licence_data' );
