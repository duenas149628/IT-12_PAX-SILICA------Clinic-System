<?php

// Use a writable session directory in isolated CLI environments.
session_save_path(sys_get_temp_dir());
require_once __DIR__ . '/../config/auth.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (($argv[1] ?? '') === 'csrf-reject') {
    $_POST['csrf_token'] = 'invalid-token';
    csrf_verify();
    exit(99);
}

function check(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$token = csrf_token();
check((bool) preg_match('/^[a-f0-9]{64}$/', $token), 'CSRF token is a 256-bit hex value');
check(str_contains(csrf_field(), h($token)), 'CSRF form helper emits the session token safely');
check(ini_get('session.use_strict_mode') === '1', 'strict session mode is enabled');
check(ini_get('session.use_only_cookies') === '1', 'session IDs are cookie-only');
$cookieParams = session_get_cookie_params();
check($cookieParams['httponly'] && ($cookieParams['samesite'] ?? '') === 'Lax', 'session cookies use HttpOnly and SameSite=Lax');
$_POST['csrf_token'] = $token;
csrf_verify();

$pipes = [];
$process = proc_open([PHP_BINARY, __FILE__, 'csrf-reject'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
check(is_resource($process), 'can launch isolated CSRF rejection check');
fclose($pipes[0]);
$childOutput = stream_get_contents($pipes[1]);
fclose($pipes[1]);
fclose($pipes[2]);
$childStatus = proc_close($process);
check($childStatus === 0 && str_contains($childOutput, 'Invalid or expired form token'), 'invalid CSRF tokens are rejected');

$projectRoot = dirname(__DIR__);
$phpFiles = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($projectRoot));
foreach ($phpFiles as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php' || str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'EXPO-GO-appdev' . DIRECTORY_SEPARATOR)) {
        continue;
    }
    if ($file->getPathname() === __FILE__) {
        continue;
    }
    $source = file_get_contents($file->getPathname());
    preg_match_all("/REQUEST_METHOD'\]\\s*===\\s*'POST'/", $source, $handlers);
    preg_match_all('/csrf_verify\\s*\\(\\s*\\)/', $source, $checks);
    preg_match_all("/<form\\b(?=[^>]*\\bmethod\\s*=\\s*[\"']?POST\\b)[^>]*>/is", $source, $forms);
    preg_match_all('/csrf_field\\s*\\(\\s*\\)/', $source, $tokens);
    if (basename($file->getPathname()) === 'auth.php') {
        continue;
    } elseif (basename($file->getPathname()) === 'logout.php') {
        check(count($checks[0]) === 1, 'logout verifies its CSRF token');
    } else {
        check(count($handlers[0]) === count($checks[0]), basename($file->getPathname()) . ' protects each POST handler');
    }
    check(count($forms[0]) <= count($tokens[0]), basename($file->getPathname()) . ' includes CSRF tokens in POST forms');
}

$authSource = file_get_contents($projectRoot . '/config/auth.php');
$databaseSource = file_get_contents($projectRoot . '/config/database.php');
check(str_contains($authSource, 'APP_CANONICAL_URL') && str_contains($authSource, 'Strict-Transport-Security'), 'production HTTPS and HSTS guards are configured');
check(str_contains($databaseSource, 'strtolower($username) === \'root\'') && str_contains($databaseSource, "getenv('DB_PASSWORD')"), 'production database credentials reject root and require an explicit password');

$_SERVER['REMOTE_ADDR'] = '198.51.100.23';
login_clear_failures();
for ($attempt = 0; $attempt < 5; $attempt++) {
    login_record_failure();
}
check(login_is_rate_limited(), 'login throttle blocks after five failures');
login_clear_failures();
check(!login_is_rate_limited(), 'login throttle clears after successful-auth reset');

audit_log('security.smoke_test', ['test' => true]);
$auditPath = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR
    . 'tooth-lounge-audit-' . hash('sha256', dirname(__DIR__)) . '.jsonl';
$auditLines = file($auditPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$lastEvent = json_decode((string) end($auditLines), true);
check(($lastEvent['event'] ?? null) === 'security.smoke_test', 'audit event is written as JSONL');
check(!array_key_exists('ip', $lastEvent ?? []), 'audit entry does not store the raw IP address');
@unlink($auditPath);

echo "Security smoke checks passed.\n";
