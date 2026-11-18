<?php
/**
 * License tab. The form, option keys and store calls live in edd-license/edd-plugin-license.php.
 *
 * @package BuddyPress_Group_Moderation
 * @since   1.0.1
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="bgm-card">
	<h2 class="bgm-card__title"><?php esc_html_e( 'Plugin License', 'buddypress-group-moderation' ); ?></h2>
	<p class="bgm-card__desc"><?php esc_html_e( 'An active licence key keeps plugin updates coming to this site.', 'buddypress-group-moderation' ); ?></p>
	<?php wbcom_group_moderation_render_license_section(); ?>
</div>
