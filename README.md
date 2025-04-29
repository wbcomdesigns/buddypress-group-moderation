# BuddyPress Group Moderation

**Author:** Wbcom Designs  
**Version:** 1.0.0  
**Requires at least:** WordPress 5.0, BuddyPress 5.0  
**Tested up to:** WordPress 6.2, BuddyPress 11.0  
**License:** GPL-2.0+

## Description

BuddyPress Group Moderation introduces a moderation system for BuddyPress groups, requiring admin approval for new groups before they become active.

## Features

- All newly created groups are automatically set to "pending" status
- Admins receive notifications when new groups are created
- Admins can approve or reject pending groups from a dedicated admin page
- Group creators are notified when their group is approved or rejected
- Pending groups can be hidden from regular users until approved
- Admin-created groups can be automatically approved (optional)
- Email notifications complement BuddyPress on-site notifications

## Installation

1. Upload the `buddypress-group-moderation` folder to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Navigate to "BuddyPress → Pending Groups" to view and manage pending groups
4. Configure the plugin settings by clicking on the "Settings" button on the Pending Groups page

## Requirements

- WordPress 5.0 or higher
- BuddyPress 5.0 or higher with the Groups component activated

## Configuration

You can configure the plugin by navigating to "BuddyPress → Pending Groups" and clicking on the "Settings" button. Available settings include:

- **Auto-approve admin groups**: When enabled, groups created by site administrators are automatically approved
- **Send email notifications**: When enabled, email notifications are sent in addition to BuddyPress on-site notifications

## Usage

### For Administrators

1. When a new group is created, you'll receive a notification and (optionally) an email
2. Go to "BuddyPress → Pending Groups" to see all pending groups
3. Click "Approve" to approve the group with the originally requested visibility settings
4. Click "Reject" to delete the group and notify the creator

### For Group Creators

1. When you create a new group, it will be set to pending status
2. You'll see a notice on your group page indicating that the group is pending approval
3. When your group is approved or rejected, you'll receive a notification and (optionally) an email
4. If approved, your group will be set to the visibility type you originally requested

## Frequently Asked Questions

### Does this plugin modify the BuddyPress core?

No, this plugin uses hooks and filters to add functionality without modifying BuddyPress core files.

### Can I approve groups without changing their visibility type?

Yes, when a group is approved, it automatically receives the visibility type (public, private, or hidden) that was originally requested by the creator.

### Will pending groups appear in group directories?

By default, pending groups are hidden from regular users in group directories and activity streams. Only the group creator and site administrators can see pending groups.

## Changelog

### 1.0.0

- Initial release

## Credits

Created by [Wbcom Designs](https://wbcomdesigns.com/)
