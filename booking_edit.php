<?php

require_once __DIR__ . '/functions/booking.php';

require_login();

$bookingIdInput = $_GET['id'] ?? $_POST['booking_id'] ?? null;
$bookingId = filter_var($bookingIdInput, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
if ($bookingId === false || $bookingId === null) {
    flash('notification', 'ID pesanan tidak valid.', 'warning');
    redirect(BASE_URL . 'booking_riwayat.php');
}

try {
    $detail = get_booking_detail_by_id($pdo, $bookingId, (int) $_SESSION['user_id']);
} catch (PDOException $exception) {
    error_log('Load booking for schedule edit database error: ' . $exception->getMessage());
    flash('notification', 'Pesanan gagal dimuat. Silakan coba kembali.', 'danger');
    redirect(BASE_URL . 'booking_riwayat.php');
}

if (!$detail) {
    flash('notification', 'Pesanan tidak ditemukan atau Anda tidak memiliki akses.', 'warning');
    redirect(BASE_URL . 'booking_riwayat.php');
}
if ((int) $detail['user_id'] !== (int) $_SESSION['user_id']) {
    flash('notification', 'Hanya pemilik pesanan yang dapat mengubah jadwal.', 'warning');
    redirect(BASE_URL . 'booking_detail.php?id=' . $bookingId);
}
if ($detail['status'] !== 'pending') {
    flash('notification', 'Jadwal hanya dapat diubah saat pesanan berstatus pending.', 'warning');
    redirect(BASE_URL . 'booking_detail.php?id=' . $bookingId);
}

$page_title = 'Ubah Jadwal Booking';
$csrfToken = cart_csrf_token();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenInput = $_POST['csrf_token'] ?? '';
    $token = is_string($tokenInput) ? $tokenInput : '';
    $itemIdInput = $_POST['item_id'] ?? null;
    $itemId = filter_var($itemIdInput, FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    $startDateInput = $_POST['start_date'] ?? '';
    $endDateInput = $_POST['end_date'] ?? '';
    $startDate = is_string($startDateInput) ? $startDateInput : '';
    $endDate = is_string($endDateInput) ? $endDateInput : '';

    if (!isset($_SESSION['cart_csrf_token']) || !hash_equals($_SESSION['cart_csrf_token'], $token)) {
        flash('notification', 'Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.', 'danger');
        redirect(BASE_URL . 'booking_detail.php?id=' . $bookingId);
    }
    if ($itemId === false || $itemId === null) {
        flash('notification', 'Unit motor yang dipilih tidak valid.', 'warning');
        redirect(BASE_URL . 'booking_edit.php?id=' . $bookingId);
    }

    try {
        update_booking_item_dates(
            $pdo,
            $bookingId,
            (int) $_SESSION['user_id'],
            $itemId,
            $startDate,
            $endDate
        );
        flash('notification', 'Jadwal sewa berhasil diperbarui.', 'success');
    } catch (InvalidArgumentException $exception) {
        flash('notification', $exception->getMessage(), 'warning');
    } catch (RuntimeException $exception) {
        flash('notification', $exception->getMessage(), 'warning');
    } catch (PDOException $exception) {
        error_log('Update booking dates database error: ' . $exception->getMessage());
        flash('notification', 'Jadwal gagal diperbarui karena terjadi masalah. Silakan coba lagi.', 'danger');
    }

    redirect(BASE_URL . 'booking_detail.php?id=' . $bookingId);
}

require __DIR__ . '/includes/header.php';

?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h1 class="h2 mb-1">Ubah Jadwal</h1>
        <p class="text-muted mb-0">Booking <?= e($detail['booking_code']) ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="<?= e(BASE_URL . 'booking_detail.php?id=' . $bookingId) ?>">Kembali ke Detail</a>
</div>

<div class="alert alert-info" role="status">
    Perubahan jadwal hanya tersedia selama pesanan berstatus pending. Total biaya akan dihitung ulang menggunakan harga saat pemesanan.
</div>

<?php foreach ($detail['items'] as $item): ?>
    <div class="card mb-3">
        <div class="card-body">
            <h2 class="h5"><?= e($item['brand'] . ' ' . $item['model']) ?></h2>
            <p class="text-muted">Harga snapshot: <?= e(format_rupiah($item['price_per_day'])) ?> / hari</p>
            <form method="post" action="<?= e(BASE_URL . 'booking_edit.php?id=' . $bookingId) ?>" class="row g-3 align-items-end">
                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                <input type="hidden" name="booking_id" value="<?= e($bookingId) ?>">
                <input type="hidden" name="item_id" value="<?= e($item['item_id']) ?>">
                <div class="col-sm-5">
                    <label for="start_date_<?= e($item['item_id']) ?>" class="form-label">Tanggal Mulai</label>
                    <input type="date" class="form-control" id="start_date_<?= e($item['item_id']) ?>" name="start_date" min="<?= e(date('Y-m-d')) ?>" value="<?= e($item['start_date']) ?>" required>
                </div>
                <div class="col-sm-5">
                    <label for="end_date_<?= e($item['item_id']) ?>" class="form-label">Tanggal Selesai</label>
                    <input type="date" class="form-control" id="end_date_<?= e($item['item_id']) ?>" name="end_date" min="<?= e(date('Y-m-d')) ?>" value="<?= e($item['end_date']) ?>" required>
                </div>
                <div class="col-sm-2">
                    <button type="submit" class="btn btn-primary w-100">Simpan</button>
                </div>
            </form>
        </div>
    </div>
<?php endforeach; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
