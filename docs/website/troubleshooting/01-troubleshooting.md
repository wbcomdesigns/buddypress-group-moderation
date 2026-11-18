# Troubleshooting

**Included in Free**

## New groups go live without waiting

- Check **Settings > BuddyPress > Components**: Groups must be on. With it off, the plugin shows a notice and does nothing.
- Groups created by administrators go live at once while **Auto-approve admin groups** is on.

## Nobody gets emails

Check that **Send email notifications** is on, and that your site can send email (test with an SMTP plugin). On-site notifications need the BuddyPress **Notifications** component.

## A notice says the update files are missing

The `edd-license` folder was removed, for example by a security scanner or a partial upload. The plugin still works, but automatic updates are off. Reinstall the plugin from wbcomdesigns.com.

## A notice says the plugin does nothing on BuddyBoss

The plugin works with BuddyPress only. Deactivate it on BuddyBoss sites.
