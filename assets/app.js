/**
 * Main Dashboard Client Script
 *
 * Code curation and educational adaptation:
 * Dimitrios Kanatas
 * https://labschool.gr
 * https://labschool.mysch.gr
 *
 * Keeps the clock live, changes the sea background by time of day and refreshes
 * telemetry values from the local JSON endpoint without reloading the page.
 */

    const clockPanel = document.querySelector('.clock-panel');
    const liveTime = document.getElementById('liveTime');
    const liveDate = document.getElementById('liveDate');
    const locale = clockPanel?.dataset.locale || 'el-GR';
    const container = document.querySelector('.container');
    const telemetryUrl = container?.dataset.telemetryUrl;
    const backgroundClasses = ['time-bg-morning', 'time-bg-noon', 'time-bg-afternoon', 'time-bg-night'];

    /*
     * The visual theme follows broad parts of the day. PHP sets the initial
     * class for first paint; JavaScript keeps it correct while the page stays
     * open for long periods.
     */
    function getBackgroundPeriod(hour) {
        if (hour >= 6 && hour < 12) {
            return 'morning';
        }

        if (hour >= 12 && hour < 17) {
            return 'noon';
        }

        if (hour >= 17 && hour < 21) {
            return 'afternoon';
        }

        return 'night';
    }

    function updateBackground(now) {
        document.body.classList.remove(...backgroundClasses);
        document.body.classList.add(`time-bg-${getBackgroundPeriod(now.getHours())}`);
    }

    function updateClock() {
        const now = new Date();
        updateBackground(now);

        liveTime.textContent = new Intl.DateTimeFormat(locale, {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false
        }).format(now);

        liveDate.textContent = new Intl.DateTimeFormat(locale, {
            weekday: 'long',
            day: '2-digit',
            month: 'long',
            year: 'numeric'
        }).format(now);
    }

    function updateTelemetryCard(card) {
        const element = document.querySelector(`[data-measurement-key="${card.key}"]`);

        if (!element) {
            return;
        }

        const label = element.querySelector('[data-field="label"]');
        const value = element.querySelector('[data-field="value"]');
        const description = element.querySelector('[data-field="description"]');

        if (label) {
            label.textContent = card.label;
        }

        if (value) {
            value.className = card.value_class;
            value.innerHTML = `${card.value_html}<span class="unit">${card.unit_html}</span>`;
        }

        if (description) {
            description.textContent = card.description;
        }
    }

    async function refreshTelemetry() {
        if (!telemetryUrl) {
            return;
        }

        try {
            /*
             * The timestamp query parameter prevents browser caching. The
             * server still applies its own telemetry cache to protect upstream
             * resources.
             */
            const separator = telemetryUrl.includes('?') ? '&' : '?';
            const response = await fetch(`${telemetryUrl}${separator}_=${Date.now()}`, {
                cache: 'no-store'
            });

            if (!response.ok) {
                return;
            }

            const payload = await response.json();

            if (!payload || !Array.isArray(payload.cards)) {
                return;
            }

            payload.cards.forEach(updateTelemetryCard);

            const measurementTime = document.querySelector('[data-field="measurement-time"]');
            const updatedAt = document.querySelector('[data-field="updated-at"]');

            if (measurementTime && payload.measurement_time) {
                measurementTime.textContent = payload.measurement_time;
            }

            if (updatedAt && payload.updated_at) {
                updatedAt.textContent = payload.updated_at;
            }
        } catch (error) {
            // Keep the last visible values if the JSON endpoint is temporarily unavailable.
        }
    }

    updateClock();
    setInterval(updateClock, 1000);
    setInterval(refreshTelemetry, 60000);
