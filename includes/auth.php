<?php

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/helpers.php';

function is_logged_in()
{
    return isset($_SESSION['user_id']);
}

function current_user()
{
    if (!is_logged_in()) {
        return null;
    }

    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

function require_login()
{
    if (!is_logged_in()) {
        flash('notification', 'Silakan login terlebih dahulu.', 'warning');
        redirect(BASE_URL . 'login.php');
    }
}
