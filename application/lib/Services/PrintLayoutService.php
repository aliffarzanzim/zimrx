<?php
declare(strict_types=1);

// Resolves print layout configurations and paper dimensions for doctors and profiles.
final class PrintLayoutService
{
    public function __construct(private readonly \PDO $pdo) {}

    // Fetch doctor's active layout settings merged with system defaults
    public function resolveLayout(int $doctorId, int $profileId = 0): array
    {
        if (!function_exists('zimrx_print_load_settings')) {
            require_once __DIR__ . '/../print_setup_lib.php';
        }

        $settings = zimrx_print_load_settings($this->pdo, $doctorId, $profileId);
        $defaults  = zimrx_print_default_options();

        return array_merge($defaults, $settings);
    }

    // Effective page width and height in centimeters
    public function pageDimensions(int $doctorId, int $profileId = 0): array
    {
        $layout = $this->resolveLayout($doctorId, $profileId);
        return [
            'width'  => (float) ($layout['page_width']  ?? 21.0),
            'height' => (float) ($layout['page_height'] ?? 29.7),
        ];
    }
}
