/**
 * TV Mode Client Script
 *
 * Code curation and educational adaptation:
 * Dimitrios Kanatas
 * https://labschool.gr
 * https://labschool.mysch.gr
 *
 * Designed for televisions and large displays: it cycles through one metric at
 * a time, keeps the clock live and refreshes values from the local JSON API.
 */

(function () {
    var stage = document.querySelector('.tv-stage');

    if (!stage) {
        return;
    }

    var slides = document.querySelectorAll('[data-tv-slide]');
    var interval = parseInt(stage.getAttribute('data-slide-interval'), 10) || 7500;
    var transitionGap = Math.min(1800, Math.max(1200, Math.round(interval * 0.15)));
    var visibleDuration = Math.max(4000, interval - transitionGap);
    var locale = stage.getAttribute('data-locale') || 'el-GR';
    var telemetryUrl = stage.getAttribute('data-telemetry-url');
    var current = 0;

    /*
     * Older TV browsers may have incomplete Intl support. The fallback keeps
     * the display readable even on simpler embedded browsers.
     */
    function pad(value) {
        return value < 10 ? '0' + value : String(value);
    }

    function fallbackTime(now) {
        return pad(now.getHours()) + ':' + pad(now.getMinutes()) + ':' + pad(now.getSeconds());
    }

    function fallbackDate(now) {
        return pad(now.getDate()) + '/' + pad(now.getMonth() + 1) + '/' + now.getFullYear();
    }

    function updateClock() {
        var now = new Date();
        var timeElement = document.getElementById('tvTime');
        var dateElement = document.getElementById('tvDate');

        if (timeElement) {
            if (window.Intl && window.Intl.DateTimeFormat) {
                timeElement.textContent = new Intl.DateTimeFormat(locale, {
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false
                }).format(now);
            } else {
                timeElement.textContent = fallbackTime(now);
            }
        }

        if (dateElement) {
            if (window.Intl && window.Intl.DateTimeFormat) {
                dateElement.textContent = new Intl.DateTimeFormat(locale, {
                    weekday: 'long',
                    day: '2-digit',
                    month: 'long',
                    year: 'numeric'
                }).format(now);
            } else {
                dateElement.textContent = fallbackDate(now);
            }
        }
    }

    function showSlide(index) {
        var i;

        for (i = 0; i < slides.length; i += 1) {
            if (i === index) {
                slides[i].className = slides[i].className.replace(/\s*active/g, '') + ' active';
            } else {
                slides[i].className = slides[i].className.replace(/\s*active/g, '');
            }
        }

        current = index;
    }

    function runSlides() {
        /*
         * Each slide fades out, leaves a short calm gap and then fades the next
         * card in. This avoids a stressful progress-bar feeling on public TVs.
         */
        window.setTimeout(function () {
            slides[current].className = slides[current].className.replace(/\s*active/g, '');

            window.setTimeout(function () {
                showSlide((current + 1) % slides.length);
                runSlides();
            }, transitionGap);
        }, visibleDuration);
    }

    function updateTelemetryCard(card) {
        var element = document.querySelector('[data-measurement-key="' + card.key + '"]');
        var label;
        var value;
        var description;
        var measurementTime;

        if (!element) {
            return;
        }

        label = element.querySelector('[data-field="label"]');
        value = element.querySelector('[data-field="value"]');
        description = element.querySelector('[data-field="description"]');
        measurementTime = element.querySelector('[data-field="measurement-time"]');

        if (label) {
            label.textContent = card.label;
        }

        if (value) {
            value.className = card.available ? 'tv-value' : 'tv-value missing';
            value.innerHTML = card.value_html + '<span class="unit">' + card.unit_html + '</span>';
        }

        if (description) {
            description.textContent = card.description;
        }

        if (measurementTime && card.time) {
            measurementTime.textContent = card.time;
        }
    }

    function refreshTelemetry() {
        var separator;

        if (!telemetryUrl || !window.fetch) {
            return;
        }

        separator = telemetryUrl.indexOf('?') === -1 ? '?' : '&';

        /*
         * The TV page can stay open for days. Refreshing only the JSON payload
         * avoids full page reloads and keeps the animation/video stable.
         */
        window.fetch(telemetryUrl + separator + '_=' + Date.now(), {
            cache: 'no-store'
        })
            .then(function (response) {
                return response.ok ? response.json() : null;
            })
            .then(function (payload) {
                if (!payload || !payload.cards || !payload.cards.forEach) {
                    return;
                }

                payload.cards.forEach(updateTelemetryCard);
            })
            .catch(function () {
                // Keep the current values if the JSON endpoint is temporarily unavailable.
            });
    }

    if (slides.length > 0) {
        showSlide(0);
        runSlides();
    }

    updateClock();
    window.setInterval(updateClock, 1000);
    window.setInterval(refreshTelemetry, 60000);
}());
