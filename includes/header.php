<?php

require_once __DIR__ . '/auth.php';

$pageTitle = isset($page_title) && $page_title !== '' ? $page_title : 'Rental Motor Kemiri';
$notification = flash('notification');
$notificationTypes = [
    'success' => 'success',
    'error' => 'danger',
    'danger' => 'danger',
    'warning' => 'warning',
    'info' => 'info',
];
$notificationType = is_array($notification) && isset($notificationTypes[$notification['type']])
    ? $notificationTypes[$notification['type']]
    : 'info';
$cartCount = 0;
$headerUser = current_user();

if (is_logged_in()) {
    try {
        $cartCountStatement = $pdo->prepare(
            'SELECT COUNT(*) FROM cart_items WHERE user_id = :user_id'
        );
        $cartCountStatement->execute([
            ':user_id' => (int) $_SESSION['user_id'],
        ]);
        $cartCount = (int) $cartCountStatement->fetchColumn();
    } catch (PDOException $exception) {
        error_log('Load navbar cart count database error: ' . $exception->getMessage());
    }
}

?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="<?= e(BASE_URL . 'assets/css/style.css') ?>" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light site-navbar sticky-top">
    <div class="container">
        <a class="navbar-brand site-brand" href="<?= e(BASE_URL) ?>">
            <span class="site-brand__mark" aria-hidden="true">R</span>
            <span>Rental Motor <strong>Kemiri</strong></span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNavbar" aria-controls="mainNavbar" aria-expanded="false" aria-label="Buka navigasi">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="mainNavbar">
            <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-1">
                <li class="nav-item">
                    <a class="nav-link" href="<?= e(BASE_URL . 'katalog.php') ?>">Katalog</a>
                </li>
                <?php if (is_logged_in()): ?>
                    <li class="nav-item">
                        <a class="nav-link nav-cart-link" href="<?= e(BASE_URL . 'cart.php') ?>">
                            Keranjang
                            <?php if ($cartCount > 0): ?>
                                <span class="nav-cart-count"><?= e($cartCount) ?><span class="visually-hidden"> item</span></span>
                            <?php endif; ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(BASE_URL . 'booking_riwayat.php') ?>">Riwayat Booking</a>
                    </li>
                    <li class="nav-item dropdown ms-lg-2">
                        <button class="btn profile-menu-toggle dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="profile-avatar" aria-hidden="true">
                                <?= e(isset($headerUser['full_name']) ? strtoupper(substr($headerUser['full_name'], 0, 1)) : 'U') ?>
                            </span>
                            <span class="profile-menu-name"><?= e($headerUser['full_name'] ?? 'Akun Saya') ?></span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-lg-2">
                            <li><span class="dropdown-item-text small text-muted"><?= e($headerUser['email'] ?? '') ?></span></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?= e(BASE_URL . 'profil.php') ?>">Profil Saya</a></li>
                            <li><a class="dropdown-item" href="<?= e(BASE_URL . 'booking_riwayat.php') ?>">Riwayat Booking</a></li>
                            <li><a class="dropdown-item text-danger" href="<?= e(BASE_URL . 'logout.php') ?>">Keluar</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="<?= e(BASE_URL . 'login.php') ?>">Login</a>
                    </li>
                    <li class="nav-item nav-register-item">
                        <a class="btn btn-primary nav-register-link" href="<?= e(BASE_URL . 'register.php') ?>">Daftar</a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>
<main class="container py-4">
    <?php if (is_array($notification) && isset($notification['message'])): ?>
        <div class="alert alert-<?= e($notificationType) ?> alert-dismissible fade show" role="alert">
            <?= e($notification['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Tutup"></button>
        </div>
    <?php endif; ?>
