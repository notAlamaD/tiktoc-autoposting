# TikTok Auto Poster

WordPress plugin for automatically publishing posts to TikTok using the official API.

## Features
- OAuth-based connection with storage of access/refresh tokens.
- Automatic or queued publishing for selected post types when first published.
- Description templating with common post tags.
- Queue dashboard with status, attempts, and logging controls.
- Connected accounts table to review the TikTok profile linked to the plugin.
- Cron-based retries and token refresh handling.

## Installation
1. Copy the `tiktok-auto-poster` directory to your WordPress `wp-content/plugins/` folder.
2. Activate **TikTok Auto Poster** from the Plugins screen.
3. On first activation, the plugin creates the queue table and schedules the cron task.

## Configuration
1. Navigate to **Settings → TikTok Auto Poster**.
2. Enter your TikTok `Client Key` and `Client Secret`. The redirect URI displayed on the page must be added to your TikTok app configuration.
3. Click **Connect TikTok account** (opens in a new tab) in the same settings screen to complete OAuth and store access/refresh tokens.
4. Choose which post types and statuses should trigger posting.
5. Configure media source, description template, queue usage, interval, and optional API logging.
6. Use **TikTok Posts** menu to inspect the queue, see connected accounts, and review recent statuses.

### TikTok app setup
- Create a TikTok developer application and enable content publishing permissions.
- Set the redirect URI to the value shown in plugin settings (`/tiktok-oauth-callback/`).
- After saving credentials, use the **Connect TikTok account** button on the settings page (opens in a new tab) to populate `access_token` and `refresh_token` values.

### Information for TikTok App Review
Provide the following explanation in the TikTok review form to describe how the plugin uses TikTok products and scopes:

- **Products**
  - **Login Kit**: Used to authenticate the site administrator via OAuth so the plugin can obtain and refresh tokens for posting.
  - **Content Posting API**: Used to upload media and publish TikTok posts that mirror WordPress content.

- **Scopes**
  - **`user.info.basic`**: Retrieved during OAuth to confirm the TikTok account identity and store the corresponding user ID alongside tokens.
  - **`video.upload`**: Required to upload video (and image, if supported) files selected from the WordPress post to TikTok.
  - **`video.publish`**: Required to publish the uploaded media with the generated description to the connected TikTok account.

If you submit a revision to TikTok, note any changes you made (for example, updated redirect URI or revised scopes) when resubmitting.

## Notes
- Tokens are stored in `wp_options` with encryption using the WordPress salt.
- The cron interval uses the value chosen in settings (5/15/30/60 minutes).
- Logging retains the latest 50 API responses when enabled.

## Development
The codebase is organized into the following components:
- `includes/class-tiktok-api-client.php`: TikTok HTTP client with token refresh and logging.
- `includes/class-tiktok-settings.php`: Admin settings, connected accounts, and queue pages.
- `includes/class-tiktok-queue.php`: Database queue CRUD and activation hooks.
- `includes/class-tiktok-cron.php`: Cron schedules and queue processing.
- `includes/class-tiktok-hooks.php`: Post status hooks to enqueue or publish immediately.
- `includes/helpers.php`: Utility helpers for encryption, templating, and media selection.

Internationalization files can be added under `tiktok-auto-poster/languages/`.
