=== Cloud Media Offload ===
Contributors: aliosmanyuksel
Tags: media offload, cloudflare r2, amazon s3, cdn, woocommerce
Requires at least: 5.6
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Offload WordPress/WooCommerce media to Cloudflare R2 or any S3-compatible storage and serve it from your CDN. Lightweight, no SDK.

== Description ==
Cloud Media Offload moves your WordPress and WooCommerce media to Cloudflare R2 (or any S3-compatible bucket: Wasabi, Backblaze B2, DigitalOcean Spaces, MinIO, and more) and rewrites your site's media URLs to serve from your CDN domain.

It signs AWS Signature V4 requests by hand over plain cURL — no bundled SDK, tiny footprint, works on PHP 7.4 through 8.3+.

Features:

* Cloudflare R2 and generic S3-compatible storage (endpoint / region / path-style).
* Automatic offload of new uploads, with HEAD verification before marking complete.
* Two-layer URL rewriting: per-attachment filters (always safe) plus an optional, opt-in full-page output-buffer rewrite for theme and page-builder embedded images.
* Bulk migration of existing media (browser progress UI plus WP-CLI: wp r2mo migrate).
* Guided 5-step setup wizard and one-click connection test.
* Optional deletion of local copies after a verified upload.
* Credentials can be set as wp-config.php constants (overriding the database).
* Best-effort cache flush for popular cache plugins.

== Installation ==
1. Upload the plugin to /wp-content/plugins/cloud-media-offload and activate it.
2. Open Cloud Offload > Setup Wizard, choose your provider, and enter your credentials, bucket, and Public Base URL.
3. Click Test connection.
4. Run Cloud Offload > Migration to move existing media.
5. After migration shows 0 pending, enable Full-page URL rewriting in Settings.

For best security, define your credentials in wp-config.php:

    define('R2MO_ACCOUNT_ID', '...');
    define('R2MO_ACCESS_KEY', '...');
    define('R2MO_SECRET_KEY', '...');
    define('R2MO_BUCKET', '...');
    define('R2MO_ENDPOINT', '...');   // generic S3-compatible only
    define('R2MO_REGION', '...');
    define('R2MO_PUBLIC_BASE', 'https://cdn.example.com');

== Frequently Asked Questions ==

= Does it work with Cloudflare R2's zero egress fees? =
Yes. Point your Public Base URL at an R2 custom domain (or r2.dev) and your media is served with no egress charges.

= Are my secret keys safe? =
Define them as wp-config.php constants and they are never stored in the database. Otherwise they are stored in the options table, like most offload plugins.

= Will it work with WooCommerce and Woodmart? =
Yes. Product and gallery images use the standard media library. The optional full-page rewrite also covers theme and page-builder embedded image URLs.

== Changelog ==

= 1.0.1 =
* Fix: Prevent uninstall.php from deleting _r2mo_offloaded post metadata during plugin deletion, preserving offload state on reinstall.

= 1.0.0 =
* Initial release.

== Upgrade Notice ==

= 1.0.1 =
Prevent uninstall.php from deleting _r2mo_offloaded post metadata during plugin deletion, preserving offload state on reinstall.

= 1.0.0 =
Initial release.
