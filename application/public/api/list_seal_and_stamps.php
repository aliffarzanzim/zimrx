<?php
declare(strict_types=1);

// Seal and stamps gallery API: lists uploaded signatures and categorized system stamp presets.

require_once dirname(__DIR__) . '/init.php';
require_login();

header('Content-Type: application/json');

$query   = strtolower(trim((string)($_GET['q'] ?? '')));
$cat     = strtolower(trim((string)($_GET['cat'] ?? '')));

$result  = [];
$categories = [];

// User-uploaded seal and stamps (newest first)
$uploadDir = ZIMRX_UPLOADS_DIR . '/seal-and-stamps';
$uploadUrl = '/uploads/seal-and-stamps';

if (is_dir($uploadDir)) {
    $files = glob($uploadDir . '/*.{svg,png,jpg,jpeg,webp}', GLOB_BRACE) ?: [];
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

// Built-in general preset stamps
$baseDir = __DIR__ . '/../assets/images/seal_and_stamps';
$baseUrl = 'assets/images/seal_and_stamps';

if (!is_dir($baseDir)) {
    mkdir($baseDir, 0750, true);
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

// Categorized preset stamps in subdirectories
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
