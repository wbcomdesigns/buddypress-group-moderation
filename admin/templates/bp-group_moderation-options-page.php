<?php
	/**
	 * This template file is used for fetching desired options page file at admin settings end.
	 *
	 * @link       https://wbcomdesigns.com/
	 *
	 * @since      1.0.0
	 *
	 * @package    BuddyPress_Group_Moderation
	 * @subpackage BuddyPress_Group_Moderation/admin/inc
	 */

	// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

	$bgm_tab = filter_input( INPUT_GET, 'tab' ) ? filter_input( INPUT_GET, 'tab' ) : 'welcome';
    

switch ( $bgm_tab ) {
	case 'welcome':
		include 'bgm-welcome-page.php';
		break;
	case 'general':
		include 'bgm-general-settings-tab.php';
		break;
	case 'support':
		include 'bgm-support-tab.php';
		break;
}