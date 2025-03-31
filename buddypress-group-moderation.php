<?php
/**
 * Plugin Name: BuddyPress Group Moderation
 * Plugin URI: https://wbcomdesigns.com/plugins/buddypress-group-moderation
 * Description: Adds a moderation system to BuddyPress groups. New groups require admin approval before becoming active.
 * Version: 1.0.0
 * Author: Wbcom Designs
 * Author URI: https://wbcomdesigns.com
 * Text Domain: bp-group-moderation
 * Domain Path: /languages
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 *
 * @package BuddyPress_Group_Moderation
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
	/**
	 * Register our component to the notifications component array.
	 *
	 * @param array $component_names Array of component names.
	 * @return array
	 */
	public function register_notifications_component( $component_names = array() ) {
		// Force $component_names to be an array
		if ( ! is_array( $component_names ) ) {
			$component_names = array();
		}
		
		// Add our component to the registered components
		array_push( $component_names, 'bp_group_moderation' );
		
		return $component_names;
	}

	/**
	 * Format the notifications for our component.
	 *
	 * @param string $action            The notification action.
	 * @param int    $item_id           The item ID.
	 * @param int    $secondary_item_id The secondary item ID.
	 * @param int    $total_items       The total number of items to format.
	 * @param string $format            The format to return. 'string' or 'object'.
	 * @param string $component_action  The component action.
	 * @param string $component_name    The component name.
	 * @param int    $id                The notification ID.
	 * @return string|object
	 */
	public function format_notifications( $action, $item_id, $secondary_item_id, $total_items, $format, $component_action, $component_name, $id ) {
		// Only handle our component's notifications
		if ( 'bp_group_moderation' !== $component_name ) {
			return $action;
		}
		
		// Format based on the component action
		$group_id = $item_id;
		$group = groups_get_group( $group_id );
		
		if ( 'new_group_pending' === $component_action ) {
			if ( 1 === $total_items ) {
				$text = sprintf( __( 'New group "%s" is pending approval', 'bp-group-moderation' ), $group->name );
				$link = admin_url( 'admin.php?page=bp-pending-groups' );
			} else {
				$text = sprintf( __( '%d new groups are pending approval', 'bp-group-moderation' ), $total_items );
				$link = admin_url( 'admin.php?page=bp-pending-groups' );
			}
		} elseif ( 'group_approved' === $component_action ) {
			$text = sprintf( __( 'Your group "%s" has been approved!', 'bp-group-moderation' ), $group->name );
			$link = bp_get_group_permalink( $group );
		} elseif ( 'group_rejected' === $component_action ) {
			$text = __( 'Your group was not approved by site administrators.', 'bp-group-moderation' );
			$link = bp_get_loggedin_user_domain();
		} else {
			return $action;
		}
		
		// WordPress Toolbar.
		if ( 'string' === $format ) {
			$return = '<a href="' . esc_url( $link ) . '">' . esc_html( $text ) . '</a>';
		} else {
			$return = array(
				'text' => $text,
				'link' => $link
			);
		}
		
		return $return;
	}
}

// Initialize the plugin.
function bp_group_moderation_init() {
	return BP_Group_Moderation::get_instance();
}
add_action( 'plugins_loaded', 'bp_group_moderation_init' );

/**
 * Activation hook for the plugin.
 */
function bp_group_moderation_activate() {
	// Set default options.
	add_option( 'bp_group_moderation_auto_approve_admin', true );
	add_option( 'bp_group_moderation_hide_pending', true );
	add_option( 'bp_group_moderation_send_emails', true );
}
register_activation_hook( __FILE__, 'bp_group_moderation_activate' );

/**
 * Deactivation hook for the plugin.
 */
function bp_group_moderation_deactivate() {
	// Nothing to do here yet.
}
register_deactivation_hook( __FILE__, 'bp_group_moderation_deactivate' );


// Define plugin constants.
define( 'BP_GROUP_MODERATION_VERSION', '1.0.0' );
define( 'BP_GROUP_MODERATION_PLUGIN_FILE', __FILE__ );
define( 'BP_GROUP_MODERATION_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'BP_GROUP_MODERATION_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main class for BuddyPress Group Moderation plugin.
 */
class BP_Group_Moderation {

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;

	/**
	 * Initialize the plugin.
	 */
	private function __construct() {
		// Hooks and actions.
		add_action( 'bp_init', array( $this, 'load_plugin_textdomain' ) );
		add_action( 'bp_loaded', array( $this, 'init' ) );
		
		// Admin hooks.
		add_action( 'bp_admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		
		// AJAX handlers.
		add_action( 'wp_ajax_bp_group_moderation_handle_group', array( $this, 'ajax_handle_group' ) );
	}

	/**
	 * Return an instance of this class.
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
	 * Load the plugin text domain for translation.
	 */
	public function load_plugin_textdomain() {
		load_plugin_textdomain( 'bp-group-moderation', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * Initialize the plugin components.
	 */
	public function init() {
		// Check if BuddyPress is active.
		if ( ! function_exists( 'buddypress' ) || ! bp_is_active( 'groups' ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notice_need_buddypress' ) );
			return;
		}

		// Group creation hooks.
		add_action( 'groups_group_after_save', array( $this, 'set_group_to_pending' ) );
		
		// Filter group queries.
		add_filter( 'bp_groups_get_groups', array( $this, 'filter_pending_groups' ), 10, 2 );
		add_filter( 'bp_activity_get', array( $this, 'filter_pending_groups_activity' ), 10, 1 );
		
		// Display hooks.
		add_action( 'bp_before_group_header', array( $this, 'display_pending_notice' ) );
		
		// Notification hooks.
		add_action( 'bp_setup_globals', array( $this, 'register_notification_components' ) );
		add_filter( 'bp_notifications_get_registered_components', array( $this, 'register_notifications_component' ) );
		add_filter( 'bp_notifications_get_notifications_for_user', array( $this, 'format_notifications' ), 10, 8 );
	}

	/**
	 * Display admin notice if BuddyPress is not active.
	 */
	public function admin_notice_need_buddypress() {
		?>
		<div class="notice notice-error is-dismissible">
			<p><?php esc_html_e( 'BuddyPress Group Moderation requires BuddyPress with the Groups component activated.', 'bp-group-moderation' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Set a newly created group to pending status.
	 *
	 * @param BP_Groups_Group $group The group object.
	 */
	public function set_group_to_pending( $group ) {
		// Skip for site admins if configured to do so.
		$auto_approve_admin = get_option( 'bp_group_moderation_auto_approve_admin', true );
		if ( $auto_approve_admin && current_user_can( 'manage_options' ) ) {
			return;
		}
		
		// Only set to pending for newly created groups.
		if ( $group->is_new ) {
			// Store the original requested status.
			$original_status = $group->status;
			groups_update_groupmeta( $group->id, 'requested_status', $original_status );
			
			// Set approval status to pending.
			groups_update_groupmeta( $group->id, 'approval_status', 'pending' );
			
			// Optionally set to hidden while pending (based on settings).
			$hide_pending = get_option( 'bp_group_moderation_hide_pending', true );
			if ( $hide_pending ) {
				// Update the visibility to hidden.
				$group->status = 'hidden';
				$group->save();
			}
			
			// Notify administrators.
			$this->notify_admins_of_pending_group( $group->id );
		}
	}

	/**
	 * Notify administrators about a pending group.
	 *
	 * @param int $group_id The group ID.
	 */
	public function notify_admins_of_pending_group( $group_id ) {
		$group = groups_get_group( $group_id );
		
		// Get site administrators.
		$admin_ids = get_users( array(
			'role'    => 'administrator',
			'fields'  => 'ID',
		) );
		
		$group_url = bp_get_group_permalink( $group );
		$admin_url = admin_url( 'admin.php?page=bp-pending-groups' );
		
		$subject = sprintf( __( 'New Group Pending Approval: %s', 'bp-group-moderation' ), $group->name );
		
		$message = sprintf(
			__( 'A new group "%1$s" has been created by %2$s and is pending approval.

You can approve or reject this group from the admin dashboard: %3$s

View the group: %4$s', 'bp-group-moderation' ),
			$group->name,
			bp_core_get_user_displayname( $group->creator_id ),
			$admin_url,
			$group_url
		);
		
		// Send notifications to all admins.
		foreach ( $admin_ids as $admin_id ) {
			// Add BuddyPress notification.
			if ( bp_is_active( 'notifications' ) ) {
				bp_notifications_add_notification( array(
					'user_id'           => $admin_id,
					'item_id'           => $group_id,
					'component_name'    => 'bp_group_moderation',
					'component_action'  => 'new_group_pending',
					'date_notified'     => bp_core_current_time(),
					'is_new'            => 1,
				) );
			}
			
			// Send email notification if enabled.
			$send_email = get_option( 'bp_group_moderation_send_emails', true );
			if ( $send_email ) {
				$admin_user = get_userdata( $admin_id );
				wp_mail( $admin_user->user_email, $subject, $message );
			}
		}
	}

	/**
	 * Filter groups in directory listings to hide pending groups.
	 *
	 * @param array $groups Array of group objects.
	 * @param array $args   Array of arguments.
	 * @return array Modified array of group objects.
	 */
	public function filter_pending_groups( $groups, $args ) {
		// Don't filter for admins.
		if ( current_user_can( 'manage_options' ) ) {
			return $groups;
		}
		
		// Filter out pending groups for regular users.
		foreach ( $groups['groups'] as $key => $group ) {
			$approval_status = groups_get_groupmeta( $group->id, 'approval_status', true );
			
			// If group is pending and user is not the creator, remove it.
			if ( 'pending' === $approval_status ) {
				if ( ! groups_is_user_admin( bp_loggedin_user_id(), $group->id ) ) {
					unset( $groups['groups'][ $key ] );
					$groups['total'] = $groups['total'] - 1;
				}
			}
		}
		
		// Re-index the array.
		$groups['groups'] = array_values( $groups['groups'] );
		
		return $groups;
	}

	/**
	 * Filter group activities to hide activities from pending groups.
	 *
	 * @param array $args Array of arguments.
	 * @return array Modified array of arguments.
	 */
	public function filter_pending_groups_activity( $args ) {
		// Don't filter for admins.
		if ( current_user_can( 'manage_options' ) || ! bp_is_active( 'activity' ) ) {
			return $args;
		}
		
		global $wpdb, $bp;
		
		// Get all pending group IDs.
		$pending_group_ids = $wpdb->get_col( $wpdb->prepare(
			"SELECT g.id FROM {$bp->groups->table_name} g
			INNER JOIN {$bp->groups->table_name_groupmeta} m ON g.id = m.group_id
			WHERE m.meta_key = %s AND m.meta_value = %s",
			'approval_status',
			'pending'
		) );
		
		if ( ! empty( $pending_group_ids ) ) {
			// Format for SQL query.
			$pending_ids_str = implode( ',', array_map( 'absint', $pending_group_ids ) );
			
			// Add WHERE clause to exclude activities from pending groups.
			if ( ! empty( $args['where'] ) ) {
				$args['where'] .= " AND item_id NOT IN ({$pending_ids_str}) ";
			} else {
				$args['where'] = " item_id NOT IN ({$pending_ids_str}) ";
			}
		}
		
		return $args;
	}

	/**
	 * Display a notice for pending groups.
	 */
	public function display_pending_notice() {
		if ( ! bp_is_group() ) {
			return;
		}
		
		$group_id = bp_get_current_group_id();
		$approval_status = groups_get_groupmeta( $group_id, 'approval_status', true );
		
		if ( 'pending' === $approval_status ) {
			// Show to group admins and site admins.
			if ( groups_is_user_admin( bp_loggedin_user_id(), $group_id ) || current_user_can( 'manage_options' ) ) {
				?>
				<div class="bp-feedback warning">
					<span class="bp-icon" aria-hidden="true"></span>
					<p><?php esc_html_e( 'This group is pending approval by a site administrator. Some features may be limited until approval.', 'bp-group-moderation' ); ?></p>
				</div>
				<?php
			}
		}
	}

	/**
	 * Add admin menu for managing pending groups.
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'bp-groups',
			__( 'Pending Groups', 'bp-group-moderation' ),
			__( 'Pending Groups', 'bp-group-moderation' ),
			'manage_options',
			'bp-pending-groups',
			array( $this, 'admin_page_content' )
		);
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook The current admin page.
	 */
	public function admin_enqueue_scripts( $hook ) {
		if ( 'buddypress_page_bp-pending-groups' !== $hook ) {
			return;
		}
		
		wp_enqueue_style( 'bp-group-moderation-admin', BP_GROUP_MODERATION_PLUGIN_URL . 'assets/css/admin.css', array(), BP_GROUP_MODERATION_VERSION );
		wp_enqueue_script( 'bp-group-moderation-admin', BP_GROUP_MODERATION_PLUGIN_URL . 'assets/js/admin.js', array( 'jquery' ), BP_GROUP_MODERATION_VERSION, true );
		
		wp_localize_script( 'bp-group-moderation-admin', 'bpGroupModeration', array(
			'ajaxUrl'       => admin_url( 'admin-ajax.php' ),
			'nonce'         => wp_create_nonce( 'bp-group-moderation-nonce' ),
			'approveText'   => __( 'Approve', 'bp-group-moderation' ),
			'rejectText'    => __( 'Reject', 'bp-group-moderation' ),
			'confirmReject' => __( 'Are you sure you want to reject this group? This action cannot be undone.', 'bp-group-moderation' ),
			'loadingText'   => __( 'Processing...', 'bp-group-moderation' ),
		) );
	}

	/**
	 * Handle AJAX requests for group moderation actions.
	 */
	public function ajax_handle_group() {
		// Verify nonce.
		check_ajax_referer( 'bp-group-moderation-nonce', 'nonce' );
		
		// Check user capabilities.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'bp-group-moderation' ) ) );
		}
		
		$group_id = isset( $_POST['group_id'] ) ? intval( $_POST['group_id'] ) : 0;
		$action = isset( $_POST['action_type'] ) ? sanitize_text_field( $_POST['action_type'] ) : '';
		
		if ( ! $group_id || ! in_array( $action, array( 'approve', 'reject' ) ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid request.', 'bp-group-moderation' ) ) );
		}
		
		$result = false;
		$message = '';
		
		// Process the action.
		if ( 'approve' === $action ) {
			$result = $this->approve_group( $group_id );
			$message = __( 'Group has been approved successfully.', 'bp-group-moderation' );
		} elseif ( 'reject' === $action ) {
			$result = $this->reject_group( $group_id );
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
	public function approve_group( $group_id ) {
		// Get the originally requested status.
		$requested_status = groups_get_groupmeta( $group_id, 'requested_status', true );
		
		// Get the group object.
		$group = groups_get_group( $group_id );
		
		if ( ! $group ) {
			return false;
		}
		
		// Update the group to the requested status.
		$group->status = $requested_status;
		$result = $group->save();
		
		if ( $result ) {
			// Remove the pending flag.
			groups_delete_groupmeta( $group_id, 'approval_status' );
			
			// Notify the group creator.
			$this->notify_user_of_group_decision( $group, 'approved' );
			
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
	public function reject_group( $group_id ) {
		// Get group info before deletion.
		$group = groups_get_group( $group_id );
		
		if ( ! $group ) {
			return false;
		}
		
		$creator_id = $group->creator_id;
		$group_name = $group->name;
		
		// Notify the creator before deleting the group.
		$this->notify_user_of_group_decision( $group, 'rejected' );
		
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
	public function notify_user_of_group_decision( $group, $decision ) {
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
				'component_name'    => 'bp_group_moderation',
				'component_action'  => 'group_' . $decision,
				'date_notified'     => bp_core_current_time(),
				'is_new'            => 1,
			) );
		}
		
		// Send email notification if enabled.
		$send_email = get_option( 'bp_group_moderation_send_emails', true );
		if ( $send_email ) {
			$creator = get_userdata( $creator_id );
			
			if ( 'approved' === $decision ) {
				$subject = sprintf( __( 'Your group "%s" has been approved', 'bp-group-moderation' ), $group_name );
				$message = sprintf(
					__( 'Good news! Your group "%1$s" has been approved by a site administrator and is now live with your requested visibility setting.

Visit your group: %2$s', 'bp-group-moderation' ),
					$group_name,
					bp_get_group_permalink( $group )
				);
			} else {
				$subject = sprintf( __( 'Your group "%s" was not approved', 'bp-group-moderation' ), $group_name );
				$message = sprintf(
					__( 'We\'re sorry, but your group "%s" has not been approved by the site administrators.

If you have questions about this decision, please contact the site administrators.', 'bp-group-moderation' ),
					$group_name
				);
			}
			
			wp_mail( $creator->user_email, $subject, $message );
		}
	}

	/**
	 * Render the admin page content for managing pending groups.
	 */
	public function admin_page_content() {
		// Get pending groups.
		$pending_groups = $this->get_pending_groups();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Pending Groups', 'bp-group-moderation' ); ?></h1>
			
			<div class="bp-group-moderation-settings">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=bp-pending-groups&view=settings' ) ); ?>" class="button">
					<?php esc_html_e( 'Settings', 'bp-group-moderation' ); ?>
				</a>
			</div>
			
			<?php if ( isset( $_GET['view'] ) && 'settings' === $_GET['view'] ) : ?>
				<?php $this->render_settings_page(); ?>
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
									<a href="<?php echo esc_url( bp_get_group_permalink( $group ) ); ?>" target="_blank">
										<?php echo esc_html( $group->name ); ?>
									</a>
								</td>
								<td>
									<a href="<?php echo esc_url( bp_core_get_user_domain( $group->creator_id ) ); ?>" target="_blank">
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
	 * Render the settings page.
	 */
	public function render_settings_page() {
		// Process settings save.
		if ( isset( $_POST['bp_group_moderation_save_settings'] ) && check_admin_referer( 'bp_group_moderation_settings' ) ) {
			$auto_approve_admin = isset( $_POST['bp_group_moderation_auto_approve_admin'] ) ? 1 : 0;
			$hide_pending = isset( $_POST['bp_group_moderation_hide_pending'] ) ? 1 : 0;
			$send_emails = isset( $_POST['bp_group_moderation_send_emails'] ) ? 1 : 0;
			
			update_option( 'bp_group_moderation_auto_approve_admin', $auto_approve_admin );
			update_option( 'bp_group_moderation_hide_pending', $hide_pending );
			update_option( 'bp_group_moderation_send_emails', $send_emails );
			
			?>
			<div class="notice notice-success is-dismissible">
				<p><?php esc_html_e( 'Settings saved successfully.', 'bp-group-moderation' ); ?></p>
			</div>
			<?php
		}
		
		// Get current settings.
		$auto_approve_admin = get_option( 'bp_group_moderation_auto_approve_admin', true );
		$hide_pending = get_option( 'bp_group_moderation_hide_pending', true );
		$send_emails = get_option( 'bp_group_moderation_send_emails', true );
		
		?>
		<div class="bp-group-moderation-settings-form">
			<h2><?php esc_html_e( 'Group Moderation Settings', 'bp-group-moderation' ); ?></h2>
			
			<form method="post" action="">
				<?php wp_nonce_field( 'bp_group_moderation_settings' ); ?>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="bp_group_moderation_auto_approve_admin">
								<?php esc_html_e( 'Auto-approve admin groups', 'bp-group-moderation' ); ?>
							</label>
						</th>
						<td>
							<input type="checkbox" id="bp_group_moderation_auto_approve_admin" name="bp_group_moderation_auto_approve_admin" value="1" <?php checked( $auto_approve_admin ); ?> />
							<p class="description"><?php esc_html_e( 'Automatically approve groups created by site administrators.', 'bp-group-moderation' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="bp_group_moderation_hide_pending">
								<?php esc_html_e( 'Hide pending groups', 'bp-group-moderation' ); ?>
							</label>
						</th>
						<td>
							<input type="checkbox" id="bp_group_moderation_hide_pending" name="bp_group_moderation_hide_pending" value="1" <?php checked( $hide_pending ); ?> />
							<p class="description"><?php esc_html_e( 'Set pending groups to hidden status until approved.', 'bp-group-moderation' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="bp_group_moderation_send_emails">
								<?php esc_html_e( 'Send email notifications', 'bp-group-moderation' ); ?>
							</label>
						</th>
						<td>
							<input type="checkbox" id="bp_group_moderation_send_emails" name="bp_group_moderation_send_emails" value="1" <?php checked( $send_emails ); ?> />
							<p class="description"><?php esc_html_e( 'Send email notifications in addition to BuddyPress notifications.', 'bp-group-moderation' ); ?></p>
						</td>
					</tr>
				</table>
				
				<p class="submit">
					<input type="submit" name="bp_group_moderation_save_settings" class="button button-primary" value="<?php esc_attr_e( 'Save Settings', 'bp-group-moderation' ); ?>" />
				</p>
			</form>
		</div>
		
		<p>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=bp-pending-groups' ) ); ?>" class="button">
				<?php esc_html_e( '&laquo; Back to Pending Groups', 'bp-group-moderation' ); ?>
			</a>
		</p>
		<?php
	}

	/**
	 * Get all pending groups.
	 *
	 * @return array Array of group objects.
	 */
	public function get_pending_groups() {
		global $wpdb, $bp;
		
		$groups_table = $bp->groups->table_name;
		$meta_table = $bp->groups->table_name_groupmeta;
		
		$query = $wpdb->prepare(
			"SELECT g.* FROM {$groups_table} g
			INNER JOIN {$meta_table} m ON g.id = m.group_id
			WHERE m.meta_key = %s AND m.meta_value = %s
			ORDER BY g.date_created DESC",
			'approval_status',
			'pending'
		);
		
		$results = $wpdb->get_results( $query );
		
		// Convert to BP group objects.
		$groups = array();
		foreach ( $results as $result ) {
			$groups[] = groups_get_group( $result->id );
		}
		
		return $groups;
	}

	/**
	 * Register notification components for BuddyPress.
	 */
	public function register_notification_components() {
		if ( ! bp_is_active( 'notifications' ) ) {
			return;
		}
		
		// Register our custom notification component
		bp_notifications_register_notification_component( 'bp_group_moderation' );
	}