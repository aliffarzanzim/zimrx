<?php
declare(strict_types=1);

// Generalizes regional naming constraints: expands _bn doctor profile columns to _native, adds universal license_no, and localizes advice categories.
class Migration015GeneralizeDoctorLocalizationSchema {

    public function up(PDO $pdo): void {

        // 1. Generalize doctor profile columns from language-specific (_bn) to universal (_native)
        if (DbSchema::tableExists($pdo, 'zimrx_doctors') && !DbSchema::isView($pdo, 'zimrx_doctors')) {
            $doctorCols = [
                'full_name_native'      => 'TEXT',
                'designation_native'    => 'TEXT',
                'institute_native'      => 'TEXT',
                'qualifications_native' => 'TEXT',
                'specialty_native'      => 'TEXT',
                'license_no_en'         => 'TEXT',
                'license_no_native'     => 'TEXT',
                'native_lang'           => "TEXT NOT NULL DEFAULT 'bn'",
                'regulatory_body'       => 'TEXT',
            ];

            foreach ($doctorCols as $col => $def) {
                if (!DbSchema::columnExists($pdo, 'zimrx_doctors', $col)) {
                    $pdo->exec("ALTER TABLE zimrx_doctors ADD COLUMN {$col} {$def}");
                }
            }

            // Backfill generalized columns from legacy columns if available
            $backfills = [
                'full_name_native'      => 'full_name_bn',
                'designation_native'    => 'designation_bn',
                'institute_native'      => 'institute_bn',
                'qualifications_native' => 'qualifications_bn',
                'specialty_native'      => 'specialty_bn',
                'license_no_native'     => 'bmdc_no_bn',
                'license_no_en'         => 'bmdc_no_en',
            ];

            foreach ($backfills as $target => $source) {
                if (DbSchema::columnExists($pdo, 'zimrx_doctors', $source)) {
                    $pdo->exec(
                        "UPDATE zimrx_doctors 
                         SET {$target} = {$source} 
                         WHERE ({$target} IS NULL OR trim({$target}) = '') 
                           AND {$source} IS NOT NULL 
                           AND trim({$source}) != ''"
                    );
                }
            }
        }

        // 2. Add universal license_no to prescription header settings
        if (DbSchema::tableExists($pdo, 'zimrx_prescription_header_settings') && !DbSchema::isView($pdo, 'zimrx_prescription_header_settings')) {
            if (!DbSchema::columnExists($pdo, 'zimrx_prescription_header_settings', 'license_no')) {
                $pdo->exec("ALTER TABLE zimrx_prescription_header_settings ADD COLUMN license_no TEXT");
            }
            if (DbSchema::columnExists($pdo, 'zimrx_prescription_header_settings', 'bmdc_no')) {
                $pdo->exec(
                    "UPDATE zimrx_prescription_header_settings 
                     SET license_no = bmdc_no 
                     WHERE (license_no IS NULL OR trim(license_no) = '') 
                       AND bmdc_no IS NOT NULL 
                       AND trim(bmdc_no) != ''"
                );
            }
        }

        // 3. Add category_native to user advices for multi-language catalog support
        if (DbSchema::tableExists($pdo, 'zimrx_user_advices') && !DbSchema::isView($pdo, 'zimrx_user_advices')) {
            if (!DbSchema::columnExists($pdo, 'zimrx_user_advices', 'category_native')) {
                $pdo->exec("ALTER TABLE zimrx_user_advices ADD COLUMN category_native TEXT");
            }
            if (DbSchema::columnExists($pdo, 'zimrx_user_advices', 'category_bn')) {
                $pdo->exec(
                    "UPDATE zimrx_user_advices 
                     SET category_native = category_bn 
                     WHERE (category_native IS NULL OR trim(category_native) = '') 
                       AND category_bn IS NOT NULL 
                       AND trim(category_bn) != ''"
                );
            }
        }
    }
}
