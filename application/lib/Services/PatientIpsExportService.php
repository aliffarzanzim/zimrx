<?php
declare(strict_types=1);

namespace ZimRx\Services;

use PDO;
use RuntimeException;
use Throwable;

final class PatientIpsExportService
{
    private ?PDO $drugDb = null;

    public function __construct(
        private readonly PDO $userdataDb,
        ?PDO $drugDb = null
    ) {
        $this->drugDb = $drugDb;
    }

    private function getDrugDb(): ?PDO
    {
        if ($this->drugDb !== null) {
            return $this->drugDb;
        }

        if (class_exists('DbConnections') && method_exists('DbConnections', 'systemDb')) {
            try {
                $this->drugDb = \DbConnections::systemDb();
                return $this->drugDb;
            } catch (Throwable) {
                return null;
            }
        }

        return null;
    }

    public function exportPatientIps(int $patientId, int $doctorId = 1, ?int $visitId = null): array
    {
        $stmt = $this->userdataDb->prepare(
            'SELECT * FROM zimrx_patients WHERE id = :id AND doctor_id = :doctor_id LIMIT 1'
        );
        $stmt->execute(['id' => $patientId, 'doctor_id' => $doctorId]);
        $patient = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$patient) {
            throw new RuntimeException("Patient ID {$patientId} not found or access denied for Doctor ID {$doctorId}.");
        }

        $doctor = $this->fetchDoctor($doctorId);
        $medications = $this->fetchPrescribedDrugs($patientId, $doctorId, $visitId);
        $diagnoses = $this->fetchClinicalDiagnoses($patientId, $doctorId, $visitId);
        $vitals = $this->fetchVisitVitals($patientId, $doctorId, $visitId);

        return $this->assembleIpsBundle($patient, $doctor, $medications, $diagnoses, $vitals);
    }

    public function exportPatientIpsJson(int $patientId, int $doctorId = 1, ?int $visitId = null, bool $pretty = true): string
    {
        $bundle = $this->exportPatientIps($patientId, $doctorId, $visitId);
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }
        return (string)json_encode($bundle, $flags);
    }

    private function fetchDoctor(int $doctorId): array
    {
        try {
            $stmt = $this->userdataDb->prepare('SELECT * FROM zimrx_doctors WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $doctorId]);
            $doc = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($doc) {
                return $doc;
            }
        } catch (Throwable) {
        }

        return [
            'id' => $doctorId,
            'doctor_name' => 'Attending Physician',
            'degrees' => 'MBBS',
            'specialty' => 'General Medicine',
            'license_no' => ''
        ];
    }

    private function fetchPrescribedDrugs(int $patientId, int $doctorId, ?int $visitId): array
    {
        $sql = 'SELECT * FROM zimrx_prescription_drugs WHERE patient_id = :patient_id AND doctor_id = :doctor_id';
        $params = ['patient_id' => $patientId, 'doctor_id' => $doctorId];

        if ($visitId !== null && $visitId > 0) {
            $sql .= ' AND visit_id = :visit_id';
            $params['visit_id'] = $visitId;
        }

        $sql .= ' ORDER BY id DESC';

        try {
            $stmt = $this->userdataDb->prepare($sql);
            $stmt->execute($params);
            $drugs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            $drugs = [];
        }

        $drugDb = $this->getDrugDb();
        if ($drugDb !== null && !empty($drugs)) {
            foreach ($drugs as &$d) {
                $d['who_atc_code'] = null;
                $d['who_atc_name'] = null;
                $d['who_inn_name'] = null;
                $d['cas_number'] = null;

                $genericId = (int)($d['generic_id'] ?? 0);
                $genericName = trim((string)($d['generic_name'] ?? ''));

                if ($genericId > 0) {
                    try {
                        $q = $drugDb->prepare(
                            'SELECT g.who_atc_class, h.code_name AS atc_name, c.inn_name_en, c.cas_number
                             FROM drug_generic g
                             LEFT JOIN who_atc_hierarchy h ON g.who_atc_class = h.atc_code
                             LEFT JOIN who_inn_catalog c ON LOWER(g.generic_name) = LOWER(c.inn_name_en)
                             WHERE g.generic_id = :gid LIMIT 1'
                        );
                        $q->execute(['gid' => $genericId]);
                        $ref = $q->fetch(PDO::FETCH_ASSOC);
                        if ($ref) {
                            $d['who_atc_code'] = $ref['who_atc_class'] ?? null;
                            $d['who_atc_name'] = $ref['atc_name'] ?? null;
                            $d['who_inn_name'] = $ref['inn_name_en'] ?? null;
                            $d['cas_number'] = $ref['cas_number'] ?? null;
                        }
                    } catch (Throwable) {
                    }
                } elseif ($genericName !== '') {
                    try {
                        $q = $drugDb->prepare(
                            'SELECT g.who_atc_class, h.code_name AS atc_name, c.inn_name_en, c.cas_number
                             FROM drug_generic g
                             LEFT JOIN who_atc_hierarchy h ON g.who_atc_class = h.atc_code
                             LEFT JOIN who_inn_catalog c ON LOWER(g.generic_name) = LOWER(c.inn_name_en)
                             WHERE LOWER(g.generic_name) = LOWER(:gname) LIMIT 1'
                        );
                        $q->execute(['gname' => $genericName]);
                        $ref = $q->fetch(PDO::FETCH_ASSOC);
                        if ($ref) {
                            $d['who_atc_code'] = $ref['who_atc_class'] ?? null;
                            $d['who_atc_name'] = $ref['atc_name'] ?? null;
                            $d['who_inn_name'] = $ref['inn_name_en'] ?? null;
                            $d['cas_number'] = $ref['cas_number'] ?? null;
                        }
                    } catch (Throwable) {
                    }
                }
            }
            unset($d);
        }

        return $drugs;
    }

    private function fetchClinicalDiagnoses(int $patientId, int $doctorId, ?int $visitId): array
    {
        $findings = [];
        try {
            $sql = 'SELECT * FROM zimrx_prescription_clinical_findings 
                    WHERE doctor_id = :doctor_id AND category IN (\'dx\', \'diagnosis\')';
            $params = ['doctor_id' => $doctorId];

            if ($visitId !== null && $visitId > 0) {
                $sql .= ' AND visit_id = :visit_id';
                $params['visit_id'] = $visitId;
            }

            $sql .= ' ORDER BY id DESC';
            $stmt = $this->userdataDb->prepare($sql);
            $stmt->execute($params);
            $findings = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            $findings = [];
        }

        return $findings;
    }

    private function fetchVisitVitals(int $patientId, int $doctorId, ?int $visitId): array
    {
        $vitals = [];
        try {
            $sql = 'SELECT * FROM zimrx_visit_vitals WHERE patient_id = :patient_id AND doctor_id = :doctor_id';
            $params = ['patient_id' => $patientId, 'doctor_id' => $doctorId];

            if ($visitId !== null && $visitId > 0) {
                $sql .= ' AND visit_id = :visit_id';
                $params['visit_id'] = $visitId;
            }

            $sql .= ' ORDER BY id DESC LIMIT 1';
            $stmt = $this->userdataDb->prepare($sql);
            $stmt->execute($params);
            $vitals = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            $vitals = [];
        }

        return $vitals;
    }

    private function assembleIpsBundle(
        array $patient,
        array $doctor,
        array $medications,
        array $diagnoses,
        array $vitals
    ): array {
        $bundleUuid = $this->generateUuid();
        $compositionUuid = $this->generateUuid();
        $patientUuid = 'patient-' . ($patient['id'] ?? 1);
        $practitionerUuid = 'practitioner-' . ($doctor['id'] ?? 1);
        $nowIso = date('c');

        $entries = [];

        $nameParts = explode(' ', trim((string)($patient['full_name'] ?? 'Patient')));
        $family = count($nameParts) > 1 ? array_pop($nameParts) : $nameParts[0];
        $given = !empty($nameParts) ? $nameParts : [$family];

        $gender = match (strtolower(trim((string)($patient['gender'] ?? '')))) {
            'male', 'm' => 'male',
            'female', 'f' => 'female',
            'other' => 'other',
            default => 'unknown'
        };

        $birthDate = null;
        if (!empty($patient['dob'])) {
            $birthDate = substr(trim((string)$patient['dob']), 0, 10);
        } elseif (!empty($patient['age'])) {
            $ageInt = (int)$patient['age'];
            if ($ageInt > 0) {
                $birthYear = (int)date('Y') - $ageInt;
                $birthDate = "{$birthYear}-01-01";
            }
        }

        $patientResource = [
            'resourceType' => 'Patient',
            'id' => $patientUuid,
            'identifier' => [
                [
                    'system' => 'urn:oid:zimrx.patient.reg_no',
                    'value' => (string)($patient['reg_no'] ?? $patient['id'])
                ]
            ],
            'name' => [
                [
                    'text' => (string)$patient['full_name'],
                    'family' => $family,
                    'given' => $given
                ]
            ],
            'gender' => $gender,
        ];

        if ($birthDate !== null) {
            $patientResource['birthDate'] = $birthDate;
        }

        if (!empty($patient['mobile'])) {
            $patientResource['telecom'] = [
                ['system' => 'phone', 'value' => (string)$patient['mobile']]
            ];
        }

        if (!empty($patient['address'])) {
            $patientResource['address'] = [
                ['text' => (string)$patient['address']]
            ];
        }

        $practitionerResource = [
            'resourceType' => 'Practitioner',
            'id' => $practitionerUuid,
            'name' => [
                [
                    'text' => (string)($doctor['display_name'] ?? $doctor['doctor_name'] ?? 'Attending Physician')
                ]
            ]
        ];

        $licenseVal = (string)($doctor['license_no_en'] ?? $doctor['license_no'] ?? '');
        if ($licenseVal !== '') {
            $practitionerResource['identifier'] = [
                [
                    'system' => 'urn:oid:zimrx.practitioner.reg',
                    'value' => $licenseVal
                ]
            ];
        }

        $medicationEntries = [];
        $medicationRefs = [];

        foreach ($medications as $m) {
            $medId = 'medication-' . ($m['id'] ?? $this->generateUuid());
            $medRef = "urn:uuid:{$medId}";
            $medicationRefs[] = ['reference' => $medRef];

            $codings = [];
            if (!empty($m['who_atc_code'])) {
                $codings[] = [
                    'system' => 'http://www.whocc.no/atc',
                    'code' => (string)$m['who_atc_code'],
                    'display' => (string)($m['who_atc_name'] ?: ($m['generic_name'] ?? ''))
                ];
            }

            if (!empty($m['cas_number'])) {
                $codings[] = [
                    'system' => 'http://uts.nlm.nih.gov/fhir/inn',
                    'code' => (string)$m['cas_number'],
                    'display' => (string)($m['who_inn_name'] ?: ($m['generic_name'] ?? ''))
                ];
            }

            $displayText = trim(implode(' ', array_filter([
                $m['brand_name'] ?? $m['drug_name'] ?? '',
                $m['strength'] ?? '',
                $m['form'] ?? ''
            ])));

            if ($displayText === '') {
                $displayText = (string)($m['generic_name'] ?? 'Medication');
            }

            $dosageText = trim((string)($m['dosage'] ?? $m['dose'] ?? ''));
            $instructions = trim((string)($m['instructions'] ?? $m['instruction'] ?? ''));
            $duration = trim((string)($m['duration'] ?? ''));

            $fullDosage = $dosageText;
            if ($instructions !== '') {
                $fullDosage = $fullDosage !== '' ? "{$fullDosage} ({$instructions})" : $instructions;
            }
            if ($duration !== '') {
                $fullDosage = $fullDosage !== '' ? "{$fullDosage} for {$duration}" : $duration;
            }

            $medResource = [
                'resourceType' => 'MedicationStatement',
                'id' => $medId,
                'status' => 'active',
                'medicationCodeableConcept' => [
                    'text' => $displayText,
                ],
                'subject' => [
                    'reference' => "urn:uuid:{$patientUuid}",
                    'display' => (string)$patient['full_name']
                ]
            ];

            if (!empty($codings)) {
                $medResource['medicationCodeableConcept']['coding'] = $codings;
            }

            if ($fullDosage !== '') {
                $medResource['dosage'] = [
                    ['text' => $fullDosage]
                ];
            }

            $medicationEntries[] = [
                'fullUrl' => $medRef,
                'resource' => $medResource
            ];
        }

        $conditionEntries = [];
        $conditionRefs = [];

        foreach ($diagnoses as $d) {
            $condId = 'condition-' . ($d['id'] ?? $this->generateUuid());
            $condRef = "urn:uuid:{$condId}";
            $conditionRefs[] = ['reference' => $condRef];

            $dxText = trim((string)($d['label'] ?? $d['value'] ?? 'Clinical Diagnosis'));

            $condResource = [
                'resourceType' => 'Condition',
                'id' => $condId,
                'clinicalStatus' => [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/condition-clinical',
                            'code' => 'active'
                        ]
                    ]
                ],
                'verificationStatus' => [
                    'coding' => [
                        [
                            'system' => 'http://terminology.hl7.org/CodeSystem/condition-ver-status',
                            'code' => 'confirmed'
                        ]
                    ]
                ],
                'category' => [
                    [
                        'coding' => [
                            [
                                'system' => 'http://terminology.hl7.org/CodeSystem/condition-category',
                                'code' => 'problem-list-item'
                            ]
                        ]
                    ]
                ],
                'code' => [
                    'text' => $dxText
                ],
                'subject' => [
                    'reference' => "urn:uuid:{$patientUuid}",
                    'display' => (string)$patient['full_name']
                ]
            ];

            $conditionEntries[] = [
                'fullUrl' => $condRef,
                'resource' => $condResource
            ];
        }

        $allergyId = 'allergy-' . $this->generateUuid();
        $allergyRef = "urn:uuid:{$allergyId}";
        $allergyResource = [
            'resourceType' => 'AllergyIntolerance',
            'id' => $allergyId,
            'clinicalStatus' => [
                'coding' => [
                    [
                        'system' => 'http://terminology.hl7.org/CodeSystem/allergyintolerance-clinical',
                        'code' => 'active'
                    ]
                ]
            ],
            'code' => [
                'coding' => [
                    [
                        'system' => 'http://snomed.info/sct',
                        'code' => '716186003',
                        'display' => 'No known allergy'
                    ]
                ],
                'text' => 'No known allergies'
            ],
            'patient' => [
                'reference' => "urn:uuid:{$patientUuid}",
                'display' => (string)$patient['full_name']
            ]
        ];

        $observationEntries = [];
        $observationRefs = [];

        if (!empty($vitals['bp'])) {
            $bpParts = explode('/', (string)$vitals['bp']);
            $sys = isset($bpParts[0]) ? (float)trim($bpParts[0]) : null;
            $dia = isset($bpParts[1]) ? (float)trim($bpParts[1]) : null;

            if ($sys !== null && $sys > 0) {
                $bpId = 'obs-bp-' . ($vitals['id'] ?? $this->generateUuid());
                $bpRef = "urn:uuid:{$bpId}";
                $observationRefs[] = ['reference' => $bpRef];

                $components = [
                    [
                        'code' => [
                            'coding' => [
                                ['system' => 'http://loinc.org', 'code' => '8480-6', 'display' => 'Systolic blood pressure']
                            ]
                        ],
                        'valueQuantity' => [
                            'value' => $sys,
                            'unit' => 'mm[Hg]',
                            'system' => 'http://unitsofmeasure.org',
                            'code' => 'mm[Hg]'
                        ]
                    ]
                ];

                if ($dia !== null && $dia > 0) {
                    $components[] = [
                        'code' => [
                            'coding' => [
                                ['system' => 'http://loinc.org', 'code' => '8462-4', 'display' => 'Diastolic blood pressure']
                            ]
                        ],
                        'valueQuantity' => [
                            'value' => $dia,
                            'unit' => 'mm[Hg]',
                            'system' => 'http://unitsofmeasure.org',
                            'code' => 'mm[Hg]'
                        ]
                    ];
                }

                $observationEntries[] = [
                    'fullUrl' => $bpRef,
                    'resource' => [
                        'resourceType' => 'Observation',
                        'id' => $bpId,
                        'status' => 'final',
                        'code' => [
                            'coding' => [
                                ['system' => 'http://loinc.org', 'code' => '85354-9', 'display' => 'Blood pressure panel with all children optional']
                            ],
                            'text' => 'Blood Pressure'
                        ],
                        'subject' => ['reference' => "urn:uuid:{$patientUuid}"],
                        'component' => $components
                    ]
                ];
            }
        }

        if (!empty($vitals['pulse']) && (float)$vitals['pulse'] > 0) {
            $pulseId = 'obs-pulse-' . ($vitals['id'] ?? $this->generateUuid());
            $pulseRef = "urn:uuid:{$pulseId}";
            $observationRefs[] = ['reference' => $pulseRef];

            $observationEntries[] = [
                'fullUrl' => $pulseRef,
                'resource' => [
                    'resourceType' => 'Observation',
                    'id' => $pulseId,
                    'status' => 'final',
                    'code' => [
                        'coding' => [
                            ['system' => 'http://loinc.org', 'code' => '8867-4', 'display' => 'Heart rate']
                        ],
                        'text' => 'Pulse / Heart Rate'
                    ],
                    'subject' => ['reference' => "urn:uuid:{$patientUuid}"],
                    'valueQuantity' => [
                        'value' => (float)$vitals['pulse'],
                        'unit' => '/min',
                        'system' => 'http://unitsofmeasure.org',
                        'code' => '/min'
                    ]
                ]
            ];
        }

        $weightVal = !empty($vitals['weight']) ? (float)$vitals['weight'] : (!empty($patient['weight']) ? (float)$patient['weight'] : 0.0);
        if ($weightVal > 0) {
            $wtId = 'obs-weight-' . $this->generateUuid();
            $wtRef = "urn:uuid:{$wtId}";
            $observationRefs[] = ['reference' => $wtRef];

            $observationEntries[] = [
                'fullUrl' => $wtRef,
                'resource' => [
                    'resourceType' => 'Observation',
                    'id' => $wtId,
                    'status' => 'final',
                    'code' => [
                        'coding' => [
                            ['system' => 'http://loinc.org', 'code' => '29463-7', 'display' => 'Body weight']
                        ],
                        'text' => 'Body Weight'
                    ],
                    'subject' => ['reference' => "urn:uuid:{$patientUuid}"],
                    'valueQuantity' => [
                        'value' => $weightVal,
                        'unit' => 'kg',
                        'system' => 'http://unitsofmeasure.org',
                        'code' => 'kg'
                    ]
                ]
            ];
        }

        $sections = [
            [
                'title' => 'Medication Summary',
                'code' => [
                    'coding' => [
                        ['system' => 'http://loinc.org', 'code' => '10160-0', 'display' => 'History of Medication use']
                    ]
                ],
                'text' => [
                    'status' => 'generated',
                    'div' => '<div xmlns="http://www.w3.org/1999/xhtml">Active prescribed medications.</div>'
                ],
                'entry' => $medicationRefs
            ],
            [
                'title' => 'Problem List',
                'code' => [
                    'coding' => [
                        ['system' => 'http://loinc.org', 'code' => '11450-4', 'display' => 'Problem list - Reported']
                    ]
                ],
                'text' => [
                    'status' => 'generated',
                    'div' => '<div xmlns="http://www.w3.org/1999/xhtml">Active clinical diagnoses and problems.</div>'
                ],
                'entry' => $conditionRefs
            ],
            [
                'title' => 'Allergies and Intolerances',
                'code' => [
                    'coding' => [
                        ['system' => 'http://loinc.org', 'code' => '48765-2', 'display' => 'Allergies and adverse reactions Document']
                    ]
                ],
                'text' => [
                    'status' => 'generated',
                    'div' => '<div xmlns="http://www.w3.org/1999/xhtml">Documented allergies and adverse reactions.</div>'
                ],
                'entry' => [
                    ['reference' => $allergyRef]
                ]
            ]
        ];

        if (!empty($observationRefs)) {
            $sections[] = [
                'title' => 'Vital Signs',
                'code' => [
                    'coding' => [
                        ['system' => 'http://loinc.org', 'code' => '8716-3', 'display' => 'Vital signs']
                    ]
                ],
                'text' => [
                    'status' => 'generated',
                    'div' => '<div xmlns="http://www.w3.org/1999/xhtml">Clinical vital signs observations.</div>'
                ],
                'entry' => $observationRefs
            ];
        }

        $compositionResource = [
            'resourceType' => 'Composition',
            'id' => $compositionUuid,
            'status' => 'final',
            'type' => [
                'coding' => [
                    [
                        'system' => 'http://loinc.org',
                        'code' => '60591-5',
                        'display' => 'Patient summary Document'
                    ]
                ]
            ],
            'subject' => [
                'reference' => "urn:uuid:{$patientUuid}",
                'display' => (string)$patient['full_name']
            ],
            'date' => $nowIso,
            'author' => [
                [
                    'reference' => "urn:uuid:{$practitionerUuid}",
                    'display' => (string)($doctor['doctor_name'] ?? 'Attending Physician')
                ]
            ],
            'title' => 'International Patient Summary (IPS)',
            'section' => $sections
        ];

        $entries[] = [
            'fullUrl' => "urn:uuid:{$compositionUuid}",
            'resource' => $compositionResource
        ];

        $entries[] = [
            'fullUrl' => "urn:uuid:{$patientUuid}",
            'resource' => $patientResource
        ];

        $entries[] = [
            'fullUrl' => "urn:uuid:{$practitionerUuid}",
            'resource' => $practitionerResource
        ];

        foreach ($medicationEntries as $me) {
            $entries[] = $me;
        }

        foreach ($conditionEntries as $ce) {
            $entries[] = $ce;
        }

        $entries[] = [
            'fullUrl' => $allergyRef,
            'resource' => $allergyResource
        ];

        foreach ($observationEntries as $oe) {
            $entries[] = $oe;
        }

        return [
            'resourceType' => 'Bundle',
            'id' => $bundleUuid,
            'meta' => [
                'profile' => [
                    'http://hl7.org/fhir/uv/ips/StructureDefinition/Bundle-uv-ips'
                ]
            ],
            'identifier' => [
                'system' => 'urn:ietf:rfc:3986',
                'value' => "urn:uuid:{$bundleUuid}"
            ],
            'type' => 'document',
            'timestamp' => $nowIso,
            'entry' => $entries
        ];
    }

    private function generateUuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
