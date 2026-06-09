<?php
declare(strict_types=1);

/**
 * Device API Helpers
 *
 * Code curation and educational adaptation:
 * Dimitrios Kanatas
 * https://labschool.gr
 * https://labschool.mysch.gr
 *
 * These helpers expose a small, predictable API for devices such as ESP32
 * boards. Device clients receive numeric values and simple units instead of
 * the heavier UI-oriented payload used by the web dashboard.
 */

function deviceApiMeasurements(): array
{
    /*
     * Public device keys are intentionally short and stable. They map to the
     * internal ThingsBoard telemetry keys without exposing arbitrary queries.
     */
    return [
        'temperature' => [
            'source_key' => 'temperature',
            'unit' => 'C',
        ],
        'humidity' => [
            'source_key' => 'humidity',
            'unit' => '%',
        ],
        'pressure' => [
            'source_key' => 'pressure',
            'unit' => 'hPa',
        ],
        'co' => [
            'source_key' => 'carbonMonoxide',
            'unit' => 'ppm',
        ],
        'co2' => [
            'source_key' => 'carbonDioxide',
            'unit' => 'ppm',
        ],
        'pm25' => [
            'source_key' => 'PMS7003_MP_2_5',
            'unit' => 'ug/m3',
        ],
        'pm10' => [
            'source_key' => 'PMS7003_MP_10',
            'unit' => 'ug/m3',
        ],
    ];
}

function deviceApiRawValue(array $telemetry, string $sourceKey): mixed
{
    if (!hasTelemetryValue($telemetry, $sourceKey)) {
        return null;
    }

    $value = $telemetry[$sourceKey][0]['value'];

    if (is_numeric($value)) {
        return (float) $value;
    }

    return $value;
}

function deviceApiTimestamp(array $telemetry, string $sourceKey): ?int
{
    return telemetryTimestamp($telemetry, $sourceKey);
}

function deviceApiTimestampIso(?int $timestamp): ?string
{
    if ($timestamp === null) {
        return null;
    }

    return date(DATE_ATOM, (int) floor($timestamp / 1000));
}

function deviceApiBuildValues(array $telemetry): array
{
    $values = [];

    foreach (deviceApiMeasurements() as $key => $measurement) {
        $sourceKey = $measurement['source_key'];
        $timestamp = deviceApiTimestamp($telemetry, $sourceKey);
        $value = deviceApiRawValue($telemetry, $sourceKey);

        $values[$key] = [
            'value' => $value,
            'unit' => $measurement['unit'],
            'available' => $value !== null,
            'timestamp' => $timestamp,
            'time' => deviceApiTimestampIso($timestamp),
        ];
    }

    return $values;
}

function deviceApiSendJson(array $payload, int $statusCode = 200): void
{
    if (!headers_sent()) {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store, max-age=0');
    }

    echo json_encode(
        $payload,
        JSON_UNESCAPED_SLASHES
        | JSON_HEX_TAG
        | JSON_HEX_AMP
        | JSON_HEX_APOS
        | JSON_HEX_QUOT
    );
}

function deviceApiLoadDashboardContext(): array
{
    /*
     * The device API reuses the same cached telemetry as the main dashboard.
     * This keeps all public endpoints consistent and avoids duplicate upstream
     * requests to ThingsBoard.
     */
    ob_start();
    require __DIR__ . '/../../index.php';
    ob_end_clean();

    return [
        'data' => $data ?? [],
        'error' => $error ?? null,
        'measurement_ts' => $measurementTs ?? null,
        'telemetry_cache_ttl' => $telemetryCacheTtl ?? 60,
    ];
}
