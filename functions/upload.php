<?php

require_once __DIR__ . '/../includes/auth.php';

function upload_document($file, $destination_dir, $documentType = 'document')
{
    if (!is_array($file) || !isset($file['error'], $file['tmp_name'], $file['name'], $file['size'])) {
        throw new InvalidArgumentException('Berkas dokumen tidak diterima dengan benar.');
    }

    if (!is_int($file['error'])) {
        throw new InvalidArgumentException('Status unggahan dokumen tidak valid.');
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'Ukuran dokumen melebihi batas server.',
            UPLOAD_ERR_FORM_SIZE => 'Ukuran dokumen melebihi batas yang diizinkan.',
            UPLOAD_ERR_PARTIAL => 'Dokumen hanya terunggah sebagian.',
            UPLOAD_ERR_NO_FILE => 'Dokumen wajib diunggah.',
            UPLOAD_ERR_NO_TMP_DIR => 'Folder sementara untuk unggahan tidak tersedia.',
            UPLOAD_ERR_CANT_WRITE => 'Dokumen gagal disimpan oleh server.',
            UPLOAD_ERR_EXTENSION => 'Unggahan dokumen dihentikan oleh server.',
        ];
        throw new RuntimeException(
            isset($uploadErrors[$file['error']]) ? $uploadErrors[$file['error']] : 'Dokumen gagal diunggah.'
        );
    }

    if (!is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new InvalidArgumentException('Berkas unggahan tidak valid.');
    }

    if (!is_int($file['size']) || $file['size'] < 1 || $file['size'] > 2 * 1024 * 1024) {
        throw new InvalidArgumentException('Ukuran dokumen harus lebih dari 0 dan maksimal 2 MB.');
    }

    if (!is_string($file['name'])) {
        throw new InvalidArgumentException('Nama berkas dokumen tidak valid.');
    }

    $actualSize = filesize($file['tmp_name']);
    if ($actualSize === false || $actualSize < 1 || $actualSize > 2 * 1024 * 1024 || $actualSize !== $file['size']) {
        throw new InvalidArgumentException('Ukuran dokumen tidak valid atau melebihi 2 MB.');
    }

    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedMimeTypes = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'pdf' => ['application/pdf'],
    ];
    if (!isset($allowedMimeTypes[$extension])) {
        throw new InvalidArgumentException('Format dokumen harus JPG, JPEG, PNG, atau PDF.');
    }

    if (!is_string($documentType) || !preg_match('/^(ktp|sim|document)$/', $documentType)) {
        throw new InvalidArgumentException('Jenis dokumen tidak valid.');
    }

    if (!class_exists('finfo')) {
        throw new RuntimeException('Validasi tipe dokumen tidak tersedia pada server.');
    }

    $fileInfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $fileInfo->file($file['tmp_name']);
    if (!in_array($mimeType, $allowedMimeTypes[$extension], true)) {
        throw new InvalidArgumentException('Isi berkas tidak sesuai dengan format dokumen yang dipilih.');
    }

    if ($extension !== 'pdf' && @getimagesize($file['tmp_name']) === false) {
        throw new InvalidArgumentException('Berkas gambar tidak valid.');
    }

    $storageRoot = realpath(STORAGE_PATH);
    $destinationPath = realpath($destination_dir);
    if ($storageRoot === false || $destinationPath === false || $destinationPath !== $storageRoot) {
        throw new RuntimeException('Direktori penyimpanan dokumen privat tidak tersedia atau tidak valid.');
    }

    $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    if ($userId < 1) {
        throw new RuntimeException('Sesi pengguna tidak valid untuk mengunggah dokumen.');
    }

    try {
        $filename = $documentType . '_' . $userId . '_' . date('YmdHis') . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
    } catch (Throwable $exception) {
        throw new RuntimeException('Nama berkas dokumen aman tidak dapat dibuat.', 0, $exception);
    }

    $targetPath = $destinationPath . DIRECTORY_SEPARATOR . $filename;
    if (file_exists($targetPath) || !move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Dokumen gagal disimpan pada penyimpanan privat.');
    }

    return $filename;
}
