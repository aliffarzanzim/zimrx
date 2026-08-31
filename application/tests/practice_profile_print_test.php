<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../lib/auth.php';
require_once __DIR__ . '/../lib/db/db_connections.php';
require_once __DIR__ . '/../lib/db/db_schema.php';
require_once __DIR__ . '/../lib/db/db_sql.php';
require_once __DIR__ . '/../lib/print_setup_lib.php';
require_once __DIR__ . '/../migrations/020_practice_profile_lifecycle_and_print_settings.php';

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE zimrx_doctor_profiles (
    id INTEGER PRIMARY KEY, doctor_id INTEGER, profile_name TEXT, address TEXT, phone TEXT,
    settings_json TEXT NOT NULL DEFAULT '{}', is_active INTEGER NOT NULL DEFAULT 1,
    is_deleted INTEGER NOT NULL DEFAULT 0, updated_at TEXT
)");
$pdo->exec("INSERT INTO zimrx_doctor_profiles (id, doctor_id, profile_name, settings_json)
    VALUES (1, 1, 'Practice One', '{}'), (2, 1, 'Practice Two', '{}')");
$pdo->exec('CREATE TABLE zimrx_interface_settings (doctor_id INTEGER, setting_scope TEXT, setting_key TEXT, setting_value TEXT)');

zimrx_print_save_setup($pdo, 1, [
    'page_width_cm' => '20',
    'logo_path' => '/uploads/header-logos/profile-1-logo.png',
]);
zimrx_print_save_setup($pdo, 2, [
    'page_width_cm' => '22',
    'logo_path' => '/uploads/header-logos/profile-2-logo.png',
]);

$firstOptions = zimrx_print_load_options($pdo, 1);
$secondOptions = zimrx_print_load_options($pdo, 2);
$firstHeader = zimrx_print_load_header_settings($pdo, 1);
$secondHeader = zimrx_print_load_header_settings($pdo, 2);
if (($firstOptions['page_width'] ?? null) !== '20' || ($secondOptions['page_width'] ?? null) !== '22') {
    throw new RuntimeException('Print layout settings were not isolated per profile.');
}
if (($firstHeader['logo_path'] ?? null) !== '/uploads/header-logos/profile-1-logo.png' ||
    ($secondHeader['logo_path'] ?? null) !== '/uploads/header-logos/profile-2-logo.png') {
    throw new RuntimeException('Prescription header branding was not isolated per profile.');
}

$savedVisitSnapshot = [
    '_zimrx_print_snapshot' => [
        'profile_id' => 1,
        'header' => ['chamber_name' => 'Practice One at time of visit', 'logo_path' => '/uploads/old-logo.png'],
        'options' => ['page_width' => '19'],
    ],
];
$currentHeader = array_merge($firstHeader, ['chamber_name' => 'Renamed Practice One', 'logo_path' => '/uploads/new-logo.png']);
$currentOptions = array_merge($firstOptions, ['page_width' => '21']);
[$snapshotHeader, $snapshotOptions] = zimrx_print_restore_saved_snapshot($savedVisitSnapshot, $currentHeader, $currentOptions);
if (($snapshotHeader['chamber_name'] ?? '') !== 'Practice One at time of visit' ||
    ($snapshotHeader['logo_path'] ?? '') !== '/uploads/old-logo.png' ||
    ($snapshotOptions['page_width'] ?? '') !== '19') {
    throw new RuntimeException('Historical prescription preview did not retain its saved profile branding and print dimensions.');
}

echo "Practice profile print isolation checks passed.\n";

$migrationDb = new PDO('sqlite::memory:');
$migrationDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$migrationDb->exec("CREATE TABLE zimrx_doctor_profiles (
    id INTEGER PRIMARY KEY, doctor_id INTEGER, profile_name TEXT, address TEXT, phone TEXT,
    settings_json TEXT NOT NULL DEFAULT '{}', is_active INTEGER NOT NULL DEFAULT 1
)");
$migrationDb->exec("INSERT INTO zimrx_doctor_profiles (id, doctor_id, profile_name, address, phone, settings_json)
    VALUES (1, 1, 'Practice One', '', '', '{}')");
(new Migration020PracticeProfileLifecycleAndPrintSettings())->up($migrationDb);
$migrated = json_decode((string)$migrationDb->query('SELECT settings_json FROM zimrx_doctor_profiles WHERE id = 1')->fetchColumn(), true);
if ($migrated !== [] || !DbSchema::columnExists($migrationDb, 'zimrx_doctor_profiles', 'is_deleted') ||
    !DbSchema::columnExists($migrationDb, 'zimrx_doctor_profiles', 'deleted_at')) {
    throw new RuntimeException('Practice profile lifecycle migration did not preserve current profile settings and add lifecycle fields.');
}
echo "Practice profile lifecycle migration check passed.\n";
