# BuddyPress Group Moderation

**Author:** Wbcom Designs  
**Version:** 1.0.1  
**Requires at least:** WordPress 5.0, BuddyPress 5.0, PHP 8.1  
**Tested up to:** WordPress 7.1, BuddyPress 14.5  
**License:** GPL-2.0+

## Description

BuddyPress Group Moderation introduces a moderation system for BuddyPress groups, requiring admin approval for new groups before they become active.

## Features

- All newly created groups are automatically set to "hidden" status
- Admins receive notifications when new groups are created
- Admins can approve or reject pending groups from a dedicated admin page
- Group creators are notified when their group is approved or rejected
- Pending groups can be hidden from regular users until approved
- Admin-created groups can be automatically approved (optional)
- Email notifications complement BuddyPress on-site notifications

## Installation

1. Upload the `buddypress-group-moderation` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to "Groups → Pending Groups" to view and manage pending groups
4. Configure the plugin under "WB Plugins → Group Moderation" (the page opens on the General tab), or with the "Settings" link beside the Pending Groups page title

## Requirements

- WordPress 5.0 or higher, PHP 8.1 or higher
- BuddyPress 5.0 or higher with the Groups component activated

## Configuration

Go to "WB Plugins → Group Moderation". The page has two tabs and a "Pending Groups" button showing the pending count.

**General**

- **Auto-approve admin groups**: groups created by site administrators go live at once.
- **Send email notifications**: email administrators about new pending groups, and email creators when their group is approved or rejected, in addition to BuddyPress on-site notifications.
- **Delete settings on uninstall**: off by default. When on, deleting the plugin also deletes its settings.

**Support**: frequently asked questions and links to the documentation and support team.

## Updates

The plugin is free and needs no licence key. It has no bundled update checker; new versions are published as GitHub releases.

## Usage

### For Administrators

1. When a new group is created, you'll receive a notification and (optionally) an email
2. Go to "Groups → Pending Groups" to see pending groups, 20 per page, with the pending count shown on the menu item
3. Click "Approve" to approve the group with the originally requested visibility settings, or tick several rows and click "Approve selected"
4. Click "Reject", optionally type a reason for the creator, then "Reject and delete group". Rejecting deletes the group and cannot be undone

### For Group Creators

1. When you create a new group, it will be set to pending status.
2. You'll see a notice on your group page indicating that the group is pending approval, and your groups list labels it "Pending approval, requested: <type>".
3. When your group is approved or rejected, you'll receive a notification and (optionally) an email.
4. If approved, your group will be set to the visibility type you originally requested.
5. While pending, changing the privacy in Manage > Settings changes the type applied on approval; the group stays hidden until then.
6. Members you invite while creating the group see the invitation once the group is approved.

## Frequently Asked Questions

### Does this plugin modify the BuddyPress core?

No, this plugin uses hooks and filters to add functionality without modifying BuddyPress core files.

### Can I approve groups without changing their visibility type?

Yes, when a group is approved, it automatically receives the visibility type (public, private, or hidden) that was originally requested by the creator.

### Will pending groups appear in group directories?

By default, pending groups are hidden from regular users in group directories and activity streams. Only the group creator and site administrators can see pending groups.

### Does it work with BuddyBoss?

No. On BuddyBoss, or with the Groups component off, the plugin does nothing and shows an admin notice.

## Changelog

See `readme.txt`.

## Credits

Created by [Wbcom Designs](https://wbcomdesigns.com/)
