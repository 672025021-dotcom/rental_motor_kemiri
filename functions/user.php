<?php

require_once __DIR__ . '/../includes/auth.php';

function register_user(PDO $db, array $data)
{
    $fullName = isset($data['full_name']) && is_string($data['full_name']) ? trim($data['full_name']) : '';
    $email = isset($data['email']) && is_string($data['email']) ? strtolower(trim($data['email'])) : '';
    $password = isset($data['password']) && is_string($data['password']) ? $data['password'] : '';
    $phoneWa = isset($data['phone_wa']) && is_string($data['phone_wa']) ? trim($data['phone_wa']) : '';
    $address = isset($data['address']) && is_string($data['address']) ? trim($data['address']) : '';

    if ($fullName === '' || $email === '' || $password === '' || $phoneWa === '' || $address === '') {
        throw new InvalidArgumentException('Semua data pendaftaran wajib diisi.');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Format email tidak valid.');
    }

    if (check_email_exists($db, $email)) {
        throw new InvalidArgumentException('Email sudah terdaftar.');
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    if ($passwordHash === false) {
        throw new RuntimeException('Password tidak dapat diproses.');
    }

    $statement = $db->prepare(
        'INSERT INTO users (full_name, email, password_hash, phone_wa, address)
         VALUES (:full_name, :email, :password_hash, :phone_wa, :address)'
    );
    $statement->execute([
        ':full_name' => $fullName,
        ':email' => $email,
        ':password_hash' => $passwordHash,
        ':phone_wa' => $phoneWa,
        ':address' => $address,
    ]);

    return (int) $db->lastInsertId();
}

function login_user(PDO $db, $email, $password)
{
    if (!is_string($email) || !is_string($password) || trim($email) === '' || $password === '') {
        return false;
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $statement = $db->prepare(
        'SELECT id, full_name, email, password_hash, phone_wa, address, role
         FROM users
         WHERE email = :email
         LIMIT 1'
    );
    $statement->execute([
        ':email' => strtolower(trim($email)),
    ]);
    $user = $statement->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        return false;
    }

    if (!session_regenerate_id(true)) {
        throw new RuntimeException('Sesi login tidak dapat diperbarui.');
    }

    unset($user['password_hash']);
    $user['id'] = (int) $user['id'];
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user'] = $user;

    return true;
}

function get_user_by_id(PDO $db, $id)
{
    $statement = $db->prepare(
        'SELECT id, full_name, email, phone_wa, address, role, created_at, updated_at
         FROM users
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute([
        ':id' => $id,
    ]);

    return $statement->fetch() ?: null;
}

function update_user_profile(PDO $db, $id, array $data)
{
    $fullName = isset($data['full_name']) && is_string($data['full_name']) ? trim($data['full_name']) : '';
    $phoneWa = isset($data['phone_wa']) && is_string($data['phone_wa']) ? trim($data['phone_wa']) : '';
    $address = isset($data['address']) && is_string($data['address']) ? trim($data['address']) : '';

    if ($fullName === '' || $phoneWa === '' || $address === '') {
        throw new InvalidArgumentException('Nama lengkap, nomor WhatsApp, dan alamat wajib diisi.');
    }

    $statement = $db->prepare(
        'UPDATE users
         SET full_name = :full_name,
             phone_wa = :phone_wa,
             address = :address
         WHERE id = :id'
    );
    $statement->execute([
        ':full_name' => $fullName,
        ':phone_wa' => $phoneWa,
        ':address' => $address,
        ':id' => $id,
    ]);

    return true;
}

function check_email_exists(PDO $db, $email)
{
    if (!is_string($email) || trim($email) === '') {
        return false;
    }

    $statement = $db->prepare(
        'SELECT 1
         FROM users
         WHERE email = :email
         LIMIT 1'
    );
    $statement->execute([
        ':email' => strtolower(trim($email)),
    ]);

    return (bool) $statement->fetchColumn();
}
