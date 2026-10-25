<?php

require_once __DIR__ . '/functions/cart.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('notification', 'Permintaan tidak valid. Hapus item melalui halaman keranjang.', 'warning');
    redirect(BASE_URL . 'cart.php');
}

$tokenInput = $_POST['csrf_token'] ?? '';
$token = is_string($tokenInput) ? $tokenInput : '';
if (
    !isset($_SESSION['cart_csrf_token'])
    || !hash_equals($_SESSION['cart_csrf_token'], $token)
) {
    flash('notification', 'Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.', 'danger');
    redirect(BASE_URL . 'cart.php');
}

$cartIdInput = $_POST['cart_id'] ?? null;
$cartId = filter_var($cartIdInput, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if ($cartId === false || $cartId === null) {
    flash('notification', 'Item keranjang tidak valid.', 'warning');
    redirect(BASE_URL . 'cart.php');
}

try {
    if (delete_cart_item($pdo, $cartId, (int) $_SESSION['user_id'])) {
        flash('notification', 'Item berhasil dihapus dari keranjang.', 'success');
    } else {
        flash('notification', 'Item tidak ditemukan di keranjang Anda.', 'warning');
    }
} catch (PDOException $exception) {
    error_log('Delete cart item database error: ' . $exception->getMessage());
    flash('notification', 'Item keranjang gagal dihapus. Silakan coba lagi.', 'danger');
}

redirect(BASE_URL . 'cart.php');
