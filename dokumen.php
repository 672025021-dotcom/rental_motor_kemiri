<?php

require_once __DIR__ . '/includes/auth.php';

require_login();

$fileInput = $_GET['file'] ?? '';
$typeInput = $_GET['type'] ?? '';
$filename = is_string($fileInput) ? basename($fileInput) : '';
$documentType = is_string($typeInput) ? strtolower($typeInput) : '';

if (
    !in_array($documentType, ['ktp', 'sim'], true)
    || $filename === ''
    || $filename !== $fileInput
    || !preg_match('/^(ktp|sim)_\d+_\d{14}_[a-f0-9]{16}\.(jpg|jpeg|png|pdf)$/', $filename)
    || strpos($filename, $documentType . '_') !== 0
) {
    http_response_code(404);
    exit('Dokumen tidak ditemukan.');
}

$fileColumn = $documentType === 'ktp' ? 'ktp_file' : 'sim_file';
$isAdmin = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';
$sql = 'SELECT id FROM bookings WHERE ' . $fileColumn . ' = :filename';
$parameters = [':filename' => $filename];
if (!$isAdmin) {
    $sql .= ' AND user_id = :user_id';
    $parameters[':user_id'] = (int) $_SESSION['user_id'];
}
$sql .= ' LIMIT 1';

try {
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);
    if (!$statement->fetchColumn()) {
        http_response_code(404);
        exit('Dokumen tidak ditemukan.');
    }
} catch (PDOException $exception) {
    error_log('Private document authorization database error: ' . $exception->getMessage());
    http_response_code(500);
    exit('Dokumen sementara tidak dapat diakses.');
}

$storageRoot = realpath(STORAGE_PATH);
$documentPath = $storageRoot === false ? false : realpath($storageRoot . DIRECTORY_SEPARATOR . $filename);
if (
    $storageRoot === false
    || $documentPath === false
    || dirname($documentPath) !== $storageRoot
    || !is_file($documentPath)
    || !is_readable($documentPath)
) {
    http_response_code(404);
    exit('Dokumen tidak ditemukan.');
}

if (!class_exists('finfo')) {
    error_log('Private document MIME validation is unavailable: fileinfo extension missing.');
    http_response_code(500);
    exit('Dokumen sementara tidak dapat diakses.');
}

$mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($documentPath);
$allowedMimeTypes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'pdf' => 'application/pdf',
];
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (!isset($allowedMimeTypes[$extension]) || $mimeType !== $allowedMimeTypes[$extension]) {
    error_log('Private document MIME mismatch for booking document.');
    http_response_code(404);
    exit('Dokumen tidak ditemukan.');
}

header('Content-Type: ' . $mimeType);
header('Content-Length: ' . (string) filesize($documentPath));
header('Content-Disposition: inline; filename="' . $filename . '"');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'none'; sandbox");
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Robots-Tag: noindex, nofollow');

if (readfile($documentPath) === false) {
    error_log('Unable to stream private booking document.');
    http_response_code(500);
}
