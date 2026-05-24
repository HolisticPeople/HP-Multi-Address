#!/usr/bin/env php
<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$checks = [
    'hp-multi-address.php' => [
        'Version:           2.0.2',
        'Requires PHP:      8.5',
        "define('HP_MA_VERSION', '2.0.2')",
    ],
    'includes/Admin/SettingsPage.php' => [
        "do_action('hp_zen_enqueue_admin_surface', 'hp-multi-address')",
        'hp-zen-admin-surface hp-zen-admin-surface--hp-multi-address',
    ],
];

foreach ($checks as $file => $needles) {
    $path = $root . '/' . $file;
    $contents = is_file($path) ? (string) file_get_contents($path) : '';
    foreach ($needles as $needle) {
        if (!str_contains($contents, $needle)) {
            fwrite(STDERR, "Missing {$needle} in {$file}\n");
            exit(1);
        }
    }
}

echo "HP-Multi-Address HP-Zen-It consumer audit passed.\n";
