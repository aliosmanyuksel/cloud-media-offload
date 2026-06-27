<?php
test('cache: flush is a safe no-op when no cache plugin is present', function () {
    // No WP cache functions/actions defined in the test harness;
    // flush() must guard everything and never fatal.
    R2MO_Cache::flush();
    assert_true(true);
});
