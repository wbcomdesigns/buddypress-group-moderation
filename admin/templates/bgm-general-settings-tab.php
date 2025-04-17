<?php 


/**
 * This file is used for rendering and saving plugin general settings.
 *
 * @package bp_group_moderation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
	// Exit if accessed directly.
}

// Process settings save.
if ( isset( $_POST['bp_group_moderation_save_settings'] ) && isset( $_POST[ 'bp_group_moderation_settings_nonce' ] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ 'bp_group_moderation_settings_nonce' ] ) ), 'bp_group_moderation_settings_nonce_action' ) ) {
	   	
	$auto_approve_admin = isset( $_POST['bp_group_moderation_auto_approve_admin'] ) ? 1 : 0;
	$hide_pending = isset( $_POST['bp_group_moderation_hide_pending'] ) ? 1 : 0;
	$send_emails = isset( $_POST['bp_group_moderation_send_emails'] ) ? 1 : 0;
	
	update_option( 'bp_group_moderation_auto_approve_admin', $auto_approve_admin );
	update_option( 'bp_group_moderation_hide_pending', $hide_pending );
	update_option( 'bp_group_moderation_send_emails', $send_emails );

}
		
// Get current settings.
$auto_approve_admin = get_option( 'bp_group_moderation_auto_approve_admin', true );
$hide_pending       = get_option( 'bp_group_moderation_hide_pending', true );
$send_emails        = get_option( 'bp_group_moderation_send_emails', true );
		
?>

<div class="wbcom-tab-content">
	<div class="wbcom-admin-title-section">
		<h3 class="wbcom-welcome-title"><?php esc_html_e( 'BuddyPress Group Moderation', 'bp-group-moderation' ); ?></h3>			
	</div><!-- .wbcom-welcome-head -->
	<form method="post" action="" class="">
        <?php wp_nonce_field( 'bp_group_moderation_settings_nonce_action', 'bp_group_moderation_settings_nonce' ); ?>
		<div class="wbcom-admin-option-wrap wbcom-admin-option-wrap-view">		
			<div class="form-table">
				<div class="wbcom-settings-section-wrap">
					<div class="wbcom-settings-section-options-heading">
						<label for="bp_group_moderation_auto_approve_admin"><?php esc_html_e( 'Auto-approve admin groups', 'bp-group-moderation' ); ?></label>
						<p class="description"><?php esc_html_e( 'Automatically approve groups created by site administrators.', 'bp-group-moderation' ); ?></p>
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
						<label for="bp_group_moderation_hide_pending"><?php esc_html_e( 'Hide pending groups', 'bp-group-moderation' ); ?></label>
						<p class="description"><?php esc_html_e( 'Set pending groups to hidden status until approved.', 'bp-group-moderation' ); ?></p>
					</div>
					<div class="wbcom-settings-section-options">
						<label class="wb-switch">
							<input name='bp_group_moderation_hide_pending' type='checkbox' class="regular-text blpro-disp-resp-tr" value="1" <?php checked( $hide_pending ); ?>/>
							<div class="wb-slider wb-round"></div>
						</label>
					</div>	
			    </div>	
			</div>	
            <div class="form-table">
				<div class="wbcom-settings-section-wrap">
					<div class="wbcom-settings-section-options-heading">
						<label for="bp_group_moderation_send_emails"><?php esc_html_e( 'Send email notifications', 'bp-group-moderation' ); ?></label>
						<p class="description"><?php esc_html_e( 'Send email notifications in addition to BuddyPress notifications.', 'bp-group-moderation' ); ?></p>
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