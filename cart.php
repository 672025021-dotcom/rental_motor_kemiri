<?php

require_once __DIR__ . '/functions/cart.php';

require_login();

$page_title = 'Keranjang Sewa';
$cartItems = [];
$cartTotal = 0;
$profileAddress = '';
$cartDeliveryMethod = 'ambil_sendiri';
$cartDeliveryAddress = '';
$cartLoadError = false;

try {
    $cartItems = get_cart_items_by_user($pdo, (int) $_SESSION['user_id']);
    $cartTotal = get_cart_total($pdo, (int) $_SESSION['user_id']);
    $profileAddress = get_user_delivery_address($pdo, (int) $_SESSION['user_id']);

    if ($cartItems) {
        $cartDeliveryMethod = $cartItems[0]['pickup_method'];
        $cartDeliveryAddress = $cartItems[0]['delivery_address'] ?? '';
    }
} catch (PDOException $exception) {
    error_log('Load cart database error: ' . $exception->getMessage());
    $cartLoadError = true;
}

$csrfToken = cart_csrf_token();
$isCartDeliveryMixed = false;
foreach ($cartItems as $item) {
    if (
        $item['pickup_method'] !== $cartDeliveryMethod
        || ($item['pickup_method'] === 'diantar' && ($item['delivery_address'] ?? '') !== $cartDeliveryAddress)
    ) {
        $isCartDeliveryMixed = true;
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$cartLoadError) {
    $actionInput = $_POST['action'] ?? '';
    $tokenInput = $_POST['csrf_token'] ?? '';
    $action = is_string($actionInput) ? $actionInput : '';
    $token = is_string($tokenInput) ? $tokenInput : '';

    if ($action === 'update_delivery') {
        if (!isset($_SESSION['cart_csrf_token']) || !hash_equals($_SESSION['cart_csrf_token'], $token)) {
            flash('notification', 'Sesi formulir tidak valid. Muat ulang halaman lalu coba kembali.', 'danger');
            redirect(BASE_URL . 'cart.php');
        }

        $methodInput = $_POST['pickup_method'] ?? '';
        $addressInput = $_POST['delivery_address'] ?? '';
        $method = is_string($methodInput) ? $methodInput : '';
        $address = is_string($addressInput) ? trim($addressInput) : '';
        $useProfileAddress = isset($_POST['use_profile_address'])
            && is_string($_POST['use_profile_address'])
            && $_POST['use_profile_address'] === '1';

        try {
            if ($method === 'diantar' && $useProfileAddress) {
                $address = get_user_delivery_address($pdo, (int) $_SESSION['user_id']);
                if (trim($address) === '') {
                    throw new InvalidArgumentException('Alamat profil belum diisi. Perbarui profil atau masukkan alamat tujuan secara manual.');
                }
            }

            update_cart_delivery($pdo, (int) $_SESSION['user_id'], $method, $address);
            flash('notification', 'Metode pengambilan dan alamat telah diterapkan ke seluruh motor di keranjang.', 'success');
        } catch (InvalidArgumentException $exception) {
            flash('notification', $exception->getMessage(), 'warning');
        } catch (PDOException $exception) {
            error_log('Update cart delivery database error: ' . $exception->getMessage());
            flash('notification', 'Pengaturan pengiriman gagal disimpan. Silakan coba lagi.', 'danger');
        } catch (RuntimeException $exception) {
            flash('notification', $exception->getMessage(), 'warning');
        }

        redirect(BASE_URL . 'cart.php');
    }
}

$pickupLabels = [
    'ambil_sendiri' => 'Ambil sendiri',
    'diantar' => 'Diantar',
];

require __DIR__ . '/includes/header.php';

?>
<div class="mb-4">
    <span class="section-eyebrow">Periksa pilihan Anda</span>
    <h1 class="h2 mt-2 mb-1">Keranjang Sewa</h1>
    <p class="text-muted mb-0">Pastikan jadwal dan detail pengiriman sudah sesuai sebelum checkout.</p>
</div>

<?php if ($cartLoadError): ?>
    <div class="alert alert-danger" role="alert">
        Keranjang belum dapat dimuat karena terjadi masalah. Silakan muat ulang halaman atau coba lagi nanti.
    </div>
<?php elseif (!$cartItems): ?>
    <div class="empty-state">
        <div class="empty-state__icon" aria-hidden="true">🛵</div>
            <h2 class="h4">Keranjang Anda masih kosong</h2>
            <p class="text-muted">Jelajahi katalog dan tambahkan motor yang ingin Anda sewa.</p>
            <a class="btn btn-primary" href="<?= e(BASE_URL . 'katalog.php') ?>">Lihat Katalog Motor</a>
    </div>
<?php else: ?>
    <div class="row g-4">
        <div class="col-lg-8">
            <section class="card mb-3" aria-labelledby="cart-delivery-title">
                <div class="card-body">
                    <h2 class="h5 card-title" id="cart-delivery-title">Metode Pengambilan &amp; Pengiriman</h2>
                    <p class="small text-muted">Pengaturan berikut akan diterapkan ke seluruh motor dalam pesanan.</p>
                    <?php if ($isCartDeliveryMixed): ?>
                        <div class="alert alert-warning" role="status">
                            Beberapa motor memiliki pilihan pengiriman berbeda. Simpan pilihan di bawah untuk menerapkannya ke seluruh pesanan.
                        </div>
                    <?php endif; ?>
                    <form method="post" action="<?= e(BASE_URL . 'cart.php') ?>" id="cart-delivery-form">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="action" value="update_delivery">
                        <div class="mb-3">
                            <label for="cart_pickup_method" class="form-label">Metode Pengambilan</label>
                            <select class="form-select" id="cart_pickup_method" name="pickup_method" required>
                                <option value="ambil_sendiri" <?= $cartDeliveryMethod === 'ambil_sendiri' ? 'selected' : '' ?>>Ambil sendiri</option>
                                <option value="diantar" <?= $cartDeliveryMethod === 'diantar' ? 'selected' : '' ?>>Diantar</option>
                            </select>
                        </div>
                        <div id="cart-delivery-address-fields" <?= $cartDeliveryMethod === 'diantar' ? '' : 'hidden' ?>>
                            <div class="form-check mb-2">
                                <input
                                    class="form-check-input"
                                    type="checkbox"
                                    id="cart_use_profile_address"
                                    name="use_profile_address"
                                    value="1"
                                    data-profile-address="<?= e($profileAddress) ?>"
                                    <?= $cartDeliveryMethod === 'diantar' && $profileAddress !== '' && $cartDeliveryAddress === $profileAddress ? 'checked' : '' ?>
                                >
                                <label class="form-check-label" for="cart_use_profile_address">Gunakan Alamat Domisili dari Profil</label>
                            </div>
                            <label for="cart_delivery_address" class="form-label">Alamat Pengantaran</label>
                            <textarea class="form-control" id="cart_delivery_address" name="delivery_address" rows="3" placeholder="Contoh: alamat kos, hotel, atau stasiun"><?= e($cartDeliveryAddress) ?></textarea>
                            <?php if ($profileAddress === ''): ?>
                                <div class="form-text">Alamat profil belum diisi. Anda dapat mengetik alamat tujuan secara manual.</div>
                            <?php else: ?>
                                <div class="form-text">Alamat tetap dapat diedit untuk tujuan lain. Mengedit alamat akan menonaktifkan pilihan alamat profil.</div>
                            <?php endif; ?>
                        </div>
                        <button type="submit" class="btn btn-outline-primary mt-3">Terapkan ke Semua Motor</button>
                    </form>
                </div>
            </section>

            <?php foreach ($cartItems as $item): ?>
                <article class="card cart-item-card mb-3">
                    <div class="row g-0">
                        <div class="col-md-4">
                            <img
                                src="<?= e(motor_image_url($item['image'])) ?>"
                                class="img-fluid cart-item-image"
                                alt="<?= e($item['brand'] . ' ' . $item['model']) ?>"
                                loading="lazy"
                            >
                        </div>
                        <div class="col-md-8">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start gap-3">
                                    <h2 class="h5 card-title"><?= e($item['brand'] . ' ' . $item['model']) ?></h2>
                                    <form method="post" action="<?= e(BASE_URL . 'cart_delete.php') ?>" onsubmit="return confirm('Hapus motor ini dari keranjang?');">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="cart_id" value="<?= e($item['cart_id']) ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger">Hapus</button>
                                    </form>
                                </div>
                                <dl class="row mb-2">
                                    <dt class="col-sm-5">Tanggal Sewa</dt>
                                    <dd class="col-sm-7"><?= e($item['start_date']) ?> – <?= e($item['end_date']) ?></dd>

                                    <dt class="col-sm-5">Durasi</dt>
                                    <dd class="col-sm-7"><?= e($item['total_days']) ?> hari</dd>

                                    <dt class="col-sm-5">Metode Pengambilan</dt>
                                    <dd class="col-sm-7"><?= e($pickupLabels[$item['pickup_method']] ?? $item['pickup_method']) ?></dd>

                                    <?php if ($item['pickup_method'] === 'diantar'): ?>
                                        <dt class="col-sm-5">Alamat Pengantaran</dt>
                                        <dd class="col-sm-7"><?= e($item['delivery_address'] ?: '—') ?></dd>
                                    <?php endif; ?>

                                    <dt class="col-sm-5">Harga per Hari</dt>
                                    <dd class="col-sm-7"><?= e(format_rupiah($item['price_per_day'])) ?></dd>
                                </dl>
                                <?php if ($item['motorcycle_status'] !== 'tersedia'): ?>
                                    <p class="text-danger small">Perhatian: motor ini saat ini tidak tersedia.</p>
                                <?php endif; ?>
                                <p class="mb-0 fw-semibold">
                                    Subtotal: <?= e(format_rupiah($item['subtotal'])) ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
        <aside class="col-lg-4">
            <div class="card booking-summary-card">
                <div class="card-body">
                    <h2 class="h5 card-title">Ringkasan Biaya</h2>
                    <div class="d-flex justify-content-between border-top pt-3">
                        <span>Grand Total Estimasi</span>
                        <strong><?= e(format_rupiah($cartTotal)) ?></strong>
                    </div>
                    <p class="small text-muted mt-2">Biaya akhir dikonfirmasi saat checkout.</p>
                    <a class="btn btn-primary w-100" href="<?= e(BASE_URL . 'booking_form.php') ?>">Lanjut ke Checkout</a>
                </div>
            </div>
        </aside>
    </div>
<?php endif; ?>
<script>
    (function () {
        var method = document.getElementById('cart_pickup_method');
        var addressFields = document.getElementById('cart-delivery-address-fields');
        var address = document.getElementById('cart_delivery_address');
        var profileAddress = document.getElementById('cart_use_profile_address');

        if (!method || !addressFields || !address || !profileAddress) {
            return;
        }

        function updateDeliveryFields() {
            var isDelivery = method.value === 'diantar';
            addressFields.hidden = !isDelivery;
            address.required = isDelivery;
        }

        method.addEventListener('change', updateDeliveryFields);
        profileAddress.addEventListener('change', function () {
            if (profileAddress.checked) {
                address.value = profileAddress.getAttribute('data-profile-address') || '';
            }
        });
        address.addEventListener('input', function () {
            profileAddress.checked = false;
        });
        updateDeliveryFields();
    }());
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
