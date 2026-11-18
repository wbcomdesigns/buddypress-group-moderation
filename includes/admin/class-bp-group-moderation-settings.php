<?php
/**
 * Settings screen: WB Plugins > Group Moderation, on the suite card-panel shell.
 *
 * Replaces the legacy Wbcom wrapper (admin/wbcom/, admin/templates/). Tabs are
 * General and Support; the page opens on General. General saves through
 * the Settings API (options.php) into the same option the plugin always used.
 *
 * @package BuddyPress_Group_Moderation
 * @since   1.0.1
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class BP_Group_Moderation_Settings
 */
class BP_Group_Moderation_Settings {

	/**
	 * Settings page slug (unchanged, so bookmarks and the activation redirect keep working).
	 *
	 * @var string
	 */
	const MENU_SLUG = 'bp-group-moderation';

	/**
	 * Option holding every General setting.
	 *
	 * @var string
	 */
	const OPTION = 'bp_group_moderation_general_settings';

	/**
	 * Settings API group used by the General form.
	 *
	 * @var string
	 */
	const GROUP = 'bp_group_moderation_settings';

	/**
	 * On/off sub-keys the General form owns. Anything else in the option is kept on save.
	 *
	 * @var string[]
	 */
	const TOGGLES = array(
		'bp_group_moderation_auto_approve_admin',
		'bp_group_moderation_send_emails',
		'bp_group_moderation_delete_data_on_uninstall',
	);

	/**
	 * Hook the screen in.
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'in_admin_header', array( $this, 'suppress_foreign_notices' ), 1 );
	}

	/**
	 * Sidebar tabs, slug => label. The page opens on the first one.
	 *
	 * @return array<string, string>
	 */
	public static function get_tabs() {
		return apply_filters(
			'bp_group_moderation_admin_setting_tabs',
			array(
				'general' => __( 'General', 'buddypress-group-moderation' ),
				'support' => __( 'Support', 'buddypress-group-moderation' ),
			)
		);
	}

	/**
	 * Attach the page under the shared WB Plugins menu, creating the menu when this plugin loads first.
	 */
	public function add_menu() {
		if ( empty( $GLOBALS['admin_page_hooks']['wbcomplugins'] ) ) {
			add_menu_page( esc_html__( 'WB Plugins', 'buddypress-group-moderation' ), esc_html__( 'WB Plugins', 'buddypress-group-moderation' ), 'manage_options', 'wbcomplugins', array( $this, 'render_hub' ), 'dashicons-lightbulb', 59 );
		}

		add_submenu_page( 'wbcomplugins', esc_html__( 'BuddyPress Group Moderation', 'buddypress-group-moderation' ), esc_html__( 'Group Moderation', 'buddypress-group-moderation' ), 'manage_options', self::MENU_SLUG, array( $this, 'render_page' ) );
	}

	/**
	 * Register the General option with a sanitize callback.
	 */
	public function register_settings() {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);
	}

	/**
	 * Merge the submitted toggles over the stored option so keys this form does not own survive.
	 *
	 * @param mixed $input Submitted value.
	 * @return array
	 */
	public function sanitize( $input ) {
		$settings = get_option( self::OPTION, array() );
		$settings = is_array( $settings ) ? $settings : array();
		$input    = is_array( $input ) ? $input : array();

		foreach ( self::TOGGLES as $key ) {
			$settings[ $key ] = ! empty( $input[ $key ] );
		}

		return $settings;
	}

	/**
	 * Keep other plugins' notices off this screen.
	 */
	public function suppress_foreign_notices() {
		$screen = get_current_screen();
		if ( ! $screen || ! preg_match( '/_page_' . self::MENU_SLUG . '$/', $screen->id ) ) {
			return;
		}

		remove_all_actions( 'admin_notices' );
		remove_all_actions( 'all_admin_notices' );
	}

	/**
	 * Current tab slug, falling back to the first tab.
	 *
	 * @return string
	 */
	private function active_tab() {
		$tabs = self::get_tabs();
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab routing.

		return isset( $tabs[ $tab ] ) ? $tab : (string) key( $tabs );
	}

	/**
	 * Render the WB Plugins landing page when this plugin created the menu.
	 */
	public function render_hub() {
		include BP_GROUP_MODERATION_PLUGIN_DIR . 'includes/admin/views/hub.php';
	}

	/**
	 * Render the settings page: shell plus the active tab view.
	 */
	public function render_page() {
		$bgm_tabs     = self::get_tabs();
		$bgm_active   = $this->active_tab();
		$bgm_page_url = admin_url( 'admin.php?page=' . self::MENU_SLUG );
		$bgm_view     = BP_GROUP_MODERATION_PLUGIN_DIR . 'includes/admin/views/' . $bgm_active . '.php';
		$bgm_pending  = BP_Group_Moderation_Admin::get_instance()->bp_group_moderation_get_pending_groups( 1, 1 )['total'];

		include BP_GROUP_MODERATION_PLUGIN_DIR . 'includes/admin/views/shell.php';
	}
}
