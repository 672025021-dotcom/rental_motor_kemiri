<?php

require_once __DIR__ . '/functions/booking.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('notification', 'Permintaan tidak valid. Batalkan pesanan melalui halaman detail.', 'warning');
    redirect(BASE_URL . 'booking_riwayat.php');
}

$tokenInput = $_POST['csrf_token'] ?? '';
$token = is_string($tokenInput) ? $tokenInput : '';
if (!isset($_SESSION['cart_csrf_token']) || !hash_equals($_SESSION['cart_csrf_token'], $token)) {
    flash('notification', 'Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.', 'danger');
    redirect(BASE_URL . 'booking_riwayat.php');
}

$bookingIdInput = $_POST['booking_id'] ?? null;
$bookingId = filter_var($bookingIdInput, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
$cancelReasonInput = $_POST['cancel_reason'] ?? '';
$cancelReason = is_string($cancelReasonInput) ? trim($cancelReasonInput) : '';

if ($bookingId === false || $bookingId === null) {
    flash('notification', 'ID pesanan tidak valid.', 'warning');
    redirect(BASE_URL . 'booking_riwayat.php');
}

try {
    cancel_booking($pdo, $bookingId, (int) $_SESSION['user_id'], $cancelReason);
    flash('notification', 'Pesanan berhasil dibatalkan.', 'success');
} catch (InvalidArgumentException $exception) {
    flash('notification', $exception->getMessage(), 'warning');
} catch (RuntimeException $exception) {
    flash('notification', $exception->getMessage(), 'warning');
} catch (PDOException $exception) {
    error_log('Cancel booking database error: ' . $exception->getMessage());
    flash('notification', 'Pesanan gagal dibatalkan karena terjadi masalah. Silakan coba lagi.', 'danger');
}

redirect(BASE_URL . 'booking_detail.php?id=' . $bookingId);
