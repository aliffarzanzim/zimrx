<?php
declare(strict_types=1);

// Background images gallery API: lists user uploads and categorized system watermark presets.

require_once dirname(__DIR__) . '/init.php';
require_once dirname(__DIR__) . '/init.php';
require_login();

header('Content-Type: application/json');

$query   = strtolower(trim((string)($_GET['q'] ?? '')));
$cat     = strtolower(trim((string)($_GET['cat'] ?? '')));

$result  = [];
$categories = [];

// User-uploaded watermarks (newest first)
$uploadDir = ZIMRX_UPLOADS_DIR . '/background-images';
$uploadUrl = '/uploads/background-images';

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

// System preset watermarks by category
$baseDir = __DIR__ . '/../assets/images/background-images';
$baseUrl = 'assets/images/background-images';

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

// Unique category list for filter dropdown
$categories = array_values(array_unique($categories));

echo json_encode(['ok' => true, 'images' => $result, 'categories' => $categories]);
