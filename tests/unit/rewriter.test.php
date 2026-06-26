<?php
test('rewriter: target_base appends prefix when present', function () {
    assert_eq('https://cdn.example.com/wp', R2MO_Rewriter::target_base('https://cdn.example.com/', 'wp'));
    assert_eq('https://cdn.example.com', R2MO_Rewriter::target_base('https://cdn.example.com', ''));
});

test('rewriter: swap_base replaces the uploads base url everywhere', function () {
    $html = '<img src="https://site.com/wp-content/uploads/2026/06/a.jpg" '
          . 'srcset="https://site.com/wp-content/uploads/2026/06/a-300x200.jpg 300w">';
    $out = R2MO_Rewriter::swap_base($html, 'https://site.com/wp-content/uploads', 'https://cdn.example.com');
    assert_eq(
        '<img src="https://cdn.example.com/2026/06/a.jpg" srcset="https://cdn.example.com/2026/06/a-300x200.jpg 300w">',
        $out
    );
});

test('rewriter: swap_base is a no-op when base or target is empty', function () {
    assert_eq('x', R2MO_Rewriter::swap_base('x', '', 'https://cdn'));
    assert_eq('x', R2MO_Rewriter::swap_base('x', 'https://site', ''));
});
