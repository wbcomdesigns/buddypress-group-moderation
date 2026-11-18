<?php
/**
 * WB Plugins landing page, shown when this plugin created the shared menu.
 * Lists every Wbcom plugin page registered under it.
 *
 * @package BuddyPress_Group_Moderation
 * @since   1.0.1
 */

defined( 'ABSPATH' ) || exit;

$bgm_hub_pages = array();
foreach ( isset( $GLOBALS['submenu']['wbcomplugins'] ) ? (array) $GLOBALS['submenu']['wbcomplugins'] : array() as $bgm_entry ) {
	// Skip the landing page itself and the legacy wrapper helper pages (not plugins).
	if ( empty( $bgm_entry[2] ) || in_array( $bgm_entry[2], array( 'wbcomplugins', 'wbcom-plugins-page', 'wbcom-themes-page', 'wbcom-support-page', 'wbcom-license-page' ), true ) ) {
		continue;
	}
	$bgm_hub_pages[] = array(
		'title' => wp_strip_all_tags( (string) $bgm_entry[0] ),
		'url'   => admin_url( 'admin.php?page=' . rawurlencode( $bgm_entry[2] ) ),
	);
}
?>
<div class="wrap bgm-admin">
	<header class="bgm-page-header">
		<div class="bgm-page-header__title">
			<span class="dashicons dashicons-lightbulb" aria-hidden="true"></span>
			<div>
				<h1><?php esc_html_e( 'WB Plugins', 'buddypress-group-moderation' ); ?></h1>
				<p class="bgm-page-header__subtitle"><?php esc_html_e( 'Settings for every Wbcom Designs plugin on this site.', 'buddypress-group-moderation' ); ?></p>
			</div>
		</div>
	</header>
	<hr class="wp-header-end">

	<ul class="bgm-hub">
		<?php foreach ( $bgm_hub_pages as $bgm_hub_page ) : ?>
			<li>
				<a class="bgm-card bgm-hub__card" href="<?php echo esc_url( $bgm_hub_page['url'] ); ?>">
					<span class="dashicons dashicons-admin-plugins" aria-hidden="true"></span>
					<span class="bgm-hub__title"><?php echo esc_html( $bgm_hub_page['title'] ); ?></span>
					<span class="bgm-hub__cta"><?php esc_html_e( 'Open settings', 'buddypress-group-moderation' ); ?></span>
				</a>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
