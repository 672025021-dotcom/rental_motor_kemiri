<?php

require_once __DIR__ . '/functions/user.php';

require_login();

$page_title = 'Profil Pelanggan';
$userId = (int) $_SESSION['user_id'];
$user = get_user_by_id($pdo, $userId);

if (!$user) {
    unset($_SESSION['user_id'], $_SESSION['user']);
    flash('notification', 'Akun tidak ditemukan. Silakan login kembali.', 'warning');
    redirect(BASE_URL . 'login.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postedFullName = $_POST['full_name'] ?? '';
    $postedPhoneWa = $_POST['phone_wa'] ?? '';
    $postedAddress = $_POST['address'] ?? '';
    $form = [
        'full_name' => is_string($postedFullName) ? trim($postedFullName) : '',
        'phone_wa' => is_string($postedPhoneWa) ? trim($postedPhoneWa) : '',
        'address' => is_string($postedAddress) ? trim($postedAddress) : '',
    ];

    foreach ([
        'Nama lengkap' => $form['full_name'],
        'Nomor WhatsApp' => $form['phone_wa'],
        'Alamat' => $form['address'],
    ] as $label => $value) {
        if ($value === '') {
            $errors[] = $label . ' wajib diisi.';
        }
    }

    if (strlen($form['full_name']) > 100) {
        $errors[] = 'Nama lengkap maksimal 100 karakter.';
    }

    if (strlen($form['phone_wa']) > 20) {
        $errors[] = 'Nomor WhatsApp maksimal 20 karakter.';
    }

    if (!$errors) {
        try {
            update_user_profile($pdo, $userId, $form);
            $updatedUser = get_user_by_id($pdo, $userId);

            if (!$updatedUser) {
                unset($_SESSION['user_id'], $_SESSION['user']);
                flash('notification', 'Akun tidak ditemukan. Silakan login kembali.', 'warning');
                redirect(BASE_URL . 'login.php');
            }

            $_SESSION['user'] = $updatedUser;
            flash('notification', 'Profil berhasil diperbarui.', 'success');
            redirect(BASE_URL . 'profil.php');
        } catch (PDOException $exception) {
            error_log('Profile update database error: ' . $exception->getMessage());
            $errors[] = 'Profil gagal diperbarui karena terjadi masalah pada database. Silakan coba lagi.';
        }
    }

    $user = array_merge($user, $form);
}

require __DIR__ . '/includes/header.php';

?>
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <h1 class="h3 mb-4">Profil Pelanggan</h1>

        <?php if ($errors): ?>
            <div class="alert alert-danger" role="alert">
                <p class="mb-2">Perubahan profil belum berhasil:</p>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-body">
                <h2 class="h5 card-title">Informasi Akun</h2>
                <p class="mb-1"><strong>Email:</strong> <?= e($user['email']) ?></p>
                <p class="mb-0"><strong>Terdaftar:</strong> <?= e($user['created_at']) ?></p>
            </div>
        </div>

        <form method="post" action="<?= e(BASE_URL . 'profil.php') ?>">
            <div class="mb-3">
                <label for="full_name" class="form-label">Nama Lengkap</label>
                <input type="text" class="form-control" id="full_name" name="full_name" maxlength="100" required value="<?= e($user['full_name']) ?>">
            </div>
            <div class="mb-3">
                <label for="phone_wa" class="form-label">Nomor WhatsApp</label>
                <input type="tel" class="form-control" id="phone_wa" name="phone_wa" maxlength="20" required value="<?= e($user['phone_wa']) ?>">
            </div>
            <div class="mb-3">
                <label for="address" class="form-label">Alamat</label>
                <textarea class="form-control" id="address" name="address" rows="3" required><?= e($user['address'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
