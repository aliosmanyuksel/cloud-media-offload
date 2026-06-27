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
}
