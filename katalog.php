<?php

require_once __DIR__ . '/functions/motor.php';

$page_title = 'Katalog Motor';
$availableTypes = get_available_types();
$type = isset($_GET['type']) && is_string($_GET['type']) ? strtolower(trim($_GET['type'])) : '';
$keyword = isset($_GET['keyword']) && is_string($_GET['keyword']) ? trim($_GET['keyword']) : '';
$filters = [
    'type' => in_array($type, $availableTypes, true) ? $type : '',
    'keyword' => $keyword,
];
$motors = get_motors($pdo, $filters);
$typeLabels = [
    'matic' => 'Matic',
    'manual' => 'Manual',
    'sport' => 'Sport',
];

require __DIR__ . '/includes/header.php';

?>
<div class="page-heading d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <span class="section-eyebrow">Temukan unit pilihan Anda</span>
        <h1 class="h2 mt-2 mb-1">Katalog Motor</h1>
        <p class="text-muted mb-0">Pilih motor yang sesuai untuk perjalanan Anda.</p>
    </div>
    <span class="badge text-bg-light border px-3 py-2"><?= e(count($motors)) ?> motor ditemukan</span>
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="get" action="<?= e(BASE_URL . 'katalog.php') ?>" class="row g-3 align-items-end">
            <div class="col-sm-4 col-md-3">
                <label for="type" class="form-label">Tipe Motor</label>
                <select class="form-select" id="type" name="type">
                    <option value="">Semua Tipe</option>
                    <?php foreach ($availableTypes as $availableType): ?>
                        <option value="<?= e($availableType) ?>" <?= $filters['type'] === $availableType ? 'selected' : '' ?>>
                            <?= e($typeLabels[$availableType]) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6 col-md-6">
                <label for="keyword" class="form-label">Cari Nama atau Brand</label>
                <input type="search" class="form-control" id="keyword" name="keyword" placeholder="Contoh: Honda Beat" value="<?= e($keyword) ?>">
            </div>
            <div class="col-sm-2 col-md-3 d-flex gap-2">
                <button type="submit" class="btn btn-primary flex-grow-1">Cari Motor</button>
                <a class="btn btn-outline-secondary" href="<?= e(BASE_URL . 'katalog.php') ?>">Reset</a>
            </div>
        </form>
    </div>
</div>

<?php if (!$motors): ?>
    <div class="empty-state" role="status">
        <div class="empty-state__icon" aria-hidden="true">⌕</div>
        <h2 class="h4">Motor belum ditemukan</h2>
        <p class="text-muted mb-3">Belum ada motor tersedia yang cocok dengan pencarian Anda. Coba ubah filter atau kata kunci.</p>
        <a class="btn btn-outline-primary" href="<?= e(BASE_URL . 'katalog.php') ?>">Tampilkan Semua Motor</a>
    </div>
<?php else: ?>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-3 g-4">
        <?php foreach ($motors as $motor): ?>
            <div class="col">
                <article class="card motor-card h-100">
                    <img
                        src="<?= e(motor_image_url($motor['image'])) ?>"
                        class="card-img-top motor-card__image"
                        alt="<?= e($motor['brand'] . ' ' . $motor['model']) ?>"
                        loading="lazy"
                    >
                    <div class="card-body d-flex flex-column">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-2">
                            <span class="motor-card__type"><?= e($typeLabels[$motor['type']] ?? $motor['type']) ?></span>
                            <span class="motor-card__availability">Tersedia</span>
                        </div>
                        <h2 class="h5 card-title"><?= e($motor['brand'] . ' ' . $motor['model']) ?></h2>
                        <p class="text-muted small mb-3">
                            <?php if ($motor['engine_cc'] !== null): ?>
                                <?= e($motor['engine_cc']) ?> CC
                            <?php else: ?>
                                Motor siap disewa
                            <?php endif; ?>
                        </p>
                        <p class="mb-3 mt-auto">
                            <strong class="motor-card__price"><?= e(format_rupiah($motor['price_per_day'])) ?> <small>/ hari</small></strong>
                        </p>
                        <div class="mt-auto d-flex flex-wrap gap-2">
                            <a class="btn btn-outline-primary" href="<?= e(BASE_URL . 'motor_detail.php?id=' . (int) $motor['id']) ?>">
                                Lihat Detail
                            </a>
                            <a class="btn btn-primary" href="<?= e(BASE_URL . 'motor_detail.php?id=' . (int) $motor['id'] . '#form-keranjang') ?>">
                                Tambah ke Keranjang
                            </a>
                        </div>
                    </div>
                </article>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/includes/footer.php'; ?>
