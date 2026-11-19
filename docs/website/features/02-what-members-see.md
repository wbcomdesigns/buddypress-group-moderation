# What Members See

**Included in Free**

## The group creator

- The group page shows a notice: "Your group is currently awaiting approval from a site administrator. You will be notified once it has been approved."
- The group's type reads **Pending approval, requested: Public** (or Private, or Hidden).
- In **Manage > Settings**, the privacy options show the type the creator asked for. Changing it updates the requested type, which is applied on approval. The group stays hidden until then.
- After a decision, the creator gets an on-site notification and, when emails are on, an email.

## Other members

- A pending group does not appear in the groups directory, in activity streams, or in search.
- Every new group waits, whether it was made on the Create a Group screen, through the BuddyPress REST API, or by another plugin.
- Members the creator invites while the group is pending get no notification or email yet. The invitation is sent when the group is approved, then appears under **Groups > Invitations** and can be accepted as usual. Accepting before approval is refused.

## Site administrators

Administrators see the pending notice on the group page and receive a notification for each new pending group. The notification links to **Groups > Pending Groups**. Once a group is approved, those notifications are removed for every administrator.
