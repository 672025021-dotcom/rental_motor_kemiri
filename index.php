<?php

require_once __DIR__ . '/functions/motor.php';

$page_title = 'Beranda';
$featuredMotors = [];
$motorLoadError = false;
$typeLabels = [
    'matic' => 'Matic',
    'manual' => 'Manual',
    'sport' => 'Sport',
];

try {
    $featuredMotors = get_motors($pdo, ['limit' => 4]);
} catch (PDOException $exception) {
    error_log('Load featured motorcycles database error: ' . $exception->getMessage());
    $motorLoadError = true;
}

require __DIR__ . '/includes/header.php';

?>
<section class="home-hero mb-5">
    <div class="home-hero__content">
        <span class="home-hero__eyebrow">Rental Motor Kemiri</span>
        <h1 class="display-4 fw-bold mt-3">Sewa Motor Mudah &amp; Cepat di Kemiri</h1>
        <p class="lead mt-3 mb-4">
            Temukan motor yang cocok untuk aktivitas dan perjalanan Anda. Proses pemesanan mudah,
            pilihan unit beragam, dan siap menemani perjalanan di Kemiri.
        </p>
        <a class="btn btn-light btn-lg fw-semibold" href="<?= e(BASE_URL . 'katalog.php') ?>">
            Lihat Katalog Motor
        </a>
    </div>
    <div class="home-hero__decoration" aria-hidden="true">
        <span class="home-hero__circle home-hero__circle--one"></span>
        <span class="home-hero__circle home-hero__circle--two"></span>
    </div>
</section>

<section class="home-section mb-5" aria-labelledby="why-us-title">
    <div class="text-center mb-4">
        <span class="section-eyebrow">Nyaman untuk setiap perjalanan</span>
        <h2 class="h2 mt-2" id="why-us-title">Kenapa Memilih Kami?</h2>
        <p class="text-muted mb-0">Layanan sewa yang mudah, aman, dan sesuai kebutuhan Anda.</p>
    </div>
    <div class="row row-cols-1 row-cols-md-3 g-4">
        <div class="col">
            <article class="feature-card h-100">
                <div class="feature-card__icon" aria-hidden="true">Rp</div>
                <h3 class="h5">Harga Terjangkau</h3>
                <p class="text-muted mb-0">Pilihan tarif sewa yang jelas dan kompetitif untuk berbagai kebutuhan.</p>
            </article>
        </div>
        <div class="col">
            <article class="feature-card h-100">
                <div class="feature-card__icon" aria-hidden="true">✓</div>
                <h3 class="h5">Kondisi Motor Prima</h3>
                <p class="text-muted mb-0">Unit dirawat agar perjalanan Anda terasa nyaman dan lebih tenang.</p>
            </article>
        </div>
        <div class="col">
            <article class="feature-card h-100">
                <div class="feature-card__icon" aria-hidden="true">↗</div>
                <h3 class="h5">Layanan Antar-Jemput</h3>
                <p class="text-muted mb-0">Pilih ambil sendiri atau opsi pengantaran sesuai ketersediaan layanan.</p>
            </article>
        </div>
    </div>
</section>

<section class="home-section mb-5" aria-labelledby="featured-motors-title">
    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-end gap-2 mb-4">
        <div>
            <span class="section-eyebrow">Pilihan untuk Anda</span>
            <h2 class="h2 mt-2 mb-1" id="featured-motors-title">Motor Unggulan</h2>
            <p class="text-muted mb-0">Lihat beberapa unit yang tersedia untuk disewa.</p>
        </div>
        <a class="btn btn-outline-primary" href="<?= e(BASE_URL . 'katalog.php') ?>">Lihat Semua Motor</a>
    </div>

    <?php if ($motorLoadError): ?>
        <div class="alert alert-warning" role="alert">
            Daftar motor belum dapat dimuat. Silakan kunjungi katalog untuk mencoba kembali.
        </div>
    <?php elseif (!$featuredMotors): ?>
        <div class="alert alert-info" role="status">
            Belum ada motor tersedia. Silakan periksa kembali nanti.
        </div>
    <?php else: ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-lg-4 g-4">
            <?php foreach ($featuredMotors as $motor): ?>
                <div class="col">
                    <article class="card motor-card h-100">
                        <img
                            src="<?= e(motor_image_url($motor['image'])) ?>"
                            class="card-img-top motor-card__image"
                            alt="<?= e($motor['brand'] . ' ' . $motor['model']) ?>"
                            loading="lazy"
                        >
                        <div class="card-body d-flex flex-column">
                            <span class="motor-card__type"><?= e($typeLabels[$motor['type']] ?? $motor['type']) ?></span>
                            <span class="motor-card__availability ms-1">Tersedia</span>
                            <h3 class="h5 card-title mt-2"><?= e($motor['brand'] . ' ' . $motor['model']) ?></h3>
                            <p class="mb-3 mt-auto">
                                <strong class="motor-card__price"><?= e(format_rupiah($motor['price_per_day'])) ?> <small>/ hari</small></strong>
                            </p>
                            <a class="btn btn-primary" href="<?= e(BASE_URL . 'motor_detail.php?id=' . (int) $motor['id']) ?>">
                                Detail / Sewa
                            </a>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="home-section booking-steps mb-4" aria-labelledby="booking-steps-title">
    <div class="text-center mb-4">
        <span class="section-eyebrow">Tiga langkah sederhana</span>
        <h2 class="h2 mt-2" id="booking-steps-title">Cara Pemesanan</h2>
    </div>
    <div class="row row-cols-1 row-cols-md-3 g-4">
        <div class="col">
            <article class="booking-step h-100">
                <span class="booking-step__number">01</span>
                <h3 class="h5 mt-3">Pilih Motor</h3>
                <p class="text-muted mb-0">Jelajahi katalog dan pilih unit yang sesuai dengan perjalanan Anda.</p>
            </article>
        </div>
        <div class="col">
            <article class="booking-step h-100">
                <span class="booking-step__number">02</span>
                <h3 class="h5 mt-3">Isi Tanggal &amp; Dokumen</h3>
                <p class="text-muted mb-0">Tentukan jadwal sewa dan lengkapi dokumen identitas saat checkout.</p>
            </article>
        </div>
        <div class="col">
            <article class="booking-step h-100">
                <span class="booking-step__number">03</span>
                <h3 class="h5 mt-3">Konfirmasi &amp; Ambil Motor</h3>
                <p class="text-muted mb-0">Pantau status pesanan, lalu ambil motor atau pilih layanan pengantaran.</p>
            </article>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
