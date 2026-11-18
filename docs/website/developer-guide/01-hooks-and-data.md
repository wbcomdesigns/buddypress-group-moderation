# Hooks and Data

**Included in Free**

## Filters

| Filter | Arguments | Use |
|---|---|---|
| `bp_group_moderation_group_pending_mail_subject` | `$subject` | Subject of the email to administrators about a new pending group. |
| `bp_group_moderation_group_pending_mail` | `$message`, `$group_id`, `$admin_id` | HTML body of that email, per administrator. |
| `bp_group_moderation_group_decision_mail_subject` | `$subject` | Subject of the approved or rejected email to the creator. |
| `bp_group_moderation_group_decision_mail` | `$message`, `$group`, `$decision`, `$reason` | HTML body of that email. `$decision` is `approved` or `rejected`; `$reason` is the administrator's text, or an empty string. |
| `bp_group_moderation_admin_setting_tabs` | `$tabs` | Tabs on the settings page, slug => label. |
| `bp_group_moderation_hold_new_group` | `$hold`, `$group` | Return false to let a new group skip moderation, for groups a plugin creates for its own records. Runs for every new group, from any screen, REST or code. |

## Actions

| Action | Use |
|---|---|
| `bp_group_moderation_tab_contents` | Fires after the active settings tab, for tabs added through the filter above. |

## Stored data

| Where | Key | Meaning |
|---|---|---|
| Group meta | `approval_status` | `pending` while the group waits. Removed on approval. |
| Group meta | `requested_status` | The type applied on approval: `public`, `private` or `hidden`. |
| User meta | `bp_grp_moderation_rejected_group_{group_id}` | Name of a rejected group, kept for the creator's notification text after the group is deleted. |
| Option | `bp_group_moderation_general_settings` | The General settings. |

BuddyPress notifications use the `groups` component with the actions `new_group_pending`, `group_approved` and `group_rejected`.

A new group is held on `groups_create_group`, which every creation path fires. A pending group is kept hidden on every save, through `groups_group_before_save`, whatever screen or API saves it. Invitations to a pending group stay drafts until approval.
