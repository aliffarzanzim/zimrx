<?php
declare(strict_types=1);

namespace ZimRx\Services;

final class VisitFormModules
{
    public const MAX_MODULES = 8;
    public const MAX_FIELDS_PER_MODULE = 20;
    private const TYPES = ['text', 'textarea', 'number', 'date', 'select'];

    /** @return list<array{key:string,title:string,column:string,fields:list<array{key:string,label:string,type:string,required:bool,print:bool,options:list<string>}>>} */
    public static function loadForDoctor(\PDO $pdo, int $doctorId): array
    {
        if ($doctorId <= 0) {
            return [];
        }
        $stmt = $pdo->prepare(
            "SELECT setting_value FROM zimrx_interface_settings
             WHERE doctor_id = :doctor_id AND setting_scope = 'visit_form_modules'
               AND setting_key = 'definitions' LIMIT 1"
        );
        $stmt->execute(['doctor_id' => $doctorId]);
        $raw = $stmt->fetchColumn();
        $decoded = is_string($raw) ? json_decode($raw, true) : [];
        return is_array($decoded) ? self::normalizeDefinitions($decoded) : [];
    }

    /** @return list<array{key:string,title:string,column:string,fields:list<array{key:string,label:string,type:string,required:bool,print:bool,options:list<string>}>>} */
    public static function normalizeDefinitions(mixed $definitions): array
    {
        if (!is_array($definitions) || count($definitions) > self::MAX_MODULES) {
            throw new \InvalidArgumentException('A maximum of ' . self::MAX_MODULES . ' form modules is allowed.');
        }
        $result = [];
        $moduleKeys = [];
        foreach ($definitions as $module) {
            if (!is_array($module)) {
                throw new \InvalidArgumentException('Invalid form module.');
            }
            $title = self::label($module['title'] ?? null, 60, 'Module title');
            $key = self::key($module['key'] ?? null, 'form_', $title, $moduleKeys);
            $column = $module['column'] ?? 'left';
            if (!is_string($column) || !in_array($column, ['left', 'right'], true)) {
                throw new \InvalidArgumentException('Invalid form module column.');
            }
            $fields = $module['fields'] ?? [];
            if (!is_array($fields) || count($fields) > self::MAX_FIELDS_PER_MODULE) {
                throw new \InvalidArgumentException('Each form module can have up to ' . self::MAX_FIELDS_PER_MODULE . ' fields.');
            }
            $normalizedFields = [];
            $fieldKeys = [];
            foreach ($fields as $field) {
                if (!is_array($field)) {
                    throw new \InvalidArgumentException('Invalid form field.');
                }
                $fieldLabel = self::label($field['label'] ?? null, 60, 'Field label');
                $fieldKey = self::key($field['key'] ?? null, 'field_', $fieldLabel, $fieldKeys);
                $type = $field['type'] ?? 'text';
                if (!is_string($type) || !in_array($type, self::TYPES, true)) {
                    throw new \InvalidArgumentException('Invalid form field type.');
                }
                $options = self::normalizeOptions($field['options'] ?? [], $type);
                $normalizedFields[] = [
                    'key' => $fieldKey,
                    'label' => $fieldLabel,
                    'type' => $type,
                    'required' => ($field['required'] ?? false) === true,
                    'print' => ($field['print'] ?? false) === true,
                    'options' => $options,
                ];
                $fieldKeys[] = $fieldKey;
            }
            if ($normalizedFields === []) {
                throw new \InvalidArgumentException('Each form module must contain at least one field.');
            }
            $result[] = ['key' => $key, 'title' => $title, 'column' => $column, 'fields' => $normalizedFields];
            $moduleKeys[] = $key;
        }
        return $result;
    }

    /** @param array<string,mixed> $submitted @param list<array<string,mixed>> $definitions
     *  @return list<array{key:string,title:string,fields:list<array{key:string,label:string,value:string,print:bool}>}>
     */
    public static function normalizeResponses(array $submitted, array $definitions): array
    {
        $responses = [];
        foreach ($definitions as $module) {
            $moduleValues = $submitted[$module['key']] ?? [];
            if (!is_array($moduleValues)) {
                throw new \InvalidArgumentException('Invalid values for ' . $module['title'] . '.');
            }
            $fields = [];
            foreach ($module['fields'] as $field) {
                $value = $moduleValues[$field['key']] ?? '';
                if (!is_scalar($value)) {
                    throw new \InvalidArgumentException('Invalid value for ' . $field['label'] . '.');
                }
                $value = trim((string)$value);
                if (mb_strlen($value, 'UTF-8') > 2000) {
                    throw new \InvalidArgumentException('Values must be 2000 characters or fewer.');
                }
                if ($field['required'] && $value === '') {
                    throw new \InvalidArgumentException($field['label'] . ' is required.');
                }
                self::validateValue($value, $field);
                $fields[] = ['key' => $field['key'], 'label' => $field['label'], 'value' => $value, 'print' => $field['print']];
            }
            $responses[] = ['key' => $module['key'], 'title' => $module['title'], 'fields' => $fields];
        }
        return $responses;
    }

    private static function label(mixed $value, int $maxLength, string $name): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException($name . ' is required.');
        }
        $label = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($label === '' || mb_strlen($label, 'UTF-8') > $maxLength) {
            throw new \InvalidArgumentException($name . ' must be 1 to ' . $maxLength . ' characters.');
        }
        return $label;
    }

    private static function key(mixed $value, string $prefix, string $label, array $existing): string
    {
        $key = is_string($value) ? strtolower(trim($value)) : '';
        if ($key === '') {
            $slug = strtolower(trim(substr((string)(preg_replace('/[^a-z0-9]+/i', '_', $label) ?? ''), 0, 36), '_'));
            $key = $prefix . ($slug ?: 'item') . '_' . bin2hex(random_bytes(4));
        }
        if (!preg_match('/^' . preg_quote($prefix, '/') . '[a-z0-9_]{1,50}$/', $key) || in_array($key, $existing, true)) {
            throw new \InvalidArgumentException('Invalid or duplicate form key.');
        }
        return $key;
    }

    /** @return list<string> */
    private static function normalizeOptions(mixed $options, string $type): array
    {
        if ($type !== 'select') {
            return [];
        }
        if (!is_array($options) || count($options) < 2 || count($options) > 20) {
            throw new \InvalidArgumentException('Choice fields require 2 to 20 options.');
        }
        $result = [];
        foreach ($options as $option) {
            if (!is_string($option)) {
                throw new \InvalidArgumentException('Invalid choice option.');
            }
            $option = trim(preg_replace('/\s+/u', ' ', $option) ?? '');
            if ($option === '' || mb_strlen($option, 'UTF-8') > 60 || in_array($option, $result, true)) {
                throw new \InvalidArgumentException('Choice options must be unique and 1 to 60 characters.');
            }
            $result[] = $option;
        }
        return $result;
    }

    private static function validateValue(string $value, array $field): void
    {
        if ($value === '') {
            return;
        }
        if ($field['type'] === 'number' && !preg_match('/^-?(?:\d+|\d*\.\d+)$/', $value)) {
            throw new \InvalidArgumentException('A numeric value is required for ' . $field['label'] . '.');
        }
        if ($field['type'] === 'date') {
            $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
            $errors = \DateTimeImmutable::getLastErrors();
            if (!$date || $date->format('Y-m-d') !== $value || ($errors !== false && ($errors['warning_count'] || $errors['error_count']))) {
                throw new \InvalidArgumentException('A valid date is required for ' . $field['label'] . '.');
            }
        }
        if ($field['type'] === 'select' && !in_array($value, $field['options'], true)) {
            throw new \InvalidArgumentException('Invalid choice for ' . $field['label'] . '.');
        }
    }
}
