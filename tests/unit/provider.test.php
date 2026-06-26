<?php
test('provider: r2 derives endpoint host from account id', function () {
    $cfg = R2MO_Provider::config_from_settings([
        'provider' => 'r2', 'account_id' => 'abc123',
        'access_key' => 'AK', 'secret_key' => 'SK', 'bucket' => 'media',
    ]);
    assert_eq('abc123.r2.cloudflarestorage.com', $cfg['endpoint_host']);
    assert_eq('auto', $cfg['region']);
    assert_eq(true, $cfg['path_style']);
    assert_eq('media', $cfg['bucket']);
});

test('provider: generic strips scheme and uses given region/path-style', function () {
    $cfg = R2MO_Provider::config_from_settings([
        'provider' => 'generic', 'endpoint' => 'https://s3.us-west-1.wasabisys.com/',
        'region' => 'us-west-1', 'path_style' => 0,
        'access_key' => 'AK', 'secret_key' => 'SK', 'bucket' => 'media',
    ]);
    assert_eq('s3.us-west-1.wasabisys.com', $cfg['endpoint_host']);
    assert_eq('us-west-1', $cfg['region']);
    assert_eq(false, $cfg['path_style']);
});

test('provider: host_from_endpoint strips http scheme too', function () {
    assert_eq('minio.local:9000', R2MO_Provider::host_from_endpoint('http://minio.local:9000/'));
});
