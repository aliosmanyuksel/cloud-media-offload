<?php
if (!defined('ABSPATH')) {
    exit;
}

class R2MO_Provider {

    /** Pure: build the S3 client config from effective settings. */
    public static function config_from_settings(array $s) {
        $provider = $s['provider'] ?? 'r2';
        if ($provider === 'r2') {
            $host       = ($s['account_id'] ?? '') . '.r2.cloudflarestorage.com';
            $region     = 'auto';
            $path_style = true;
        } else {
            $host       = self::host_from_endpoint($s['endpoint'] ?? '');
            $region     = ($s['region'] ?? '') !== '' ? $s['region'] : 'us-east-1';
            $path_style = !empty($s['path_style']);
        }
        return [
            'endpoint_host' => $host,
            'region'        => $region,
            'access_key'    => $s['access_key'] ?? '',
            'secret_key'    => $s['secret_key'] ?? '',
            'bucket'        => $s['bucket'] ?? '',
            'path_style'    => $path_style,
        ];
    }

    /** Pure: normalize an endpoint URL down to a bare host[:port]. */
    public static function host_from_endpoint($endpoint) {
        $endpoint = preg_replace('#^https?://#i', '', trim((string) $endpoint));
        return rtrim($endpoint, '/');
    }
}
