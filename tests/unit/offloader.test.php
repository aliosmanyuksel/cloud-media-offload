<?php
test('offloader: map_files includes original, scaled, and sizes', function () {
    $map = R2MO_Offloader::map_files(
        '/var/www/uploads',
        '2026/06/sofa-scaled.jpg',
        [
            'original_image' => 'sofa.jpg',
            'sizes' => [
                'thumbnail' => ['file' => 'sofa-150x150.jpg'],
                'medium'    => ['file' => 'sofa-300x200.jpg'],
                'broken'    => ['file' => ''],
            ],
        ]
    );
    assert_eq('/var/www/uploads/2026/06/sofa-scaled.jpg', $map['2026/06/sofa-scaled.jpg']);
    assert_eq('/var/www/uploads/2026/06/sofa.jpg', $map['2026/06/sofa.jpg']);
    assert_eq('/var/www/uploads/2026/06/sofa-150x150.jpg', $map['2026/06/sofa-150x150.jpg']);
    assert_eq('/var/www/uploads/2026/06/sofa-300x200.jpg', $map['2026/06/sofa-300x200.jpg']);
    assert_true(!isset($map['2026/06/']), 'empty size file is skipped');
});

test('offloader: map_files handles root-level file (no subdir)', function () {
    $map = R2MO_Offloader::map_files('/up', 'logo.png', []);
    assert_eq('/up/logo.png', $map['logo.png']);
    assert_eq(1, count($map));
});

test('offloader: build_key applies prefix and trims slashes', function () {
    assert_eq('wp/2026/06/x.jpg', R2MO_Offloader::build_key('wp', '/2026/06/x.jpg'));
    assert_eq('2026/06/x.jpg', R2MO_Offloader::build_key('', '2026/06/x.jpg'));
    assert_eq('a/b/x.jpg', R2MO_Offloader::build_key('/a/b/', 'x.jpg'));
});
