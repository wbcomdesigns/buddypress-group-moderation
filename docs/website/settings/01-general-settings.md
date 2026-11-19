# General Settings

**Included in Free**

Go to **WB Plugins > Group Moderation > General**, or click **Settings** next to the Pending Groups page title. Click **Save Settings** after a change; "Settings saved." confirms it.

All options are stored in the option `bp_group_moderation_general_settings`.

## Auto-approve admin groups

**Default:** On

When on, groups created by site administrators (users who can `manage_options`) go live at once and never enter the queue. When off, administrators' groups wait in Pending Groups like everyone else's.

## Send email notifications

**Default:** On

When on, administrators get an email for each new pending group, and creators get an email when their group is approved or rejected. The rejection email includes the reason, if one was given.

When off, no emails are sent. On-site BuddyPress notifications are still sent, and the reject form tells you that the reason will not reach the creator.

## Delete settings on uninstall

**Default:** Off

When on, deleting the plugin from **Plugins** also deletes its settings. When off, the settings are kept for a reinstall. Groups, and the approval state stored on them, are never deleted.

## Support tab

The **Support** tab answers common questions and links to the documentation, support and feedback pages.
