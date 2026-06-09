<?php
declare(strict_types=1);

/*
 * Environmental Monitoring Station Dashboard - Example Configuration
 *
 * Code curation and educational adaptation:
 * Dimitrios Kanatas
 * https://labschool.gr
 * https://labschool.mysch.gr
 *
 * Copy this file to config.php and fill in your own ThingsBoard public
 * dashboard/device details. Do not commit config.php to a public repository.
 */

return [
    'thingsboard' => [
        'base_url' => 'https://your-thingsboard.example.com',
        'public_id' => 'replace-with-your-public-dashboard-id',
        'device_id' => 'replace-with-your-device-id',
        'keys' => [
            'temperature',
            'humidity',
            'pressure',
            'carbonMonoxide',
            'carbonDioxide',
            'PMS7003_MP_2_5',
            'PMS7003_MP_10',
        ],
    ],
    'cache' => [
        'telemetry_ttl' => 60,
    ],
    'site' => [
        'canonical_url' => 'https://example.com/iot-steth-axd/',
    ],
];
