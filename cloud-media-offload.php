<?php
/**
 * Plugin Name: Cloud Media Offload
 * Description: Offload WordPress & WooCommerce media to Cloudflare R2 or any S3-compatible storage and serve it from your CDN. No SDK — AWS Signature V4 signed with plain cURL.
 * Version: 1.1.0
 * Author: Ali Osman Yüksel
 * Author URI: https://alios.tr
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Text Domain: cloud-media-offload
 * Domain Path: /languages
 */

if (!defined('ABSPATH')) {
    exit;
}

define('R2MO_VERSION', '1.1.0');
define('R2MO_FILE', __FILE__);
define('R2MO_DIR', plugin_dir_path(__FILE__));
define('R2MO_URL', plugin_dir_url(__FILE__));

require_once R2MO_DIR . 'includes/class-r2mo-settings.php';
require_once R2MO_DIR . 'includes/class-r2mo-provider.php';
require_once R2MO_DIR . 'includes/class-r2mo-s3-client.php';
require_once R2MO_DIR . 'includes/class-r2mo-cache.php';
require_once R2MO_DIR . 'includes/class-r2mo-offloader.php';
require_once R2MO_DIR . 'includes/class-r2mo-rewriter.php';
require_once R2MO_DIR . 'includes/class-r2mo-migrator.php';
require_once R2MO_DIR . 'includes/class-r2mo-admin.php';
require_once R2MO_DIR . 'includes/class-r2mo-plugin.php';

add_action('plugins_loaded', function () {
    R2MO_Plugin::instance();
});
