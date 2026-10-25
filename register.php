<?php

require_once __DIR__ . '/functions/user.php';

$page_title = 'Daftar Akun';
$errors = [];
$form = [
    'full_name' => '',
    'email' => '',
    'phone_wa' => '',
    'address' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    foreach ($form as $field => $value) {
        $postedValue = $_POST[$field] ?? '';
        $form[$field] = is_string($postedValue) ? trim($postedValue) : '';
    }

    $passwordInput = $_POST['password'] ?? '';
    $passwordConfirmationInput = $_POST['password_confirmation'] ?? '';
    $password = is_string($passwordInput) ? $passwordInput : '';
    $passwordConfirmation = is_string($passwordConfirmationInput) ? $passwordConfirmationInput : '';

    foreach ([
        'Nama lengkap' => $form['full_name'],
        'Email' => $form['email'],
        'Password' => $password,
        'Konfirmasi password' => $passwordConfirmation,
        'Nomor WhatsApp' => $form['phone_wa'],
        'Alamat' => $form['address'],
    ] as $label => $value) {
        if (trim($value) === '') {
            $errors[] = $label . ' wajib diisi.';
        }
    }

    if ($form['email'] !== '' && !filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Format email tidak valid.';
    }

    if ($password !== '' && $passwordConfirmation !== '' && $password !== $passwordConfirmation) {
        $errors[] = 'Konfirmasi password tidak sesuai.';
    }

    if (!$errors) {
        try {
            if (check_email_exists($pdo, $form['email'])) {
                $errors[] = 'Email sudah terdaftar. Gunakan email lain atau silakan login.';
            } else {
                register_user($pdo, [
                    'full_name' => $form['full_name'],
                    'email' => $form['email'],
                    'password' => $password,
                    'phone_wa' => $form['phone_wa'],
                    'address' => $form['address'],
                ]);

                flash('notification', 'Pendaftaran berhasil. Silakan login dengan akun Anda.', 'success');
                redirect(BASE_URL . 'login.php');
            }
        } catch (InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        } catch (PDOException $exception) {
            error_log('Registration database error: ' . $exception->getMessage());
            $errors[] = 'Pendaftaran gagal karena terjadi masalah pada database. Silakan coba lagi.';
        } catch (RuntimeException $exception) {
            error_log('Registration error: ' . $exception->getMessage());
            $errors[] = 'Password tidak dapat diproses. Silakan coba lagi.';
        }
    }
}

require __DIR__ . '/includes/header.php';

?>
<div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
        <h1 class="h3 mb-4">Daftar Akun</h1>

        <?php if ($errors): ?>
            <div class="alert alert-danger" role="alert">
                <p class="mb-2">Pendaftaran belum berhasil:</p>
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= e($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(BASE_URL . 'register.php') ?>">
            <div class="mb-3">
                <label for="full_name" class="form-label">Nama Lengkap</label>
                <input type="text" class="form-control" id="full_name" name="full_name" maxlength="100" required value="<?= e($form['full_name']) ?>">
            </div>
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" maxlength="100" required value="<?= e($form['email']) ?>">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required autocomplete="new-password">
            </div>
            <div class="mb-3">
                <label for="password_confirmation" class="form-label">Konfirmasi Password</label>
                <input type="password" class="form-control" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
            </div>
            <div class="mb-3">
                <label for="phone_wa" class="form-label">Nomor WhatsApp</label>
                <input type="tel" class="form-control" id="phone_wa" name="phone_wa" maxlength="20" required value="<?= e($form['phone_wa']) ?>">
            </div>
            <div class="mb-3">
                <label for="address" class="form-label">Alamat</label>
                <textarea class="form-control" id="address" name="address" rows="3" required><?= e($form['address']) ?></textarea>
            </div>
            <button type="submit" class="btn btn-primary">Daftar</button>
            <p class="mt-3 mb-0">Sudah punya akun? <a href="<?= e(BASE_URL . 'login.php') ?>">Login</a></p>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
