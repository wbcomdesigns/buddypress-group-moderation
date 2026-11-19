<?php
/**
 * Settings page layout: header, sidebar navigation, active tab body.
 *
 * Set by BP_Group_Moderation_Settings::render_page():
 *
 * @var array  $bgm_tabs     Tabs, slug => label.
 * @var string $bgm_active   Active tab slug.
 * @var string $bgm_page_url Settings page URL.
 * @var string $bgm_view     Absolute path of the active tab view.
 * @var bool   $bgm_ready    BuddyPress with Groups is running (else the Pending Groups screen does not exist).
 * @var int    $bgm_pending  Pending group count.
 *
 * @package BuddyPress_Group_Moderation
 * @since   1.0.1
 */

defined( 'ABSPATH' ) || exit;

$bgm_icons = array(
	'general' => 'dashicons-admin-settings',
	'support' => 'dashicons-sos',
);
?>
<div class="wrap bgm-admin">
	<header class="bgm-page-header">
		<div class="bgm-page-header__title">
			<span class="dashicons dashicons-groups" aria-hidden="true"></span>
			<div>
				<h1><?php esc_html_e( 'BuddyPress Group Moderation', 'buddypress-group-moderation' ); ?></h1>
				<p class="bgm-page-header__subtitle"><?php esc_html_e( 'New groups wait for an administrator to approve them before members can see them.', 'buddypress-group-moderation' ); ?></p>
			</div>
		</div>
		<div class="bgm-page-header__actions">
			<?php if ( $bgm_ready ) : ?>
			<a class="bgm-btn bgm-btn--secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=bp-pending-groups' ) ); ?>">
				<?php esc_html_e( 'Pending Groups', 'buddypress-group-moderation' ); ?>
				<span class="bgm-count"><?php echo esc_html( number_format_i18n( $bgm_pending ) ); ?></span>
			</a>
			<?php endif; ?>
			<span class="bgm-version-pill">
				<?php
				/* translators: %s: plugin version. */
				echo esc_html( sprintf( __( 'Version %s', 'buddypress-group-moderation' ), BP_GROUP_MODERATION_VERSION ) );
				?>
			</span>
		</div>
	</header>
	<hr class="wp-header-end">
	<?php bp_group_moderation_required_plugin_admin_notice(); ?>

	<div class="bgm-layout">
		<nav class="bgm-sidebar" aria-label="<?php esc_attr_e( 'Group Moderation settings', 'buddypress-group-moderation' ); ?>">
			<?php foreach ( $bgm_tabs as $bgm_slug => $bgm_label ) : ?>
				<a href="<?php echo esc_url( add_query_arg( 'tab', $bgm_slug, $bgm_page_url ) ); ?>" class="bgm-nav-link<?php echo $bgm_active === $bgm_slug ? ' bgm-nav-link--active' : ''; ?>"<?php echo $bgm_active === $bgm_slug ? ' aria-current="page"' : ''; ?>>
					<span class="dashicons <?php echo esc_attr( isset( $bgm_icons[ $bgm_slug ] ) ? $bgm_icons[ $bgm_slug ] : 'dashicons-admin-generic' ); ?>" aria-hidden="true"></span>
					<?php echo esc_html( $bgm_label ); ?>
				</a>
			<?php endforeach; ?>
		</nav>

		<div class="bgm-main">
			<?php
			settings_errors();

			if ( file_exists( $bgm_view ) ) {
				include $bgm_view;
			}

			/** Fires after the active tab body, for tabs added through bp_group_moderation_admin_setting_tabs. */
			do_action( 'bp_group_moderation_tab_contents' );
			?>
		</div>
	</div>
</div>
