<?php
/**
 * General tab: moderation toggles and the uninstall data choice.
 *
 * Saves through options.php into bp_group_moderation_general_settings
 * (BP_Group_Moderation_Settings::sanitize()).
 *
 * @package BuddyPress_Group_Moderation
 * @since   1.0.1
 */

defined( 'ABSPATH' ) || exit;

$bgm_option = BP_Group_Moderation_Settings::OPTION;
$bgm_fields = array(
	'moderation' => array(
		'title'  => __( 'Moderation', 'buddypress-group-moderation' ),
		'fields' => array(
			'bp_group_moderation_auto_approve_admin' => array(
				'label' => __( 'Auto-approve admin groups', 'buddypress-group-moderation' ),
				'desc'  => __( 'Groups created by site administrators go live at once, without waiting in Pending Groups.', 'buddypress-group-moderation' ),
			),
			'bp_group_moderation_send_emails'        => array(
				'label' => __( 'Send email notifications', 'buddypress-group-moderation' ),
				'desc'  => __( 'Email administrators about new pending groups, and email creators when their group is approved or rejected.', 'buddypress-group-moderation' ),
			),
		),
	),
	'data'       => array(
		'title'  => __( 'Data', 'buddypress-group-moderation' ),
		'fields' => array(
			'bp_group_moderation_delete_data_on_uninstall' => array(
				'label' => __( 'Delete settings on uninstall', 'buddypress-group-moderation' ),
				'desc'  => __( 'When the plugin is deleted, also delete its settings and licence key. Leave off to keep them for a reinstall.', 'buddypress-group-moderation' ),
			),
		),
	),
);
?>
<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
	<?php settings_fields( BP_Group_Moderation_Settings::GROUP ); ?>

	<?php foreach ( $bgm_fields as $bgm_section ) : ?>
		<div class="bgm-card">
			<h2 class="bgm-card__title"><?php echo esc_html( $bgm_section['title'] ); ?></h2>
			<?php foreach ( $bgm_section['fields'] as $bgm_key => $bgm_field ) : ?>
				<div class="bgm-field">
					<div class="bgm-field__text">
						<label class="bgm-field__label" for="<?php echo esc_attr( $bgm_key ); ?>"><?php echo esc_html( $bgm_field['label'] ); ?></label>
						<p class="bgm-field__desc" id="<?php echo esc_attr( $bgm_key ); ?>-desc"><?php echo esc_html( $bgm_field['desc'] ); ?></p>
					</div>
					<input type="checkbox" class="bgm-switch" role="switch" id="<?php echo esc_attr( $bgm_key ); ?>" name="<?php echo esc_attr( $bgm_option . '[' . $bgm_key . ']' ); ?>" value="1" aria-describedby="<?php echo esc_attr( $bgm_key ); ?>-desc" <?php checked( bp_group_moderation_fetch_settings( $bgm_key ) ); ?>>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endforeach; ?>

	<div class="bgm-save-bar">
		<?php submit_button( __( 'Save Settings', 'buddypress-group-moderation' ), 'primary', 'submit', false ); ?>
	</div>
</form>
