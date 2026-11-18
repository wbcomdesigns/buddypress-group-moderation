=== BuddyPress Group Moderation ===
Contributors: wbcomdesigns
Tags: buddypress, groups, moderation, approval
Requires at least: 5.0
Tested up to: 7.1
Requires PHP: 8.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

New BuddyPress groups wait for an administrator to approve them before members can see them.

== Description ==

Every new group is held as hidden and pending until a site administrator approves it from Groups > Pending Groups. The creator is notified on approval or rejection, and an approved group gets the visibility its creator asked for.

Settings live under WB Plugins > Group Moderation, with General, License and Support tabs.

== Changelog ==

= 1.0.1 - November 2026 =

* New      - Opt-in setting to delete the plugin settings and licence key when the plugin is deleted, off by default.
* Improve  - Settings open on a General, License and Support panel with a Pending Groups shortcut and the pending count.
* Improve  - The settings screen follows the admin colour scheme, works at phone width and in right-to-left languages.
* Fix      - The Welcome and Themes & Extension tabs and the extra WB Plugins pages are gone; the licence form moved to the License tab with the same key and status.
* Fix      - Saving General settings keeps any other values stored in the same option.
* Fix      - The License tab no longer shows a stray "1" when no key is saved, and no PHP warnings when the licence server cannot be reached.
* Fix      - Deactivating the plugin removes its hourly pending-groups check.
* Security - Removed leftover developer tools: the hidden group status actions, the debug info view and the silenced errors in the approve/reject request.
* Dev      - The plugin header declares Requires PHP 8.1.
* Dev      - Removed the never-fired bp_group_moderation_verify_status hook and a dead settings branch on the Pending Groups page.

= 1.0.0 =

* New      - Initial release.
