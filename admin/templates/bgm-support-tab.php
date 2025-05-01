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
						<?php esc_html_e( 'Is this plugin compatible with BuddyBoss?', 'bp-group-moderation' ); ?>
					</button>
					<div class="wbcom-faq-panel">
						<p><?php esc_html_e( 'Yes, the BuddyPress Group Moderation Plugin works seamlessly with both BuddyPress and BuddyBoss platforms. It adjusts to the specific features and structure of each platform, allowing you to gather and analyze key community data.', 'bp-group-moderation' ); ?></p>
					</div>
				</div>
			</div>
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
		</div>
	</div>
</div>
</div>