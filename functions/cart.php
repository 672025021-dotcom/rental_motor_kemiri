<?php

require_once __DIR__ . '/motor.php';

function cart_csrf_token()
{
    if (!isset($_SESSION['cart_csrf_token']) || !is_string($_SESSION['cart_csrf_token'])) {
        $_SESSION['cart_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['cart_csrf_token'];
}

function get_user_delivery_address(PDO $db, $userId)
{
    $statement = $db->prepare('SELECT address FROM users WHERE id = :user_id LIMIT 1');
    $statement->execute([
        ':user_id' => $userId,
    ]);
    $address = $statement->fetchColumn();

    if ($address === false) {
        throw new RuntimeException('Profil pengguna tidak ditemukan.');
    }

    return (string) $address;
}

function update_cart_delivery(PDO $db, $userId, $pickupMethod, $deliveryAddress)
{
    $userId = filter_var($userId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($userId === false) {
        throw new InvalidArgumentException('Data pengguna tidak valid.');
    }

    if (!is_string($pickupMethod) || !in_array($pickupMethod, ['ambil_sendiri', 'diantar'], true)) {
        throw new InvalidArgumentException('Pilih metode pengambilan yang valid.');
    }

    if (!is_string($deliveryAddress)) {
        $deliveryAddress = '';
    }
    $deliveryAddress = trim($deliveryAddress);

    if ($pickupMethod === 'diantar' && $deliveryAddress === '') {
        throw new InvalidArgumentException('Alamat pengantaran wajib diisi jika motor diantar.');
    }
    if (strlen($deliveryAddress) > 65535) {
        throw new InvalidArgumentException('Alamat pengantaran terlalu panjang.');
    }

    if ($db->inTransaction()) {
        throw new RuntimeException('Pengaturan keranjang tidak dapat dimulai di dalam transaksi lain.');
    }

    $db->beginTransaction();
    try {
        $countStatement = $db->prepare(
            'SELECT id FROM cart_items WHERE user_id = :user_id FOR UPDATE'
        );
        $countStatement->execute([
            ':user_id' => $userId,
        ]);
        if (!$countStatement->fetchAll()) {
            throw new InvalidArgumentException('Keranjang Anda masih kosong.');
        }

        $statement = $db->prepare(
            'UPDATE cart_items
             SET pickup_method = :pickup_method,
                 delivery_address = :delivery_address
             WHERE user_id = :user_id'
        );
        $statement->execute([
            ':pickup_method' => $pickupMethod,
            ':delivery_address' => $pickupMethod === 'diantar' ? $deliveryAddress : null,
            ':user_id' => $userId,
        ]);

        $db->commit();

        return true;
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}

function validate_cart_dates($startDate, $endDate)
{
    if (!is_string($startDate) || !is_string($endDate)) {
        throw new InvalidArgumentException('Tanggal sewa wajib diisi dengan format yang valid.');
    }

    $start = DateTimeImmutable::createFromFormat('!Y-m-d', $startDate);
    $startErrors = DateTimeImmutable::getLastErrors();
    $startValid = $start !== false
        && ($startErrors === false || ($startErrors['warning_count'] === 0 && $startErrors['error_count'] === 0))
        && $start->format('Y-m-d') === $startDate;

    $end = DateTimeImmutable::createFromFormat('!Y-m-d', $endDate);
    $endErrors = DateTimeImmutable::getLastErrors();
    $endValid = $end !== false
        && ($endErrors === false || ($endErrors['warning_count'] === 0 && $endErrors['error_count'] === 0))
        && $end->format('Y-m-d') === $endDate;

    if (!$startValid || !$endValid) {
        throw new InvalidArgumentException('Tanggal sewa tidak valid.');
    }

    if ($startDate < date('Y-m-d')) {
        throw new InvalidArgumentException('Tanggal mulai sewa tidak boleh sebelum hari ini.');
    }

    if ($endDate < $startDate) {
        throw new InvalidArgumentException('Tanggal selesai harus sama dengan atau setelah tanggal mulai.');
    }
}

function add_to_cart(PDO $db, $userId, $motorcycleId, $startDate, $endDate, $pickupMethod, $deliveryAddress)
{
    $userId = filter_var($userId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $motorcycleId = filter_var($motorcycleId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($userId === false || $motorcycleId === false) {
        throw new InvalidArgumentException('Data pelanggan atau motor tidak valid.');
    }

    validate_cart_dates($startDate, $endDate);

    if (!is_string($pickupMethod) || !in_array($pickupMethod, ['ambil_sendiri', 'diantar'], true)) {
        throw new InvalidArgumentException('Pilih metode pengambilan yang valid.');
    }

    if (!is_string($deliveryAddress)) {
        $deliveryAddress = '';
    }
    $deliveryAddress = trim($deliveryAddress);

    if ($pickupMethod === 'diantar' && $deliveryAddress === '') {
        throw new InvalidArgumentException('Alamat pengantaran wajib diisi jika motor diantar.');
    }

    $statement = $db->prepare(
        "SELECT id FROM motorcycles WHERE id = :motorcycle_id AND status = 'tersedia' LIMIT 1"
    );
    $statement->execute([
        ':motorcycle_id' => $motorcycleId,
    ]);
    if (!$statement->fetchColumn()) {
        throw new InvalidArgumentException('Motor tidak ditemukan atau sedang tidak tersedia.');
    }

    $statement = $db->prepare(
        'INSERT INTO cart_items (user_id, motorcycle_id, start_date, end_date, pickup_method, delivery_address)
         VALUES (:user_id, :motorcycle_id, :start_date, :end_date, :pickup_method, :delivery_address)
         ON DUPLICATE KEY UPDATE
             start_date = VALUES(start_date),
             end_date = VALUES(end_date),
             pickup_method = VALUES(pickup_method),
             delivery_address = VALUES(delivery_address)'
    );
    $statement->execute([
        ':user_id' => $userId,
        ':motorcycle_id' => $motorcycleId,
        ':start_date' => $startDate,
        ':end_date' => $endDate,
        ':pickup_method' => $pickupMethod,
        ':delivery_address' => $pickupMethod === 'diantar' ? $deliveryAddress : null,
    ]);

    return true;
}

function get_cart_items_by_user(PDO $db, $userId)
{
    $statement = $db->prepare(
        'SELECT ci.id AS cart_id, ci.user_id, ci.motorcycle_id, ci.start_date, ci.end_date,
                ci.pickup_method, ci.delivery_address,
                m.brand, m.model, m.image, m.price_per_day, m.status AS motorcycle_status,
                DATEDIFF(ci.end_date, ci.start_date) + 1 AS total_days,
                (DATEDIFF(ci.end_date, ci.start_date) + 1) * m.price_per_day AS subtotal
         FROM cart_items ci
         INNER JOIN motorcycles m ON m.id = ci.motorcycle_id
         WHERE ci.user_id = :user_id
         ORDER BY ci.created_at DESC, ci.id DESC'
    );
    $statement->execute([
        ':user_id' => $userId,
    ]);

    return $statement->fetchAll();
}

function delete_cart_item(PDO $db, $cartId, $userId)
{
    $statement = $db->prepare(
        'DELETE FROM cart_items
         WHERE id = :id AND user_id = :user_id'
    );
    $statement->execute([
        ':id' => $cartId,
        ':user_id' => $userId,
    ]);

    return $statement->rowCount() > 0;
}

function get_cart_total(PDO $db, $userId)
{
    $statement = $db->prepare(
        'SELECT COALESCE(SUM((DATEDIFF(ci.end_date, ci.start_date) + 1) * m.price_per_day), 0)
         FROM cart_items ci
         INNER JOIN motorcycles m ON m.id = ci.motorcycle_id
         WHERE ci.user_id = :user_id'
    );
    $statement->execute([
        ':user_id' => $userId,
    ]);

    return (int) $statement->fetchColumn();
}
