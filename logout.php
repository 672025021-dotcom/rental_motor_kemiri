<?php

require_once __DIR__ . '/includes/auth.php';

$_SESSION = [];
session_unset();
session_destroy();

$cookieParams = session_get_cookie_params();
setcookie(
    session_name(),
    '',
    time() - 42000,
    $cookieParams['path'],
    $cookieParams['domain'],
    $cookieParams['secure'],
    $cookieParams['httponly']
);
unset($_COOKIE[session_name()]);

if (!session_start()) {
    throw new RuntimeException('Sesi baru tidak dapat dibuat setelah logout.');
}

flash('notification', 'Anda berhasil logout.', 'success');
redirect(BASE_URL . 'login.php');
