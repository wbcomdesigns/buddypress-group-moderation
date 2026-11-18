<?php
/**
 * Main class for BuddyPress Group Moderation functionality.
 *
 * @package BuddyPress_Group_Moderation
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main class for BuddyPress Group Moderation plugin.
 */
class BP_Group_Moderation {

	/**
	 * Object cache group for the pending group ids.
	 *
	 * @var string
	 */
	const CACHE_GROUP = 'bp_group_moderation';

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
		add_action( 'bp_init', array( $this, 'bp_group_moderation_load_plugin_textdomain' ) );
		add_action( 'bp_loaded', array( $this, 'bp_group_moderation_init' ) );
		add_filter( 'bp_after_has_activities_parse_args', array( $this, 'bp_group_moderation_hide_pending_group_activity' ), 10, 1 );
	}

	/**
	 * Ids of every group waiting for approval, one query per request (cached until a group's approval state changes).
	 *
	 * @return int[]
	 */
	public static function bp_group_moderation_get_pending_group_ids() {
		$ids = wp_cache_get( 'pending_ids', self::CACHE_GROUP );
		if ( is_array( $ids ) ) {
			return $ids;
		}

		global $wpdb;
		$bp = buddypress();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from BuddyPress; groupmeta has no core helper for "every group with this meta value"; cached above.
		$ids = array_map( 'intval', (array) $wpdb->get_col( $wpdb->prepare( "SELECT group_id FROM {$bp->groups->table_name_groupmeta} WHERE meta_key = %s AND meta_value = %s", 'approval_status', 'pending' ) ) );
		wp_cache_set( 'pending_ids', $ids, self::CACHE_GROUP );

		return $ids;
	}

	/**
	 * Forget the cached pending ids after a group's approval state changes.
	 */
	public static function bp_group_moderation_flush_pending_ids() {
		wp_cache_delete( 'pending_ids', self::CACHE_GROUP );
	}

	/**
	 * Whether a group is waiting for approval.
	 *
	 * @param int $group_id Group id.
	 * @return bool
	 */
	public static function bp_group_moderation_is_pending( $group_id ) {
		return $group_id > 0 && 'pending' === groups_get_groupmeta( (int) $group_id, 'approval_status', true );
	}

	/**
	 * Translated label for a group status.
	 *
	 * @param string $status public, private or hidden.
	 * @return string Empty for an unknown status.
	 */
	public static function bp_group_moderation_status_label( $status ) {
		$labels = array(
			'public'  => __( 'Public', 'buddypress-group-moderation' ),
			'private' => __( 'Private', 'buddypress-group-moderation' ),
			'hidden'  => __( 'Hidden', 'buddypress-group-moderation' ),
		);

		return isset( $labels[ $status ] ) ? $labels[ $status ] : '';
	}

	/**
	 * Hide the creation, avatar and cover activity a pending group recorded before it was hidden.
	 *
	 * BuddyPress records "created a group" while the group still has its requested (public) status,
	 * so that row would otherwise show sitewide before the group is approved.
	 *
	 * @param array $activity_args Activity loop args.
	 * @return array
	 */
	public function bp_group_moderation_hide_pending_group_activity( $activity_args ) {
		if ( ! bp_is_active( 'groups' ) || ! bp_is_active( 'activity' ) ) {
			return $activity_args;
		}

		$pending_ids = self::bp_group_moderation_get_pending_group_ids();
		if ( empty( $pending_ids ) ) {
			return $activity_args;
		}

		global $wpdb;
		$bp           = buddypress();
		$placeholders = implode( ',', array_fill( 0, count( $pending_ids ), '%d' ) );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- one query for every pending group; placeholders built above.
		$activity_ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM {$bp->activity->table_name} WHERE component = 'groups' AND type IN ( 'created_group', 'new_group_cover_photo', 'new_group_avatar' ) AND item_id IN ( {$placeholders} )", $pending_ids ) );

		if ( $activity_ids ) {
			$existing                 = empty( $activity_args['exclude'] ) ? array() : wp_parse_id_list( $activity_args['exclude'] );
			$activity_args['exclude'] = array_merge( $existing, array_map( 'intval', $activity_ids ) );
		}

		return $activity_args;
	}

	/**
	 * Get group URL in a backward-compatible way.
	 *
	 * @param BP_Groups_Group $group The group object.
	 * @return string
	 */
	public static function bp_group_moderation_get_group_url( $group ) {
		if ( function_exists( 'bp_get_group_url' ) ) {
			return bp_get_group_url( $group );
		} elseif ( function_exists( 'bp_get_group_permalink' ) ) {
			return bp_get_group_permalink( $group );
		}
		return '';
	}

	/**
	 * Load the plugin text domain for translation.
	 */
	public function bp_group_moderation_load_plugin_textdomain() {
		load_plugin_textdomain( 'buddypress-group-moderation', false, dirname( plugin_basename( BP_GROUP_MODERATION_PLUGIN_FILE ) ) . '/languages/' );
	}

	/**
	 * Initialize the plugin components.
	 */
	public function bp_group_moderation_init() {
		add_action( 'groups_group_create_complete', array( $this, 'bp_group_moderation_set_group_to_pending' ), 20, 1 );

		// Every save of a pending group, from any screen or API, keeps it hidden.
		add_action( 'groups_group_before_save', array( $this, 'bp_group_moderation_keep_pending_hidden' ) );

		// Show the creator their group is pending instead of the temporary Hidden type.
		add_filter( 'bp_get_group_type', array( $this, 'bp_group_moderation_pending_group_type' ), 10, 2 );

		// Manage > Settings preselects the type the creator asked for, not the temporary Hidden.
		add_filter( 'bp_get_new_group_status', array( $this, 'bp_group_moderation_settings_screen_status' ) );

		// Invitations to a pending group wait for approval.
		add_filter( 'bp_after_accept_invitation_parse_args', array( $this, 'bp_group_moderation_hold_invitation_accept' ) );
		add_filter( 'bp_after_has_groups_parse_args', array( $this, 'bp_group_moderation_hide_pending_invites' ) );

		// A deleted group leaves the pending list.
		add_action( 'groups_delete_group', array( __CLASS__, 'bp_group_moderation_flush_pending_ids' ) );

		if ( class_exists( 'Youzify' ) ) {
			add_action( 'bp_before_group_body', array( $this, 'bp_group_moderation_display_pending_notice' ) );
		} else {
			add_action( 'bp_before_group_header', array( $this, 'bp_group_moderation_display_pending_notice' ) );
		}

		// 1.0.0 ran an hourly repair job; the save guard above replaces it.
		if ( wp_next_scheduled( 'bp_group_moderation_hourly_check' ) ) {
			wp_clear_scheduled_hook( 'bp_group_moderation_hourly_check' );
		}
	}

	/**
	 * Label a pending group by its approval state and the type its creator asked for.
	 *
	 * @param string          $type  Group type label from BuddyPress.
	 * @param BP_Groups_Group $group The group object.
	 * @return string
	 */
	public function bp_group_moderation_pending_group_type( $type, $group ) {
		if ( empty( $group->id ) || ! self::bp_group_moderation_is_pending( $group->id ) ) {
			return $type;
		}

		$requested = self::bp_group_moderation_status_label( groups_get_groupmeta( $group->id, 'requested_status', true ) );

		if ( '' === $requested ) {
			return __( 'Pending approval', 'buddypress-group-moderation' );
		}

		/* translators: %s: group type the creator requested (Public, Private or Hidden). */
		return sprintf( __( 'Pending approval, requested: %s', 'buddypress-group-moderation' ), $requested );
	}

	/**
	 * On a pending group's Manage > Settings screen, preselect the requested privacy (Nouveau reads
	 * bp_get_new_group_status() for the radios).
	 *
	 * @param string $status Group status.
	 * @return string
	 */
	public function bp_group_moderation_settings_screen_status( $status ) {
		$group = groups_get_current_group();
		if ( 'hidden' !== $status || empty( $group->id ) || ! bp_is_group_admin_page() || ! bp_is_group_admin_screen( 'group-settings' ) || ! self::bp_group_moderation_is_pending( $group->id ) ) {
			return $status;
		}

		$requested = groups_get_groupmeta( $group->id, 'requested_status', true );

		return '' !== self::bp_group_moderation_status_label( $requested ) ? $requested : $status;
	}

	/**
	 * Keep a pending group hidden on every save; a changed privacy becomes the type applied on approval.
	 *
	 * @param BP_Groups_Group $group Group being saved (by reference).
	 */
	public function bp_group_moderation_keep_pending_hidden( $group ) {
		if ( empty( $group->id ) || 'hidden' === $group->status || ! self::bp_group_moderation_is_pending( $group->id ) ) {
			return;
		}

		if ( '' !== self::bp_group_moderation_status_label( $group->status ) ) {
			groups_update_groupmeta( $group->id, 'requested_status', $group->status );
		}
		$group->status = 'hidden';
	}

	/**
	 * Refuse accepting an invitation to a pending group (profile screen, Nouveau AJAX and REST all call this).
	 *
	 * The invitation itself is kept, so it can be accepted once the group is approved.
	 *
	 * @param array $args accept_invitation() args.
	 * @return array
	 */
	public function bp_group_moderation_hold_invitation_accept( $args ) {
		$group_id = isset( $args['item_id'] ) ? (int) $args['item_id'] : 0;
		if ( $group_id && self::bp_group_moderation_is_pending( $group_id ) && ! bp_current_user_can( 'bp_moderate' ) ) {
			$args['item_id'] = 0; // accept_invitation() then finds no invitation and returns false.
		}
		return $args;
	}

	/**
	 * Leave pending groups out of a member's Invitations list until they are approved.
	 *
	 * @param array $args bp_has_groups() args.
	 * @return array
	 */
	public function bp_group_moderation_hide_pending_invites( $args ) {
		if ( empty( $args['type'] ) || 'invites' !== $args['type'] ) {
			return $args;
		}

		$pending_ids = self::bp_group_moderation_get_pending_group_ids();
		if ( $pending_ids ) {
			$existing        = empty( $args['exclude'] ) ? array() : wp_parse_id_list( $args['exclude'] );
			$args['exclude'] = array_merge( $existing, $pending_ids );
		}

		return $args;
	}

	/**
	 * Set a newly created group to pending status.
	 *
	 * @param int $group_id The group id.
	 */
	public function bp_group_moderation_set_group_to_pending( $group_id ) {
		$group = groups_get_group( $group_id );
		if ( empty( $group->id ) || self::bp_group_moderation_is_pending( $group->id ) ) {
			return;
		}

		if ( bp_group_moderation_fetch_settings( 'bp_group_moderation_auto_approve_admin' ) && user_can( $group->creator_id, 'manage_options' ) ) {
			return;
		}

		groups_update_groupmeta( $group->id, 'requested_status', $group->status );
		groups_update_groupmeta( $group->id, 'approval_status', 'pending' );
		self::bp_group_moderation_flush_pending_ids();

		// The save guard turns the status to hidden.
		$group->save();

		$this->bp_group_moderation_notify_admins_of_pending_group( $group );
	}

	/**
	 * Notify administrators about a pending group.
	 *
	 * @param BP_Groups_Group $group The pending group.
	 */
	public function bp_group_moderation_notify_admins_of_pending_group( $group ) {
		$admin_ids = get_users(
			array(
				'role'   => 'administrator',
				'fields' => 'ID',
			)
		);

		$group_url  = self::bp_group_moderation_get_group_url( $group );
		$admin_url  = admin_url( 'admin.php?page=bp-pending-groups' );
		$send_email = bp_group_moderation_fetch_settings( 'bp_group_moderation_send_emails' );

		/* translators: %s: group name. */
		$subject = sprintf( __( 'New Group Pending Approval : %s', 'buddypress-group-moderation' ), $group->name );
		$message = sprintf(
			/* translators: 1: group name, 2: creator name, 3: group URL, 4: group URL as text, 5: Pending Groups URL, 6: Pending Groups URL as text. */
			__(
				"<p><strong>Hello Admin</strong></p>
			<p>A new group titled “%1\$s” has been created by <strong>“%2\$s”</strong> and is currently <strong>awaiting your approval</strong>.</p>
			<p>Please review the submission to ensure it aligns with the community standards.</p>
			<p><strong>View Group :</strong></p>
			<p><a href='%3\$s'>%4\$s</a></p>
			<p><strong>Approve or Reject :</strong></p>
			<p><a href='%5\$s'>%6\$s</a></p>
			<p><strong>Thank you for keeping our community safe and welcoming!</strong></p>",
				'buddypress-group-moderation'
			),
			esc_html( $group->name ),
			esc_html( bp_core_get_user_displayname( $group->creator_id ) ),
			esc_url( $group_url ),
			esc_html( $group_url ),
			esc_url( $admin_url ),
			esc_html( $admin_url )
		);
		$subject = apply_filters( 'bp_group_moderation_group_pending_mail_subject', $subject );

		foreach ( $admin_ids as $admin_id ) {
			if ( bp_is_active( 'notifications' ) ) {
				bp_notifications_add_notification(
					array(
						'user_id'           => $admin_id,
						'item_id'           => $group->id,
						'secondary_item_id' => $group->creator_id,
						'component_name'    => 'groups',
						'component_action'  => 'new_group_pending',
						'date_notified'     => bp_core_current_time(),
						'is_new'            => 1,
					)
				);
			}

			if ( $send_email ) {
				$admin_user = get_userdata( $admin_id );
				if ( $admin_user ) {
					wp_mail( $admin_user->user_email, $subject, apply_filters( 'bp_group_moderation_group_pending_mail', $message, $group->id, $admin_id ), array( 'Content-Type: text/html; charset=UTF-8' ) );
				}
			}
		}
	}

	/**
	 * Display a notice for pending groups to the group's admins and site admins.
	 */
	public function bp_group_moderation_display_pending_notice() {
		if ( ! bp_is_group() ) {
			return;
		}

		$group_id = bp_get_current_group_id();
		if ( ! self::bp_group_moderation_is_pending( $group_id ) ) {
			return;
		}

		if ( ! groups_is_user_admin( bp_loggedin_user_id(), $group_id ) && ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$id_attr = class_exists( 'Youzify' ) ? ' id="message"' : '';
		?>
		<div<?php echo $id_attr; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed string. ?> class="bp-feedback bp-messages warning">
			<span class="bp-icon" aria-hidden="true"></span>
			<p><?php esc_html_e( 'Your group is currently awaiting approval from a site administrator. You will be notified once it has been approved.', 'buddypress-group-moderation' ); ?></p>
		</div>
		<?php
	}
}
