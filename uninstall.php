<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('r2mo_settings');

// Note: We do NOT delete the '_r2mo_offloaded' post meta markers here.
// Doing so would cause reinstalled instances of the plugin to lose track
// of which media files have already been offloaded, resulting in duplicate uploads.
