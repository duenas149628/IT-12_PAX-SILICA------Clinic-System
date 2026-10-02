<?php

const STAFF_ISSUED_TEMPORARY_PASSWORD = '1234';

$appEnvironment = strtolower((string) (getenv('APP_ENV') ?: 'development'));
$isProduction = $appEnvironment === 'production';
$requestIsHttps = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';

if ($isProduction && PHP_SAPI !== 'cli') {
    $canonicalUrl = rtrim((string) getenv('APP_CANONICAL_URL'), '/');
    $canonicalParts = $canonicalUrl !== '' ? parse_url($canonicalUrl) : false;
    if (
        !is_array($canonicalParts)
        || strtolower((string) ($canonicalParts['scheme'] ?? '')) !== 'https'
        || empty($canonicalParts['host'])
        || isset($canonicalParts['user'])
        || isset($canonicalParts['pass'])
        || (isset($canonicalParts['path']) && $canonicalParts['path'] !== '' && $canonicalParts['path'] !== '/')
    ) {
        error_log('Production requires APP_CANONICAL_URL to be an HTTPS origin.');
        http_response_code(500);
        exit('Server configuration error.');
    }

    if (!$requestIsHttps) {
        $requestUri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        if ($requestUri === '' || $requestUri[0] !== '/' || str_starts_with($requestUri, '//')) {
            $requestUri = '/';
        }
        header('Location: ' . $canonicalUrl . $requestUri, true, 308);
        exit;
    }

    header('Strict-Transport-Security: max-age=31536000');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header("Permissions-Policy: camera=(), microphone=(), geolocation=()");
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https://images.unsplash.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; script-src 'self' 'unsafe-inline'; font-src 'self' data: https://cdnjs.cloudflare.com https://cdn.jsdelivr.net; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'; upgrade-insecure-requests");
    header('Cache-Control: private, no-store, max-age=0');
}

// Harden the session before it is started. HTTPS must be set by the TLS web
// server or trusted FastCGI configuration; arbitrary forwarded headers are ignored.
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $requestIsHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (PHP_SAPI !== 'cli') {
        session_start();
    }
}

if (PHP_SAPI !== 'cli' && !empty($_SESSION['must_change_password'])) {
    $currentScript = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (!in_array($currentScript, ['password_change.php', 'logout.php'], true)) {
        $currentDirectory = basename(dirname((string) ($_SERVER['SCRIPT_NAME'] ?? '')));
        $passwordChangePath = $currentDirectory === 'auth'
            ? 'password_change.php'
            : '../auth/password_change.php';
        header('Location: ' . $passwordChangePath);
        exit;
    }
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '">';
}

function csrf_verify(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(400);
        exit('Invalid or expired form token. Reload the page and try again.');
    }
}

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Read untrusted form/query values without allowing array input to cause TypeErrors. */
function request_string(array $source, string $key): string
{
    $value = $source[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function audit_log(string $event, array $details = []): void
{
    $entry = [
        'time_utc' => gmdate('c'),
        'event' => preg_replace('/[^a-z0-9_.-]/i', '_', $event),
        'user_id' => isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null,
        'ip_hash' => hash('sha256', (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown')),
        'details' => $details,
    ];
    $path = audit_log_path();
    $written = @file_put_contents($path, json_encode($entry, JSON_INVALID_UTF8_SUBSTITUTE) . PHP_EOL, FILE_APPEND | LOCK_EX);
    if ($written === false) {
        error_log('Unable to write Tooth Lounge security audit event: ' . $entry['event']);
    }
}

function audit_log_path(): string
{
    $configuredPath = getenv('AUDIT_LOG_PATH');
    return is_string($configuredPath) && $configuredPath !== ''
        ? $configuredPath
        : rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
            . 'tooth-lounge-audit-' . hash('sha256', dirname(__DIR__)) . '.jsonl';
}

function login_rate_file(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
        . 'tooth-lounge-login-' . hash('sha256', $ip) . '.json';
}

function login_rate_update(bool $recordFailure = false): array
{
    $path = login_rate_file();
    $handle = @fopen($path, 'c+');
    if (!$handle) {
        return [];
    }
    flock($handle, LOCK_EX);
    $raw = stream_get_contents($handle);
    $data = json_decode($raw ?: '[]', true);
    $attempts = is_array($data) ? $data : [];
    $attempts = array_values(array_filter($attempts, static fn($time) => is_int($time) && $time > time() - 900));
    if ($recordFailure) {
        $attempts[] = time();
    }
    ftruncate($handle, 0);
    rewind($handle);
    fwrite($handle, json_encode($attempts));
    fflush($handle);
    flock($handle, LOCK_UN);
    fclose($handle);
    return $attempts;
}

function login_is_rate_limited(): bool
{
    return count(login_rate_update()) >= 5;
}

function login_record_failure(): void
{
    login_rate_update(true);
}

function login_clear_failures(): void
{
    @unlink(login_rate_file());
}
