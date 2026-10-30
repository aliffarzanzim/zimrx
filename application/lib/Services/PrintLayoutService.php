<?php
declare(strict_types=1);

namespace ZimRx\Services;

use PDO;

// Resolves print layout configurations and paper dimensions for doctors and profiles.
final class PrintLayoutService
{
    public function __construct(private readonly PDO $pdo) {}

    // Fetch the selected practice profile's layout merged with system defaults
    public function resolveLayout(int $profileId): array
    {
        if (!function_exists('zimrx_print_load_options')) {
            require_once __DIR__ . '/../print_setup_lib.php';
        }

        $settings = zimrx_print_load_options($this->pdo, $profileId);
        $defaults  = zimrx_print_default_options();

        return array_merge($defaults, $settings);
    }

    // Effective page width and height in centimeters
    public function pageDimensions(int $profileId): array
    {
        $layout = $this->resolveLayout($profileId);
        return [
            'width'  => (float) ($layout['page_width']  ?? 21.0),
            'height' => (float) ($layout['page_height'] ?? 29.7),
        ];
    }
}

if (!class_exists('PrintLayoutService', false)) {
    class_alias(PrintLayoutService::class, 'PrintLayoutService');
}

