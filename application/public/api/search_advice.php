<?php
// Search prescription advice templates by query string, category group, or individual entry.
declare(strict_types=1);

define('ZIMRX_DB_LIGHTWEIGHT', true);
require_once dirname(__DIR__) . '/init.php';
require_once ZIMRX_BASE_DIR . '/lib/rx_template_lib.php';
require_login();

header('Content-Type: application/json');

try {
    $q = trim((string)($_GET['q'] ?? ''));
    $category = trim((string)($_GET['category'] ?? ''));
    $mode = trim((string)($_GET['mode'] ?? 'advice'));

    $doctorId = rx_active_doctor_id();
    $preferredLang = function_exists('zimrx_clinical_language')
        ? zimrx_clinical_language(null, $doctorId, $_GET['lang'] ?? null)
        : (rx_clean($_GET['lang'] ?? '') ?: 'en');
    $settings = rx_phrase_settings('advice', $doctorId);
    $allRows = rx_phrase_rows('advice', $doctorId, false); // false means exclude hidden!

    if ($mode === 'category') {
        $normQ = mb_strtolower($q, 'UTF-8');
        $groups = [];
        foreach ($allRows as $row) {
            $catEn = trim((string)($row['category_en'] ?? ''));
            $catBn = trim((string)($row['category_bn'] ?? ''));
            $catKey = ($preferredLang === 'en') ? ($catEn ?: $catBn ?: 'Uncategorized') : ($catBn ?: $catEn ?: 'Uncategorized');
            if ($catKey === '') {
                $catKey = 'Uncategorized';
            }
            
            if (!isset($groups[$catKey])) {
                $groups[$catKey] = [
                    'category' => $catKey,
                    'category_en' => $catEn,
                    'category_bn' => $catBn,
                    'total' => 0,
                    'is_pinned' => 0,
                    'sort_order' => (int)($row['sort_order'] ?? 0),
                    'max_usage' => 0,
                    'advices' => []
                ];
            }
            
            $groups[$catKey]['total']++;
            $groups[$catKey]['advices'][] = $row;
            if ((int)($row['is_pinned'] ?? 0) === 1) {
                $groups[$catKey]['is_pinned'] = 1;
            }
            if ((int)($row['sort_order'] ?? 0) < $groups[$catKey]['sort_order']) {
                $groups[$catKey]['sort_order'] = (int)$row['sort_order'];
            }
            if ((int)($row['usage_count'] ?? 0) > $groups[$catKey]['max_usage']) {
                $groups[$catKey]['max_usage'] = (int)$row['usage_count'];
            }
        }

        $filteredGroups = [];
        foreach ($groups as $catKey => $group) {
            if ($q === '') {
                $filteredGroups[] = $group;
                continue;
            }
            
            $matches = false;
            foreach (['category_en', 'category_bn', 'category_search_alias'] as $field) {
                $firstRow = $group['advices'][0];
                if (isset($firstRow[$field]) && $firstRow[$field] !== '') {
                    $val = mb_strtolower($firstRow[$field], 'UTF-8');
                    if (str_contains($val, $normQ)) {
                        $matches = true;
                        break;
                    }
                }
            }
            
            if (!$matches) {
                foreach ($group['advices'] as $row) {
                    foreach (['value_bn', 'value_en'] as $field) {
                        if (isset($row[$field]) && $row[$field] !== '') {
                            $val = mb_strtolower($row[$field], 'UTF-8');
                            if (str_contains($val, $normQ)) {
                                $matches = true;
                                break 2;
                            }
                        }
                    }
                }
            }
            
            if ($matches) {
                $filteredGroups[] = $group;
            }
        }

        usort($filteredGroups, static function ($a, $b) use ($settings) {
            if ((int)$a['is_pinned'] !== (int)$b['is_pinned']) {
                return (int)$b['is_pinned'] <=> (int)$a['is_pinned'];
            }
            
            if (($settings['show_mode'] ?? 'serial') === 'usage') {
                if ($b['max_usage'] !== $a['max_usage']) {
                    return $b['max_usage'] <=> $a['max_usage'];
                }
            }
            
            if ($a['sort_order'] !== $b['sort_order']) {
                return $a['sort_order'] <=> $b['sort_order'];
            }
            
            return strcasecmp($a['category'], $b['category']);
        });

        $filteredGroups = array_slice($filteredGroups, 0, 20);
        $result = [];
        foreach ($filteredGroups as $group) {
            $result[] = [
                'category' => $group['category'],
                'total' => $group['total'],
                'is_pinned' => $group['is_pinned']
            ];
        }
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Individual advice search mode
    $normQ = mb_strtolower($q, 'UTF-8');
    $category = trim($category);
    
    $results = [];
    $idx = 0;
    foreach ($allRows as $row) {
        if ($category !== '' && trim($row['category_en'] ?? '') !== $category && trim($row['category_bn'] ?? '') !== $category) {
            continue;
        }
        
        if ($q !== '') {
            $matches = false;
            foreach (['value_bn', 'value_en', 'category_en', 'category_bn', 'category_search_alias'] as $field) {
                if (isset($row[$field]) && $row[$field] !== '') {
                    $val = mb_strtolower($row[$field], 'UTF-8');
                    if (str_contains($val, $normQ)) {
                        $matches = true;
                        break;
                    }
                }
            }
            if (!$matches) {
                continue;
            }
        }
        
        $valBn = (string)($row['value_bn'] ?? '');
        $valEn = (string)($row['value_en'] ?? '');
        $catEn = (string)($row['category_en'] ?? '');
        $catBn = (string)($row['category_bn'] ?? '');
        $adviceDisplay = ($preferredLang === 'en') ? ($valEn !== '' ? $valEn : $valBn) : ($valBn !== '' ? $valBn : $valEn);
        $categoryDisplay = ($preferredLang === 'en') ? ($catEn !== '' ? $catEn : $catBn) : ($catBn !== '' ? $catBn : $catEn);

        $results[] = [
            'id' => $row['id'],
            'category' => $categoryDisplay,
            'category_en' => $catEn,
            'category_bn' => $catBn,
            'advice' => $adviceDisplay,
            'advice_bn' => $valBn,
            'advice_en' => $valEn,
            'is_pinned' => $row['is_pinned'],
            'original_index' => $idx++
        ];
    }
    
    if ($q !== '') {
        usort($results, static function ($a, $b) use ($normQ) {
            if ((int)$a['is_pinned'] !== (int)$b['is_pinned']) {
                return (int)$b['is_pinned'] <=> (int)$a['is_pinned'];
            }
            $aStarts = (mb_stripos($a['advice'], $normQ) === 0 || mb_stripos($a['advice_en'], $normQ) === 0);
            $bStarts = (mb_stripos($b['advice'], $normQ) === 0 || mb_stripos($b['advice_en'], $normQ) === 0);
            if ($aStarts !== $bStarts) {
                return $aStarts ? -1 : 1;
            }
            return $a['original_index'] <=> $b['original_index'];
        });
    }

    $results = array_slice($results, 0, 40);
    echo json_encode($results, JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('[ZimRx] search_advice error: ' . $e->getMessage());
    echo json_encode([]);
}
