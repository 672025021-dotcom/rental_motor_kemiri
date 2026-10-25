<?php

require_once __DIR__ . '/functions/user.php';

$page_title = 'Login';
$email = '';
$password = '';

if (is_logged_in()) {
    redirect(BASE_URL . 'katalog.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailInput = $_POST['email'] ?? '';
    $passwordInput = $_POST['password'] ?? '';
    $email = is_string($emailInput) ? trim($emailInput) : '';
    $password = is_string($passwordInput) ? $passwordInput : '';

    if ($email === '' || $password === '') {
        flash('notification', 'Email dan password wajib diisi.', 'warning');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('notification', 'Format email tidak valid.', 'warning');
    } else {
        try {
            if (login_user($pdo, $email, $password)) {
                $user = current_user();
                $welcomeName = isset($user['full_name']) ? $user['full_name'] : 'pelanggan';
                flash('notification', 'Selamat datang, ' . $welcomeName . '!', 'success');
                redirect(BASE_URL . 'katalog.php');
            }

            flash('notification', 'Email atau password salah.', 'danger');
        } catch (PDOException $exception) {
            error_log('Login database error: ' . $exception->getMessage());
            flash('notification', 'Login gagal karena terjadi masalah pada database. Silakan coba lagi.', 'danger');
        } catch (RuntimeException $exception) {
            error_log('Login session error: ' . $exception->getMessage());
            flash('notification', 'Sesi login tidak dapat dibuat. Silakan coba lagi.', 'danger');
        }
    }
}

require __DIR__ . '/includes/header.php';

?>
<div class="row justify-content-center">
    <div class="col-md-6 col-lg-5">
        <h1 class="h3 mb-4">Login</h1>
        <form method="post" action="<?= e(BASE_URL . 'login.php') ?>">
            <div class="mb-3">
                <label for="email" class="form-label">Email</label>
                <input type="email" class="form-control" id="email" name="email" maxlength="100" required autocomplete="email" value="<?= e($email) ?>">
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" required autocomplete="current-password">
            </div>
            <button type="submit" class="btn btn-primary">Login</button>
            <p class="mt-3 mb-0">Belum punya akun? <a href="<?= e(BASE_URL . 'register.php') ?>">Daftar</a></p>
        </form>
    </div>
</div>
<?php require __DIR__ . '/includes/footer.php'; ?>
