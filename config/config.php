<?php

require_once __DIR__ . '/../includes/env.php';

load_env(__DIR__ . '/../.env');

$connection = env('DB_CONNECTION', 'mysql');
if ($connection !== 'mysql') {
    throw new RuntimeException('Unsupported database connection: ' . $connection);
}

$host = env('DB_HOST', '127.0.0.1');
$port = filter_var(env('DB_PORT', '3306'), FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1, 'max_range' => 65535],
]);
$dbname = env('DB_DATABASE', 'rent_motor_kemiri');
$username = env('DB_USERNAME', 'root');
$password = env('DB_PASSWORD', '');

if ($port === false) {
    throw new RuntimeException('DB_PORT must be a valid TCP port between 1 and 65535.');
}

$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
$pdo = new PDO($dsn, $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

if (!defined('BASE_URL')) {
    define('BASE_URL', env('BASE_URL', 'http://localhost/rental_motor_kemiri/'));
}

if (!defined('UPLOAD_PATH')) {
    define('UPLOAD_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'motor' . DIRECTORY_SEPARATOR);
}

if (!defined('STORAGE_PATH')) {
    define('STORAGE_PATH', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'dokumen' . DIRECTORY_SEPARATOR);
}
