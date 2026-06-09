# Security Policy

This project is designed as a public environmental measurements display application with
server-side caching and a lightweight API for educational and informational displays.

## Supported Deployment

The recommended deployment model is:

- PHP served through Apache or another production web server;
- writable cache files stored inside `private/`;
- `private/` blocked from direct web access;
- real ThingsBoard configuration stored in `config.php`;
- `config.php` blocked from direct web access and excluded from Git;
- no telemetry cache, token cache or private configuration committed to GitHub.

## Secrets and Configuration

Do not commit:

- real ThingsBoard public IDs or device IDs if they are not intended for publication;
- `config.php`;
- generated public tokens;
- cache files from `private/`;
- server-specific credentials;
- local deployment notes containing secrets.

Use an example configuration file with placeholders when publishing the project.

## Recommended File Permissions

Public files should normally be readable by the web server but not writable by PHP.

The `private/` directory should be writable only by the PHP/web-server user because it stores cache
files.

Recommended Linux deployment pattern:

```bash
sudo chown -R ubuntu:ubuntu .
sudo chown -R www-data:www-data private
sudo find . -path ./private -prune -o -type d -exec chmod 755 {} \;
sudo find . -path ./private -prune -o -type f -exec chmod 644 {} \;
sudo find private -type d -exec chmod 750 {} \;
sudo find private -type f -exec chmod 640 {} \;
```

## Reporting Security Issues

If you discover a security issue, do not publish exploit details publicly.
Contact the project maintainer or the responsible organization privately with enough information to
reproduce and fix the issue.

## Scope

Security issues may include:

- exposure of tokens or private configuration;
- bypass of the `private/` directory protection;
- injection vulnerabilities;
- excessive API request patterns that may overload the station or dashboard server;
- incorrect headers that weaken browser security.
