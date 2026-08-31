<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_login();

header('Content-Type: application/json');

$query   = strtolower(trim((string)($_GET['q'] ?? '')));
$cat     = strtolower(trim((string)($_GET['cat'] ?? '')));

$result  = [];
$categories = [];

// 1. User Uploaded seal & stamps (newest first)
$uploadDir = ZIMRX_UPLOADS_DIR . '/seal-and-stamps';
$uploadUrl = '/uploads/seal-and-stamps';

if (is_dir($uploadDir)) {
    $files = glob($uploadDir . '/*.{svg,png,jpg,jpeg,webp}', GLOB_BRACE) ?: [];
    // Sort newest first by mtime
    usort($files, fn($a, $b) => filemtime($b) <=> filemtime($a));

    if (count($files) > 0) {
        $categories[] = 'Uploaded';
    }

    foreach ($files as $file) {
        $name = pathinfo($file, PATHINFO_FILENAME);
        if ($cat !== '' && strtolower($cat) !== 'uploaded') {
            continue;
        }
        if ($query !== '' && stripos($name, $query) === false && stripos('uploaded', $query) === false) {
            continue;
        }
        $result[] = [
            'name'     => $name,
            'category' => 'Uploaded',
            'url'      => $uploadUrl . '/' . basename($file),
            'ext'      => strtolower(pathinfo($file, PATHINFO_EXTENSION)),
        ];
    }
}

// 2. Scan flat files from assets/presets
$baseDir = __DIR__ . '/../assets/images/seal_and_stamps';
$baseUrl = 'assets/images/seal_and_stamps';

if (!is_dir($baseDir)) {
    mkdir($baseDir, 0777, true);
}

$categories[] = 'General';

$flatFiles = glob($baseDir . '/*.{svg,png,jpg,jpeg,webp}', GLOB_BRACE) ?: [];
foreach ($flatFiles as $file) {
    $name = pathinfo($file, PATHINFO_FILENAME);
    if ($cat !== '' && $cat !== 'general') {
        continue;
    }
    if ($query !== '' && stripos($name, $query) === false) {
        continue;
    }
    $result[] = [
        'name'     => $name,
        'category' => 'General',
        'url'      => $baseUrl . '/' . basename($file),
        'ext'      => strtolower(pathinfo($file, PATHINFO_EXTENSION)),
    ];
}

// 3. Scan subdirectories
$dirs    = glob($baseDir . '/*', GLOB_ONLYDIR) ?: [];
foreach ($dirs as $dir) {
    $category = basename($dir);
    $categories[] = $category;
    if ($cat !== '' && strtolower($category) !== $cat) {
        continue;
    }

    $files = glob($dir . '/*.{svg,png,jpg,jpeg,webp}', GLOB_BRACE) ?: [];
    foreach ($files as $file) {
        $name = pathinfo($file, PATHINFO_FILENAME);
        if ($query !== '' && stripos($name, $query) === false && stripos($category, $query) === false) {
            
            continue;
        }
        $result[] = [
            'name'     => $name,
            'category' => $category,
            'url'      => $baseUrl . '/' . $category . '/' . basename($file),
            'ext'      => strtolower(pathinfo($file, PATHINFO_EXTENSION)),
        ];
    }
}

echo json_encode(['ok' => true, 'images' => $result, 'categories' => array_values(array_unique($categories))]);
