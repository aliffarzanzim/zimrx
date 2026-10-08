<?php
declare(strict_types=1);

namespace ZimRx\DrugStudio;

use InvalidArgumentException;

class DrugCatalogValidator
{
    /**
     * Validate an ISO 3166-1 alpha-2 country code.
     */
    public static function validateCountryCode(string $code): string
    {
        $clean = strtoupper(trim($code));
        if (!preg_match('/^[A-Z]{2}$/', $clean)) {
            throw new InvalidArgumentException("Country code must be exactly 2 letters (ISO 3166-1 alpha-2). Received: {$code}");
        }
        return $clean;
    }

    /**
     * Validate generic payload for addition or section update.
     *
     * @param array<string, mixed> $data
     * @return array<string, string> Field errors map
     */
    public static function validateGeneric(array $data, bool $isNew = false): array
    {
        $errors = [];

        if ($isNew || array_key_exists('generic_name', $data)) {
            $name = trim((string)($data['generic_name'] ?? ''));
            if ($name === '') {
                $errors['generic_name'] = 'Generic name is required.';
            } elseif (strlen($name) > 255) {
                $errors['generic_name'] = 'Generic name cannot exceed 255 characters.';
            }
        }

        $booleanFields = [
            'is_antibiotic',
            'is_high_alert_medicine',
            'is_safe_in_pregnancy',
            'is_safe_in_lactation',
            'require_renal_adjustments',
            'is_safe_in_hepatic_impairment',
            'is_safe_in_paediatric',
            'requires_tapering',
        ];

        foreach ($booleanFields as $field) {
            if (array_key_exists($field, $data)) {
                $val = $data[$field];
                if (!in_array($val, [0, 1, '0', '1', true, false], true)) {
                    $errors[$field] = "Field {$field} must be a boolean flag.";
                }
            }
        }

        return $errors;
    }

    /**
     * Validate therapeutic classification payload.
     *
     * @param array<string, mixed> $data
     * @return array<string, string> Field errors map
     */
    public static function validateClassification(array $data, bool $isNew = false): array
    {
        $errors = [];

        if ($isNew || array_key_exists('class_name', $data)) {
            $name = trim((string)($data['class_name'] ?? ''));
            if ($name === '') {
                $errors['class_name'] = 'Class name is required.';
            }
        }

        return $errors;
    }

    /**
     * Validate indication payload.
     *
     * @param array<string, mixed> $data
     * @return array<string, string> Field errors map
     */
    public static function validateIndication(array $data, bool $isNew = false): array
    {
        $errors = [];

        if ($isNew || array_key_exists('indication_name', $data)) {
            $name = trim((string)($data['indication_name'] ?? ''));
            if ($name === '') {
                $errors['indication_name'] = 'Indication name is required.';
            }
        }

        return $errors;
    }

    /**
     * Validate trade name (commercial brand) payload.
     *
     * @param array<string, mixed> $data
     * @return array<string, string> Field errors map
     */
    public static function validateBrand(array $data, bool $isNew = false): array
    {
        $errors = [];

        if ($isNew || array_key_exists('brand_name', $data)) {
            $name = trim((string)($data['brand_name'] ?? ''));
            if ($name === '') {
                $errors['brand_name'] = 'Trade brand name is required.';
            }
        }

        if ($isNew || array_key_exists('generic_id', $data)) {
            $gid = trim((string)($data['generic_id'] ?? ''));
            if ($gid === '' || !ctype_digit($gid)) {
                $errors['generic_id'] = 'Valid linked generic is required.';
            }
        }

        if ($isNew || array_key_exists('manufacturer_id', $data)) {
            $mid = trim((string)($data['manufacturer_id'] ?? ''));
            if ($mid === '') {
                $errors['manufacturer_id'] = 'Manufacturer selection is required.';
            }
        }

        return $errors;
    }

    /**
     * Validate manufacturer payload.
     *
     * @param array<string, mixed> $data
     * @return array<string, string> Field errors map
     */
    public static function validateManufacturer(array $data, bool $isNew = false): array
    {
        $errors = [];

        if ($isNew || array_key_exists('manufacturer_name', $data)) {
            $name = trim((string)($data['manufacturer_name'] ?? ''));
            if ($name === '') {
                $errors['manufacturer_name'] = 'Manufacturer name is required.';
            }
        }

        return $errors;
    }

    /**
     * Validate dosage form payload.
     *
     * @param array<string, mixed> $data
     * @return array<string, string> Field errors map
     */
    public static function validateDosageForm(array $data, bool $isNew = false): array
    {
        $errors = [];

        if ($isNew || array_key_exists('form', $data) || array_key_exists('form_name', $data)) {
            $form = trim((string)($data['form'] ?? $data['form_name'] ?? ''));
            if ($form === '') {
                $errors['form'] = 'Dosage form name is required.';
            }
        }

        return $errors;
    }
}
