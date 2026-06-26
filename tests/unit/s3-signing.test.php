<?php
test('s3: canonical_key encodes segments but keeps slashes', function () {
    assert_eq('2026/06/a%20b.jpg', R2MO_S3_Client::canonical_key('2026/06/a b.jpg'));
});

test('s3: derive_signing_key matches AWS known-answer vector', function () {
    // Canonical AWS SigV4 example inputs.
    $key = R2MO_S3_Client::derive_signing_key(
        'wJalrXUtnFEMI/K7MDENG/bPxRfiCYEXAMPLEKEY',
        '20120215', 'us-east-1', 'iam'
    );
    assert_eq(
        '004aa806e13dae88b9032d9261bcb04c67d023afadd221e6b0d206e1760e0b5e',
        bin2hex($key)
    );
});

test('s3: host_header and canonical_uri for path-style', function () {
    $cfg = ['endpoint_host' => 'acct.r2.cloudflarestorage.com', 'bucket' => 'media', 'path_style' => true];
    assert_eq('acct.r2.cloudflarestorage.com', R2MO_S3_Client::host_header($cfg));
    assert_eq('/media/2026/06/x.jpg', R2MO_S3_Client::canonical_uri($cfg, '2026/06/x.jpg'));
});

test('s3: host_header and canonical_uri for virtual-hosted', function () {
    $cfg = ['endpoint_host' => 's3.wasabisys.com', 'bucket' => 'media', 'path_style' => false];
    assert_eq('media.s3.wasabisys.com', R2MO_S3_Client::host_header($cfg));
    assert_eq('/2026/06/x.jpg', R2MO_S3_Client::canonical_uri($cfg, '2026/06/x.jpg'));
});

test('s3: build_canonical_request produces exact string', function () {
    $headers = [
        'content-type'         => 'image/jpeg',
        'host'                 => 'acct.r2.cloudflarestorage.com',
        'x-amz-content-sha256' => 'PAYLOAD',
        'x-amz-date'           => '20260627T120000Z',
    ];
    $cr = R2MO_S3_Client::build_canonical_request(
        'PUT', '/media/x.jpg', $headers,
        'content-type;host;x-amz-content-sha256;x-amz-date', 'PAYLOAD'
    );
    $expected = "PUT\n/media/x.jpg\n\n"
        . "content-type:image/jpeg\nhost:acct.r2.cloudflarestorage.com\n"
        . "x-amz-content-sha256:PAYLOAD\nx-amz-date:20260627T120000Z\n\n"
        . "content-type;host;x-amz-content-sha256;x-amz-date\nPAYLOAD";
    assert_eq($expected, $cr);
});

test('s3: string_to_sign hashes the canonical request', function () {
    $sts = R2MO_S3_Client::string_to_sign(
        '20260627T120000Z', '20260627/auto/s3/aws4_request', 'CANON'
    );
    $expected = "AWS4-HMAC-SHA256\n20260627T120000Z\n20260627/auto/s3/aws4_request\n" . hash('sha256', 'CANON');
    assert_eq($expected, $sts);
});
