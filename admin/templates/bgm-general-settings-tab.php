<?php 


/**
 * Handles rendering and saving of the plugin's general settings.
 *
 * @package bp_group_moderation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
	// Exit if accessed directly.
}

$bpgrp_settings_notice = "display:none";
// Handle saving of settings.
if ( isset( $_POST['bp_group_moderation_save_settings'] ) && isset( $_POST[ 'bp_group_moderation_settings_nonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ 'bp_group_moderation_settings_nonce' ] ) ), 'bp_group_moderation_settings_nonce_action' ) ) {
	
	$bpgrp_settings_notice = "";

	$auto_approve_admin = isset( $_POST['bp_group_moderation_auto_approve_admin'] ) ? true : false;
	$send_emails        = isset( $_POST['bp_group_moderation_send_emails'] ) ? true : false;
	
	$bp_group_moderation_settings_update = array(
		'bp_group_moderation_auto_approve_admin' => $auto_approve_admin,
		'bp_group_moderation_send_emails'        => $send_emails
	);	

	update_option( 'bp_group_moderation_general_settings', $bp_group_moderation_settings_update );
}
		
// Get current settings.
$auto_approve_admin = bp_group_moderation_fetch_settings( 'bp_group_moderation_auto_approve_admin' );
$send_emails        = bp_group_moderation_fetch_settings( 'bp_group_moderation_send_emails' );

?>

<div class="wbcom-tab-content">
	<div class="wbcom-admin-title-section">
		<h3 class="wbcom-welcome-title"><?php esc_html_e( 'BuddyPress Group Moderation', 'bp-group-moderation' ); ?></h3>			
	</div><!-- .wbcom-welcome-head -->
	<div id="setting-error-bpgm_message" class="notice notice-success is-dismissible" style=<?php echo esc_attr( $bpgrp_settings_notice ); ?>> 
		<p><strong><?php esc_html_e( 'Settings saved successfully.', 'bp-group-moderation' ); ?></strong></p>
		<button type="button" class="notice-dismiss"></button>
	</div>
	<form method="post" action="">
        <?php wp_nonce_field( 'bp_group_moderation_settings_nonce_action', 'bp_group_moderation_settings_nonce' ); ?>
		<div class="wbcom-admin-option-wrap wbcom-admin-option-wrap-view">		
			<div class="form-table">
				<div class="wbcom-settings-section-wrap">
					<div class="wbcom-settings-section-options-heading">
						<label for="bp_group_moderation_auto_approve_admin"><?php esc_html_e( 'Auto-approve admin groups', 'bp-group-moderation' ); ?></label>
						<p class="description"><?php esc_html_e( 'Automatically approve groups created by site administrators without requiring manual moderation.', 'bp-group-moderation' ); ?></p>
					</div>
					<div class="wbcom-settings-section-options">
						<label class="wb-switch">
							<input name='bp_group_moderation_auto_approve_admin' type='checkbox' class="regular-text blpro-disp-resp-tr" value="1" <?php checked( $auto_approve_admin ); ?>/>
							<div class="wb-slider wb-round"></div>
						</label>
					</div>	
			    </div>	
			</div>
            <div class="form-table">
				<div class="wbcom-settings-section-wrap">
					<div class="wbcom-settings-section-options-heading">
						<label for="bp_group_moderation_send_emails"><?php esc_html_e( 'Send email notifications', 'bp-group-moderation' ); ?></label>
						<p class="description"><?php esc_html_e( 'Send email notifications related to moderation status updates.', 'bp-group-moderation' ); ?></p>
					</div>
					<div class="wbcom-settings-section-options">
						<label class="wb-switch">
							<input name='bp_group_moderation_send_emails' type='checkbox' class="regular-text blpro-disp-resp-tr" value="1" <?php checked( $send_emails ); ?>/>
							<div class="wb-slider wb-round"></div>
						</label>
					</div>	
			    </div>	
			</div>		
		</div>
		 <input type="submit" name="bp_group_moderation_save_settings" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'bp-group-moderation' ); ?>" />
	</form>
</div>

<?php