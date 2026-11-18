<?php
/**
 * Support tab: FAQ and help links.
 *
 * @package BuddyPress_Group_Moderation
 * @since   1.0.1
 */

defined( 'ABSPATH' ) || exit;

$bgm_faqs = array(
	__( 'Can I approve groups without changing their visibility type?', 'buddypress-group-moderation' ) => __( 'Yes. When a group is approved it gets the visibility (public, private or hidden) its creator originally asked for.', 'buddypress-group-moderation' ),
	__( 'Will pending groups appear in group directories?', 'buddypress-group-moderation' ) => __( 'No. Pending groups are hidden from members in group directories and activity streams. Only the group creator and site administrators can see them.', 'buddypress-group-moderation' ),
	__( 'Where do I approve or reject groups?', 'buddypress-group-moderation' )           => __( 'Under Groups > Pending Groups. You can approve or reject one group at a time, or tick several and approve them together.', 'buddypress-group-moderation' ),
	__( 'Is this plugin compatible with Youzify?', 'buddypress-group-moderation' )       => __( 'Yes. The pending notice shows on Youzify group pages too.', 'buddypress-group-moderation' ),
);
?>
<div class="bgm-card">
	<h2 class="bgm-card__title"><?php esc_html_e( 'Frequently Asked Questions', 'buddypress-group-moderation' ); ?></h2>
	<?php foreach ( $bgm_faqs as $bgm_q => $bgm_a ) : ?>
		<details class="bgm-faq">
			<summary><?php echo esc_html( $bgm_q ); ?></summary>
			<p><?php echo esc_html( $bgm_a ); ?></p>
		</details>
	<?php endforeach; ?>
</div>

<div class="bgm-card">
	<h2 class="bgm-card__title"><?php esc_html_e( 'Still need help?', 'buddypress-group-moderation' ); ?></h2>
	<p class="bgm-card__desc"><?php esc_html_e( 'The documentation covers every feature, and our support team is ready if you need a hand.', 'buddypress-group-moderation' ); ?></p>
	<div class="bgm-save-bar">
		<a class="bgm-btn bgm-btn--secondary" href="https://docs.wbcomdesigns.com/doc_category/bp-group-moderation/" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Read Documentation', 'buddypress-group-moderation' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'buddypress-group-moderation' ); ?></span>
		</a>
		<a class="bgm-btn bgm-btn--secondary" href="https://wbcomdesigns.com/support/" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Get Support', 'buddypress-group-moderation' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'buddypress-group-moderation' ); ?></span>
		</a>
		<a class="bgm-btn bgm-btn--secondary" href="https://wbcomdesigns.com/submit-review/" target="_blank" rel="noopener noreferrer">
			<?php esc_html_e( 'Send Feedback', 'buddypress-group-moderation' ); ?><span class="screen-reader-text"> <?php esc_html_e( '(opens in a new tab)', 'buddypress-group-moderation' ); ?></span>
		</a>
	</div>
</div>
