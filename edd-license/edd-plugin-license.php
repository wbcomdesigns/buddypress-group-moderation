<?php
// this is the URL our updater / license checker pings. This should be the URL of the site with EDD installed
if ( ! defined( 'EDD_GROUP_MODERATION_STORE_URL' ) ) {
	define( 'EDD_GROUP_MODERATION_STORE_URL', 'https://wbcomdesigns.com/' ); // you should use your own CONSTANT name, and be sure to replace it throughout this file
}

// the name of your product. This should match the download name in EDD exactly
if ( ! defined( 'EDD_GROUP_MODERATION_ITEM_NAME' ) ) {
	define( 'EDD_GROUP_MODERATION_ITEM_NAME', 'BuddyPress Group Moderation' ); // you should use your own CONSTANT name, and be sure to replace it throughout this file
}

// the settings screen the license form lives on: the License tab of WB Plugins > Group Moderation
if ( ! defined( 'EDD_GROUP_MODERATION_PLUGIN_LICENSE_PAGE' ) ) {
	define( 'EDD_GROUP_MODERATION_PLUGIN_LICENSE_PAGE', 'bp-group-moderation&tab=license' );
}

/**
 * Whether the current admin request is a screen that reads the license status from the store.
 *
 * @return bool
 */
function edd_group_moderation_is_license_screen() {
	global $pagenow;
	// phpcs:disable WordPress.Security.NonceVerification.Recommended -- read-only screen check.
	$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
	$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
	// phpcs:enable
	return 'plugins.php' === $pagenow || 'index.php' === $pagenow || 'wbcom-license-page' === $page || ( 'bp-group-moderation' === $page && 'license' === $tab );
}

if ( ! class_exists( 'EDD_GROUP_MODERATION_PLUGIN_UPDATER' ) ) {
	// load our custom updater.
	include dirname( __FILE__ ) . '/edd_group_moderation_plugin_updater.php';
}


function edd_group_moderation_plugin_updater() {
	// retrieve our license key from the DB.
	$license_key = trim( get_option( 'edd_wbcom_group_moderation_license_key' ) );

	// setup the updater
	$edd_updater = new EDD_GROUP_MODERATION_PLUGIN_UPDATER(
		EDD_GROUP_MODERATION_STORE_URL,
		BP_GROUP_MODERATION_PLUGIN_FILE,
		array(
			'version'   => BP_GROUP_MODERATION_VERSION,             // current version number.
			'license'   => $license_key,        // license key (used get_option above to retrieve from DB).
			'item_name' => EDD_GROUP_MODERATION_ITEM_NAME,  // name of this plugin.
			'author'    => 'wbcomdesigns',  // author of this plugin.
			'url'       => home_url(),
		)
	);
}
add_action( 'admin_init', 'edd_group_moderation_plugin_updater', 0 );


/************************************
 * the code below is just a standard
 * options page. Substitute with
 * your own.
 */
function edd_wbcom_group_moderation_register_option() {
	// creates our settings in the options table
	register_setting( 'edd_wbcom_group_moderation_license', 'edd_wbcom_group_moderation_license_key', 'edd_group_moderation_sanitize_license' );
}
add_action( 'admin_init', 'edd_wbcom_group_moderation_register_option' );

function edd_group_moderation_sanitize_license( $new ) {
	$old = get_option( 'edd_wbcom_group_moderation_license_key' );
	if ( $old && $old != $new ) {
		delete_option( 'edd_wbcom_group_moderation_license_status' ); // new license has been entered, so must reactivate
	}
	return $new;
}

/************************************
 * this illustrates how to activate
 * a license key
 *************************************/
function edd_wbcom_group_moderation_activate_license() {
	// listen for our activate button to be clicked
	if ( isset( $_POST['edd_group_moderation_license_activate'] ) ) {
		// run a quick security check
		if ( ! check_admin_referer( 'edd_wbcom_group_moderation_nonce', 'edd_wbcom_group_moderation_nonce' ) ) {
			return; // get out if we didn't click the Activate button
		}

		// retrieve the license from the database
		$license = isset( $_POST['edd_wbcom_group_moderation_license_key'] ) ? sanitize_text_field( wp_unslash( $_POST['edd_wbcom_group_moderation_license_key'] ) ) : '';

		// data to send in our API request
		$api_params = array(
			'edd_action' => 'activate_license',
			'license'    => $license,
			'item_name'  => urlencode( EDD_GROUP_MODERATION_ITEM_NAME ), // the name of our product in EDD
			'url'        => home_url(),
		);

		// Call the custom API.
		$response = wp_remote_post(
			EDD_GROUP_MODERATION_STORE_URL,
			array(
				'timeout'   => 15,
				'sslverify' => false,
				'body'      => $api_params,
			)
		);

		// make sure the response came back okay
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			if ( is_wp_error( $response ) ) {
				$message = $response->get_error_message();
			} else {
				$message = esc_html__( 'An error occurred, please try again.', 'buddypress-group-moderation' );
			}
		} else {
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			if ( false === $license_data->success ) {
				switch ( $license_data->error ) {
					case 'expired':
						$message = sprintf(
							esc_html__( 'Your license key expired on %s.', 'buddypress-group-moderation' ),
							date_i18n( get_option( 'date_format' ), strtotime( $license_data->expires, current_time( 'timestamp' ) ) )
						);
						break;

					case 'revoked':
						$message = esc_html__( 'Your license key has been disabled.', 'buddypress-group-moderation' );
						break;

					case 'missing':
						$message = esc_html__( 'Invalid license.', 'buddypress-group-moderation' );
						break;

					case 'invalid':
					case 'site_inactive':
						$message = esc_html__( 'Your license is not active for this URL.', 'buddypress-group-moderation' );
						break;

					case 'item_name_mismatch':
						$message = sprintf( esc_html__( 'This appears to be an invalid license key for %s.', 'buddypress-group-moderation' ), EDD_GROUP_MODERATION_ITEM_NAME );
						break;

					case 'no_activations_left':
						$message = esc_html__( 'Your license key has reached its activation limit.', 'buddypress-group-moderation' );
						break;

					default:
						$message = esc_html__( 'An error occurred, please try again.', 'buddypress-group-moderation' );
						break;
				}
			} else {
				set_transient( 'edd_wbcom_group_moderation_license_key_data', $license_data, 12 * HOUR_IN_SECONDS );
			}
		}

		// Check if anything passed on a message constituting a failure
		if ( ! empty( $message ) ) {
			$base_url = admin_url( 'admin.php?page=' . EDD_GROUP_MODERATION_PLUGIN_LICENSE_PAGE );
			$redirect = add_query_arg(
				array(
					'bgm_license_activation' => 'false',
					'message'             => urlencode( $message ),
				),
				$base_url
			);
			$license  = trim( $license );
			update_option( 'edd_wbcom_group_moderation_license_key', $license );
			update_option( 'edd_wbcom_group_moderation_license_status', $license_data->license );
			wp_redirect( $redirect );
			exit();
		}

		// $license_data->license will be either "valid" or "invalid"
		$license = trim( $license );
		update_option( 'edd_wbcom_group_moderation_license_key', $license );
		update_option( 'edd_wbcom_group_moderation_license_status', $license_data->license );
		wp_redirect( admin_url( 'admin.php?page=' . EDD_GROUP_MODERATION_PLUGIN_LICENSE_PAGE ) );
		exit();
	}
}
add_action( 'admin_init', 'edd_wbcom_group_moderation_activate_license' );


/***********************************************
 * Illustrates how to deactivate a license key.
 * This will decrease the site count
 ***********************************************/

function edd_wbcom_group_moderation_deactivate_license() {
	// listen for our activate button to be clicked
	if ( isset( $_POST['edd_group_moderation_license_deactivate'] ) ) {
		// run a quick security check
		if ( ! check_admin_referer( 'edd_wbcom_group_moderation_nonce', 'edd_wbcom_group_moderation_nonce' ) ) {
			return; // get out if we didn't click the Activate button
		}

		// retrieve the license from the database
		$license = trim( get_option( 'edd_wbcom_group_moderation_license_key' ) );

		// data to send in our API request
		$api_params = array(
			'edd_action' => 'deactivate_license',
			'license'    => $license,
			'item_name'  => urlencode( EDD_GROUP_MODERATION_ITEM_NAME ), // the name of our product in EDD
			'url'        => home_url(),
		);

		// Call the custom API.
		$response = wp_remote_post(
			EDD_GROUP_MODERATION_STORE_URL,
			array(
				'timeout'   => 15,
				'sslverify' => false,
				'body'      => $api_params,
			)
		);

		// make sure the response came back okay
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			if ( is_wp_error( $response ) ) {
				$message = $response->get_error_message();
			} else {
				$message = __( 'An error occurred, please try again.', 'buddypress-group-moderation' );
			}

			$base_url = admin_url( 'admin.php?page=' . EDD_GROUP_MODERATION_PLUGIN_LICENSE_PAGE );
			$redirect = add_query_arg(
				array(
					'bgm_license_activation' => 'false',
					'message'             => urlencode( $message ),
				),
				$base_url
			);

			wp_redirect( $redirect );
			exit();
		}

		// decode the license data
		$license_data = json_decode( wp_remote_retrieve_body( $response ) );
		delete_transient( 'edd_wbcom_group_moderation_license_key_data' );

		// $license_data->license will be either "deactivated" or "failed"
		if ( $license_data->license == 'deactivated' || 'failed' === $license_data->license ) {
			delete_option( 'edd_wbcom_group_moderation_license_status' );
		}

		wp_redirect( admin_url( 'admin.php?page=' . EDD_GROUP_MODERATION_PLUGIN_LICENSE_PAGE ) );
		exit();
	}
}
add_action( 'admin_init', 'edd_wbcom_group_moderation_deactivate_license' );


/************************************
 * this illustrates how to check if
 * a license key is still valid
 * the updater does this for you,
 * so this is only needed if you
 * want to do something custom
 *************************************/
add_action( 'admin_init', 'edd_wbcom_group_moderation_check_license' );
function edd_wbcom_group_moderation_check_license() {
	if ( edd_group_moderation_is_license_screen() ) {

		$license_data = get_transient( 'edd_wbcom_group_moderation_license_key_data' );
		$license      = trim( get_option( 'edd_wbcom_group_moderation_license_key' ) );

		if ( empty( $license_data ) && $license != '' ) {

			$api_params = array(
				'edd_action' => 'check_license',
				'license'    => $license,
				'item_name'  => urlencode( EDD_GROUP_MODERATION_ITEM_NAME ),
				'url'        => home_url(),
			);

			// Call the custom API.
			$response = wp_remote_post(
				EDD_GROUP_MODERATION_STORE_URL,
				array(
					'timeout'   => 15,
					'sslverify' => false,
					'body'      => $api_params,
				)
			);

			if ( is_wp_error( $response ) ) {
				return false;
			}

			$license_data = json_decode( wp_remote_retrieve_body( $response ) );

			if ( ! empty( $license_data ) ) {
				set_transient( 'edd_wbcom_group_moderation_license_key_data', $license_data, 12 * HOUR_IN_SECONDS );
			}
		}
	}
}

/**
 * This is a means of catching errors from the activation method above and displaying it to the customer
 */
function edd_wbcom_group_moderation_admin_notices() {
	$license_activation = filter_input( INPUT_GET, 'bgm_license_activation' ) ? filter_input( INPUT_GET, 'bgm_license_activation' ) : '';
	$error_message      = filter_input( INPUT_GET, 'message' ) ? filter_input( INPUT_GET, 'message' ) : '';
	$license_data       = get_transient( 'edd_wbcom_group_moderation_license_key_data' );
	$license            = trim( get_option( 'edd_wbcom_group_moderation_license_key' ) );

	// Ensure $license_data is an object before accessing properties.
	if ( $license_data && is_object( $license_data ) ) {
		if ( isset( $license_activation ) && ! empty( $error_message ) || ( ! empty( $license_data ) && $license_data->license == 'expired' ) ) {
			if ( $license_activation === '' ) {
				$license_activation = $license_data->license;
			}
			switch ( $license_activation ) {
				case 'expired':
					?>
					<div class="notice notice-error is-dismissible">
					<p>
					<?php
					$message = sprintf(
						/* translators: %1$s: Expire Time*/
						__( 'Your BuddyPress Group Moderation license key expired on %s.', 'buddypress-group-moderation' ),
						date_i18n( get_option( 'date_format' ), strtotime( $license_data->expires, current_time( 'timestamp' ) ) )
					);
					echo esc_html( $message );
					?>
					</p>
					</div>
					<?php
					break;
				case 'false':
					$message = urldecode( $error_message );
					?>
					<div class="error">
						<p><?php echo esc_html( $message ); ?></p>
					</div>
					<?php
					break;

				case 'true':
				default:
					// Success message if activation is successful.
					break;
			}
		}
	}

	if ( empty( $license ) ) {
		?>
		<div class="notice notice-error is-dismissible">
			<p>
			<?php
			echo esc_html__( 'Please activate your BuddyPress Group Moderation license key.', 'buddypress-group-moderation' );
			?>
			</p>			
		</div>
		<?php
	}
}
add_action( 'admin_notices', 'edd_wbcom_group_moderation_admin_notices' );

/**
 * Render the license form on the License tab of the settings screen.
 */
function wbcom_group_moderation_render_license_section() {

	$license        = (string) get_option( 'edd_wbcom_group_moderation_license_key', '' );
	$status         = get_option( 'edd_wbcom_group_moderation_license_status' );
	$license_output = edd_group_moderation_active_license_message();
	$remote_status  = ( is_array( $license_output ) && isset( $license_output['license_data']->license ) ) ? $license_output['license_data']->license : '';
	$message        = is_array( $license_output ) ? $license_output['message'] : '';

	if ( 'valid' === $status && 'valid' === $remote_status ) {
		$status_class = 'active';
		$status_text  = __( 'Active', 'buddypress-group-moderation' );
	} elseif ( 'expired' === $remote_status ) {
		$status_class = 'expired';
		$status_text  = __( 'Expired', 'buddypress-group-moderation' );
	} elseif ( 'invalid' === $remote_status ) {
		$status_class = 'invalid';
		$status_text  = __( 'Invalid', 'buddypress-group-moderation' );
	} else {
		$status_class = 'inactive';
		$status_text  = __( 'Inactive', 'buddypress-group-moderation' );
	}
	?>
	<form method="post" action="<?php echo esc_url( admin_url( 'options.php' ) ); ?>">
		<?php
		settings_fields( 'edd_wbcom_group_moderation_license' );
		wp_nonce_field( 'edd_wbcom_group_moderation_nonce', 'edd_wbcom_group_moderation_nonce' );
		?>
		<label class="bgm-field__label" for="edd_wbcom_group_moderation_license_key"><?php esc_html_e( 'License key', 'buddypress-group-moderation' ); ?></label>
		<div class="bgm-license__row">
			<input id="edd_wbcom_group_moderation_license_key" name="edd_wbcom_group_moderation_license_key" type="text" class="regular-text" value="<?php echo esc_attr( $license ); ?>" autocomplete="off" />
			<span class="bgm-license__status bgm-license__status--<?php echo esc_attr( $status_class ); ?>"><?php echo esc_html( $status_text ); ?></span>
			<?php if ( 'valid' === $status ) : ?>
				<input type="submit" class="button button-secondary" name="edd_group_moderation_license_deactivate" value="<?php esc_attr_e( 'Deactivate License', 'buddypress-group-moderation' ); ?>"/>
			<?php else : ?>
				<input type="submit" class="button button-primary" name="edd_group_moderation_license_activate" value="<?php esc_attr_e( 'Activate License', 'buddypress-group-moderation' ); ?>"/>
			<?php endif; ?>
		</div>
		<?php if ( $message ) : ?>
			<p class="bgm-license__message"><?php echo esc_html( $message ); ?></p>
		<?php endif; ?>
		<p class="bgm-license__message">
			<?php
			/* translators: %s: plugin version. */
			echo esc_html( sprintf( __( 'BuddyPress Group Moderation %s', 'buddypress-group-moderation' ), BP_GROUP_MODERATION_VERSION ) );
			?>
		</p>
	</form>
	<?php
}

/**
 * License activation message
 *
 * @return array $output store license data.
 */
function edd_group_moderation_active_license_message() {
	if ( edd_group_moderation_is_license_screen() ) {

		$license_data = get_transient( 'edd_wbcom_group_moderation_license_key_data' );
		$license      = trim( get_option( 'edd_wbcom_group_moderation_license_key' ) );

			$api_params = array(
				'edd_action' => 'check_license',
				'license'    => $license,
				'item_name'  => urlencode( EDD_GROUP_MODERATION_ITEM_NAME ),
				'url'        => home_url(),
			);

			// Call the custom API.
			$response = wp_remote_post(
				EDD_GROUP_MODERATION_STORE_URL,
				array(
					'timeout'   => 15,
					'sslverify' => false,
					'body'      => $api_params,
				)
			);

		if ( is_wp_error( $response ) ) {
			return false;
		}

			$output = array();
			$output['license_data'] = json_decode( wp_remote_retrieve_body( $response ) );
			$message = '';
			// make sure the response came back okay
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {

			if ( is_wp_error( $response ) ) {
				$message = $response->get_error_message();
			} else {
				$message = __( 'An error occurred, please try again.', 'buddypress-group-moderation' );
			}
		} else {
			$license_data = json_decode( wp_remote_retrieve_body( $response ) );
			// Get expire date
			$expires = false;
			if ( isset( $license_data->expires ) && 'lifetime' != $license_data->expires ) {
				$expires    = date_i18n( get_option( 'date_format' ), strtotime( $license_data->expires, current_time( 'timestamp' ) ) );
			} elseif ( isset( $license_data->expires ) && 'lifetime' == $license_data->expires ) {
				$expires = 'lifetime';
			}

			if ( isset( $license_data->license ) && 'valid' === $license_data->license ) {
				// Get site counts
				$site_count    = $license_data->site_count;
				$license_limit = $license_data->license_limit;
				$message = __( 'License key is active.', 'buddypress-group-moderation' );
				if ( isset( $expires ) && 'lifetime' != $expires ) {
					$message .= sprintf( __( ' Expires %s.', 'buddypress-group-moderation' ), $expires ) . ' ';
				}
				if ( $license_limit ) {
					$message .= sprintf( __( 'You have %1$s/%2$s-sites activated.', 'buddypress-group-moderation' ), $site_count, $license_limit );
				}
			}
		}
			$output['message'] = $message;
			return $output;
	}
}
