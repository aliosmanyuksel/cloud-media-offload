<?php
/**
 * Plugin Name: R2 Media Offload
 * Description: WordPress medyasını Cloudflare R2 (veya S3-uyumlu depolama) üzerine taşır ve sunar. SDK kullanmaz; AWS Signature V4'ü saf cURL ile imzalar.
 * Version: 1.0.0
 * Author: Berkant Mobilya
 * License: GPL-2.0-or-later
 * Requires PHP: 7.4
 * Text Domain: r2-media-offload
 */

if (!defined('ABSPATH')) {
    exit;
}

define('R2MO_VERSION', '1.0.0');
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
