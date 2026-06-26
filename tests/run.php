<?php
require __DIR__ . '/bootstrap.php';
foreach (glob(__DIR__ . '/unit/*.test.php') as $f) {
    require $f;
}
run_tests();
