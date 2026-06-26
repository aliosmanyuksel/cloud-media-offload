<?php
// Pure classes guard with `if (!defined('ABSPATH')) exit;` — satisfy it for standalone testing.
define('ABSPATH', __DIR__ . '/');
define('R2MO_TESTING', true);

require __DIR__ . '/lib.php';

$inc = dirname(__DIR__) . '/includes/';
foreach (['settings', 'provider', 's3-client', 'offloader', 'rewriter', 'cache'] as $c) {
    $path = $inc . 'class-r2mo-' . $c . '.php';
    if (file_exists($path)) {
        require $path;
    }
}
