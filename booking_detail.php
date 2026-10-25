<?php

require_once __DIR__ . '/functions/booking.php';

require_login();

$bookingIdInput = $_GET['id'] ?? null;
$bookingId = filter_var($bookingIdInput, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
if ($bookingId === false || $bookingId === null) {
    flash('notification', 'ID pesanan tidak valid.', 'warning');
    redirect(BASE_URL . 'booking_riwayat.php');
}

try {
    $booking = get_booking_detail_by_id($pdo, $bookingId, (int) $_SESSION['user_id']);
} catch (PDOException $exception) {
    error_log('Load booking detail database error: ' . $exception->getMessage());
    flash('notification', 'Detail pesanan gagal dimuat. Silakan coba kembali.', 'danger');
    redirect(BASE_URL . 'booking_riwayat.php');
}

if (!$booking) {
    flash('notification', 'Pesanan tidak ditemukan atau Anda tidak memiliki akses.', 'warning');
    redirect(BASE_URL . 'booking_riwayat.php');
}

try {
    $logs = get_booking_logs($pdo, $bookingId);
} catch (PDOException $exception) {
    error_log('Load booking status logs database error: ' . $exception->getMessage());
    flash('notification', 'Timeline status pesanan gagal dimuat. Silakan coba kembali.', 'danger');
    redirect(BASE_URL . 'booking_riwayat.php');
}

$page_title = 'Detail Booking ' . $booking['booking_code'];
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
$isOwner = (int) $booking['user_id'] === (int) $_SESSION['user_id'];
$canManageBooking = $isOwner;
$csrfToken = cart_csrf_token();

require __DIR__ . '/includes/header.php';

?>
<div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-4">
    <div>
        <a href="<?= e(BASE_URL . 'booking_riwayat.php') ?>" class="text-decoration-none">&larr; Kembali ke Riwayat</a>
        <h1 class="h2 mt-2 mb-1">Booking <?= e($booking['booking_code']) ?></h1>
        <?php $statusClass = $statusClasses[$booking['status']] ?? 'secondary'; ?>
        <?php $statusBadgeClass = isset($statusLabels[$booking['status']]) ? 'badge-status--' . $booking['status'] : ''; ?>
        <span class="badge badge-status <?= e($statusBadgeClass) ?> text-bg-<?= e($statusClass) ?>">
            <?= e($statusLabels[$booking['status']] ?? $booking['status']) ?>
        </span>
        <span class="small text-muted ms-2">Dibuat <?= e($booking['created_at']) ?></span>
    </div>
    <?php if ($canManageBooking && $booking['status'] === 'pending'): ?>
        <a class="btn btn-outline-primary" href="<?= e(BASE_URL . 'booking_edit.php?id=' . (int) $booking['id']) ?>">Ubah Jadwal</a>
    <?php endif; ?>
</div>

<div class="row g-4">
    <section class="col-lg-5" aria-labelledby="booking-summary-title">
        <div class="card booking-detail-card h-100">
            <div class="card-body">
                <h2 class="h5 card-title" id="booking-summary-title">Informasi Pesanan</h2>
                <dl class="row mb-0">
                    <dt class="col-sm-5">Kode Booking</dt>
                    <dd class="col-sm-7"><?= e($booking['booking_code']) ?></dd>

                    <dt class="col-sm-5">Tanggal Dibuat</dt>
                    <dd class="col-sm-7"><?= e($booking['created_at']) ?></dd>

                    <dt class="col-sm-5">Pengambilan</dt>
                    <dd class="col-sm-7"><?= e($pickupLabels[$booking['pickup_method']] ?? $booking['pickup_method']) ?></dd>

                    <?php if ($booking['pickup_method'] === 'diantar'): ?>
                        <dt class="col-sm-5">Alamat Pengantaran</dt>
                        <dd class="col-sm-7"><?= e($booking['delivery_address'] ?: '—') ?></dd>
                    <?php endif; ?>

                    <dt class="col-sm-5">Catatan</dt>
                    <dd class="col-sm-7"><?= e($booking['notes'] ?: '—') ?></dd>

                    <dt class="col-sm-5">Total Biaya</dt>
                    <dd class="col-sm-7 fw-semibold"><?= e(format_rupiah($booking['total_price'])) ?></dd>

                    <?php if ($booking['status'] === 'dibatalkan'): ?>
                        <dt class="col-sm-5">Alasan Batal</dt>
                        <dd class="col-sm-7"><?= e($booking['cancel_reason'] ?: '—') ?></dd>
                    <?php endif; ?>
                </dl>
            </div>
        </div>
    </section>

    <section class="col-lg-7" aria-labelledby="booking-items-title">
        <div class="card booking-detail-card">
            <div class="card-body">
                <h2 class="h5 card-title" id="booking-items-title">Rincian Unit Motor</h2>
                <div class="table-responsive">
                    <table class="table table-striped align-middle mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Motor</th>
                                <th scope="col">Tanggal Sewa</th>
                                <th scope="col">Durasi</th>
                                <th scope="col">Harga/Hari</th>
                                <th scope="col">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($booking['items'] as $item): ?>
                                <tr>
                                    <td>
                                        <strong><?= e($item['brand'] . ' ' . $item['model']) ?></strong>
                                        <div class="small text-muted"><?= e($item['plate_number']) ?></div>
                                    </td>
                                    <td><?= e($item['start_date']) ?><br>&ndash; <?= e($item['end_date']) ?></td>
                                    <td><?= e($item['total_days']) ?> hari</td>
                                    <td><?= e(format_rupiah($item['price_per_day'])) ?></td>
                                    <td><?= e(format_rupiah($item['subtotal'])) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (!$booking['items']): ?>
                    <p class="text-muted mb-0">Rincian unit tidak tersedia.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="col-md-5" aria-labelledby="booking-documents-title">
        <div class="card booking-detail-card h-100">
            <div class="card-body">
                <h2 class="h5 card-title" id="booking-documents-title">Dokumen Identitas</h2>
                <p><a href="<?= e(BASE_URL . 'dokumen.php?file=' . rawurlencode($booking['ktp_file']) . '&type=ktp') ?>" target="_blank" rel="noopener noreferrer">Lihat dokumen KTP</a></p>
                <p class="mb-0"><a href="<?= e(BASE_URL . 'dokumen.php?file=' . rawurlencode($booking['sim_file']) . '&type=sim') ?>" target="_blank" rel="noopener noreferrer">Lihat dokumen SIM</a></p>
            </div>
        </div>
    </section>

    <section class="col-md-7" aria-labelledby="booking-timeline-title">
        <div class="card booking-detail-card">
            <div class="card-body">
                <h2 class="h5 card-title" id="booking-timeline-title">Timeline Status</h2>
                <?php if ($logs): ?>
                    <ol class="booking-timeline">
                        <?php foreach ($logs as $log): ?>
                            <li class="booking-timeline__item">
                                <div class="d-flex justify-content-between gap-3">
                                    <strong>
                                        <?= $log['old_status'] !== null ? e($statusLabels[$log['old_status']] ?? $log['old_status']) . ' &rarr; ' : '' ?>
                                        <?= e($statusLabels[$log['new_status']] ?? $log['new_status']) ?>
                                    </strong>
                                    <time class="booking-timeline__meta text-nowrap"><?= e($log['created_at']) ?></time>
                                </div>
                                <?php if ($log['note']): ?>
                                    <p class="mb-0 mt-1"><?= e($log['note']) ?></p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php else: ?>
                    <p class="text-muted mb-0">Belum ada catatan perubahan status.</p>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <?php if ($canManageBooking && $booking['status'] === 'pending'): ?>
        <section class="col-12" aria-labelledby="cancel-booking-title">
            <div class="card border-danger">
                <div class="card-body">
                    <h2 class="h5 text-danger" id="cancel-booking-title">Batalkan Pesanan</h2>
                    <p class="text-muted">Pesanan berstatus pending dapat dibatalkan. Tindakan ini tidak dapat dibatalkan kembali.</p>
                    <form method="post" action="<?= e(BASE_URL . 'booking_batal.php') ?>" class="row g-3 align-items-end" onsubmit="return confirm('Yakin ingin membatalkan pesanan ini?');">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="booking_id" value="<?= e($booking['id']) ?>">
                        <div class="col-md-8">
                            <label for="cancel_reason" class="form-label">Alasan Pembatalan</label>
                            <input type="text" class="form-control" id="cancel_reason" name="cancel_reason" maxlength="255" required>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-danger">Batalkan Pesanan</button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
