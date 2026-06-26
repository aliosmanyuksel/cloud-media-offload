<?php
if (!defined('ABSPATH')) {
    exit;
}

class R2MO_Settings {

    const OPTION = 'r2mo_settings';

    /** Map of setting key => wp-config constant name. */
    const CONSTANTS = [
        'account_id'  => 'R2MO_ACCOUNT_ID',
        'access_key'  => 'R2MO_ACCESS_KEY',
        'secret_key'  => 'R2MO_SECRET_KEY',
        'bucket'      => 'R2MO_BUCKET',
        'endpoint'    => 'R2MO_ENDPOINT',
        'region'      => 'R2MO_REGION',
        'public_base' => 'R2MO_PUBLIC_BASE',
    ];

    /** Pure: constants (non-empty) win over db values. */
    public static function resolve(array $db, array $const) {
        $out = $db;
        foreach ($const as $k => $v) {
            if ($v !== null && $v !== '') {
                $out[$k] = $v;
            }
        }
        return $out;
    }

    /** Read defined wp-config constants into a key=>value map. */
    public static function constant_values() {
        $vals = [];
        foreach (self::CONSTANTS as $key => $const) {
            $vals[$key] = defined($const) ? constant($const) : null;
        }
        return $vals;
    }

    /** Returns true if a given setting key is locked by a wp-config constant. */
    public static function is_constant($key) {
        return isset(self::CONSTANTS[$key]) && defined(self::CONSTANTS[$key]);
    }

    /** Default values merged under stored options. */
    public static function defaults() {
        return [
            'provider'         => 'r2',
            'account_id'       => '',
            'endpoint'         => '',
            'region'           => 'auto',
            'path_style'       => 1,
            'access_key'       => '',
            'secret_key'       => '',
            'bucket'           => '',
            'public_base'      => '',
            'prefix'           => '',
            'auto_offload'     => 1,
            'delete_local'     => 0,
            'fullpage_rewrite' => 0,
            'batch_size'       => 20,
        ];
    }

    /** Effective (constant-resolved) settings used by every other class. */
    public static function get() {
        $db = get_option(self::OPTION, []);
        if (!is_array($db)) {
            $db = [];
        }
        $merged = array_merge(self::defaults(), $db);
        return self::resolve($merged, self::constant_values());
    }

    /** register_setting sanitize callback. */
    public static function sanitize($input) {
        $d = self::defaults();
        $in = is_array($input) ? $input : [];
        return [
            'provider'         => in_array(($in['provider'] ?? 'r2'), ['r2', 'generic'], true) ? ($in['provider'] ?? 'r2') : 'r2',
            'account_id'       => sanitize_text_field($in['account_id'] ?? ''),
            'endpoint'         => sanitize_text_field($in['endpoint'] ?? ''),
            'region'           => sanitize_text_field($in['region'] ?? 'auto'),
            'path_style'       => !empty($in['path_style']) ? 1 : 0,
            'access_key'       => sanitize_text_field($in['access_key'] ?? ''),
            'secret_key'       => sanitize_text_field($in['secret_key'] ?? ''),
            'bucket'           => sanitize_text_field($in['bucket'] ?? ''),
            'public_base'      => esc_url_raw($in['public_base'] ?? ''),
            'prefix'           => trim(sanitize_text_field($in['prefix'] ?? ''), '/'),
            'auto_offload'     => !empty($in['auto_offload']) ? 1 : 0,
            'delete_local'     => !empty($in['delete_local']) ? 1 : 0,
            'fullpage_rewrite' => !empty($in['fullpage_rewrite']) ? 1 : 0,
            'batch_size'       => max(1, min(200, (int) ($in['batch_size'] ?? $d['batch_size']))),
        ];
    }

    public static function register() {
        register_setting('r2mo_group', self::OPTION, [__CLASS__, 'sanitize']);
    }
}
