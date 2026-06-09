<?php
declare(strict_types=1);

/**
 * Environmental Monitoring Station TV Mode
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
 * This page reuses the dashboard data model and renders a large-screen,
 * television-friendly presentation with one measurement per slide.
 */

/*
 * The main dashboard already contains the shared configuration, translation
 * loading, telemetry cache and card preparation. Output buffering lets this
 * page reuse that context without printing the regular dashboard HTML.
 */
ob_start();
require __DIR__ . '/index.php';
ob_end_clean();

/*
 * TV mode uses a Google-hosted icon font, so its CSP is slightly wider than
 * the main dashboard. Other sources remain self-hosted.
 */
header(
    'Content-Security-Policy: '
    . "default-src 'self'; "
    . "script-src 'self'; "
    . "style-src 'self' https://fonts.googleapis.com; "
    . "img-src 'self' data:; "
    . "media-src 'self'; "
    . "font-src 'self' https://fonts.gstatic.com; "
    . "connect-src 'self'; "
    . "object-src 'none'; "
    . "base-uri 'self'; "
    . "form-action 'self'; "
    . "frame-ancestors 'none'"
);

$tvAssetFiles = [
    __DIR__ . '/assets/tv.css',
    __DIR__ . '/assets/tv.js',
    __DIR__ . '/assets/favicon.svg',
    __DIR__ . '/assets/sea-tv-loop-720p.mp4',
    __DIR__ . '/assets/sea-tv-poster.jpg',
    __DIR__ . '/assets/logo_env.png',
    __DIR__ . '/assets/qrcode.png',
];

$tvAssetVersion = (string) max(array_map(
    static fn (string $file): int => is_file($file) ? (int) filemtime($file) : time(),
    $tvAssetFiles
));

$tvVideoPath = __DIR__ . '/assets/sea-tv-loop-720p.mp4';
$tvVideoExists = is_file($tvVideoPath);
$tvVideoUrl = 'assets/sea-tv-loop-720p.mp4';
$tvPosterUrl = 'assets/sea-tv-poster.jpg';
$tvSlideInterval = 10000;
$regularPageUrl = 'index.php?lang=' . rawurlencode($lang);
$tvPageTitle = $t['tv_page_title'] ?? $t['page_title'];
$tvIntroText = $t['tv_intro_text'] ?? $t['subtitle'];

/*
 * Icons are decorative and help viewers identify each metric quickly from a
 * distance. The text labels remain the accessible source of meaning.
 */
$tvIconByKey = [
    'temperature' => 'thermostat',
    'humidity' => 'humidity_percentage',
    'pressure' => 'compress',
    'carbonMonoxide' => 'air',
    'carbonDioxide' => 'co2',
    'PMS7003_MP_2_5' => 'blur_on',
    'PMS7003_MP_10' => 'grain',
];
?>
<!doctype html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($tvPageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($tvIntroText, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@48,500,0,0&display=swap" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg?v=<?= htmlspecialchars($tvAssetVersion, ENT_QUOTES, 'UTF-8') ?>">
    <link rel="stylesheet" href="assets/tv.css?v=<?= htmlspecialchars($tvAssetVersion, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body>
<div class="tv-background" aria-hidden="true">
    <?php if ($tvVideoExists): ?>
        <video autoplay muted loop playsinline poster="<?= htmlspecialchars($tvPosterUrl, ENT_QUOTES, 'UTF-8') ?>?v=<?= htmlspecialchars($tvAssetVersion, ENT_QUOTES, 'UTF-8') ?>">
            <source src="<?= htmlspecialchars($tvVideoUrl, ENT_QUOTES, 'UTF-8') ?>?v=<?= htmlspecialchars($tvAssetVersion, ENT_QUOTES, 'UTF-8') ?>" type="video/mp4">
        </video>
    <?php else: ?>
        <div class="tv-fallback-bg"></div>
    <?php endif; ?>
    <div class="tv-overlay"></div>
</div>

<main
    class="tv-stage"
    data-slide-interval="<?= $tvSlideInterval ?>"
    data-locale="<?= htmlspecialchars($clientLocale, ENT_QUOTES, 'UTF-8') ?>"
    data-telemetry-url="api/telemetry.php?lang=<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>"
>
    <header class="tv-topbar">
        <div>
            <div class="tv-brand"><?= htmlspecialchars($t['heading'], ENT_QUOTES, 'UTF-8') ?></div>
            <div class="tv-subtitle"><?= htmlspecialchars($t['subtitle'], ENT_QUOTES, 'UTF-8') ?></div>
        </div>
        <div class="tv-clock">
            <div id="tvTime"><?= htmlspecialchars(date('H:i:s'), ENT_QUOTES, 'UTF-8') ?></div>
            <div id="tvDate"><?= htmlspecialchars($currentDateLong, ENT_QUOTES, 'UTF-8') ?></div>
        </div>
    </header>

    <?php if ($error): ?>
        <div class="tv-error">
            <?= htmlspecialchars($t['error_message'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <section class="tv-slides" aria-label="<?= htmlspecialchars($tvPageTitle, ENT_QUOTES, 'UTF-8') ?>">
        <?php foreach ($measurementCards as $index => $card): ?>
            <article class="tv-card<?= $index === 0 ? ' active' : '' ?>" data-tv-slide data-measurement-key="<?= htmlspecialchars($card['key'], ENT_QUOTES, 'UTF-8') ?>">
                <span class="material-symbols-outlined tv-icon" aria-hidden="true">
                    <?= htmlspecialchars($tvIconByKey[$card['key']] ?? 'monitoring', ENT_QUOTES, 'UTF-8') ?>
                </span>
                <h2 data-field="label"><?= htmlspecialchars($card['label'], ENT_QUOTES, 'UTF-8') ?></h2>
                <div class="<?= htmlspecialchars('tv-value ' . telemetryValueClass($data, $card['key']), ENT_QUOTES, 'UTF-8') ?>" data-field="value" aria-live="polite">
                    <?= telemetryValueHtml($data, $card['key'], $t['missing_value']) ?>
                    <span class="unit"><?= telemetryUnitHtml($data, $card['key'], $card['unit']) ?></span>
                </div>
                <p data-field="description"><?= htmlspecialchars($card['description'], ENT_QUOTES, 'UTF-8') ?></p>
                <div class="tv-meta">
                    <span><?= htmlspecialchars($t['measured_at'], ENT_QUOTES, 'UTF-8') ?>: <span data-field="measurement-time" aria-live="polite"><?= htmlspecialchars($measurementTime, ENT_QUOTES, 'UTF-8') ?></span></span>
                </div>
            </article>
        <?php endforeach; ?>
    </section>

    <aside class="tv-logo-panel" aria-label="<?= htmlspecialchars($t['source_label'], ENT_QUOTES, 'UTF-8') ?>">
        <img
            class="tv-identity-logo"
            src="assets/logo_env.png?v=<?= htmlspecialchars($tvAssetVersion, ENT_QUOTES, 'UTF-8') ?>"
            alt="<?= htmlspecialchars($t['logo_alt'], ENT_QUOTES, 'UTF-8') ?>"
        >
    </aside>

    <aside class="tv-qr-panel" aria-label="<?= htmlspecialchars($t['tv_qr_label'] ?? $t['page_title'], ENT_QUOTES, 'UTF-8') ?>">
            <img
                class="tv-qr"
                src="assets/qrcode.png?v=<?= htmlspecialchars($tvAssetVersion, ENT_QUOTES, 'UTF-8') ?>"
                alt="<?= htmlspecialchars($t['tv_qr_alt'] ?? $t['page_title'], ENT_QUOTES, 'UTF-8') ?>"
            >
    </aside>
</main>

<div class="tv-mobile-message">
    <div>
        <h1><?= htmlspecialchars($t['tv_mobile_title'] ?? $tvPageTitle, ENT_QUOTES, 'UTF-8') ?></h1>
        <p><?= htmlspecialchars($t['tv_mobile_text'] ?? $tvIntroText, ENT_QUOTES, 'UTF-8') ?></p>
        <a href="<?= htmlspecialchars($regularPageUrl, ENT_QUOTES, 'UTF-8') ?>">
            <?= htmlspecialchars($t['tv_open_default'] ?? $t['page_title'], ENT_QUOTES, 'UTF-8') ?>
        </a>
    </div>
</div>

<script src="assets/tv.js?v=<?= htmlspecialchars($tvAssetVersion, ENT_QUOTES, 'UTF-8') ?>" defer></script>
</body>
</html>
