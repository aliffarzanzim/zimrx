<?php
declare(strict_types=1);

// Checks drug-drug interactions between prescribed generic IDs.
final class DrugInteractionService
{
    public function __construct(private readonly \PDO $pdo) {}

    // Query interactions between all pairs in the given generic ID list, ordered by clinical severity
    public function findInteractions(array $genericIds): array
    {
        if (count($genericIds) < 2) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($genericIds), '?'));

        $sql = "
            SELECT
                i.drug_a_name   AS drug_a,
                i.drug_b_name   AS drug_b,
                i.severity,
                i.description
            FROM drug_interaction i
            WHERE i.drug_a_generic_id IN ({$placeholders})
              AND i.drug_b_generic_id IN ({$placeholders})
            ORDER BY
                CASE i.severity
                    WHEN 'contraindicated' THEN 1
                    WHEN 'major'           THEN 2
                    WHEN 'moderate'        THEN 3
                    WHEN 'minor'           THEN 4
                    ELSE                        5
                END
        ";

        $params = array_merge(array_values($genericIds), array_values($genericIds));

        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
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
