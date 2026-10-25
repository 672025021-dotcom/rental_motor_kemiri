<?php

function e($string)
{
    return htmlspecialchars((string) $string, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

function format_rupiah($angka)
{
    return 'Rp ' . number_format((float) $angka, 0, ',', '.');
}

function flash($name, $message = null, $type = 'info')
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        if (headers_sent($file, $line)) {
            throw new RuntimeException("Session must be started before output at {$file}:{$line}.");
        }

        session_start();
    }

    if ($message !== null) {
        $_SESSION['_flash'][$name] = [
            'message' => $message,
            'type' => $type,
        ];

        return null;
    }

    if (!isset($_SESSION['_flash'][$name])) {
        return null;
    }

    $flash = $_SESSION['_flash'][$name];
    unset($_SESSION['_flash'][$name]);

    if (empty($_SESSION['_flash'])) {
        unset($_SESSION['_flash']);
    }

    return $flash;
}
