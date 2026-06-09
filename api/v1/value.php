<?php
declare(strict_types=1);

/**
 * Lightweight Single Value Endpoint
 *
 * Code curation and educational adaptation:
 * Dimitrios Kanatas
 * https://labschool.gr
 * https://labschool.mysch.gr
 *
 * Returns one whitelisted measurement by public key. The optional
 * format=text output is intentionally tiny for microcontroller sketches.
 */

require __DIR__ . '/_helpers.php';
$context = deviceApiLoadDashboardContext();

$key = isset($_GET['key']) && is_string($_GET['key']) ? strtolower(trim($_GET['key'])) : '';
$measurements = deviceApiMeasurements();

if ($key === '' || !isset($measurements[$key])) {
    /*
     * Reject unknown keys instead of passing them through to ThingsBoard.
     * This makes the endpoint easier to document and safer to expose.
     */
    deviceApiSendJson([
        'ok' => false,
        'error' => 'Unsupported or missing key.',
        'allowed_keys' => array_keys($measurements),
    ], 400);
    exit;
}

$sourceKey = $measurements[$key]['source_key'];
$value = deviceApiRawValue($context['data'], $sourceKey);
$timestamp = deviceApiTimestamp($context['data'], $sourceKey);
$format = isset($_GET['format']) && is_string($_GET['format']) ? strtolower(trim($_GET['format'])) : 'json';

if ($format === 'text') {
    if (!headers_sent()) {
        header('Content-Type: text/plain; charset=UTF-8');
        header('Cache-Control: no-store, max-age=0');
    }

    echo $value === null ? '' : (string) $value;
    exit;
}

deviceApiSendJson([
    'ok' => $context['error'] === null,
    'key' => $key,
    'value' => $value,
    'unit' => $measurements[$key]['unit'],
    'available' => $value !== null,
    'timestamp' => $timestamp,
    'time' => deviceApiTimestampIso($timestamp),
]);
