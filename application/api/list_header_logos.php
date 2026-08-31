<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_login();

header('Content-Type: application/json');

$result = [];

// 1. User-uploaded logos (newest first)
$uploadDir = ZIMRX_UPLOADS_DIR . '/header-logos';
$uploadUrl = 'userdata/uploads/header-logos';

if (is_dir($uploadDir)) {
    $files = glob($uploadDir . '/*.{jpg,jpeg,png,gif,webp}', GLOB_BRACE) ?: [];
    // Sort newest first by mtime
    usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));
    foreach ($files as $file) {
        $name = pathinfo($file, PATHINFO_FILENAME);
        $result[] = [
            'name'     => $name,
            'category' => 'Uploaded',
            'url'      => '/uploads/header-logos/' . basename($file),
            'ext'      => strtolower(pathinfo($file, PATHINFO_EXTENSION)),
        ];
    }
}

// 2. Asset logos
$assetDir = __DIR__ . '/../assets/images/logos';
$assetUrl = 'assets/images/logos';

if (is_dir($assetDir)) {
    $files = glob($assetDir . '/*.{jpg,jpeg,png,gif,webp,svg}', GLOB_BRACE) ?: [];
    sort($files);
    foreach ($files as $file) {
        $name = pathinfo($file, PATHINFO_FILENAME);
        $result[] = [
            'name'     => $name,
            'category' => 'Preset',
            'url'      => $assetUrl . '/' . basename($file),
            'ext'      => strtolower(pathinfo($file, PATHINFO_EXTENSION)),
        ];
    }
}

$categories = array_values(array_unique(array_column($result, 'category')));

echo json_encode(['ok' => true, 'images' => $result, 'categories' => $categories]);
