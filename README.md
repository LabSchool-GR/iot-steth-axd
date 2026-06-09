# Environmental Station Measurements Display Application

> Ελληνικά: Εφαρμογή απεικόνισης, προβολής και τεκμηρίωσης τιμών που αντλούνται
> από Περιβαλλοντικό Σταθμό του Συλλόγου Τεχνολογίας Θράκης.

This repository contains a PHP-based web application for displaying and
documenting environmental measurement values retrieved from a specific
Technology Club of Thrace Environmental Station.

The application is not the environmental station itself. It is a public-facing
display layer for presenting the station's current values in a clear,
accessible and respectful way.

## Live Application

- Application: https://labschool.gr/iot-steth-axd/
- Documentation: GitHub Pages from the `/docs` folder
- Data source: https://steth.gr/env

## Main Features

- Responsive measurements display for desktop, tablet and mobile screens.
- TV Mode for televisions, waiting rooms, schools and public displays.
- Greek default interface with English language option.
- Live date and time using the Europe/Athens timezone.
- Measurement descriptions and units for educational use.
- Server-side ThingsBoard token and telemetry caching.
- Lightweight JSON/plain-text API for ESP32 and other microcontrollers.
- Required visible attribution to the measurement source.

## Screenshots

Documentation screenshots are stored in:

```text
docs/assets/images/
```

The GitHub Pages documentation uses:

- `iot-steth-axd.png`
- `iot-steth-axd-tv-mode.png`

## Documentation

The documentation site is inside the `docs/` folder.

Recommended GitHub Pages setting:

```text
Source: Deploy from a branch
Branch: main
Folder: /docs
```

Documentation pages:

- Greek default page: `docs/index.html`
- English page: `docs/en.html`

## Requirements

- PHP 8.1 or newer recommended.
- PHP cURL extension.
- Apache or another production web server.
- A writable `private/` directory for server-side cache files.

The PHP `intl` extension is optional. If it is not available, the application
uses a built-in date formatting fallback.

## Configuration

Real deployment settings are stored in `config.php`.

Do not commit `config.php` to a public repository.

To configure a deployment:

```bash
cp config.example.php config.php
```

Then edit `config.php` and set:

- ThingsBoard base URL;
- public dashboard ID;
- device ID;
- telemetry keys;
- canonical site URL.

The public repository should include only `config.example.php`.

## Local Use

Clone or download the repository, create `config.php`, then run the project
through a local PHP server or a local environment such as XAMPP.

Example PHP development server:

```bash
php -S 127.0.0.1:8088 -t .
```

Then open:

```text
http://127.0.0.1:8088/
```

## Server Deployment

Recommended Linux permissions:

```bash
sudo chown -R ubuntu:ubuntu .
sudo chown -R www-data:www-data private
sudo find . -path ./private -prune -o -type d -exec chmod 755 {} \;
sudo find . -path ./private -prune -o -type f -exec chmod 644 {} \;
sudo find private -type d -exec chmod 750 {} \;
sudo find private -type f -exec chmod 640 {} \;
```

Do not upload or commit generated private cache files:

```text
private/*.json
```

## Lightweight API

The application includes a small device-friendly API.

All values:

```text
GET /api/v1/latest.php
```

Compact JSON:

```text
GET /api/v1/latest.php?flat=1
```

Single value:

```text
GET /api/v1/value.php?key=temperature
```

Plain text value:

```text
GET /api/v1/value.php?key=temperature&format=text
```

Allowed public keys:

```text
temperature, humidity, pressure, co, co2, pm25, pm10
```

Clients should avoid unnecessary request rates. A normal educational display or
microcontroller project should request values no more than once per minute.

## Repository Safety

The repository is prepared so that deployment-specific and generated files are
not published.

Important ignored files:

```text
config.php
private/*.json
private/*.log
private/*.tmp
assets/sea-background.png
assets/sea-morning.png
assets/sea-noon.png
assets/sea-afternoon.png
assets/sea-night.png
```

Before publishing, verify that no real ThingsBoard IDs, tokens or generated
cache files have been committed.

## Attribution and Use Terms

The station measurement values are provided by:

```text
Technology Club of Thrace Environmental Station
https://steth.gr/env
```

Public displays that show station measurement values must keep:

- the Technology Club of Thrace logo;
- a clear measurement source label;
- a link or reference to https://steth.gr/env.

The source attribution must remain visible in the regular display, TV mode,
kiosk mode or any similar public display.

## License

This project is published under a custom Educational Source-Available License.
It is intended for educational, public-interest and community information use.

Read the full terms:

- `LICENSE.md`
- `DATA_TERMS.md`
- `NOTICE.md`
- `ATTRIBUTION.md`
- `TRADEMARK.md`
- `SECURITY.md`

## Code Curation and Educational Adaptation

```text
Dimitrios Kanatas
https://labschool.gr
https://labschool.mysch.gr
```
