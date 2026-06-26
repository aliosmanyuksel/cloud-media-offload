<?php
$GLOBALS['__r2mo_tests'] = [];

function test($name, callable $fn) {
    $GLOBALS['__r2mo_tests'][] = [$name, $fn];
}

function assert_eq($expected, $actual, $msg = '') {
    if ($expected !== $actual) {
        throw new Exception(
            ($msg ? $msg . ' — ' : '') .
            'expected ' . var_export($expected, true) .
            ' got ' . var_export($actual, true)
        );
    }
}

function assert_true($cond, $msg = '') {
    if ($cond !== true) {
        throw new Exception(($msg ?: 'assert_true failed'));
    }
}

function run_tests() {
    $pass = 0; $fail = 0;
    foreach ($GLOBALS['__r2mo_tests'] as [$name, $fn]) {
        try {
            $fn();
            $pass++;
            echo "  ok {$name}\n";
        } catch (Throwable $e) {
            $fail++;
            echo "  x {$name}: " . $e->getMessage() . "\n";
        }
    }
    echo "\n{$pass} passed, {$fail} failed\n";
    exit($fail === 0 ? 0 : 1);
}
