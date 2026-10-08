<?php
declare(strict_types=1);

namespace ZimRx\DrugStudio;

use Throwable;
use InvalidArgumentException;

class DrugStudioApi
{
    private DrugCatalogRepository $repository;
    private DrugCatalogExporter $exporter;

    public function __construct(DrugCatalogRepository $repository, DrugCatalogExporter $exporter)
    {
        $this->repository = $repository;
        $this->exporter = $exporter;
    }

    /**
     * Dispatch API action and output JSON.
     */
    public function handle(string $action): void
    {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $input = $this->getRequestPayload();

            $result = match ($action) {
                'stats', 'get_stats' => $this->handleGetStats(),

                // Generics
                'search_generics', 'list_generics' => $this->handleSearchGenerics($input),
                'get_generic' => $this->handleGetGeneric((int)($input['id'] ?? $_GET['id'] ?? 0)),
                'save_generic_section', 'save_generic' => $this->handleSaveGenericSection($input),
                'add_generic', 'create_generic' => $this->handleAddGeneric($input),
                'delete_generic' => $this->handleDeleteGeneric($input),

                // Classes
                'search_classes', 'list_classes' => $this->handleSearchClasses($input),
                'get_class' => $this->handleGetClass((int)($input['id'] ?? $_GET['id'] ?? 0)),
                'save_class' => $this->handleSaveClass($input),
                'add_class', 'create_class' => $this->handleAddClass($input),
                'delete_class' => $this->handleDeleteClass($input),

                // Indications
                'search_indications', 'list_indications' => $this->handleSearchIndications($input),
                'get_indication' => $this->handleGetIndication((int)($input['id'] ?? $_GET['id'] ?? 0)),
                'save_indication' => $this->handleSaveIndication($input),
                'add_indication', 'create_indication' => $this->handleAddIndication($input),
                'delete_indication' => $this->handleDeleteIndication($input),
                'link_class_generic' => $this->handleLinkClassGeneric($input),
                'unlink_class_generic' => $this->handleUnlinkClassGeneric($input),
                'link_indication_generic' => $this->handleLinkIndicationGeneric($input),
                'unlink_indication_generic' => $this->handleUnlinkIndicationGeneric($input),

                // Country Packs & Formularies
                'list_countries' => $this->repository->listCountryPacks(),
                'add_country', 'create_country' => $this->handleAddCountry($input),
                'search_brands', 'list_brands' => $this->handleSearchBrands($input),
                'get_brand' => $this->handleGetBrand(
                    (string)($input['country'] ?? $_GET['country'] ?? 'BD'),
                    (string)($input['id'] ?? $_GET['id'] ?? '')
                ),
                'save_brand_section', 'save_brand' => $this->handleSaveBrandSection($input),
                'add_brand', 'create_brand' => $this->handleAddBrand($input),
                'delete_brand' => $this->handleDeleteBrand($input),
                'search_manufacturers', 'list_manufacturers' => $this->handleSearchManufacturers($input),
                'get_manufacturer' => $this->handleGetManufacturer(
                    (string)($input['country'] ?? $_GET['country'] ?? 'BD'),
                    (int)($input['id'] ?? $_GET['id'] ?? 0)
                ),
                'save_manufacturer' => $this->handleSaveManufacturer($input),
                'add_manufacturer', 'create_manufacturer' => $this->handleAddManufacturer($input),
                'delete_manufacturer' => $this->handleDeleteManufacturer($input),

                // Dosage Forms
                'search_dosage_forms', 'list_dosage_forms' => $this->handleSearchDosageForms($input),
                'get_dosage_form' => $this->handleGetDosageForm((int)($input['id'] ?? $_GET['id'] ?? 0)),
                'save_dosage_form_section', 'save_dosage_form' => $this->handleSaveDosageFormSection($input),
                'add_dosage_form', 'create_dosage_form' => $this->handleAddDosageForm($input),
                'delete_dosage_form' => $this->handleDeleteDosageForm($input),

                // Manifests & Seeds
                'get_manifest' => $this->exporter->loadManifest((string)($input['scope'] ?? $_GET['scope'] ?? 'core')),
                'save_manifest' => $this->handleSaveManifest($input),
                'export_seed' => $this->handleExportSeed($input),

                default => throw new InvalidArgumentException("Unknown studio API action: {$action}"),
            };

            echo json_encode(['success' => true, 'data' => $result], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
    }

    private function getRequestPayload(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw) {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                return $json;
            }
        }
        return $_POST;
    }

    private function handleGetGeneric(int $id): array
    {
        $row = $this->repository->getGeneric($id);
        if (!$row) {
            throw new InvalidArgumentException("Generic record not found for ID {$id}.");
        }
        return $row;
    }

    private function handleSaveGenericSection(array $input): array
    {
        $id = (int)($input['id'] ?? 0);
        $fields = (array)($input['fields'] ?? []);

        $errors = DrugCatalogValidator::validateGeneric($fields, false);
        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $this->repository->updateGenericSection($id, $fields);
        return $this->handleGetGeneric($id);
    }

    private function handleAddGeneric(array $input): array
    {
        $fields = (array)($input['fields'] ?? $input);
        $errors = DrugCatalogValidator::validateGeneric($fields, true);
        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $newId = $this->repository->insertGeneric($fields);
        return $this->handleGetGeneric($newId);
    }

    private function handleGetClass(int $id): array
    {
        $row = $this->repository->getClass($id);
        if (!$row) {
            throw new InvalidArgumentException("Classification not found for ID {$id}.");
        }
        return $row;
    }

    private function handleSaveClass(array $input): array
    {
        $id = (int)($input['id'] ?? 0);
        $fields = (array)($input['fields'] ?? []);

        $errors = DrugCatalogValidator::validateClassification($fields, false);
        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $this->repository->updateClass($id, $fields);
        return $this->handleGetClass($id);
    }

    private function handleAddClass(array $input): array
    {
        $fields = (array)($input['fields'] ?? $input);
        $errors = DrugCatalogValidator::validateClassification($fields, true);
        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $newId = $this->repository->insertClass($fields);
        return $this->handleGetClass($newId);
    }

    private function handleGetIndication(int $id): array
    {
        $row = $this->repository->getIndication($id);
        if (!$row) {
            throw new InvalidArgumentException("Indication not found for ID {$id}.");
        }
        return $row;
    }

    private function handleSaveIndication(array $input): array
    {
        $id = (int)($input['id'] ?? 0);
        $fields = (array)($input['fields'] ?? []);

        $errors = DrugCatalogValidator::validateIndication($fields, false);
        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $this->repository->updateIndication($id, $fields);
        return $this->handleGetIndication($id);
    }

    private function handleAddIndication(array $input): array
    {
        $fields = (array)($input['fields'] ?? $input);
        $errors = DrugCatalogValidator::validateIndication($fields, true);
        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $newId = $this->repository->insertIndication($fields);
        return $this->handleGetIndication($newId);
    }

    private function handleLinkClassGeneric(array $input): array
    {
        $classId = (int)($input['class_id'] ?? 0);
        $genericId = (int)($input['generic_id'] ?? 0);
        $this->repository->linkGenericToClass($classId, $genericId);
        return $this->handleGetGeneric($genericId);
    }

    private function handleUnlinkClassGeneric(array $input): array
    {
        $classId = (int)($input['class_id'] ?? 0);
        $genericId = (int)($input['generic_id'] ?? 0);
        $this->repository->unlinkGenericFromClass($classId, $genericId);
        return $this->handleGetGeneric($genericId);
    }

    private function handleLinkIndicationGeneric(array $input): array
    {
        $indicationId = (int)($input['indication_id'] ?? 0);
        $genericId = (int)($input['generic_id'] ?? 0);
        $this->repository->linkGenericToIndication($indicationId, $genericId);
        return $this->handleGetGeneric($genericId);
    }

    private function handleUnlinkIndicationGeneric(array $input): array
    {
        $indicationId = (int)($input['indication_id'] ?? 0);
        $genericId = (int)($input['generic_id'] ?? 0);
        $this->repository->unlinkGenericFromIndication($indicationId, $genericId);
        return $this->handleGetGeneric($genericId);
    }

    private function handleAddCountry(array $input): array
    {
        $code = (string)($input['country_code'] ?? '');
        $name = (string)($input['country_name'] ?? '');
        $this->repository->addCountryPack($code, $name, $input);
        return $this->repository->listCountryPacks();
    }

    private function handleGetBrand(string $country, string $brandId): array
    {
        $row = $this->repository->getBrand($country, $brandId);
        if (!$row) {
            throw new InvalidArgumentException("Trade brand not found for ID {$brandId} in {$country}.");
        }
        return $row;
    }

    private function handleSaveBrandSection(array $input): array
    {
        $country = (string)($input['country'] ?? 'BD');
        $id = (string)($input['id'] ?? '');
        $fields = (array)($input['fields'] ?? []);

        $errors = DrugCatalogValidator::validateBrand($fields, false);
        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $this->repository->updateBrandSection($country, $id, $fields);
        return $this->handleGetBrand($country, $id);
    }

    private function handleAddBrand(array $input): array
    {
        $country = (string)($input['country'] ?? 'BD');
        $fields = (array)($input['fields'] ?? $input);

        $errors = DrugCatalogValidator::validateBrand($fields, true);
        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $newId = $this->repository->insertBrand($country, $fields);
        return $this->handleGetBrand($country, $newId);
    }

    private function handleAddManufacturer(array $input): array
    {
        $country = (string)($input['country'] ?? 'BD');
        $fields = (array)($input['fields'] ?? $input);

        $errors = DrugCatalogValidator::validateManufacturer($fields, true);
        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $newId = $this->repository->insertManufacturer($country, $fields);
        return ['id' => $newId, 'manufacturer_name' => $fields['manufacturer_name']];
    }

    private function handleGetDosageForm(int $id): array
    {
        $row = $this->repository->getDosageForm($id);
        if (!$row) {
            throw new InvalidArgumentException("Dosage form not found for ID {$id}.");
        }
        return $row;
    }

    private function handleSaveDosageFormSection(array $input): array
    {
        $id = (int)($input['id'] ?? 0);
        $fields = (array)($input['fields'] ?? []);

        $errors = DrugCatalogValidator::validateDosageForm($fields, false);
        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $this->repository->updateDosageFormSection($id, $fields);
        return $this->handleGetDosageForm($id);
    }

    private function handleAddDosageForm(array $input): array
    {
        $fields = (array)($input['fields'] ?? $input);
        $errors = DrugCatalogValidator::validateDosageForm($fields, true);
        if (!empty($errors)) {
            throw new InvalidArgumentException(implode(' ', $errors));
        }

        $newId = $this->repository->insertDosageForm($fields);
        return $this->handleGetDosageForm($newId);
    }

    private function handleSaveManifest(array $input): array
    {
        $scope = (string)($input['scope'] ?? 'core');
        $manifest = (array)($input['manifest'] ?? []);
        $this->exporter->saveManifest($scope, $manifest);
        return $this->exporter->loadManifest($scope);
    }

    private function handleExportSeed(array $input): array
    {
        $scope = (string)($input['scope'] ?? 'core');
        $overrides = (array)($input['manifest'] ?? []);
        return $this->exporter->export($scope, $overrides);
    }

    private function handleGetStats(): array
    {
        $raw = $this->repository->getStudioStats();
        return array_merge($raw, [
            'generics' => $raw['total_generics'] ?? 0,
            'brands' => $raw['total_brands'] ?? 0,
            'dosage_forms' => $raw['total_dosage_forms'] ?? 0,
            'classes' => $raw['total_classes'] ?? 0,
            'indications' => $raw['total_indications'] ?? 0,
            'countries_count' => $raw['total_country_packs'] ?? 0,
        ]);
    }

    private function handleSearchGenerics(array $input): array
    {
        $q = (string)($input['q'] ?? $input['query'] ?? $_GET['q'] ?? $_GET['query'] ?? '');
        $limit = (int)($input['limit'] ?? $_GET['limit'] ?? 40);
        $items = $this->repository->searchGenerics($q, $limit);
        return ['items' => $items, 'total' => count($items)];
    }

    private function handleSearchClasses(array $input): array
    {
        $q = (string)($input['q'] ?? $input['query'] ?? $_GET['q'] ?? $_GET['query'] ?? '');
        $limit = (int)($input['limit'] ?? $_GET['limit'] ?? 40);
        $items = $this->repository->searchClasses($q, $limit);
        return ['items' => $items, 'total' => count($items)];
    }

    private function handleSearchIndications(array $input): array
    {
        $q = (string)($input['q'] ?? $input['query'] ?? $_GET['q'] ?? $_GET['query'] ?? '');
        $limit = (int)($input['limit'] ?? $_GET['limit'] ?? 40);
        $items = $this->repository->searchIndications($q, $limit);
        return ['items' => $items, 'total' => count($items)];
    }

    private function handleSearchBrands(array $input): array
    {
        $country = (string)($input['country'] ?? $_GET['country'] ?? 'BD');
        $q = (string)($input['q'] ?? $input['query'] ?? $_GET['q'] ?? $_GET['query'] ?? '');
        $limit = (int)($input['limit'] ?? $_GET['limit'] ?? 40);
        $items = $this->repository->searchBrands($country, $q, $limit);
        return ['items' => $items, 'total' => count($items)];
    }

    private function handleSearchManufacturers(array $input): array
    {
        $country = (string)($input['country'] ?? $_GET['country'] ?? 'BD');
        $q = (string)($input['q'] ?? $input['query'] ?? $_GET['q'] ?? $_GET['query'] ?? '');
        $limit = (int)($input['limit'] ?? $_GET['limit'] ?? 40);
        $items = $this->repository->searchManufacturers($country, $q, $limit);
        return ['items' => $items, 'total' => count($items)];
    }

    private function handleGetManufacturer(string $country, int $id): array
    {
        $mfg = $this->repository->getManufacturer($country, $id);
        if (!$mfg) {
            throw new InvalidArgumentException("Manufacturer not found for ID {$id}.");
        }
        return $mfg;
    }

    private function handleSaveManufacturer(array $input): array
    {
        $country = (string)($input['country'] ?? 'BD');
        $id = (int)($input['id'] ?? 0);
        $fields = (array)($input['fields'] ?? []);
        $this->repository->updateManufacturer($country, $id, $fields);
        return $this->handleGetManufacturer($country, $id);
    }

    private function handleSearchDosageForms(array $input): array
    {
        $q = (string)($input['q'] ?? $input['query'] ?? $_GET['q'] ?? $_GET['query'] ?? '');
        $limit = (int)($input['limit'] ?? $_GET['limit'] ?? 40);
        $items = $this->repository->searchDosageForms($q, $limit);
        return ['items' => $items, 'total' => count($items)];
    }

    private function handleDeleteGeneric(array $input): array
    {
        $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
        $this->repository->deleteGeneric($id);
        return ['deleted' => true, 'id' => $id];
    }

    private function handleDeleteClass(array $input): array
    {
        $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
        $this->repository->deleteClass($id);
        return ['deleted' => true, 'id' => $id];
    }

    private function handleDeleteIndication(array $input): array
    {
        $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
        $this->repository->deleteIndication($id);
        return ['deleted' => true, 'id' => $id];
    }

    private function handleDeleteBrand(array $input): array
    {
        $country = (string)($input['country'] ?? $_GET['country'] ?? 'BD');
        $id = (string)($input['id'] ?? $_GET['id'] ?? '');
        $this->repository->deleteBrand($country, $id);
        return ['deleted' => true, 'id' => $id];
    }

    private function handleDeleteManufacturer(array $input): array
    {
        $country = (string)($input['country'] ?? $_GET['country'] ?? 'BD');
        $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
        $this->repository->deleteManufacturer($country, $id);
        return ['deleted' => true, 'id' => $id];
    }

    private function handleDeleteDosageForm(array $input): array
    {
        $id = (int)($input['id'] ?? $_GET['id'] ?? 0);
        $this->repository->deleteDosageForm($id);
        return ['deleted' => true, 'id' => $id];
    }
}
