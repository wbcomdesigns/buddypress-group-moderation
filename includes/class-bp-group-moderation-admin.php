<?php
/**
 * Admin class for BuddyPress Group Moderation.
 *
 * @package BuddyPress_Group_Moderation
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class for admin functionality.
 */
class BP_Group_Moderation_Admin {

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Get the singleton instance of this class.
	 *
	 * @return object A single instance of this class.
	 */
	public static function get_instance() {
		// If the single instance hasn't been set, set it now.
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Initialize the class.
	 */
	private function __construct() {		
		// Admin hooks.
		add_action( 'bp_admin_menu', array( $this, 'bp_group_moderation_add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'bp_group_moderation_admin_enqueue_scripts' ) );
		
		// AJAX handlers.
		add_action( 'wp_ajax_bp_group_moderation_handle_group', array( $this, 'bp_group_moderation_ajax_handle_group' ) );

		add_action( 'admin_menu', array( $this,'bp_group_moderation_add_plugin_settings_page' ) );
	}

	/**
	 * Get group URL in a backward-compatible way.
	 *
	 * @param BP_Groups_Group $group The group object.
	 * @return string
	 */
	protected static function bp_group_moderation_get_group_url( $group ) {
		if ( function_exists( 'bp_get_group_url' ) ) {
			return bp_get_group_url( $group );
		} elseif ( function_exists( 'bp_get_group_permalink' ) ) {
			return bp_get_group_permalink( $group );
		}
		return '';
	}


	/**
	 * Add admin sub menu for plugin settings.
	 *
	 * @since 1.0.0
	 */
	public function bp_group_moderation_add_plugin_settings_page() {			
		if ( empty( $GLOBALS['admin_page_hooks']['wbcomplugins'] ) ) {

			add_menu_page( esc_html__( 'WB Plugins', 'bp-group-moderation' ), esc_html__( 'WB Plugins', 'bp-group-moderation' ), 'manage_options', 'wbcomplugins', array( $this, 'bp_group_moderation_settings_page' ), 'dashicons-lightbulb', 59 );

			add_submenu_page( 'wbcomplugins', esc_html__( 'General', 'bp-group-moderation' ), esc_html__( 'General', 'bp-group-moderation' ), 'manage_options', 'wbcomplugins' );
		}
		add_submenu_page( 'wbcomplugins', esc_html__( 'BuddyPress Group Moderation Settings Page', 'bp-group-moderation' ), esc_html__( 'Group Moderation', 'bp-group-moderation' ), 'manage_options', 'bp-group-moderation', array( $this, 'bp_group_moderation_settings_page' ) );
	}

	/**
	 * Callable function for settings page.
	 *
	 * @since 1.0.0
	 */
	public function bp_group_moderation_settings_page() {
		$current          = filter_input( INPUT_GET, 'tab' ) ? filter_input( INPUT_GET, 'tab' ) : 'welcome';
		$group_mod_tabs = apply_filters(
			'bp_group_moderation_admin_setting_tabs',
			array(
				'welcome'                => __( 'Welcome', 'bp-group-moderation' ),
				'general'                => __( 'General', 'bp-group-moderation' ),
				'support'                => __( 'Support', 'bp-group-moderation' ),
			)
		);
		?>
		<div class="wrap">
			<div class="wbcom-bb-plugins-offer-wrapper">
				<div id="wb_admin_logo">

				</div>
			</div>
			<div class="wbcom-wrap bp-group-moderation-wrap">
				<div class="blpro-header">
					<div class="wbcom_admin_header-wrapper">
						<div id="wb_admin_plugin_name">
							<?php							
								esc_html_e( 'BuddyPress Group Moderation', 'bp-group-moderation' );
							?>
							<span>
							<?php
								// translators: %s is replaced with the plugin version
								printf( esc_html__( 'Version %s', 'bp-group-moderation' ), esc_html( BP_GROUP_MODERATION_VERSION ) );
							?>
							</span>
						</div>
						<?php echo do_shortcode( '[wbcom_admin_setting_header]' ); ?>
					</div>
				</div>
				<div class="wbcom-admin-settings-page">
					<div class="wbcom-tabs-section">
						<div class="nav-tab-wrapper">
							<div class="wb-responsive-menu">
								<span><?php esc_html_e( 'Menu', 'bp-group-moderation' ); ?></span>
								<input class="wb-toggle-btn" type="checkbox" id="wb-toggle-btn">
								<label class="wb-toggle-icon" for="wb-toggle-btn">
									<span class="wb-icon-bars"></span>
								</label>
							</div>
							<ul>
							<?php
							foreach ( $group_mod_tabs as $group_mod_tab => $group_mod_name ) {
								$class     = ( $group_mod_tab == $current ) ? 'nav-tab-active' : '';
								$bp_group_moderation_nonce = wp_create_nonce( 'bp_group_moderation_nonce' );
								echo '<li id="' . esc_attr( $group_mod_tab ) . '"><a class="nav-tab ' . esc_attr( $class ) . '" href="admin.php?page=bp-group-moderation&tab=' . esc_attr( $group_mod_tab ) . '&nonce=' . esc_attr( $bp_group_moderation_nonce ) . '">' . esc_html( $group_mod_name ) . '</a></li>';
							}
							?>
							</ul>
						</div>
					</div>
					<?php
					include BP_GROUP_MODERATION_PLUGIN_DIR . 'admin/templates/bp-group_moderation-options-page.php';
					do_action( 'bp_group_moderation_tab_contents' );
					?>
				</div>
			</div> <!-- closing div class wbcom-wrap -->
		</div> <!-- closing div class wrap -->
		<?php
	}

	/**
	 * Add admin menu for managing pending groups.
	 */
	public function bp_group_moderation_add_admin_menu() {
		add_submenu_page(
			'bp-groups',
			__( 'Pending Groups', 'bp-group-moderation' ),
			__( 'Pending Groups', 'bp-group-moderation' ),
			'manage_options',
			'bp-pending-groups',
			array( $this, 'bp_group_moderation_admin_page_content' )
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook The current admin page.
	 */
	public function bp_group_moderation_admin_enqueue_scripts( $hook ) {		
		if ( strpos( $hook, 'bp-pending-groups' ) === false && strpos( $hook, 'bp-group-moderation' ) === false ) {
			return;
		}

		wp_enqueue_style( 'bp-group-moderation-admin', BP_GROUP_MODERATION_PLUGIN_URL . 'assets/css/bp-group-moderation-admin.css', array(), BP_GROUP_MODERATION_VERSION );
		wp_enqueue_script(
			'bp-group-moderation-admin',
			BP_GROUP_MODERATION_PLUGIN_URL . 'assets/js/bp-group-moderation-admin.js',
			array( 'jquery' ),
			BP_GROUP_MODERATION_VERSION,
			true
		);
		
		wp_localize_script( 'bp-group-moderation-admin', 'bpGroupModeration', array(
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'bp_group_moderation_nonce' ),
			'approveText'   => __( 'Approve', 'bp-group-moderation' ),
			'rejectText'    => __( 'Reject', 'bp-group-moderation' ),
			'confirmReject' => __( 'Are you sure you want to reject this group? This action cannot be undone.', 'bp-group-moderation' ),
			'loadingText'   => __( 'Processing...', 'bp-group-moderation' ),
		) );
		
	}

	/**
	 * Handle AJAX requests for group moderation actions.
	 */
	public function bp_group_moderation_ajax_handle_group() {
		
		if ( defined( 'DOING_AJAX' ) && DOING_AJAX ) {
			@error_reporting( 0 );
		}
		
		// Verify nonce.
		check_ajax_referer( 'bp_group_moderation_nonce', 'nonce' );
		
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'bp-group-moderation' ) ) );
		}
		
		$group_id = isset( $_POST['group_id'] ) ? intval( $_POST['group_id'] ) : 0;
		$action = isset( $_POST['action_type'] ) ? sanitize_text_field( wp_unslash( $_POST['action_type'] ) ) : '';
		
		if ( ! $group_id || ! in_array( $action, array( 'approve', 'reject' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'bp-group-moderation' ) ) );
		}
		
		$result = false;
		$message = '';
		
		// Process the action.
		if ( 'approve' === $action ) {
			$result = $this->bp_group_moderation_approve_group( $group_id );
			$message = __( 'Group approved successfully.', 'bp-group-moderation' );
		} elseif ( 'reject' === $action ) {
			$result = $this->bp_group_moderation_reject_group( $group_id );
			$message = __( 'Group has been rejected.', 'bp-group-moderation' );
		}
		
		if ( $result ) {
			wp_send_json_success( array( 'message' => $message ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'An error occurred while processing the request.', 'bp-group-moderation' ) ) );
		}
	}

	/**
	 * Approve a pending group.
	 *
	 * @param int $group_id The group ID.
	 * @return bool Success status.
	 */
	public function bp_group_moderation_approve_group( $group_id ) {
		global $wpdb ;
		// Get the originally requested status.
		$requested_status = groups_get_groupmeta( $group_id, 'requested_status', true );

		$result  = false;

		// Get the group object.
		$group = groups_get_group( $group_id );
		
		if ( ! $group ) {
			return false;
		}
		
		if( 'hidden' !== $requested_status ) {
			
			// Update the group to the requested status.		
			$table_name = $wpdb->prefix . 'bp_groups';
			$result     = $wpdb->update(
				$table_name,
				array( 'status' => $requested_status ),
				array( 'id' => $group_id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		if ( $result || ( 'hidden' === $requested_status )  ) {
			// Remove the pending flag.
			groups_delete_groupmeta( $group_id, 'approval_status' );
			
			// Notify the group creator.
			$this->bp_group_moderation_notify_user_of_group_decision( $group, 'approved' );
			
			return true;
		}
		
		return false;
	}

	/**
	 * Reject a pending group.
	 *
	 * @param int $group_id The group ID.
	 * @return bool Success status.
	 */
	public function bp_group_moderation_reject_group( $group_id ) {
		// Get group info before deletion.
		$group = groups_get_group( $group_id );
		
		if ( ! $group ) {
			return false;
		}
		
		// Notify the creator before deleting the group.
		$this->bp_group_moderation_notify_user_of_group_decision( $group, 'rejected' );
		
		// Delete the group.
		$result = groups_delete_group( $group_id );
		
		return $result;
	}

	/**
	 * Notify a user about their group's approval status.
	 *
	 * @param BP_Groups_Group $group The group object.
	 * @param string          $decision 'approved' or 'rejected'.
	 */
	public function bp_group_moderation_notify_user_of_group_decision( $group, $decision ) {
		if ( ! in_array( $decision, array( 'approved', 'rejected' ) ) ) {
			return;
		}
		
		$creator_id = $group->creator_id;
		$group_name = $group->name;
		$group_id = $group->id;
		
		// Add BuddyPress notification.
		if ( bp_is_active( 'notifications' ) ) {
			bp_notifications_add_notification( array(
				'user_id'           => $creator_id,
				'item_id'           => $group_id,
				'secondary_item_id' => $group->creator_id,
				'component_name'    => 'groups',
				'component_action'  => 'group_' . $decision,
				'date_notified'     => bp_core_current_time(),
				'is_new'            => 1,
			) );
		}
		
		// Send email notification if enabled.
		$send_email = bp_group_moderation_fetch_settings( 'bp_group_moderation_send_emails' );
		if ( $send_email ) {
			$creator = get_userdata( $creator_id );
			
			if ( 'approved' === $decision ) {
				$subject = sprintf( __( 'Your Group Has Been Approved : %s', 'bp-group-moderation' ), $group_name );
				$message = sprintf( __( "<p><strong>Dear  %s </strong></p>
					<p> We’re happy to let you know that your group, <strong>“%s”</strong>, has been reviewed and approved by our moderation team!</p>
					<p> Your group is now live and visible to the community. You can start inviting members, sharing updates, and building discussions right away. </p>
					<p><strong>View your group : </strong></p>
					<p><a href='%s'>%s</a></p>
					<p>Thank you for contributing to our community. If you have any questions or need help managing your group, feel free to reach out to us.</p>
					<p><strong>Warm Regards,</strong></p>
					<p><strong>The %s Team</strong></p>", 'bp-group-moderation' ),					
					
					esc_html( bp_core_get_user_displayname( $creator_id ) ),
					esc_html( $group_name ),
					esc_url( self::bp_group_moderation_get_group_url( $group ) ),
					esc_url( self::bp_group_moderation_get_group_url( $group ) ),
					esc_html( get_bloginfo( 'name' ) )
				);

			} else {
				$subject = sprintf( __( 'Group Submission Not Approved : %s', 'bp-group-moderation' ), $group_name );
				$message = sprintf( __( "<p><strong>Dear  %s </strong></p>
					<p>Thank you for submitting your group, <strong>“%s”</strong>, to our community platform. </p>
					<p> After a thorough review, we regret to inform you that your group submission has not been approved by our moderation team at this time.</p>
					<p>If you would like feedback on your submission or wish to explore how it could be revised to meet our community guidelines, please feel free to contact our support team.</p>
					<p>We truly appreciate your involvement and hope you’ll continue to be an engaged member of our community.</p>
					<p><strong>Warm regards,</strong></p>
					<p><strong>The %s Team</strong></p>" , 'bp-group-moderation' ),					
					
					esc_html( bp_core_get_user_displayname( $creator_id ) ),
					esc_html( $group_name ),
					esc_html( get_bloginfo( 'name' ) )
				);
			}
			
			if( !empty(  $group->name ) && !empty( $creator_id ) ) {
				update_user_meta( $creator_id, 'bp_grp_moderation_rejected_group_'.$group_id, $group->name );
			}
			$headers = array('Content-Type: text/html; charset=UTF-8');

			$subject = apply_filters( 'bp_group_moderation_group_decision_mail_subject', $subject );
			$message = apply_filters( 'bp_group_moderation_group_decision_mail', $message, $group, $decision );

			wp_mail( $creator->user_email, $subject, $message, $headers );
		}
	}

	/**
	 * Render the admin page content for managing pending groups.
	 */
	public function bp_group_moderation_admin_page_content() {
		
		if( ( isset( $_GET[ 'action' ] ) || isset( $_GET[ 'id' ] ) )  && isset( $_GET['_wp_nonce'] ) ) {

			$nonce_value     = !empty( $_GET['_wp_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wp_nonce'] ) ) : ''; 
			$notification_id = !empty( $_GET['id'] ) ? sanitize_text_field( wp_unslash( $_GET['id'] ) ) : 0; 

			$notification_component = !empty( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : ''; 

			if(  ! wp_verify_nonce( $nonce_value, 'bp_group_moderation_notification_nonce'  ) ) { 
				return;
			}
			
			if ( class_exists('BP_Notifications_Notification') ) {

				if( empty( $notification_id ) && !empty( $notification_component )) {
							
					bp_notifications_mark_notifications_by_type( bp_loggedin_user_id(), 'groups' , $notification_component );
				
				} elseif( !empty( $notification_id ) ) {
									
					BP_Notifications_Notification::update(
						array( 'is_new' => 0 ),
						array( 'id'     => $notification_id )
					);
				}	
			}
		}

		// Get pending groups.
		$pending_groups = $this->bp_group_moderation_get_pending_groups();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Pending Groups', 'bp-group-moderation' ); ?></h1>
		
			<?php 
			if ( isset( $_GET['view'] ) && 'settings' === $_GET['view'] ) :
				$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
				if ( ! wp_verify_nonce( $nonce, 'bp_pending_groups_settings' ) ) {
					wp_die( esc_html__( 'Security verification failed.', 'bp-group-moderation' ) );
				}
			?>
			<?php else : ?>
				<?php if ( ! empty( $pending_groups ) ) : ?>
					<div id="bp-group-moderation-messages"></div>
					
					<table class="wp-list-table widefat fixed striped bp-group-moderation-table">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Group Name', 'bp-group-moderation' ); ?></th>
								<th><?php esc_html_e( 'Creator', 'bp-group-moderation' ); ?></th>
								<th><?php esc_html_e( 'Created', 'bp-group-moderation' ); ?></th>
								<th><?php esc_html_e( 'Requested Type', 'bp-group-moderation' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'bp-group-moderation' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $pending_groups as $group ) : 
								$creator = get_userdata( $group->creator_id );
								$requested_status = groups_get_groupmeta( $group->id, 'requested_status', true );
							?>
							<tr id="group-<?php echo esc_attr( $group->id ); ?>">
								<td>
								<a href="<?php echo esc_url( self::bp_group_moderation_get_group_url( $group ) ); ?>" target="_blank">
									<?php echo esc_html( $group->name ); ?>
								</a>

								</td>
								<td>
									<a href="<?php echo esc_url( bp_members_get_user_url( $group->creator_id ) ); ?>" target="_blank">
										<?php echo esc_html( $creator->display_name ); ?>
									</a>
								</td>
								<td><?php echo esc_html( bp_core_time_since( $group->date_created ) ); ?></td>
								<td><?php echo esc_html( ucfirst( $requested_status ) ); ?></td>
								<td>
									<div class="bp-group-moderation-actions">
										<button class="button button-primary bp-group-approve" data-group-id="<?php echo esc_attr( $group->id ); ?>">
											<?php esc_html_e( 'Approve', 'bp-group-moderation' ); ?>
										</button>
										<button class="button bp-group-reject" data-group-id="<?php echo esc_attr( $group->id ); ?>">
											<?php esc_html_e( 'Reject', 'bp-group-moderation' ); ?>
										</button>
									</div>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<div class="bp-group-moderation-no-items">
						<p><?php esc_html_e( 'No pending groups found.', 'bp-group-moderation' ); ?></p>
					</div>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Get all pending groups.
	 *
	 * @return array Array of group objects.
	 */
	public function bp_group_moderation_get_pending_groups() {
		global $wpdb, $bp;
		
		$groups_table = $bp->groups->table_name;
		$meta_table = $bp->groups->table_name_groupmeta;

		//phpcs:disable
		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT g.* FROM {$groups_table} g            
			INNER JOIN {$meta_table} m ON g.id = m.group_id
			WHERE m.meta_key = %s AND m.meta_value = %s
			ORDER BY g.date_created DESC",
			'approval_status',
			'pending'
		) );
		//phpcs:enable 
		
		// Convert to BP group objects.
		$groups = array();
		foreach ( $results as $result ) {
			$groups[] = groups_get_group( $result->id );
		}
		
		return $groups;
	}
}