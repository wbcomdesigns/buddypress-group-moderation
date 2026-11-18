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

* New      - Reject opens a confirm inside the row with an optional reason, and the reason is included in the email to the group creator.
* New      - Approve selected approves every ticked group on the Pending Groups page.
* New      - Opt-in setting to delete the plugin settings when the plugin is deleted, off by default.
* Improve  - Settings open on a General and Support panel with a Pending Groups shortcut and the pending count.
* Improve  - Plugin updates arrive on a built-in free licence key, with no licence key to enter or activate.
* Improve  - The settings screen and the Pending Groups list follow the admin colour scheme, work at phone width and in right-to-left languages.
* Improve  - The pending notice on the group page uses the BuddyPress notice style, in light and dark mode.
* Improve  - Manage > Settings on a pending group shows the type the creator asked for, and changing it updates the type applied on approval.
* Improve  - With the Groups component off, or on BuddyBoss, the plugin stays active and shows a notice instead of deactivating itself.
* Improve  - Settings fall back to their defaults when nothing is saved: auto-approve admin groups on, emails on, delete on uninstall off.
* Improve  - Pending groups are found with one query and cached, instead of one query per group.
* Fix      - Approving a group no longer fails with "An error occurred" and gives the group the type its creator asked for.
* Fix      - The creator of a pending group could make it public from Manage > Settings before approval.
* Fix      - Members invited while the group was being created could join it before approval; the invitation now waits for approval.
* Fix      - The rejected-group notification showed a blank group name when emails were off.
* Fix      - The Pending Groups page was blank when opened from an expired notification link.
* Fix      - Notifications about a deleted group are removed with the group, and approval removes the pending notifications of every administrator.
* Fix      - Removed the hourly pending-groups check; a pending group now stays hidden on every save, and old schedules are cleared.
* Fix      - The Welcome and Themes & Extension tabs and the extra WB Plugins pages are gone.
* Fix      - Saving General settings keeps any other values stored in the same option.
* Security - A member could mark another member's notifications as read by changing the id in the link.
* Security - Removed leftover developer tools: the hidden group status actions, the debug info view and the silenced errors in the approve/reject request.
* Dev      - bp_group_moderation_group_decision_mail passes the reject reason as a fourth argument.
* Dev      - The plugin header declares Requires PHP 8.1.
* Dev      - Removed the never-fired bp_group_moderation_verify_status hook, three filters on hooks BuddyPress never fires, and a dead settings branch on the Pending Groups page.
* Dev      - Removed the licence screen, notices and stored licence options; the key is the EDD_GROUP_MODERATION_FREE_LICENSE_KEY constant and old licence data is deleted on the next admin page load.
* Dev      - A missing edd-license folder shows an admin notice instead of a fatal error.

= 1.0.0 =

* New      - Initial release.
