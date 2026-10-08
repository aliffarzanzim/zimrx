<?php
declare(strict_types=1);

namespace ZimRx\LocaleStudio;

use Throwable;

class LocaleStudioApi
{
    private LocaleCatalogManager $manager;

    public function __construct(?string $localesDir = null)
    {
        $this->manager = new LocaleCatalogManager($localesDir);
    }

    public function handle(string $action): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $response = match ($action) {
                'list_languages' => $this->listLanguages(),
                'get_catalogs' => $this->getCatalogs(),
                'load_catalog' => $this->loadCatalog(),
                'save_catalog' => $this->saveCatalog(),
                'update_status' => $this->updateStatus(),
                'load_manifest' => $this->loadManifest(),
                'save_manifest' => $this->saveManifest(),
                'create_language' => $this->createLanguage(),
                'create_catalog' => $this->createCatalog(),
                'validate_locales' => $this->validateLocales(),
                default => ['success' => false, 'error' => "Unknown action: {$action}"],
            };
        } catch (Throwable $e) {
            http_response_code(400);
            $response = [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }

        echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function listLanguages(): array
    {
        $languages = $this->manager->listLanguages();
        $allCatalogs = $this->manager->discoverAllCatalogFiles();

        return [
            'success' => true,
            'languages' => $languages,
            'all_catalogs' => $allCatalogs,
            'locales_dir' => $this->manager->getLocalesDir(),
        ];
    }

    private function getCatalogs(): array
    {
        $allCatalogs = $this->manager->discoverAllCatalogFiles();
        return [
            'success' => true,
            'catalogs' => $allCatalogs,
        ];
    }

    private function loadCatalog(): array
    {
        $targetCode = LocaleCatalogManager::sanitizeCode((string)($_GET['target'] ?? 'bn'));
        $refCode = LocaleCatalogManager::sanitizeCode((string)($_GET['ref'] ?? 'en'));
        $catalogFile = LocaleCatalogManager::sanitizeCatalogName((string)($_GET['catalog'] ?? 'instructions')) . '.json';

        $refData = $this->manager->loadCatalog($refCode, $catalogFile);
        $targetData = $this->manager->loadCatalog($targetCode, $catalogFile);

        $targetManifest = $this->manager->loadManifest($targetCode);
        $statusMap = $targetManifest['catalogs_status'] ?? [];
        $catalogStatus = $statusMap[$catalogFile] ?? [
            'complete' => false,
            'verified' => false,
            'updated_at' => null,
            'verified_by' => null,
        ];

        return [
            'success' => true,
            'catalog_file' => $catalogFile,
            'ref_code' => $refCode,
            'target_code' => $targetCode,
            'ref_data' => $refData,
            'target_data' => $targetData,
            'catalog_status' => $catalogStatus,
            'target_manifest' => $targetManifest,
        ];
    }

    private function saveCatalog(): array
    {
        $body = $this->getJsonBody();
        $targetCode = LocaleCatalogManager::sanitizeCode((string)($body['target'] ?? ''));
        $rawCatalog = trim((string)($body['catalog'] ?? ''));
        if ($targetCode === '' || $rawCatalog === '') {
            throw new \InvalidArgumentException("Target language and catalog filename are required.");
        }
        $catalogFile = LocaleCatalogManager::sanitizeCatalogName($rawCatalog) . '.json';
        $data = (array)($body['data'] ?? []);
        $isComplete = isset($body['complete']) ? (bool)$body['complete'] : null;
        $isVerified = isset($body['verified']) ? (bool)$body['verified'] : null;
        $contributor = isset($body['contributor']) ? trim((string)$body['contributor']) : null;

        return $this->manager->saveCatalog(
            $targetCode,
            $catalogFile,
            $data,
            $isComplete,
            $isVerified,
            $contributor
        );
    }

    private function updateStatus(): array
    {
        $body = $this->getJsonBody();
        $targetCode = LocaleCatalogManager::sanitizeCode((string)($body['target'] ?? ''));
        $rawCatalog = trim((string)($body['catalog'] ?? ''));
        if ($targetCode === '' || $rawCatalog === '') {
            throw new \InvalidArgumentException("Target language and catalog filename are required.");
        }
        $catalogFile = LocaleCatalogManager::sanitizeCatalogName($rawCatalog) . '.json';
        $isComplete = isset($body['complete']) ? (bool)$body['complete'] : null;
        $isVerified = isset($body['verified']) ? (bool)$body['verified'] : null;
        $contributor = isset($body['contributor']) ? trim((string)$body['contributor']) : null;

        return $this->manager->updateCatalogStatus(
            $targetCode,
            $catalogFile,
            $isComplete,
            $isVerified,
            $contributor
        );
    }

    private function loadManifest(): array
    {
        $code = trim((string)($_GET['code'] ?? 'en'));
        $manifest = $this->manager->loadManifest($code);

        return [
            'success' => true,
            'manifest' => $manifest,
        ];
    }

    private function saveManifest(): array
    {
        $body = $this->getJsonBody();
        $code = trim((string)($body['code'] ?? ''));
        $data = (array)($body['manifest'] ?? []);

        if ($code === '') {
            throw new \InvalidArgumentException("Language code is required.");
        }

        $saved = $this->manager->saveManifest($code, $data);

        return [
            'success' => $saved,
            'code' => $code,
            'message' => "Manifest for {$code} updated successfully.",
        ];
    }

    private function createLanguage(): array
    {
        $body = $this->getJsonBody();
        $code = trim((string)($body['code'] ?? ''));
        $meta = (array)($body['meta'] ?? []);
        $cloneFrom = trim((string)($body['clone_from'] ?? 'en'));

        if ($code === '') {
            throw new \InvalidArgumentException("Language code is required (e.g. 'es', 'fr', 'ar').");
        }

        return $this->manager->createLanguage($code, $meta, $cloneFrom);
    }

    private function createCatalog(): array
    {
        $body = $this->getJsonBody();
        $catalogName = trim((string)($body['name'] ?? ''));

        if ($catalogName === '') {
            throw new \InvalidArgumentException("Catalog name is required (e.g. 'symptoms' or 'investigations.json').");
        }

        return $this->manager->createCatalogFile($catalogName);
    }

    private function validateLocales(): array
    {
        $report = $this->manager->validateAllLocales();
        return [
            'success' => true,
            'report' => $report,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getJsonBody(): array
    {
        $raw = (string)file_get_contents('php://input');
        if (trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException("Invalid JSON payload.");
        }

        return $decoded;
    }
}
