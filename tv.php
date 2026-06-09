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
 * TV mode uses only self-hosted scripts, styles, media, images and inline SVG
 * markup. This keeps it suitable for local/offline display after deployment.
 */
header(
    'Content-Security-Policy: '
    . "default-src 'self'; "
    . "script-src 'self'; "
    . "style-src 'self'; "
    . "img-src 'self' data:; "
    . "media-src 'self'; "
    . "font-src 'self'; "
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
 * Icons are decorative inline SVGs. Keeping them in the page avoids external
 * icon fonts or CDNs, which is important for offline/local TV deployments.
 */
$tvIconByKey = [
    'temperature' => '<svg viewBox="0 0 24 24" focusable="false"><path d="M14 14.8V5a2 2 0 0 0-4 0v9.8a4 4 0 1 0 4 0Z"/><path d="M12 7v8"/><path d="M10 19h4"/></svg>',
    'humidity' => '<svg viewBox="0 0 24 24" focusable="false"><path d="M12 3s6 6.4 6 11a6 6 0 0 1-12 0c0-4.6 6-11 6-11Z"/><path d="M9.4 14.5c.4 1.5 1.5 2.4 3 2.4"/></svg>',
    'pressure' => '<svg viewBox="0 0 24 24" focusable="false"><path d="M4 14a8 8 0 0 1 16 0"/><path d="M7 14h2"/><path d="M15 14h2"/><path d="M12 14l4-4"/><circle cx="12" cy="14" r="1.5"/></svg>',
    'carbonMonoxide' => '<svg viewBox="0 0 24 24" focusable="false"><path d="M4 9h9a3 3 0 1 0-3-3"/><path d="M4 14h13a3 3 0 1 1-3 3"/><path d="M4 19h6"/></svg>',
    'carbonDioxide' => '<svg viewBox="0 0 24 24" focusable="false"><path d="M7.5 16.5a4.5 4.5 0 1 1 0-9"/><path d="M14 16.5a4.5 4.5 0 1 0 0-9h-2v9h2Z"/><path d="M17 18h4"/><path d="M21 18c0-1.5-1-2.1-2-2.1s-2 .6-2 2.1"/></svg>',
    'PMS7003_MP_2_5' => '<svg viewBox="0 0 24 24" focusable="false"><circle cx="7" cy="8" r="2"/><circle cx="14" cy="7" r="1.5"/><circle cx="17" cy="13" r="2"/><circle cx="8.5" cy="16" r="1.5"/><circle cx="12" cy="12" r="1.1"/></svg>',
    'PMS7003_MP_10' => '<svg viewBox="0 0 24 24" focusable="false"><circle cx="7" cy="8" r="2.6"/><circle cx="15.5" cy="8.5" r="2"/><circle cx="16" cy="16" r="2.8"/><circle cx="8" cy="16" r="1.9"/></svg>',
];

$tvFallbackIcon = '<svg viewBox="0 0 24 24" focusable="false"><path d="M4 18h16"/><path d="M6 15l4-4 3 3 5-7"/><path d="M18 7h-4"/></svg>';
?>
<!doctype html>
<html lang="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($tvPageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($tvIntroText, ENT_QUOTES, 'UTF-8') ?>">
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
                <span class="tv-icon" aria-hidden="true">
                    <?= $tvIconByKey[$card['key']] ?? $tvFallbackIcon ?>
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
