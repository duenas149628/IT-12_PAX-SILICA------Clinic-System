# Production deployment security

## Application configuration

Run PHP 8.1+ with PDO MySQL and keep the project directory owned by the deploy operator. The PHP worker should have read access to application files and write access only to its session directory and the configured audit-log file. Keep database credentials outside the repository and web root.

Set these environment variables in the PHP-FPM pool, Apache service environment, or deployment secret manager. PHP-FPM commonly clears the worker environment, so explicitly pass the required values in the pool config; see [php-fpm-pool.env.example](/C:/laragon/www/tooth-lounge/deploy/php-fpm-pool.env.example). Keep the real DB password in a secret manager or protected service configuration, not this example file:

```text
APP_ENV=production
APP_CANONICAL_URL=https://clinic.example.com
DB_HOST=127.0.0.1
DB_NAME=tooth-lounge
DB_USER=tooth_lounge_app
DB_PASSWORD=<unique generated secret>
AUDIT_LOG_PATH=/var/log/tooth-lounge/security.jsonl
```

The app rejects production startup if the HTTPS canonical origin or database settings are missing, if the database user is `root`, or if the database password is empty. Grant the application database account only the SQL privileges it needs. Set the audit path to a persistent directory outside the web root and grant the PHP worker append/write access to that file only. Rotate and retain audit logs under the clinic policy.

`APP_CANONICAL_URL` must be the HTTPS origin only, with no path, query, or credentials. The app redirects HTTP requests to this configured host and sets the Secure session-cookie flag only when the web server marks the request as HTTPS. Never trust a client-supplied `X-Forwarded-Proto` header. If TLS terminates at a reverse proxy, configure the proxy-to-PHP connection so PHP receives `HTTPS=on`, and restrict the origin server to requests from that proxy.

## Nginx

Use [nginx-tooth-lounge.conf](/C:/laragon/www/tooth-lounge/deploy/nginx-tooth-lounge.conf) as a starting point. Replace the example hostname, certificate paths, and PHP-FPM socket. Keep the application root outside any shared upload directory, and keep backups outside the web root. The sample blocks direct web requests to configuration, database migrations, scripts, docs, tests, source-control metadata, dotfiles, and backup-like files.

## Apache

Enable `mod_rewrite` and `mod_authz_core`, then use the repository [`.htaccess`](/C:/laragon/www/tooth-lounge/.htaccess) protections or equivalent virtual-host rules. Configure a port 80 virtual host to redirect to the fixed HTTPS hostname, and a TLS virtual host with TLS 1.2 or newer and a valid certificate. Set `AllowOverride` to permit the included rules, or move those rules into the virtual-host configuration. For PHP-FPM behind a proxy, set `HTTPS=on` only on the trusted TLS path.

Do not expose `.git`, `config/`, `database/`, `scripts/`, `docs/`, `tests/`, or `EXPO-GO-appdev/` over HTTP. Deny directory listings. Keep PHP errors out of responses (`display_errors=Off`) and send them to protected server logs. Set `expose_php=Off`, use a supported patched PHP/MySQL release, and restrict inbound database traffic to the application host.

## Release checks

1. Deploy a supported PHP and MySQL build and verify the required PDO MySQL extension is enabled.
2. Install a valid TLS certificate, redirect all HTTP traffic to HTTPS, and confirm PHP sees `HTTPS=on` on secure requests.
3. Configure production environment variables through the service manager or secret manager; never commit them.
4. Confirm `/config/`, `/.git/`, `/docs/`, `/tests/`, `/.env`, and backup-file URLs return a denial or 404, while the login and required PHP pages load.
5. Run `php tests/security_smoke.php`, then perform the database-backed login, password-change, backup restore, and appointment workflow checks in a staging environment.
6. Verify the session cookie has `Secure`, `HttpOnly`, and `SameSite=Lax`; verify HSTS and the configured security headers on HTTPS responses.
