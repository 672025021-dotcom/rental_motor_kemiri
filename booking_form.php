<?php

require_once __DIR__ . '/functions/booking.php';

require_login();

$userId = (int) $_SESSION['user_id'];
try {
    $cartItems = get_cart_items_by_user($pdo, $userId);
    $cartTotal = get_cart_total($pdo, $userId);
} catch (PDOException $exception) {
    error_log('Load checkout cart database error: ' . $exception->getMessage());
    flash('notification', 'Ringkasan keranjang gagal dimuat. Silakan coba kembali.', 'danger');
    redirect(BASE_URL . 'cart.php');
}

if (!$cartItems) {
    flash('notification', 'Keranjang Anda masih kosong. Tambahkan motor sebelum checkout.', 'warning');
    redirect(BASE_URL . 'cart.php');
}

$page_title = 'Checkout';
$csrfToken = cart_csrf_token();
$firstItem = $cartItems[0];
$pickupMethod = $firstItem['pickup_method'];
$deliveryAddress = $firstItem['delivery_address'] ?? '';
foreach ($cartItems as $item) {
    if (
        $item['pickup_method'] !== $pickupMethod
        || ($item['delivery_address'] ?? '') !== $deliveryAddress
    ) {
        flash('notification', 'Samakan metode pengambilan dan alamat untuk seluruh motor dari halaman keranjang sebelum checkout.', 'warning');
        redirect(BASE_URL . 'cart.php');
    }
}
if ($pickupMethod === 'diantar' && trim($deliveryAddress) === '') {
    flash('notification', 'Alamat pengantaran belum ditentukan. Silakan atur alamat dari halaman keranjang.', 'warning');
    redirect(BASE_URL . 'cart.php');
}

$form = [
    'notes' => '',
];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenInput = $_POST['csrf_token'] ?? '';
    $token = is_string($tokenInput) ? $tokenInput : '';

    if (!isset($_SESSION['cart_csrf_token']) || !hash_equals($_SESSION['cart_csrf_token'], $token)) {
        $errors[] = 'Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.';
    }

    $notesInput = $_POST['notes'] ?? '';
    $form['notes'] = is_string($notesInput) ? trim($notesInput) : '';

    if (!$errors) {
        try {
            $booking = process_checkout($pdo, $userId, $form, $_FILES);
            flash(
                'notification',
                'Checkout berhasil. Kode booking Anda: ' . $booking['booking_code'],
                'success'
            );
            redirect(BASE_URL . 'booking_riwayat.php');
        } catch (InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        } catch (PDOException $exception) {
            error_log('Checkout database error: ' . $exception->getMessage());
            $errors[] = 'Checkout gagal karena terjadi masalah pada database. Silakan coba lagi.';
        } catch (RuntimeException $exception) {
            $errors[] = $exception->getMessage();
        }
    }
}

$pickupLabel = $pickupMethod === 'diantar' ? 'Diantar' : 'Ambil sendiri';

require __DIR__ . '/includes/header.php';

?>
<div class="mb-4">
    <span class="section-eyebrow">Langkah terakhir</span>
    <h1 class="h2 mt-2 mb-3">Checkout Pemesanan</h1>
    <div class="checkout-step-indicator" aria-label="Tahap checkout">
        <span class="checkout-step-indicator__step"><span class="checkout-step-indicator__number">1</span> Ringkasan</span>
        <span aria-hidden="true">—</span>
        <span class="checkout-step-indicator__step"><span class="checkout-step-indicator__number">2</span> Dokumen</span>
        <span aria-hidden="true">—</span>
        <span class="checkout-step-indicator__step"><span class="checkout-step-indicator__number">3</span> Konfirmasi</span>
    </div>
</div>

<?php if ($errors): ?>
    <div class="alert alert-danger" role="alert">
        <p class="mb-2">Checkout belum berhasil:</p>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= e($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row g-4">
    <section class="col-lg-7" aria-labelledby="order-summary-title">
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h2 class="h5 card-title mb-0" id="order-summary-title">Ringkasan Pesanan</h2>
                    <span class="badge text-bg-light border"><?= e(count($cartItems)) ?> unit</span>
                </div>
                <?php foreach ($cartItems as $item): ?>
                    <div class="d-flex justify-content-between gap-3 border-top py-3">
                        <div>
                            <strong><?= e($item['brand'] . ' ' . $item['model']) ?></strong>
                            <div class="small text-muted">
                                <?= e($item['start_date']) ?> – <?= e($item['end_date']) ?>
                                (<?= e($item['total_days']) ?> hari)
                            </div>
                        </div>
                        <strong class="text-nowrap"><?= e(format_rupiah($item['subtotal'])) ?></strong>
                    </div>
                <?php endforeach; ?>
                <div class="d-flex justify-content-between border-top pt-3">
                    <strong>Total Biaya Keseluruhan</strong>
                    <strong><?= e(format_rupiah($cartTotal)) ?></strong>
                </div>
            </div>
        </div>
        <div class="card mt-3">
            <div class="card-body">
                <h2 class="h5 card-title">Ringkasan Pengiriman</h2>
                <p class="mb-1"><strong>Metode:</strong> <?= e($pickupLabel) ?></p>
                <?php if ($pickupMethod === 'diantar'): ?>
                    <p class="mb-0"><strong>Alamat:</strong> <?= e($deliveryAddress) ?></p>
                <?php else: ?>
                    <p class="text-muted mb-0">Motor akan diambil sendiri.</p>
                <?php endif; ?>
                <a class="small d-inline-block mt-2" href="<?= e(BASE_URL . 'cart.php') ?>">Ubah pengiriman di keranjang</a>
            </div>
        </div>
    </section>

    <section class="col-lg-5" aria-labelledby="checkout-form-title">
        <div class="card booking-summary-card">
            <div class="card-body">
                <h2 class="h5 card-title" id="checkout-form-title">Data Checkout</h2>
                <form method="post" action="<?= e(BASE_URL . 'booking_form.php') ?>" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

                    <div class="document-upload-card mb-3">
                        <label for="ktp" class="form-label">Unggah KTP</label>
                        <input type="file" class="form-control" id="ktp" name="ktp" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" required>
                        <div class="form-text">Format JPG, JPEG, PNG, atau PDF; maksimal 2 MB.</div>
                    </div>
                    <div class="document-upload-card mb-3">
                        <label for="sim" class="form-label">Unggah SIM</label>
                        <input type="file" class="form-control" id="sim" name="sim" accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf" required>
                        <div class="form-text">Format JPG, JPEG, PNG, atau PDF; maksimal 2 MB.</div>
                    </div>
                    <div class="mb-3">
                        <label for="notes" class="form-label">Catatan Tambahan (opsional)</label>
                        <textarea class="form-control" id="notes" name="notes" rows="3" maxlength="255"><?= e($form['notes']) ?></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100">Konfirmasi Pemesanan</button>
                    <a class="btn btn-link w-100 mt-2" href="<?= e(BASE_URL . 'cart.php') ?>">Kembali ke Keranjang</a>
                </form>
            </div>
        </div>
    </section>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
