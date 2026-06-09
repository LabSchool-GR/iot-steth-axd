# Device API

Lightweight endpoints for ESP32 boards and other small devices.

Base URL on production:

```text
https://labschool.gr/iot-steth-axd/api/v1/
```

## Latest values

```text
GET latest.php
```

Returns all station measurements with units, availability and timestamps.

```json
{
  "ok": true,
  "station": "alexandroupoli-center",
  "timezone": "Europe/Athens",
  "cache_ttl": 60,
  "values": {
    "temperature": {
      "value": 28.5,
      "unit": "C",
      "available": true,
      "timestamp": 1780840800000,
      "time": "2026-06-07T13:00:00+03:00"
    }
  }
}
```

For a smaller payload:

```text
GET latest.php?flat=1
```

Example flat response:

```json
{"temperature":28.5,"humidity":47.2,"pressure":1013.1,"co":0.58,"co2":420,"pm25":18,"pm10":22}
```

## Single value

```text
GET value.php?key=temperature
```

Allowed keys:

```text
temperature, humidity, pressure, co, co2, pm25, pm10
```

Plain text output for very small clients:

```text
GET value.php?key=temperature&format=text
```

The endpoints reuse the same server-side telemetry cache as the web page.
They do not expose arbitrary ThingsBoard keys.
