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

    /** $headers is an assoc array with lowercase, ascending-sorted names and trimmed values. */
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

    /**
     * Sign and send a request. For PUT, $body_file is streamed.
     * @return array{code:int, body:string, error:string}
     */
    public function request($method, $key, $body_file = null, $content_type = 'application/octet-stream') {
        if (!function_exists('curl_init')) {
            return ['code' => 0, 'body' => '', 'error' => __('cURL extension not available', 'cloud-media-offload')];
        }

        $cfg    = $this->cfg;
        $region = $cfg['region'];
        $amz    = gmdate('Ymd\THis\Z');
        $date   = gmdate('Ymd');

        $payload_hash = ($body_file !== null && is_readable($body_file))
            ? hash_file('sha256', $body_file)
            : hash('sha256', '');

        $host = self::host_header($cfg);
        $uri  = self::canonical_uri($cfg, $key);

        $headers_assoc = [
            'content-type'         => $content_type,
            'host'                 => $host,
            'x-amz-content-sha256' => $payload_hash,
            'x-amz-date'           => $amz,
        ];
        $signed_headers = 'content-type;host;x-amz-content-sha256;x-amz-date';

        $canonical_request = self::build_canonical_request($method, $uri, $headers_assoc, $signed_headers, $payload_hash);
        $scope             = "{$date}/{$region}/" . self::SERVICE . "/aws4_request";
        $string_to_sign    = self::string_to_sign($amz, $scope, $canonical_request);

        $signing_key = self::derive_signing_key($cfg['secret_key'], $date, $region, self::SERVICE);
        $signature   = hash_hmac('sha256', $string_to_sign, $signing_key);

        $authorization = self::ALGO
            . " Credential={$cfg['access_key']}/{$scope},"
            . " SignedHeaders={$signed_headers},"
            . " Signature={$signature}";

        $url = "https://{$host}{$uri}";

        $http_headers = [
            "Authorization: {$authorization}",
            "Content-Type: {$content_type}",
            "x-amz-content-sha256: {$payload_hash}",
            "x-amz-date: {$amz}",
            "Host: {$host}",
        ];

        // Raw cURL (not WP HTTP API) so large PUT bodies stream from disk via
        // CURLOPT_INFILE instead of being buffered into memory. TLS is verified.
        // phpcs:disable WordPress.WP.AlternativeFunctions
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $http_headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 120,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ]);
        if ($method === 'HEAD') {
            curl_setopt($ch, CURLOPT_NOBODY, true);
        }

        $fh = null;
        if ($method === 'PUT' && $body_file !== null && is_readable($body_file)) {
            $fh = fopen($body_file, 'rb');
            curl_setopt($ch, CURLOPT_PUT, true);
            curl_setopt($ch, CURLOPT_INFILE, $fh);
            curl_setopt($ch, CURLOPT_INFILESIZE, filesize($body_file));
        }

        $resp  = curl_exec($ch);
        $code  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        if (is_resource($fh)) {
            fclose($fh);
        }
        // phpcs:enable WordPress.WP.AlternativeFunctions

        return ['code' => $code, 'body' => (string) $resp, 'error' => $error];
    }

    public function put($key, $file, $content_type = 'application/octet-stream') {
        return $this->request('PUT', $key, $file, $content_type);
    }

    public function head($key) {
        return $this->request('HEAD', $key);
    }

    public function delete($key) {
        return $this->request('DELETE', $key);
    }

    /** True when an object exists (HEAD returns 2xx). */
    public function exists($key) {
        $r = $this->head($key);
        return $r['code'] >= 200 && $r['code'] < 300;
    }
}
