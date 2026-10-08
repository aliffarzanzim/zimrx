<?php
declare(strict_types=1);

namespace ZimRx\Services;

use PDO;
use PDOException;

// Checks drug-drug interactions between prescribed generic IDs.
final class DrugInteractionService
{
    public function __construct(private readonly PDO $pdo) {}

    // Query interactions between all pairs in the given generic ID list
    public function findInteractions(array $genericIds): array
    {
        $cleanIds = array_values(array_filter(array_map('trim', array_map('strval', $genericIds)), fn($id) => $id !== ''));
        if (count($cleanIds) < 2) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));

        $sql = "
            SELECT
                i.id,
                i.drug_a,
                i.drug_b,
                i.drug_a_generic_id,
                i.drug_b_generic_id,
                i.interaction,
                i.interaction_type
            FROM drug_interaction i
            WHERE i.drug_a_generic_id IN ({$placeholders})
              AND i.drug_b_generic_id IN ({$placeholders})
              AND i.drug_a_generic_id <> ''
              AND i.drug_b_generic_id <> ''
            ORDER BY i.id ASC
            LIMIT 50
        ";

        $params = array_merge($cleanIds, $cleanIds);

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException) {
            return [];
        }
    }

    // Verify whether the drug_interaction table contains generic ID columns for pair queries
    public function hasGenericIdColumns(): bool
    {
        $cols = [];
        foreach ($this->pdo->query('PRAGMA table_info(drug_interaction)') as $row) {
            $cols[$row['name'] ?? ''] = true;
        }
        return isset($cols['drug_a_generic_id'], $cols['drug_b_generic_id']);
    }
}

if (!class_exists('DrugInteractionService', false)) {
    class_alias(DrugInteractionService::class, 'DrugInteractionService');
}

