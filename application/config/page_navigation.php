<?php
declare(strict_types=1);

return [
    'appointments' => [
        'label' => 'Appointment pages',
        'header_page' => 'appointments.php',
        'items' => [
            ['key' => 'queue', 'label' => 'Appointment Queue', 'href' => 'appointments.php', 'count' => 'queue'],
            ['key' => 'followups', 'label' => 'Follow-up Patients', 'href' => 'followups.php', 'count' => 'followups'],
        ],
    ],
];
