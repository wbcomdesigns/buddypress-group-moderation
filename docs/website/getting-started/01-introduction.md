# Introduction

**Included in Free**

BuddyPress Group Moderation makes every new BuddyPress group wait for a site administrator before members can see it.

## What the plugin does

- A new group is saved as **hidden** and marked **pending approval**. The type the creator picked (public, private or hidden) is remembered.
- Site administrators get an on-site notification and an email for each new pending group.
- Administrators approve or reject groups from **Groups > Pending Groups**.
- On approval the group gets the type its creator asked for, and the creator is notified.
- On rejection the group is deleted, and the creator is notified, with an optional reason in the email.

While a group is pending, it does not appear in group directories or activity streams, nobody can join it, and its creator cannot make it public from the group's settings.

## Requirements

- WordPress with PHP 8.1 or later.
- BuddyPress with the **Groups** component turned on.

The plugin does not work with BuddyBoss. On a BuddyBoss site it stays inactive in practice and shows a notice saying so.
