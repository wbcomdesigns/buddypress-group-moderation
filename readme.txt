=== BuddyPress Group Moderation ===
Contributors: wbcomdesigns
Tags: buddypress, groups, moderation, approval
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

New BuddyPress groups wait for an administrator to approve them before members can see them.

== Description ==

Every new group is held as hidden and pending until a site administrator approves it from Groups > Pending Groups. The creator is notified on approval or rejection, and an approved group gets the visibility its creator asked for.

Settings live under WB Plugins > Group Moderation, with General and Support tabs. The plugin is free: updates come from Wbcom Designs on a built-in licence key, so there is no key to enter.

== Changelog ==

= 1.0.1 - November 2026 =

* New      - Opt-in setting to delete the plugin settings when the plugin is deleted, off by default.
* Improve  - Settings open on a General and Support panel with a Pending Groups shortcut and the pending count.
* Improve  - Plugin updates arrive on a built-in free licence key, with no licence key to enter or activate.
* Improve  - The settings screen follows the admin colour scheme, works at phone width and in right-to-left languages.
* Fix      - The Welcome and Themes & Extension tabs and the extra WB Plugins pages are gone.
* Fix      - Saving General settings keeps any other values stored in the same option.
* Fix      - Deactivating the plugin removes its hourly pending-groups check.
* Security - Removed leftover developer tools: the hidden group status actions, the debug info view and the silenced errors in the approve/reject request.
* Dev      - The plugin header declares Requires PHP 8.1.
* Dev      - Removed the never-fired bp_group_moderation_verify_status hook and a dead settings branch on the Pending Groups page.
* Dev      - Removed the licence screen, notices and stored licence options; the key is the EDD_GROUP_MODERATION_FREE_LICENSE_KEY constant and old licence data is deleted on the next admin page load.

= 1.0.0 =

* New      - Initial release.
