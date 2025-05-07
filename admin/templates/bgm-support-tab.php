<?php
/**
 * This file is used for rendering and saving plugin FAQ settings.
 *
 * @package bp_stats
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
	// Exit if accessed directly.
}
?>
<div class="wbcom-tab-content">      
<div class="wbcom-faq-adming-setting bp-stats-faq-support">
	<div class="wbcom-admin-title-section">
		<h3><?php esc_html_e( 'Frequently Asked Questions', 'bp-group-moderation' ); ?></h3>
	</div>
	<div class="wbcom-faq-admin-settings-block">
		<div id="wbcom-faq-settings-section" class="wbcom-faq-table">
			<div class="wbcom-faq-section-row">
				<div class="wbcom-faq-admin-row">
					<button class="wbcom-faq-accordion">
						<?php esc_html_e( 'Is this plugin compatible with Youzify?', 'bp-group-moderation' ); ?>
					</button>
					<div class="wbcom-faq-panel">
						<p><?php esc_html_e( 'Yes, the BuddyPress Group Moderation Plugin is fully compatible with Youzify. ', 'bp-group-moderation' ); ?></p>
					</div>
				</div>
			</div>
			<div class="wbcom-faq-section-row">
				<div class="wbcom-faq-admin-row">
					<button class="wbcom-faq-accordion">
						<?php esc_html_e( 'Can I approve groups without changing their visibility type?', 'bp-group-moderation' ); ?>
					</button>
					<div class="wbcom-faq-panel">
						<p><?php esc_html_e( 'Yes, when a group is approved, it automatically receives the visibility type (public, private, or hidden) that was originally requested by the creator.', 'bp-group-moderation' ); ?></p>
					</div>
				</div>
			</div>
			<div class="wbcom-faq-section-row">
				<div class="wbcom-faq-admin-row">
					<button class="wbcom-faq-accordion">
						<?php esc_html_e( 'Will pending groups appear in group directories?', 'bp-group-moderation' ); ?>
					</button>
					<div class="wbcom-faq-panel">
						<p><?php esc_html_e( 'By default, pending groups are hidden from regular users in group directories and activity streams. Only the group creator and site administrators can see pending groups.', 'bp-group-moderation' ); ?></p>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>
</div>