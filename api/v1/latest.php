<?php
declare(strict_types=1);

/**
 * Lightweight Latest Values Endpoint
 *
 * Code curation and educational adaptation:
 * Dimitrios Kanatas
 * https://labschool.gr
 * https://labschool.mysch.gr
 *
 * Returns all public measurements in a device-friendly JSON format. Use
 * ?flat=1 for the smallest response when labels, units and timestamps are not
 * needed by the client.
 */

require __DIR__ . '/_helpers.php';
$context = deviceApiLoadDashboardContext();

$values = deviceApiBuildValues($context['data']);
$flat = isset($_GET['flat']) && in_array((string) $_GET['flat'], ['1', 'true', 'yes'], true);

if ($flat) {
    $flatValues = [];

    foreach ($values as $key => $entry) {
        $flatValues[$key] = $entry['value'];
    }

    /*
     * Flat mode is useful for microcontrollers and low-bandwidth clients.
     * Example: {"temperature":28.4,"humidity":49.2}
     */
    deviceApiSendJson($flatValues);
    exit;
}

deviceApiSendJson([
    'ok' => $context['error'] === null,
    'error' => $context['error'] === null ? null : 'Telemetry is temporarily unavailable.',
    'station' => 'alexandroupoli-center',
    'source' => 'Technology Club of Thrace Environmental Station',
    'timezone' => 'Europe/Athens',
    'measurement_timestamp' => $context['measurement_ts'],
    'measurement_time' => deviceApiTimestampIso($context['measurement_ts']),
    'updated_at' => date(DATE_ATOM),
    'cache_ttl' => $context['telemetry_cache_ttl'],
    'values' => $values,
]);
