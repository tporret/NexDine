# NexDine WordPress Plugin

This repository contains the NexDine WordPress plugin starter scaffold.

## Structure

- `nexdine.php`: Plugin bootstrap and lifecycle hooks.
- `includes/`: Core classes, i18n, activation/deactivation.
- `admin/`: Admin-facing hooks and assets.
- `public/`: Front-end hooks and assets.
- `uninstall.php`: Cleanup logic for plugin uninstall.

## Local Development

1. Place this folder in your WordPress installation at `wp-content/plugins/nexdine`.
2. Activate **NexDine** from **Plugins** in WordPress admin.
3. Build plugin features in `includes/`, `admin/`, and `public/`.

## Vapi Account Setup

1. In WordPress admin, go to **Settings > NexDine AI Voice**.
2. Add your Vapi **Public Key**, **Private Key**, and optional IDs/secrets.
3. Save settings. NexDine stores credentials in the WordPress options table under `nexdine_vapi_settings`.

### Security note

- The Vapi private key is encrypted at rest before it is saved in the database.
