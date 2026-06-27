<?php
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

delete_option('r2mo_settings');

// Remove the offloaded marker from all attachments.
delete_post_meta_by_key('_r2mo_offloaded');
