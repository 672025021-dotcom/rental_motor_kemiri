<?php

require_once __DIR__ . '/functions/cart.php';

$motorIdInput = $_GET['id'] ?? null;
$motorId = filter_var($motorIdInput, FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);

if ($motorId === false || $motorId === null) {
    flash('notification', 'ID motor tidak valid.', 'danger');
    redirect(BASE_URL . 'katalog.php');
}

$motor = get_motor_by_id($pdo, $motorId);
if (!$motor) {
    flash('notification', 'Motor yang Anda cari tidak ditemukan.', 'danger');
    redirect(BASE_URL . 'katalog.php');
}

$page_title = $motor['brand'] . ' ' . $motor['model'];
$today = date('Y-m-d');
$startDate = $today;
$endDate = $today;
$csrfToken = cart_csrf_token();
$profileAddress = '';

if (is_logged_in()) {
    try {
        $profileAddress = get_user_delivery_address($pdo, (int) $_SESSION['user_id']);
    } catch (PDOException $exception) {
        error_log('Load customer delivery address database error: ' . $exception->getMessage());
    }
}

$typeLabels = [
    'matic' => 'Matic',
    'manual' => 'Manual',
    'sport' => 'Sport',
];

require __DIR__ . '/includes/header.php';

?>
<nav aria-label="breadcrumb" class="mb-4">
    <ol class="breadcrumb">
        <li class="breadcrumb-item"><a href="<?= e(BASE_URL . 'katalog.php') ?>">Katalog</a></li>
        <li class="breadcrumb-item active" aria-current="page"><?= e($motor['brand'] . ' ' . $motor['model']) ?></li>
    </ol>
</nav>

<div class="row g-4 g-lg-5">
    <div class="col-lg-6">
        <div class="detail-image-frame">
        <img
            src="<?= e(motor_image_url($motor['image'])) ?>"
            class="img-fluid"
            alt="<?= e($motor['brand'] . ' ' . $motor['model']) ?>"
        >
        </div>
    </div>
    <div class="col-lg-6">
        <div class="d-flex align-items-center gap-2 mb-2">
            <span class="motor-card__type"><?= e($typeLabels[$motor['type']] ?? $motor['type']) ?></span>
            <?php if ($motor['status'] === 'tersedia'): ?>
                <span class="motor-card__availability">Tersedia</span>
            <?php endif; ?>
        </div>
        <h1 class="display-6 mb-2"><?= e($motor['brand'] . ' ' . $motor['model']) ?></h1>
        <p class="motor-card__price fs-3 mb-4">
            <?= e(format_rupiah($motor['price_per_day'])) ?> <small>/ hari</small>
        </p>

        <dl class="row motor-spec-list border rounded-4 bg-white p-3 p-sm-4 mb-4">
            <dt class="col-sm-4">Nomor Plat</dt>
            <dd class="col-sm-8"><?= e($motor['plate_number']) ?></dd>

            <dt class="col-sm-4">Tipe Motor</dt>
            <dd class="col-sm-8"><?= e($typeLabels[$motor['type']] ?? $motor['type']) ?></dd>

            <dt class="col-sm-4">CC Mesin</dt>
            <dd class="col-sm-8"><?= $motor['engine_cc'] !== null ? e($motor['engine_cc'] . ' CC') : '—' ?></dd>

            <dt class="col-sm-4">Tahun</dt>
            <dd class="col-sm-8"><?= $motor['year'] !== null ? e($motor['year']) : '—' ?></dd>

            <dt class="col-sm-4">Warna</dt>
            <dd class="col-sm-8"><?= e($motor['color'] ?: '—') ?></dd>

            <dt class="col-sm-4">Spesifikasi</dt>
            <dd class="col-sm-8"><?= e($motor['description'] ?: 'Belum ada deskripsi spesifikasi.') ?></dd>

            <dt class="col-sm-4">Ketersediaan</dt>
            <dd class="col-sm-8"><?= e(ucfirst($motor['status'])) ?></dd>
        </dl>

        <section id="form-keranjang" class="card mt-4">
            <div class="card-body">
                <h2 class="h5 card-title mb-1">Rencanakan Sewa Anda</h2>
                <p class="text-muted small mb-4">Pilih tanggal serta metode pengambilan yang Anda inginkan.</p>
                <?php if ($motor['status'] === 'tersedia'): ?>
                    <form method="post" action="<?= e(BASE_URL . 'cart_add.php') ?>">
                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                        <input type="hidden" name="motorcycle_id" value="<?= e($motorId) ?>">
                        <div class="row g-3">
                            <div class="col-sm-6">
                                <label for="start_date" class="form-label">Tanggal Mulai</label>
                                <input type="date" class="form-control" id="start_date" name="start_date" min="<?= e($today) ?>" required value="<?= e($startDate) ?>">
                            </div>
                            <div class="col-sm-6">
                                <label for="end_date" class="form-label">Tanggal Selesai</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" min="<?= e($today) ?>" required value="<?= e($endDate) ?>">
                            </div>
                            <div class="col-sm-6">
                                <label for="pickup_method" class="form-label">Metode Pengambilan</label>
                                <select class="form-select" id="pickup_method" name="pickup_method" required>
                                    <option value="ambil_sendiri">Ambil sendiri</option>
                                    <option value="diantar">Diantar</option>
                                </select>
                            </div>
                            <div class="col-12" id="delivery-address-fields" hidden>
                                <?php if (is_logged_in()): ?>
                                    <div class="form-check mb-2">
                                        <input
                                            class="form-check-input"
                                            type="checkbox"
                                            id="use_profile_address"
                                            name="use_profile_address"
                                            value="1"
                                            data-profile-address="<?= e($profileAddress) ?>"
                                            <?= $profileAddress === '' ? 'disabled' : '' ?>
                                        >
                                        <label class="form-check-label" for="use_profile_address">Gunakan Alamat Domisili dari Profil</label>
                                    </div>
                                <?php endif; ?>
                                <label for="delivery_address" class="form-label">Alamat Pengantaran</label>
                                <textarea class="form-control" id="delivery_address" name="delivery_address" rows="2" placeholder="Contoh: alamat kos, hotel, atau stasiun"></textarea>
                                <?php if (is_logged_in() && $profileAddress === ''): ?>
                                    <div class="form-text">Alamat profil belum diisi. Anda dapat mengetik alamat tujuan secara manual.</div>
                                <?php else: ?>
                                    <div class="form-text">Alamat dapat diedit jika tujuan pengantaran berbeda dari alamat profil.</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mt-3">Tambah ke Keranjang</button>
                    </form>
                <?php else: ?>
                    <p class="mb-0 text-muted">Motor ini belum tersedia untuk ditambahkan ke keranjang.</p>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>
<script>
    (function () {
        var method = document.getElementById('pickup_method');
        var addressFields = document.getElementById('delivery-address-fields');
        var address = document.getElementById('delivery_address');
        var profileAddress = document.getElementById('use_profile_address');

        if (!method || !addressFields || !address) {
            return;
        }

        function updateDeliveryFields() {
            var isDelivery = method.value === 'diantar';
            addressFields.hidden = !isDelivery;
            address.required = isDelivery;
        }

        method.addEventListener('change', updateDeliveryFields);
        if (profileAddress) {
            profileAddress.addEventListener('change', function () {
                if (profileAddress.checked) {
                    address.value = profileAddress.getAttribute('data-profile-address') || '';
                }
            });
            address.addEventListener('input', function () {
                profileAddress.checked = false;
            });
        }
        updateDeliveryFields();
    }());
</script>
<?php require __DIR__ . '/includes/footer.php'; ?>
