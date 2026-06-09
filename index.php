<?php
declare(strict_types=1);

date_default_timezone_set('Europe/Athens');

/**
 * Environmental Monitoring Station Dashboard
 *
 * Code curation and educational adaptation:
 * Dimitrios Kanatas
 * https://labschool.gr
 * https://labschool.mysch.gr
 *
 * Data source:
 * Technology Club of Thrace Environmental Station
 * https://steth.gr/env
 *
 * This file is the main public dashboard. It loads local configuration,
 * retrieves cached telemetry from ThingsBoard, prepares translated UI labels,
 * and renders the accessible HTML page.
 */

/* -----------------------------
   Configuration
----------------------------- */

$configFile = __DIR__ . '/config.php';

/*
 * Deployment-specific ThingsBoard values live in config.php.
 * The real config is intentionally ignored by Git, while config.example.php
 * documents the required structure for public distribution.
 */
if (!is_readable($configFile)) {
    throw new RuntimeException(
        'Missing config.php. Copy config.example.php to config.php and fill in the deployment settings.'
    );
}

$config = require $configFile;

if (!is_array($config)) {
    throw new RuntimeException('config.php must return a configuration array.');
}

$thingsboardConfig = $config['thingsboard'] ?? [];
$cacheConfig = $config['cache'] ?? [];
$siteConfig = $config['site'] ?? [];

$baseUrl = isset($thingsboardConfig['base_url']) ? rtrim((string) $thingsboardConfig['base_url'], '/') : '';
$publicId = isset($thingsboardConfig['public_id']) ? (string) $thingsboardConfig['public_id'] : '';
$deviceId = isset($thingsboardConfig['device_id']) ? (string) $thingsboardConfig['device_id'] : '';
$keys = $thingsboardConfig['keys'] ?? [];

if ($baseUrl === '' || $publicId === '' || $deviceId === '' || !is_array($keys) || $keys === []) {
    throw new RuntimeException('config.php contains incomplete ThingsBoard settings.');
}

$tokenCacheFile = __DIR__ . '/private/thingsboard_public_token.json';
$telemetryCacheFile = __DIR__ . '/private/thingsboard_telemetry_cache.json';
$telemetryCacheTtl = isset($cacheConfig['telemetry_ttl']) ? max(10, (int) $cacheConfig['telemetry_ttl']) : 60;
$canonicalUrl = isset($siteConfig['canonical_url'])
    ? rtrim((string) $siteConfig['canonical_url'], '/') . '/'
    : 'https://labschool.gr/iot-steth-axd/';
$socialImageUrl = $canonicalUrl . 'assets/social-preview.jpg';
$socialImageWidth = 1200;
$socialImageHeight = 630;
$socialImageType = 'image/jpeg';

$languageFiles = [
    'el' => __DIR__ . '/lang/el.json',
    'en' => __DIR__ . '/lang/en.json',
];

/*
 * The language is chosen from the query string, but only known language files
 * are accepted. Unknown values fall back to Greek, the default language.
 */
$lang = isset($_GET['lang']) && is_string($_GET['lang']) ? $_GET['lang'] : 'el';
$translations = loadTranslations($languageFiles);

if (!array_key_exists($lang, $translations)) {
    $lang = 'el';
}

$t = $translations[$lang];

sendSecurityHeaders();

/* -----------------------------
   Helper functions
----------------------------- */

function loadTranslations(array $languageFiles): array
{
    $translations = [];

    /*
     * Translation files are regular JSON documents, which keeps long UI text
     * outside PHP and makes future edits easier for non-programmers.
     */
    foreach ($languageFiles as $code => $file) {
        if (!is_readable($file)) {
            if ($code === 'el') {
                throw new RuntimeException('Default language file is missing.');
            }

            continue;
        }

        $json = file_get_contents($file);

        if ($json === false) {
            if ($code === 'el') {
                throw new RuntimeException('Cannot read default language file.');
            }

            continue;
        }

        $data = json_decode($json, true);

        if (!is_array($data)) {
            if ($code === 'el') {
                throw new RuntimeException('Default language file contains invalid JSON.');
            }

            continue;
        }

        $translations[$code] = $data;
    }

    if (!isset($translations['el'])) {
        throw new RuntimeException('Default language file is not loaded.');
    }

    foreach ($translations as $code => $translation) {
        if ($code !== 'el') {
            $translations[$code] = array_replace_recursive($translations['el'], $translation);
        }
    }

    return $translations;
}

function sendSecurityHeaders(): void
{
    if (headers_sent()) {
        return;
    }

    /*
     * The CSP is intentionally strict because CSS and JavaScript live in
     * separate files. This reduces the attack surface for script injection.
     */
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header(
        'Content-Security-Policy: '
        . "default-src 'self'; "
        . "script-src 'self'; "
        . "style-src 'self'; "
        . "img-src 'self' data:; "
        . "font-src 'self'; "
        . "connect-src 'self'; "
        . "object-src 'none'; "
        . "base-uri 'self'; "
        . "form-action 'self'; "
        . "frame-ancestors 'none'"
    );
}

function httpRequest(string $url, string $method = 'GET', array $headers = [], ?string $body = null): array
{
    $ch = curl_init($url);

    /*
     * Always verify TLS certificates when calling the upstream ThingsBoard
     * server. Environmental data is public, but transport integrity still
     * matters.
     */
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ];

    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = $body;
    }

    curl_setopt_array($ch, $options);

    $responseBody = curl_exec($ch);

    if ($responseBody === false) {
        $error = curl_error($ch);
        curl_close($ch);

        throw new RuntimeException('cURL error: ' . $error);
    }

    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    return [
        'status' => $status,
        'body' => $responseBody,
    ];
}

function base64UrlDecode(string $data): string
{
    $data = str_replace(['-', '_'], ['+', '/'], $data);
    $padding = strlen($data) % 4;

    if ($padding > 0) {
        $data .= str_repeat('=', 4 - $padding);
    }

    $decoded = base64_decode($data, true);

    if ($decoded === false) {
        throw new RuntimeException('Cannot decode JWT payload.');
    }

    return $decoded;
}

function getJwtExpiration(string $token): int
{
    $parts = explode('.', $token);

    if (count($parts) < 2) {
        throw new RuntimeException('Invalid JWT token.');
    }

    $payloadJson = base64UrlDecode($parts[1]);
    $payload = json_decode($payloadJson, true);

    if (!is_array($payload) || !isset($payload['exp'])) {
        throw new RuntimeException('JWT token does not contain exp.');
    }

    return (int) $payload['exp'];
}

function readCachedToken(string $cacheFile): ?array
{
    /*
     * Public login tokens are cached server-side. This avoids requesting a new
     * token on every page load and keeps the upstream service quieter.
     */
    if (!is_readable($cacheFile)) {
        return null;
    }

    $json = file_get_contents($cacheFile);

    if ($json === false) {
        return null;
    }

    $data = json_decode($json, true);

    if (!is_array($data) || empty($data['token']) || empty($data['expires_at'])) {
        return null;
    }

    return $data;
}

function writeCachedToken(string $cacheFile, string $token, int $expiresAt): void
{
    ensureCacheDirectory(dirname($cacheFile));

    $data = [
        'token' => $token,
        'expires_at' => $expiresAt,
        'saved_at' => time(),
    ];

    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        throw new RuntimeException('Cannot encode token cache JSON.');
    }

    $bytes = file_put_contents(
        $cacheFile,
        $json,
        LOCK_EX
    );

    if ($bytes === false) {
        throw new RuntimeException('Cannot write token cache file.');
    }
}

function deleteCachedToken(string $cacheFile): void
{
    if (is_file($cacheFile) && !unlink($cacheFile)) {
        throw new RuntimeException('Cannot remove expired token cache file.');
    }
}

function ensureCacheDirectory(string $cacheDir): void
{
    if (!is_dir($cacheDir) && !mkdir($cacheDir, 0750, true) && !is_dir($cacheDir)) {
        throw new RuntimeException('Cannot create cache directory.');
    }
}

function readCachedTelemetry(string $cacheFile, int $ttl): ?array
{
    /*
     * Telemetry values are cached for a short time. The dashboard can refresh
     * often without forcing every viewer to hit ThingsBoard directly.
     */
    if (!is_readable($cacheFile)) {
        return null;
    }

    $json = file_get_contents($cacheFile);

    if ($json === false) {
        return null;
    }

    $cached = json_decode($json, true);

    if (
        !is_array($cached)
        || !isset($cached['saved_at'], $cached['data'])
        || !is_array($cached['data'])
    ) {
        return null;
    }

    if (time() - (int) $cached['saved_at'] > $ttl) {
        return null;
    }

    return $cached['data'];
}

function writeCachedTelemetry(string $cacheFile, array $data): void
{
    ensureCacheDirectory(dirname($cacheFile));

    $json = json_encode([
        'saved_at' => time(),
        'data' => $data,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if ($json === false) {
        throw new RuntimeException('Cannot encode telemetry cache JSON.');
    }

    $bytes = file_put_contents($cacheFile, $json, LOCK_EX);

    if ($bytes === false) {
        throw new RuntimeException('Cannot write telemetry cache file.');
    }
}

function deleteCachedTelemetry(string $cacheFile): void
{
    if (is_file($cacheFile) && !unlink($cacheFile)) {
        throw new RuntimeException('Cannot remove telemetry cache file.');
    }
}

function getPublicToken(string $baseUrl, string $publicId, string $cacheFile): string
{
    $cached = readCachedToken($cacheFile);

    /*
     * Use cached token if it is still valid.
     * Refresh 5 minutes before expiration.
     */
    if ($cached !== null && time() < ((int) $cached['expires_at'] - 300)) {
        return (string) $cached['token'];
    }

    /*
     * ThingsBoard installations may accept the public login in different forms.
     * We try the common variants.
     */
    $attempts = [
        [
            'url' => $baseUrl . '/api/auth/login/public?publicId=' . urlencode($publicId),
            'method' => 'GET',
            'headers' => [
                'Accept: application/json',
            ],
            'body' => null,
        ],
        [
            'url' => $baseUrl . '/api/auth/login/public?publicId=' . urlencode($publicId),
            'method' => 'POST',
            'headers' => [
                'Accept: application/json',
            ],
            'body' => null,
        ],
        [
            'url' => $baseUrl . '/api/auth/login/public',
            'method' => 'POST',
            'headers' => [
                'Accept: application/json',
                'Content-Type: application/json',
            ],
            'body' => json_encode([
                'publicId' => $publicId,
            ]),
        ],
    ];

    $lastError = '';

    foreach ($attempts as $attempt) {
        $response = httpRequest(
            $attempt['url'],
            $attempt['method'],
            $attempt['headers'],
            $attempt['body']
        );

        if ($response['status'] >= 200 && $response['status'] < 300) {
            $json = json_decode($response['body'], true);

            if (is_array($json) && !empty($json['token'])) {
                $token = (string) $json['token'];
                $expiresAt = getJwtExpiration($token);

                writeCachedToken($cacheFile, $token, $expiresAt);

                return $token;
            }

            $lastError = 'Token response did not contain token: ' . $response['body'];
        } else {
            $lastError = 'HTTP ' . $response['status'] . ' - ' . $response['body'];
        }
    }

    throw new RuntimeException('Could not get public token. Last error: ' . $lastError);
}

function getTelemetry(
    string $baseUrl,
    string $deviceId,
    array $keys,
    string $token
): array {
    /*
     * ThingsBoard returns time-series values keyed by sensor name. We request
     * only the configured keys so the response stays small and predictable.
     */
    $url = $baseUrl
        . '/api/plugins/telemetry/DEVICE/'
        . rawurlencode($deviceId)
        . '/values/timeseries?keys='
        . rawurlencode(implode(',', $keys));

    $response = httpRequest($url, 'GET', [
        'Accept: application/json',
        'X-Authorization: Bearer ' . $token,
    ]);

    if ($response['status'] === 401 || $response['status'] === 403) {
        throw new RuntimeException('TOKEN_EXPIRED_OR_UNAUTHORIZED');
    }

    if ($response['status'] < 200 || $response['status'] >= 300) {
        throw new RuntimeException(
            'Telemetry error. HTTP ' . $response['status'] . ' - ' . $response['body']
        );
    }

    $json = json_decode($response['body'], true);

    if (!is_array($json)) {
        throw new RuntimeException('Invalid telemetry JSON.');
    }

    return $json;
}

function getTelemetryCached(
    string $baseUrl,
    string $deviceId,
    array $keys,
    string $token,
    string $cacheFile,
    int $ttl
): array {
    $cached = readCachedTelemetry($cacheFile, $ttl);

    if ($cached !== null) {
        return $cached;
    }

    $data = getTelemetry($baseUrl, $deviceId, $keys, $token);
    writeCachedTelemetry($cacheFile, $data);

    return $data;
}

function telemetryValue(array $data, string $key, string $missingLabel): string
{
    return hasTelemetryValue($data, $key)
        ? (string) $data[$key][0]['value']
        : $missingLabel;
}

function telemetryValueHtml(array $data, string $key, string $missingLabel): string
{
    $value = telemetryValue($data, $key, $missingLabel);

    if (!hasTelemetryValue($data, $key)) {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }

    /*
     * Values with decimals are split into integer and decimal spans so CSS can
     * style the decimal part without changing the numeric meaning.
     */
    if (preg_match('/^(-?\d+)([.,]\d+)$/', trim($value), $matches) === 1) {
        return htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8')
            . '<span class="decimal">'
            . htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8')
            . '</span>';
    }

    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function hasTelemetryValue(array $data, string $key): bool
{
    return isset($data[$key][0])
        && array_key_exists('value', $data[$key][0])
        && $data[$key][0]['value'] !== null
        && $data[$key][0]['value'] !== '';
}

function telemetryValueClass(array $data, string $key): string
{
    return hasTelemetryValue($data, $key) ? 'value' : 'value missing';
}

function telemetryUnitHtml(array $data, string $key, string $unitHtml): string
{
    return hasTelemetryValue($data, $key) ? $unitHtml : '';
}

function telemetryTimestamp(array $data, string $key): ?int
{
    return isset($data[$key][0]['ts'])
        ? (int) $data[$key][0]['ts']
        : null;
}

function latestTelemetryTimestamp(array $data, array $keys): ?int
{
    $latest = null;

    foreach ($keys as $key) {
        $timestamp = telemetryTimestamp($data, $key);

        if ($timestamp !== null && ($latest === null || $timestamp > $latest)) {
            $latest = $timestamp;
        }
    }

    return $latest;
}

function formatTelemetryTimestamp(?int $timestamp, string $missingLabel): string
{
    if ($timestamp === null) {
        return $missingLabel;
    }

    return date('d/m/Y H:i:s', (int) floor($timestamp / 1000));
}

function formatCurrentDateLong(string $lang): string
{
    /*
     * IntlDateFormatter gives the best localized date output. The manual
     * fallback keeps the page usable on simple PHP installations without intl.
     */
    if (class_exists(IntlDateFormatter::class)) {
        $formatter = new IntlDateFormatter(
            $lang === 'el' ? 'el_GR' : 'en_GB',
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            'Europe/Athens',
            IntlDateFormatter::GREGORIAN,
            $lang === 'el' ? 'EEEE d MMMM y' : 'EEEE, d MMMM y'
        );

        $formatted = $formatter->format(time());

        if ($formatted !== false) {
            return $formatted;
        }
    }

    $timestamp = time();

    if ($lang === 'el') {
        $days = [
            1 => 'Δευτέρα',
            2 => 'Τρίτη',
            3 => 'Τετάρτη',
            4 => 'Πέμπτη',
            5 => 'Παρασκευή',
            6 => 'Σάββατο',
            7 => 'Κυριακή',
        ];
        $months = [
            1 => 'Ιανουαρίου',
            2 => 'Φεβρουαρίου',
            3 => 'Μαρτίου',
            4 => 'Απριλίου',
            5 => 'Μαΐου',
            6 => 'Ιουνίου',
            7 => 'Ιουλίου',
            8 => 'Αυγούστου',
            9 => 'Σεπτεμβρίου',
            10 => 'Οκτωβρίου',
            11 => 'Νοεμβρίου',
            12 => 'Δεκεμβρίου',
        ];

        return $days[(int) date('N', $timestamp)]
            . ' '
            . date('d', $timestamp)
            . ' '
            . $months[(int) date('n', $timestamp)]
            . ' '
            . date('Y', $timestamp);
    }

    return date('l d F Y', $timestamp);
}

/* -----------------------------
   Main execution
----------------------------- */

$error = null;
$data = [];
$tokenExpiresAt = null;

try {
    /*
     * First load a cached or fresh public token, then load telemetry through
     * the short-lived telemetry cache. If the token expired unexpectedly, clear
     * both caches and retry once with a new token.
     */
    $token = getPublicToken($baseUrl, $publicId, $tokenCacheFile);
    $tokenExpiresAt = getJwtExpiration($token);

    try {
        $data = getTelemetryCached(
            $baseUrl,
            $deviceId,
            $keys,
            $token,
            $telemetryCacheFile,
            $telemetryCacheTtl
        );
    } catch (RuntimeException $e) {
        /*
         * If token failed, delete cache, get a new token and retry once.
         */
        if ($e->getMessage() === 'TOKEN_EXPIRED_OR_UNAUTHORIZED') {
            deleteCachedToken($tokenCacheFile);
            deleteCachedTelemetry($telemetryCacheFile);

            $token = getPublicToken($baseUrl, $publicId, $tokenCacheFile);
            $tokenExpiresAt = getJwtExpiration($token);
            $data = getTelemetryCached(
                $baseUrl,
                $deviceId,
                $keys,
                $token,
                $telemetryCacheFile,
                $telemetryCacheTtl
            );
        } else {
            throw $e;
        }
    }
} catch (Throwable $e) {
    $error = $e->getMessage();
    error_log($error);
}

$measurementTs = latestTelemetryTimestamp($data, $keys);
$measurementTime = formatTelemetryTimestamp($measurementTs, $t['missing_value']);
$pageUpdatedTime = date('d/m/Y H:i:s');
$clientLocale = $lang === 'el' ? 'el-GR' : 'en-GB';
$currentDateLong = formatCurrentDateLong($lang);
$assetVersion = (string) max(
    filemtime(__DIR__ . '/assets/app.css') ?: time(),
    filemtime(__DIR__ . '/assets/app.js') ?: time(),
    filemtime(__DIR__ . '/assets/favicon.svg') ?: time()
);

/*
 * The first paint receives the correct time-of-day background from PHP.
 * JavaScript keeps it updated after the page has loaded.
 */
$currentHour = (int) date('G');
$backgroundPeriod = match (true) {
    $currentHour >= 6 && $currentHour < 12 => 'morning',
    $currentHour >= 12 && $currentHour < 17 => 'noon',
    $currentHour >= 17 && $currentHour < 21 => 'afternoon',
    default => 'night',
};
$measurementCards = [
    [
        'label' => $t['temperature'],
        'key' => 'temperature',
        'unit' => '&deg;C',
        'description' => $t['descriptions']['temperature'],
    ],
    [
        'label' => $t['humidity'],
        'key' => 'humidity',
        'unit' => '%',
        'description' => $t['descriptions']['humidity'],
    ],
    [
        'label' => $t['pressure'],
        'key' => 'pressure',
        'unit' => 'hPa',
        'description' => $t['descriptions']['pressure'],
    ],
    [
        'label' => $t['carbon_monoxide'],
        'key' => 'carbonMonoxide',
        'unit' => 'ppm',
        'description' => $t['descriptions']['carbon_monoxide'],
    ],
    [
        'label' => $t['carbon_dioxide'],
        'key' => 'carbonDioxide',
        'unit' => 'ppm',
        'description' => $t['descriptions']['carbon_dioxide'],
    ],
    [
        'label' => $t['pm25'],
        'key' => 'PMS7003_MP_2_5',
        'unit' => '&micro;g/m&sup3;',
        'description' => $t['descriptions']['pm25'],
    ],
    [
        'label' => $t['pm10'],
        'key' => 'PMS7003_MP_10',
        'unit' => '&micro;g/m&sup3;',
        'description' => $t['descriptions']['pm10'],
    ],
];

?>
<!doctype html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title><?= htmlspecialchars($t['page_title'], ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($t['subtitle'], ENT_QUOTES, 'UTF-8') ?>">
    <link rel="canonical" href="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>">

    <meta property="og:type" content="website">
    <meta property="og:locale" content="<?= $lang === 'el' ? 'el_GR' : 'en_GB' ?>">
    <meta property="og:title" content="<?= htmlspecialchars($t['page_title'], ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($t['subtitle'], ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonicalUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image" content="<?= htmlspecialchars($socialImageUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image:secure_url" content="<?= htmlspecialchars($socialImageUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image:type" content="<?= htmlspecialchars($socialImageType, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image:width" content="<?= $socialImageWidth ?>">
    <meta property="og:image:height" content="<?= $socialImageHeight ?>">
    <meta property="og:image:alt" content="<?= htmlspecialchars($t['page_title'], ENT_QUOTES, 'UTF-8') ?>">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= htmlspecialchars($t['page_title'], ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($t['subtitle'], ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($socialImageUrl, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:image:alt" content="<?= htmlspecialchars($t['page_title'], ENT_QUOTES, 'UTF-8') ?>">
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg?v=<?= htmlspecialchars($assetVersion, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="assets/app.css?v=<?= htmlspecialchars($assetVersion, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="time-bg-<?= htmlspecialchars($backgroundPeriod, ENT_QUOTES, 'UTF-8') ?>">
<main class="container" data-telemetry-url="api/telemetry.php?lang=<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
    <header class="hero">
        <div class="intro">
            <h1><?= htmlspecialchars($t['heading'], ENT_QUOTES, 'UTF-8') ?></h1>
            <div class="subtitle"><?= htmlspecialchars($t['subtitle'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>

        <section
            class="clock-panel"
            aria-label="<?= htmlspecialchars($t['clock_label'], ENT_QUOTES, 'UTF-8') ?>"
            data-locale="<?= htmlspecialchars($clientLocale, ENT_QUOTES, 'UTF-8') ?>"
        >
            <div class="clock-label"><?= htmlspecialchars($t['clock_label'], ENT_QUOTES, 'UTF-8') ?></div>
            <div class="live-time" id="liveTime"><?= htmlspecialchars(date('H:i:s'), ENT_QUOTES, 'UTF-8') ?></div>
            <div class="live-date" id="liveDate"><?= htmlspecialchars($currentDateLong, ENT_QUOTES, 'UTF-8') ?></div>
        </section>
    </header>

    <?php if ($error): ?>
        <div class="error">
            <strong><?= htmlspecialchars($t['error_label'], ENT_QUOTES, 'UTF-8') ?></strong>
            <?= htmlspecialchars($t['error_message'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="section-head">
        <h2><?= htmlspecialchars($t['api_data_title'], ENT_QUOTES, 'UTF-8') ?></h2>
    </div>

    <div class="grid">
        <?php foreach ($measurementCards as $card): ?>
            <div class="card" data-measurement-key="<?= htmlspecialchars($card['key'], ENT_QUOTES, 'UTF-8') ?>">
                <div>
                    <div class="label" data-field="label"><?= htmlspecialchars($card['label'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="<?= htmlspecialchars(telemetryValueClass($data, $card['key']), ENT_QUOTES, 'UTF-8') ?>" data-field="value" aria-live="polite">
                        <?= telemetryValueHtml($data, $card['key'], $t['missing_value']) ?>
                        <span class="unit"><?= telemetryUnitHtml($data, $card['key'], $card['unit']) ?></span>
                    </div>
                </div>
                <p class="description" data-field="description"><?= htmlspecialchars($card['description'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
        <?php endforeach; ?>

        <div class="card source-card">
            <img
                class="source-logo"
                src="assets/logo_env.png"
                alt="<?= htmlspecialchars($t['logo_alt'], ENT_QUOTES, 'UTF-8') ?>"
            >
            <a class="source-link" href="<?= htmlspecialchars($t['source_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" rel="noopener noreferrer">
                <?= htmlspecialchars($t['source_label'], ENT_QUOTES, 'UTF-8') ?>
            </a>
        </div>
    </div>

    <div class="footer">
        <div class="footer-panel status-panel">
            <div class="status-text">
                <div><?= htmlspecialchars($t['measured_at'], ENT_QUOTES, 'UTF-8') ?>: <span data-field="measurement-time" aria-live="polite"><?= htmlspecialchars($measurementTime, ENT_QUOTES, 'UTF-8') ?></span></div>
                <div><?= htmlspecialchars($t['updated_at'], ENT_QUOTES, 'UTF-8') ?>: <span data-field="updated-at" aria-live="polite"><?= htmlspecialchars($pageUpdatedTime, ENT_QUOTES, 'UTF-8') ?></span></div>
                <div><?= htmlspecialchars($t['auto_refresh'], ENT_QUOTES, 'UTF-8') ?></div>
            </div>

            <a class="refresh" href="?lang=<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($t['refresh_now'], ENT_QUOTES, 'UTF-8') ?></a>
        </div>

        <div class="footer-panel tv-mode-panel">
            <a class="tv-mode-link" href="tv.php?lang=<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
                <?= htmlspecialchars($t['tv_mode_link'], ENT_QUOTES, 'UTF-8') ?>
            </a>
        </div>

        <div class="footer-panel language-panel">
            <nav class="language-switch" aria-label="<?= htmlspecialchars($t['language_label'], ENT_QUOTES, 'UTF-8') ?>">
                <?php foreach ($translations as $code => $translation): ?>
                    <a
                        class="<?= $lang === $code ? 'active' : '' ?>"
                        href="?lang=<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"
                        hreflang="<?= htmlspecialchars($code, ENT_QUOTES, 'UTF-8') ?>"
                    ><?= htmlspecialchars($translation['language_name'], ENT_QUOTES, 'UTF-8') ?></a>
                <?php endforeach; ?>
            </nav>
        </div>
    </div>
</main>
<script src="assets/app.js?v=<?= htmlspecialchars($assetVersion, ENT_QUOTES, 'UTF-8') ?>" defer></script>
</body>
</html>

