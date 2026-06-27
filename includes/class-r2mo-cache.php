<?php
if (!defined('ABSPATH')) {
    exit;
}

class R2MO_Cache {

    /** Best-effort purge of common cache plugins. Every call is guarded. */
    public static function flush() {
        // LiteSpeed Cache
        // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
        if (function_exists('do_action') && function_exists('has_action') && has_action('litespeed_purge_all')) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
            do_action('litespeed_purge_all');
        }
        // WP Rocket
        if (function_exists('rocket_clean_domain')) {
            rocket_clean_domain();
        }
        // W3 Total Cache
        if (function_exists('w3tc_flush_all')) {
            w3tc_flush_all();
        }
        // WP Fastest Cache
        if (function_exists('do_action')) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
            do_action('wpfc_clear_all_cache', true);
        }
        // Autoptimize
        if (class_exists('autoptimizeCache') && method_exists('autoptimizeCache', 'clearall')) {
            autoptimizeCache::clearall();
        }
        // WP-Optimize
        if (function_exists('WP_Optimize') && method_exists(WP_Optimize(), 'get_page_cache')) {
            $pc = WP_Optimize()->get_page_cache();
            if ($pc && method_exists($pc, 'purge')) {
                $pc->purge();
            }
        }
        // Woodmart theme cache
        if (function_exists('do_action')) {
            // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
            do_action('woodmart_clear_all_cache');
        }
    }
}
