<?php

/** File containing the general function used in the entire plugin code. */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * General function to retreive settings.
 * @param string $setting_key key whose value to be retrieved.
 * @return bool $bp_group_moderation_settings_value value of the setting.
 * 
 * @since 1.0.0
 * 
 */

if( ! function_exists( 'bp_group_moderation_fetch_settings' ) ) {
    
    function bp_group_moderation_fetch_settings( $setting_key ) {

        if( empty( $setting_key ) ) { 
            return false;
        }

        $bp_group_moderation_settings = !empty( get_option( 'bp_group_moderation_general_settings', true ) ) ? get_option( 'bp_group_moderation_general_settings', true ) : array();

        $bp_group_moderation_settings_value = !empty( $bp_group_moderation_settings ) ?  $bp_group_moderation_settings[ $setting_key ] : false;

        return $bp_group_moderation_settings_value;

    }
}

?>
