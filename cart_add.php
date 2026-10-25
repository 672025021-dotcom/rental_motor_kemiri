<?php

require_once __DIR__ . '/functions/cart.php';

require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    flash('notification', 'Permintaan tidak valid. Silakan tambahkan motor melalui katalog.', 'warning');
    redirect(BASE_URL . 'katalog.php');
}

$tokenInput = $_POST['csrf_token'] ?? '';
$token = is_string($tokenInput) ? $tokenInput : '';
if (
    !isset($_SESSION['cart_csrf_token'])
    || !hash_equals($_SESSION['cart_csrf_token'], $token)
) {
    flash('notification', 'Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.', 'danger');
    redirect(BASE_URL . 'katalog.php');
}

$motorcycleId = $_POST['motorcycle_id'] ?? null;
$startDateInput = $_POST['start_date'] ?? '';
$endDateInput = $_POST['end_date'] ?? '';
$pickupMethodInput = $_POST['pickup_method'] ?? 'ambil_sendiri';
$deliveryAddressInput = $_POST['delivery_address'] ?? '';
$useProfileAddress = isset($_POST['use_profile_address'])
    && is_string($_POST['use_profile_address'])
    && $_POST['use_profile_address'] === '1';
$startDate = is_string($startDateInput) ? $startDateInput : '';
$endDate = is_string($endDateInput) ? $endDateInput : '';
$pickupMethod = is_string($pickupMethodInput) ? $pickupMethodInput : '';
$deliveryAddress = is_string($deliveryAddressInput) ? $deliveryAddressInput : '';

try {
    if ($pickupMethod === 'diantar' && $useProfileAddress) {
        $deliveryAddress = get_user_delivery_address($pdo, (int) $_SESSION['user_id']);
        if (trim($deliveryAddress) === '') {
            throw new InvalidArgumentException('Alamat profil belum diisi. Perbarui profil atau masukkan alamat tujuan secara manual.');
        }
    }

    add_to_cart(
        $pdo,
        (int) $_SESSION['user_id'],
        $motorcycleId,
        $startDate,
        $endDate,
        $pickupMethod,
        $deliveryAddress
    );
    flash('notification', 'Motor berhasil ditambahkan ke keranjang.', 'success');
    redirect(BASE_URL . 'cart.php');
} catch (InvalidArgumentException $exception) {
    flash('notification', $exception->getMessage(), 'warning');
    redirect(BASE_URL . 'motor_detail.php?id=' . (int) $motorcycleId . '#form-keranjang');
} catch (PDOException $exception) {
    error_log('Add to cart database error: ' . $exception->getMessage());
    flash('notification', 'Motor gagal ditambahkan ke keranjang karena terjadi masalah. Silakan coba lagi.', 'danger');
    redirect(BASE_URL . 'motor_detail.php?id=' . (int) $motorcycleId . '#form-keranjang');
} catch (RuntimeException $exception) {
    flash('notification', $exception->getMessage(), 'warning');
    redirect(BASE_URL . 'motor_detail.php?id=' . (int) $motorcycleId . '#form-keranjang');
}
