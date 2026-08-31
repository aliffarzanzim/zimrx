<?php
declare(strict_types=1);

namespace ZimRx\Services;

// Bedside clinical calculations: BMI (WHO), Mosteller BSA, GCS, Wagner Diabetic Foot, and Insulin regimen distribution.
final class ClinicalCalculationService
{
    // Calculate Body Mass Index (BMI) and WHO 2000 classification
    public static function calculateBmi(float $weightKg, float $heightCm): ?array
    {
        if ($weightKg <= 0.0 || $heightCm <= 0.0) {
            return null;
        }

        $heightM = $heightCm / 100.0;
        $bmi = round($weightKg / ($heightM * $heightM), 2);

        $category = match (true) {
            $bmi < 18.5 => 'Underweight',
            $bmi < 25.0 => 'Normal',
            $bmi < 30.0 => 'Overweight',
            $bmi < 35.0 => 'Obese Class I',
            $bmi < 40.0 => 'Obese Class II',
            default     => 'Obese Class III',
        };

        $idealMin = round(18.5 * $heightM * $heightM, 1);
        $idealMax = round(24.9 * $heightM * $heightM, 1);

        return [
            'bmi' => $bmi,
            'category' => $category,
            'ideal_weight_min' => $idealMin,
            'ideal_weight_max' => $idealMax,
        ];
    }

    // Mosteller Body Surface Area: sqrt( (height_cm * weight_kg) / 3600 )
    public static function calculateBsaMosteller(float $heightCm, float $weightKg): ?float
    {
        if ($heightCm <= 0.0 || $weightKg <= 0.0) {
            return null;
        }

        return round(sqrt(($heightCm * $weightKg) / 3600.0), 2);
    }

    // Glasgow Coma Scale (GCS): Eye (1-4), Verbal (1-5), Motor (1-6)
    public static function calculateGcs(int $eye, int $verbal, int $motor): array
    {
        $eye = max(1, min(4, $eye));
        $verbal = max(1, min(5, $verbal));
        $motor = max(1, min(6, $motor));
        $total = $eye + $verbal + $motor;

        $severity = match (true) {
            $total <= 8  => 'Severe (Coma)',
            $total <= 12 => 'Moderate Brain Injury',
            default      => 'Mild Brain Injury / Normal',
        };

        return [
            'eye' => $eye,
            'verbal' => $verbal,
            'motor' => $motor,
            'total' => $total,
            'severity' => $severity,
        ];
    }

    // Wagner Diabetic Foot Ulcer Staging (0 to 5)
    public static function getWagnerStage(int $grade): ?array
    {
        $stages = [
            0 => [
                'grade' => 0,
                'name' => 'Grade 0: Intact skin',
                'description' => 'Intact skin / pre-ulcerative lesion, healed ulcer, presence of bony deformity',
                'risk' => 'Low to Moderate',
            ],
            1 => [
                'grade' => 1,
                'name' => 'Grade 1: Superficial ulcer',
                'description' => 'Superficial ulcer not involving tendon, capsule, or bone',
                'risk' => 'Moderate',
            ],
            2 => [
                'grade' => 2,
                'name' => 'Grade 2: Deep ulcer',
                'description' => 'Deep ulcer penetrating to tendon, capsule, or bone without osteomyelitis or abscess',
                'risk' => 'High',
            ],
            3 => [
                'grade' => 3,
                'name' => 'Grade 3: Deep ulcer with abscess/osteomyelitis',
                'description' => 'Deep ulcer with abscess, osteomyelitis, or joint sepsis',
                'risk' => 'Urgent Hospital Care',
            ],
            4 => [
                'grade' => 4,
                'name' => 'Grade 4: Localized gangrene',
                'description' => 'Localized gangrene involving portion of forefoot or heel',
                'risk' => 'Surgical Emergency',
            ],
            5 => [
                'grade' => 5,
                'name' => 'Grade 5: Extensive gangrene',
                'description' => 'Extensive gangrene involving the entire foot requiring major amputation',
                'risk' => 'Critical Emergency',
            ],
        ];

        return $stages[$grade] ?? null;
    }

    // Distribute insulin daily units into split regimen
    public static function distributeInsulin(float $totalUnits, string $regimen = 'BD'): array
    {
        $total = max(0.0, round($totalUnits, 1));
        if ($regimen === 'BD') {
            $morning = (int)round($total * (2.0 / 3.0));
            $evening = (int)round($total * (1.0 / 3.0));
            return [
                'total' => $total,
                'regimen' => 'BD',
                'morning' => $morning,
                'noon' => 0,
                'night' => $evening,
                'label' => "{$morning} + 0 + {$evening}",
            ];
        }

        $third = (int)round($total / 3.0);
        return [
            'total' => $total,
            'regimen' => 'TDS',
            'morning' => $third,
            'noon' => $third,
            'night' => $third,
            'label' => "{$third} + {$third} + {$third}",
        ];
    }
}

if (!class_exists('ClinicalCalculationService', false)) {
    class_alias(ClinicalCalculationService::class, 'ClinicalCalculationService');
}
