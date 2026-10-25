<?php

require_once __DIR__ . '/functions/booking.php';

require_login();

$page_title = 'Riwayat Booking';
$bookings = [];
$loadError = false;
$pickupLabels = [
    'ambil_sendiri' => 'Ambil sendiri',
    'diantar' => 'Diantar',
];
$statusLabels = [
    'pending' => 'Pending',
    'dikonfirmasi' => 'Dikonfirmasi',
    'berjalan' => 'Berjalan',
    'selesai' => 'Selesai',
    'dibatalkan' => 'Dibatalkan',
];
$statusClasses = [
    'pending' => 'warning',
    'dikonfirmasi' => 'info',
    'berjalan' => 'primary',
    'selesai' => 'success',
    'dibatalkan' => 'secondary',
];
$paymentLabels = [
    'belum_bayar' => 'Belum Bayar',
    'lunas' => 'Lunas',
    'dikembalikan' => 'Dikembalikan',
];

try {
    $bookings = get_user_bookings($pdo, (int) $_SESSION['user_id']);
} catch (PDOException $exception) {
    error_log('Load booking history database error: ' . $exception->getMessage());
    $loadError = true;
}

require __DIR__ . '/includes/header.php';

?>
<div class="mb-4">
    <span class="section-eyebrow">Pesanan Anda</span>
    <h1 class="h2 mt-2 mb-1">Riwayat Pesanan</h1>
    <p class="text-muted mb-0">Pantau status booking dan lihat rincian transaksi Anda.</p>
</div>

<?php if ($loadError): ?>
    <div class="alert alert-danger" role="alert">
        Riwayat pesanan belum dapat dimuat. Silakan coba kembali nanti.
    </div>
<?php elseif (!$bookings): ?>
    <div class="empty-state">
        <div class="empty-state__icon" aria-hidden="true">▤</div>
        <h2 class="h4">Belum ada pesanan</h2>
        <p class="text-muted">Pesanan yang Anda buat akan muncul di halaman ini.</p>
        <a class="btn btn-primary" href="<?= e(BASE_URL . 'katalog.php') ?>">Jelajahi Katalog</a>
    </div>
<?php else: ?>
    <div class="card">
      <div class="card-body p-0">
        <div class="table-responsive border-0">
        <table class="table table-striped table-hover align-middle mb-0">
            <thead class="table-light">
                <tr>
                    <th scope="col">Kode Booking</th>
                    <th scope="col">Tanggal Transaksi</th>
                    <th scope="col">Pengambilan</th>
                    <th scope="col">Total Biaya</th>
                    <th scope="col">Status Pesanan</th>
                    <th scope="col">Pembayaran</th>
                    <th scope="col"><span class="visually-hidden">Aksi</span></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bookings as $booking): ?>
                    <tr>
                        <td class="fw-semibold"><?= e($booking['booking_code']) ?></td>
                        <td><?= e($booking['created_at']) ?></td>
                        <td><?= e($pickupLabels[$booking['pickup_method']] ?? $booking['pickup_method']) ?></td>
                        <td><?= e(format_rupiah($booking['total_price'])) ?></td>
                        <td>
                            <?php $statusClass = $statusClasses[$booking['status']] ?? 'secondary'; ?>
                            <?php $statusBadgeClass = isset($statusLabels[$booking['status']]) ? 'badge-status--' . $booking['status'] : ''; ?>
                            <span class="badge badge-status <?= e($statusBadgeClass) ?> text-bg-<?= e($statusClass) ?>">
                                <?= e($statusLabels[$booking['status']] ?? $booking['status']) ?>
                            </span>
                        </td>
                        <td><?= e($paymentLabels[$booking['payment_status']] ?? $booking['payment_status']) ?></td>
                        <td>
                            <a class="btn btn-sm btn-outline-primary" href="<?= e(BASE_URL . 'booking_detail.php?id=' . (int) $booking['id']) ?>">
                                Lihat Detail
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        </div>
      </div>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
