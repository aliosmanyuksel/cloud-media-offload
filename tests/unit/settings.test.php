<?php
test('resolve: constants override db values', function () {
    $db = ['account_id' => 'db-acct', 'bucket' => 'db-bucket', 'region' => 'auto'];
    $const = ['account_id' => 'const-acct', 'bucket' => null, 'secret_key' => 'const-secret'];
    $out = R2MO_Settings::resolve($db, $const);
    assert_eq('const-acct', $out['account_id'], 'const wins when set');
    assert_eq('db-bucket', $out['bucket'], 'null const does not override db');
    assert_eq('auto', $out['region'], 'db value preserved');
    assert_eq('const-secret', $out['secret_key'], 'const-only key added');
});

test('resolve: empty-string constant does not override', function () {
    $out = R2MO_Settings::resolve(['bucket' => 'real'], ['bucket' => '']);
    assert_eq('real', $out['bucket']);
});
