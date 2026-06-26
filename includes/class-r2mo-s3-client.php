<?php
if (!defined('ABSPATH')) {
    exit;
}

class R2MO_S3_Client {

    const SERVICE = 's3';
    const ALGO    = 'AWS4-HMAC-SHA256';

    /** @var array endpoint_host, region, access_key, secret_key, bucket, path_style */
    private $cfg;

    public function __construct(array $cfg) {
        $this->cfg = $cfg;
    }

    /* ---------------- Pure primitives ---------------- */

    public static function canonical_key($key) {
        return implode('/', array_map('rawurlencode', explode('/', $key)));
    }

    public static function derive_signing_key($secret, $date, $region, $service) {
        $k = hash_hmac('sha256', $date, 'AWS4' . $secret, true);
        $k = hash_hmac('sha256', $region, $k, true);
        $k = hash_hmac('sha256', $service, $k, true);
        return hash_hmac('sha256', 'aws4_request', $k, true);
    }

    public static function host_header(array $cfg) {
        return !empty($cfg['path_style'])
            ? $cfg['endpoint_host']
            : $cfg['bucket'] . '.' . $cfg['endpoint_host'];
    }

    public static function canonical_uri(array $cfg, $key) {
        $ck = self::canonical_key($key);
        return !empty($cfg['path_style'])
            ? '/' . $cfg['bucket'] . '/' . $ck
            : '/' . $ck;
    }

    /** $headers is an assoc array already sorted ascending by lowercase name. */
    public static function build_canonical_request($method, $uri, array $headers, $signed_headers, $payload_hash) {
        $canon = '';
        foreach ($headers as $name => $value) {
            $canon .= $name . ':' . $value . "\n";
        }
        return implode("\n", [$method, $uri, '', $canon, $signed_headers, $payload_hash]);
    }

    public static function string_to_sign($amz_date, $scope, $canonical_request) {
        return implode("\n", [
            self::ALGO,
            $amz_date,
            $scope,
            hash('sha256', $canonical_request),
        ]);
    }

    /* ---------------- Request execution (Task 5) ---------------- */
}
