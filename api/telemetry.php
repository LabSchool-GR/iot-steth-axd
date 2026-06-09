<?php
declare(strict_types=1);

/**
 * Dashboard Telemetry JSON Endpoint
 *
 * Code curation and educational adaptation:
 * Dimitrios Kanatas
 * https://labschool.gr
 * https://labschool.mysch.gr
 *
 * This endpoint returns UI-ready telemetry data for the browser. It includes
 * translated labels, descriptions and preformatted HTML fragments used by the
 * dashboard and TV mode JavaScript refresh loops.
 */

/*
 * Reuse the main dashboard context so the JSON endpoint and HTML page share
 * the same cache, translations and missing-sensor handling.
 */
ob_start();
require __DIR__ . '/../index.php';
ob_end_clean();

$cards = [];

foreach ($measurementCards as $card) {
    $available = hasTelemetryValue($data, $card['key']);
    $timestamp = telemetryTimestamp($data, $card['key']);

    $cards[] = [
        'key' => $card['key'],
        'label' => $card['label'],
        'description' => $card['description'],
        'available' => $available,
        'value' => telemetryValue($data, $card['key'], $t['missing_value']),
        'value_html' => telemetryValueHtml($data, $card['key'], $t['missing_value']),
        'value_class' => telemetryValueClass($data, $card['key']),
        'unit_html' => telemetryUnitHtml($data, $card['key'], $card['unit']),
        'timestamp' => $timestamp,
        'time' => formatTelemetryTimestamp($timestamp, $t['missing_value']),
    ];
}

$payload = [
    'ok' => $error === null,
    'error' => $error === null ? null : $t['error_message'],
    'lang' => $lang,
    'locale' => $clientLocale,
    'station' => [
        'title' => $t['heading'],
        'subtitle' => $t['subtitle'],
    ],
    'measurement_time' => $measurementTime,
    'measurement_timestamp' => $measurementTs,
    'updated_at' => $pageUpdatedTime,
    'cache_ttl' => $telemetryCacheTtl,
    'cards' => $cards,
];

if (!headers_sent()) {
    /*
     * The browser should ask for fresh JSON on each refresh cycle. Server-side
     * telemetry caching still prevents excessive upstream ThingsBoard calls.
     */
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, max-age=0');
}

echo json_encode(
    $payload,
    JSON_UNESCAPED_UNICODE
    | JSON_UNESCAPED_SLASHES
    | JSON_HEX_TAG
    | JSON_HEX_AMP
    | JSON_HEX_APOS
    | JSON_HEX_QUOT
);
