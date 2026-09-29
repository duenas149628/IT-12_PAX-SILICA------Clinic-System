<?php

$appEnvironment = strtolower((string) (getenv('APP_ENV') ?: 'development'));
$host = getenv('DB_HOST');
$dbname = getenv('DB_NAME');
$username = getenv('DB_USER');
$password = getenv('DB_PASSWORD');

if ($appEnvironment === 'production') {
    if (
        !is_string($host) || trim($host) === ''
        || !is_string($dbname) || trim($dbname) === ''
        || !is_string($username) || trim($username) === ''
        || !is_string($password) || $password === ''
        || strtolower($username) === 'root'
    ) {
        error_log('Production database configuration is incomplete or uses a prohibited account.');
        http_response_code(500);
        die('Server configuration error.');
    }
} else {
    $host = is_string($host) && $host !== '' ? $host : '127.0.0.1';
    $dbname = is_string($dbname) && $dbname !== '' ? $dbname : 'tooth-lounge';
    $username = is_string($username) && $username !== '' ? $username : 'root';
    $password = is_string($password) ? $password : '';
}

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

} catch (PDOException $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('A server error occurred. Please try again later.');
}
