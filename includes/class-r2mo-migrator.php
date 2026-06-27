<?php
if (!defined('ABSPATH')) {
    exit;
}

class R2MO_Migrator {

    const NONCE = 'r2mo_nonce';

    /** @var R2MO_Offloader */
    private $offloader;

    public function __construct(R2MO_Offloader $offloader) {
        $this->offloader = $offloader;
    }

    /** Attachment IDs not yet offloaded. */
    public function get_pending_ids($limit = -1) {
        return get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'posts_per_page' => $limit,
            'fields'         => 'ids',
            'no_found_rows'  => true,
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
            'meta_query'     => [[
                'key'     => R2MO_Offloader::META_OFFLOADED,
                'compare' => 'NOT EXISTS',
            ]],
        ]);
    }

    public function count_pending() {
        return count($this->get_pending_ids(-1));
    }

    /** AJAX: process one batch. */
    public function ajax_migrate_batch() {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'cloud-media-offload'));
        }
        $s    = R2MO_Settings::get();
        $ids  = $this->get_pending_ids((int) $s['batch_size']);
        $done = 0;
        $errors = [];
        foreach ($ids as $id) {
            $r = $this->offloader->offload_attachment($id);
            if ($r['ok']) {
                $done++;
            } else {
                $errors[] = "#{$id}: {$r['msg']}";
            }
        }
        $remaining = $this->count_pending();
        if ($done > 0) {
            R2MO_Cache::flush();
        }
        wp_send_json_success([
            'processed' => $done,
            'remaining' => $remaining,
            'errors'    => $errors,
        ]);
    }

    /** AJAX: write a temp object then delete it, to verify credentials. */
    public function ajax_test_connection() {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'cloud-media-offload'));
        }
        $s      = R2MO_Settings::get();
        $client = new R2MO_S3_Client(R2MO_Provider::config_from_settings($s));
        $key    = R2MO_Offloader::build_key($s['prefix'], '_r2mo-test.txt');

        $tmp = wp_tempnam('r2mo-test');
        file_put_contents($tmp, 'r2mo ' . gmdate('c'));
        $put = $client->put($key, $tmp, 'text/plain');
        wp_delete_file($tmp);

        if ($put['code'] >= 200 && $put['code'] < 300) {
            $client->delete($key);
            wp_send_json_success(__('Connection successful', 'cloud-media-offload') . ' ✓');
        }
        /* translators: 1: HTTP status code, 2: error message, 3: response body snippet */
        wp_send_json_error(sprintf( __('HTTP %1$d — %2$s %3$s', 'cloud-media-offload'), $put['code'], $put['error'], substr($put['body'], 0, 300) ));
    }

    /** WP-CLI: wp r2mo migrate [--delete-local] */
    public function cli_migrate($args, $assoc) {
        if (!empty($assoc['delete-local'])) {
            $opt = get_option(R2MO_Settings::OPTION, []);
            $opt['delete_local'] = 1;
            update_option(R2MO_Settings::OPTION, $opt);
            WP_CLI::log(__('delete-local enabled: uploaded files will be deleted locally.', 'cloud-media-offload'));
        }
        $ids   = $this->get_pending_ids(-1);
        $total = count($ids);
        /* translators: %d: number of attachments */
        WP_CLI::log(sprintf( __('To migrate: %d attachment(s)', 'cloud-media-offload'), $total ));
        $ok = 0; $fail = 0;
        foreach ($ids as $id) {
            $r = $this->offloader->offload_attachment($id);
            if ($r['ok']) {
                $ok++;
            } else {
                $fail++;
                WP_CLI::warning("#{$id}: {$r['msg']}");
            }
        }
        R2MO_Cache::flush();
        /* translators: 1: number of successful migrations, 2: number of failed migrations */
        WP_CLI::success(sprintf( __('Done. Succeeded: %1$d, Failed: %2$d', 'cloud-media-offload'), $ok, $fail ));
    }

    /** Cron action: process a batch in the background. */
    public function cron_migrate_batch() {
        if (!get_option('r2mo_migration_active')) {
            $timestamp = wp_next_scheduled('r2mo_cron_migration_batch');
            if ($timestamp) {
                wp_unschedule_event($timestamp, 'r2mo_cron_migration_batch');
            }
            return;
        }

        // Lock check to prevent concurrent processes running
        if (get_transient('r2mo_cron_migration_lock')) {
            return;
        }
        set_transient('r2mo_cron_migration_lock', 1, 55); // 55 seconds lock

        $s = R2MO_Settings::get();
        // A safe batch size for cron execution to prevent timeouts
        $batch_size = isset($s['batch_size']) ? (int) $s['batch_size'] : 20;
        $batch_size = min(max($batch_size, 1), 30); // limit to a max of 30 for background reliability

        $ids = $this->get_pending_ids($batch_size);
        if (empty($ids)) {
            update_option('r2mo_migration_active', 0);
            $timestamp = wp_next_scheduled('r2mo_cron_migration_batch');
            if ($timestamp) {
                wp_unschedule_event($timestamp, 'r2mo_cron_migration_batch');
            }
            delete_transient('r2mo_cron_migration_lock');
            return;
        }

        $stats = get_option('r2mo_migration_stats', [
            'processed' => 0,
            'errors'    => [],
        ]);

        $done = 0;
        foreach ($ids as $id) {
            $r = $this->offloader->offload_attachment($id);
            if ($r['ok']) {
                $done++;
            } else {
                $stats['errors'][] = "#{$id}: {$r['msg']}";
            }
        }

        $stats['processed'] += $done;
        
        // Truncate errors to prevent database bloat
        if (count($stats['errors']) > 100) {
            $stats['errors'] = array_slice($stats['errors'], -100);
        }

        update_option('r2mo_migration_stats', $stats);

        if ($done > 0) {
            R2MO_Cache::flush();
        }

        delete_transient('r2mo_cron_migration_lock');
    }

    /** AJAX: Start background migration. */
    public function ajax_start_background_migration() {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'cloud-media-offload'));
        }

        update_option('r2mo_migration_active', 1);
        update_option('r2mo_migration_stats', [
            'processed' => 0,
            'errors'    => [],
        ]);

        if (!wp_next_scheduled('r2mo_cron_migration_batch')) {
            wp_schedule_event(time(), 'r2mo_1min', 'r2mo_cron_migration_batch');
        }

        wp_send_json_success(__('Background migration started', 'cloud-media-offload'));
    }

    /** AJAX: Stop background migration. */
    public function ajax_stop_background_migration() {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'cloud-media-offload'));
        }

        update_option('r2mo_migration_active', 0);
        
        $timestamp = wp_next_scheduled('r2mo_cron_migration_batch');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'r2mo_cron_migration_batch');
        }

        delete_transient('r2mo_cron_migration_lock');

        wp_send_json_success(__('Background migration stopped', 'cloud-media-offload'));
    }

    /** AJAX: Get status of background migration. */
    public function ajax_get_background_migration_status() {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(__('Permission denied', 'cloud-media-offload'));
        }

        $active    = (int) get_option('r2mo_migration_active', 0);
        $stats     = get_option('r2mo_migration_stats', ['processed' => 0, 'errors' => []]);
        $pending   = $this->count_pending();
        $scheduled = wp_next_scheduled('r2mo_cron_migration_batch');

        wp_send_json_success([
            'active'    => $active,
            'processed' => $stats['processed'],
            'errors'    => $stats['errors'],
            'remaining' => $pending,
            'next_run'  => $scheduled ? $scheduled - time() : 0,
        ]);
    }
}
