<?php
// Pure classes guard with `if (!defined('ABSPATH')) exit;` — satisfy it for standalone testing.
define('ABSPATH', __DIR__ . '/');
define('R2MO_TESTING', true);

require __DIR__ . '/lib.php';

// Minimal gettext stubs so i18n-wrapped classes load under the standalone harness.
if (!function_exists('__'))            { function __($t, $d = 'default') { return $t; } }
if (!function_exists('esc_html__'))    { function esc_html__($t, $d = 'default') { return $t; } }
if (!function_exists('esc_attr__'))    { function esc_attr__($t, $d = 'default') { return $t; } }
if (!function_exists('esc_html_e'))    { function esc_html_e($t, $d = 'default') { echo $t; } }
if (!function_exists('esc_attr_e'))    { function esc_attr_e($t, $d = 'default') { echo $t; } }
if (!function_exists('_e'))            { function _e($t, $d = 'default') { echo $t; } }
if (!function_exists('_x'))            { function _x($t, $c, $d = 'default') { return $t; } }

$inc = dirname(__DIR__) . '/includes/';
foreach (['settings', 'provider', 's3-client', 'offloader', 'rewriter', 'cache'] as $c) {
    $path = $inc . 'class-r2mo-' . $c . '.php';
    if (file_exists($path)) {
        require $path;
    }
}
