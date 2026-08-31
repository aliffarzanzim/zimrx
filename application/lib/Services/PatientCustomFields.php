<?php
declare(strict_types=1);

namespace ZimRx\Services;

/**
 * Doctor-scoped patient custom-field definitions and value validation.
 */
final class PatientCustomFields
{
    public const MAX_FIELDS = 20;
    private const MAX_LABEL_LENGTH = 60;
    private const MAX_VALUE_LENGTH = 500;
    private const TYPES = ['text', 'number', 'date', 'select'];
    private const PLACEMENTS = ['main', 'more_info', 'hidden'];

    /**
     * @return list<array{key:string,label:string,type:string,placement:string,options:list<string>}>
     */
    public static function loadForDoctor(\PDO $pdo, int $doctorId): array
    {
        if ($doctorId <= 0) {
            return [];
        }

        $stmt = $pdo->prepare(
            "SELECT setting_value FROM zimrx_interface_settings
             WHERE doctor_id = :doctor_id
               AND setting_scope = 'particulars_custom_fields'
               AND setting_key = 'definitions'
             LIMIT 1"
        );
        $stmt->execute(['doctor_id' => $doctorId]);
        $raw = $stmt->fetchColumn();
        $decoded = is_string($raw) ? json_decode($raw, true) : [];

        return is_array($decoded) ? self::normalizeDefinitions($decoded) : [];
    }

    /**
     * @param mixed $definitions
     * @return list<array{key:string,label:string,type:string,placement:string,options:list<string>}>
     */
    public static function normalizeDefinitions(mixed $definitions): array
    {
        if (!is_array($definitions)) {
            throw new \InvalidArgumentException('Invalid custom field definitions.');
        }
        if (count($definitions) > self::MAX_FIELDS) {
            throw new \InvalidArgumentException('A maximum of ' . self::MAX_FIELDS . ' custom fields is allowed.');
        }

        $normalized = [];
        $keys = [];
        foreach ($definitions as $definition) {
            if (!is_array($definition)) {
                throw new \InvalidArgumentException('Invalid custom field definition.');
            }

            $label = self::normalizeLabel($definition['label'] ?? null);
            $key = self::normalizeKey($definition['key'] ?? null, $label, $keys);
            $type = strtolower(trim((string)($definition['type'] ?? 'text')));
            if (!in_array($type, self::TYPES, true)) {
                throw new \InvalidArgumentException('Invalid custom field type.');
            }

            $placement = trim((string)($definition['placement'] ?? 'more_info'));
            if (!in_array($placement, self::PLACEMENTS, true)) {
                throw new \InvalidArgumentException('Invalid custom field placement.');
            }

            $options = self::normalizeOptions($definition['options'] ?? [], $type);
            $keys[] = $key;
            $normalized[] = [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'placement' => $placement,
                'options' => $options,
            ];
        }

        return $normalized;
    }

    /**
     * Replaces untrusted custom values with the values allowed by active definitions.
     *
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public static function normalizeExtraValues(
        array $extra,
        array $definitions,
        array $existingExtra = [],
        int $doctorId = 0
    ): array
    {
        if ($doctorId <= 0) {
            throw new \InvalidArgumentException('A doctor scope is required for custom patient fields.');
        }

        $submitted = $extra['custom_fields'] ?? [];
        if (!is_array($submitted)) {
            throw new \InvalidArgumentException('Invalid custom patient field values.');
        }

        $storedByDoctor = is_array($existingExtra['custom_fields_by_doctor'] ?? null)
            ? $existingExtra['custom_fields_by_doctor']
            : [];
        $doctorKey = (string)$doctorId;
        $existingValues = $storedByDoctor[$doctorKey] ?? [];
        if (!is_array($existingValues)) {
            $existingValues = [];
        }
        $values = [];
        foreach ($existingValues as $key => $value) {
            if (is_string($key) && preg_match('/^custom_[a-z0-9_]{1,50}$/', $key)
                && (is_string($value) || is_int($value) || is_float($value))) {
                $values[$key] = (string)$value;
            }
        }
        foreach ($definitions as $definition) {
            $key = $definition['key'];
            if (($definition['placement'] ?? 'more_info') === 'hidden') {
                continue;
            }
            if (!array_key_exists($key, $submitted)) {
                continue;
            }
            $value = $submitted[$key];
            if (is_array($value) || is_object($value)) {
                throw new \InvalidArgumentException('Invalid custom patient field value.');
            }

            $value = trim((string)$value);
            if (mb_strlen($value, 'UTF-8') > self::MAX_VALUE_LENGTH) {
                throw new \InvalidArgumentException('Custom patient field values must be ' . self::MAX_VALUE_LENGTH . ' characters or fewer.');
            }
            if ($value !== '') {
                self::validateValue($value, $definition);
            }
            $values[$key] = $value;
        }

        $storedByDoctor[$doctorKey] = $values;
        $extra['custom_fields_by_doctor'] = $storedByDoctor;
        unset($extra['custom_fields'], $extra['unscoped_custom_fields']);
        return $extra;
    }

    /**
     * Projects only the active doctor's values for a patient response.
     *
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public static function projectForDoctor(array $extra, int $doctorId): array
    {
        $storedByDoctor = is_array($extra['custom_fields_by_doctor'] ?? null)
            ? $extra['custom_fields_by_doctor']
            : [];
        $values = $storedByDoctor[(string)$doctorId] ?? [];
        if (!is_array($values)) {
            $values = [];
        }
        unset($extra['custom_fields_by_doctor'], $extra['unscoped_custom_fields']);
        $extra['custom_fields'] = $values;
        return $extra;
    }

    private static function normalizeLabel(mixed $value): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Custom field label is required.');
        }
        $label = trim(preg_replace('/\s+/', ' ', $value) ?? '');
        if ($label === '' || mb_strlen($label, 'UTF-8') > self::MAX_LABEL_LENGTH) {
            throw new \InvalidArgumentException('Custom field labels must be 1 to ' . self::MAX_LABEL_LENGTH . ' characters.');
        }
        return $label;
    }

    /**
     * @param list<string> $existingKeys
     */
    private static function normalizeKey(mixed $value, string $label, array $existingKeys): string
    {
        $candidate = is_string($value) ? strtolower(trim($value)) : '';
        if ($candidate === '') {
            $candidate = strtolower((string)(preg_replace('/[^a-z0-9]+/i', '_', $label) ?? ''));
            $candidate = trim($candidate, '_');
            $candidate = substr($candidate !== '' ? $candidate : 'field', 0, 41);
            $candidate = 'custom_' . $candidate . '_' . bin2hex(random_bytes(4));
        }

        if (!preg_match('/^custom_[a-z0-9_]{1,50}$/', $candidate)) {
            throw new \InvalidArgumentException('Invalid custom field key.');
        }
        if (in_array($candidate, $existingKeys, true)) {
            throw new \InvalidArgumentException('Custom field keys must be unique.');
        }
        return $candidate;
    }

    /**
     * @return list<string>
     */
    private static function normalizeOptions(mixed $options, string $type): array
    {
        if ($type !== 'select') {
            return [];
        }
        if (!is_array($options) || count($options) < 2 || count($options) > 20) {
            throw new \InvalidArgumentException('Choice fields require 2 to 20 options.');
        }

        $normalized = [];
        foreach ($options as $option) {
            if (!is_string($option)) {
                throw new \InvalidArgumentException('Invalid custom field option.');
            }
            $option = trim(preg_replace('/\s+/', ' ', $option) ?? '');
            if ($option === '' || mb_strlen($option, 'UTF-8') > 60 || in_array($option, $normalized, true)) {
                throw new \InvalidArgumentException('Custom field options must be unique and 1 to 60 characters.');
            }
            $normalized[] = $option;
        }
        return $normalized;
    }

    /**
     * @param array{key:string,label:string,type:string,placement:string,options:list<string>} $definition
     */
    private static function validateValue(string $value, array $definition): void
    {
        if ($definition['type'] === 'number' && !preg_match('/^-?(?:\d+|\d*\.\d+)$/', $value)) {
            throw new \InvalidArgumentException('A numeric value is required for ' . $definition['label'] . '.');
        }
        if ($definition['type'] === 'date' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            throw new \InvalidArgumentException('A valid date is required for ' . $definition['label'] . '.');
        }
        if ($definition['type'] === 'date') {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            $errors = \DateTimeImmutable::getLastErrors();
            if (!$date || $date->format('Y-m-d') !== $value
                || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
                throw new \InvalidArgumentException('A valid date is required for ' . $definition['label'] . '.');
            }
        }
        if ($definition['type'] === 'select' && !in_array($value, $definition['options'], true)) {
            throw new \InvalidArgumentException('Invalid selection for ' . $definition['label'] . '.');
        }
    }
}
