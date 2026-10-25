<?php

require_once __DIR__ . '/cart.php';
require_once __DIR__ . '/upload.php';

function process_checkout(PDO $db, $userId, array $bookingData, array $files)
{
    $userId = filter_var($userId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($userId === false) {
        throw new InvalidArgumentException('Data pelanggan tidak valid.');
    }

    $notes = isset($bookingData['notes']) && is_string($bookingData['notes'])
        ? trim($bookingData['notes'])
        : '';
    if (function_exists('mb_strlen') ? mb_strlen($notes, 'UTF-8') > 255 : strlen($notes) > 255) {
        throw new InvalidArgumentException('Catatan tambahan maksimal 255 karakter.');
    }

    if (!isset($files['ktp']) || !isset($files['sim'])) {
        throw new InvalidArgumentException('Dokumen KTP dan SIM wajib diunggah.');
    }

    if ($db->inTransaction()) {
        throw new RuntimeException('Proses checkout tidak dapat dimulai di dalam transaksi lain.');
    }

    $uploadedFiles = [];
    $db->beginTransaction();

    try {
        $cartStatement = $db->prepare(
            "SELECT ci.motorcycle_id, ci.start_date, ci.end_date, ci.pickup_method,
                    ci.delivery_address, m.brand, m.model, m.price_per_day, m.status AS motorcycle_status
             FROM cart_items ci
             INNER JOIN motorcycles m ON m.id = ci.motorcycle_id
             WHERE ci.user_id = :user_id
             ORDER BY ci.id ASC
             FOR UPDATE"
        );
        $cartStatement->execute([
            ':user_id' => $userId,
        ]);
        $cartItems = $cartStatement->fetchAll();

        if (!$cartItems) {
            throw new InvalidArgumentException('Keranjang kosong. Tambahkan motor sebelum checkout.');
        }

        $pickupMethod = $cartItems[0]['pickup_method'];
        $deliveryAddress = $cartItems[0]['delivery_address'] ?? '';
        if (!in_array($pickupMethod, ['ambil_sendiri', 'diantar'], true)) {
            throw new RuntimeException('Metode pengambilan pada keranjang tidak valid.');
        }

        foreach ($cartItems as $item) {
            $itemAddress = $item['delivery_address'] ?? '';
            if ($item['pickup_method'] !== $pickupMethod || $itemAddress !== $deliveryAddress) {
                throw new InvalidArgumentException(
                    'Pilihan pengambilan atau alamat di keranjang belum sama untuk seluruh motor. '
                    . 'Atur semuanya dari halaman keranjang sebelum checkout.'
                );
            }
        }

        if ($pickupMethod === 'diantar' && trim($deliveryAddress) === '') {
            throw new InvalidArgumentException('Alamat pengantaran belum diatur di keranjang.');
        }
        if (strlen($deliveryAddress) > 65535) {
            throw new InvalidArgumentException('Alamat pengantaran terlalu panjang.');
        }

        $totalPrice = 0;
        $maxUnsignedInt = 4294967295;
        foreach ($cartItems as &$item) {
            if ($item['motorcycle_status'] !== 'tersedia') {
                throw new InvalidArgumentException(
                    'Motor ' . $item['brand'] . ' ' . $item['model'] . ' sudah tidak tersedia.'
                );
            }

            validate_cart_dates($item['start_date'], $item['end_date']);
            $startDate = new DateTimeImmutable($item['start_date']);
            $endDate = new DateTimeImmutable($item['end_date']);
            $item['total_days'] = (int) $startDate->diff($endDate)->days + 1;
            $item['price_per_day'] = (int) $item['price_per_day'];

            if ($item['total_days'] > 65535) {
                throw new InvalidArgumentException('Durasi sewa melebihi batas yang dapat diproses.');
            }

            $item['subtotal'] = $item['total_days'] * $item['price_per_day'];
            if ($item['subtotal'] > $maxUnsignedInt) {
                throw new InvalidArgumentException('Subtotal salah satu motor melebihi batas sistem.');
            }

            $totalPrice += $item['subtotal'];
            if ($totalPrice > $maxUnsignedInt) {
                throw new InvalidArgumentException('Total biaya melebihi batas sistem.');
            }
        }
        unset($item);

        $uploadedFiles[] = upload_document($files['ktp'], STORAGE_PATH, 'ktp');
        $uploadedFiles[] = upload_document($files['sim'], STORAGE_PATH, 'sim');

        $bookingCode = null;
        $bookingId = null;
        $insertBooking = $db->prepare(
            "INSERT INTO bookings
                (booking_code, user_id, pickup_method, delivery_address, notes, total_price,
                 ktp_file, sim_file, status, payment_status)
             VALUES
                (:booking_code, :user_id, :pickup_method, :delivery_address, :notes, :total_price,
                 :ktp_file, :sim_file, 'pending', 'belum_bayar')"
        );

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $bookingCode = 'RM-' . date('Ymd') . '-' . str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            try {
                $insertBooking->execute([
                    ':booking_code' => $bookingCode,
                    ':user_id' => $userId,
                    ':pickup_method' => $pickupMethod,
                    ':delivery_address' => $pickupMethod === 'diantar' ? $deliveryAddress : null,
                    ':notes' => $notes !== '' ? $notes : null,
                    ':total_price' => $totalPrice,
                    ':ktp_file' => $uploadedFiles[0],
                    ':sim_file' => $uploadedFiles[1],
                ]);
                $bookingId = (int) $db->lastInsertId();
                break;
            } catch (PDOException $exception) {
                $errorInfo = $exception->errorInfo;
                $isDuplicateCode = isset($errorInfo[1]) && (int) $errorInfo[1] === 1062;
                if (!$isDuplicateCode || $attempt === 4) {
                    throw $exception;
                }
            }
        }

        if ($bookingId === null) {
            throw new RuntimeException('Kode booking unik tidak dapat dibuat.');
        }

        $insertItem = $db->prepare(
            'INSERT INTO booking_items
                (booking_id, motorcycle_id, start_date, end_date, total_days, price_per_day, subtotal)
             VALUES
                (:booking_id, :motorcycle_id, :start_date, :end_date, :total_days, :price_per_day, :subtotal)'
        );
        foreach ($cartItems as $item) {
            $insertItem->execute([
                ':booking_id' => $bookingId,
                ':motorcycle_id' => $item['motorcycle_id'],
                ':start_date' => $item['start_date'],
                ':end_date' => $item['end_date'],
                ':total_days' => $item['total_days'],
                ':price_per_day' => $item['price_per_day'],
                ':subtotal' => $item['subtotal'],
            ]);
        }

        $insertLog = $db->prepare(
            "INSERT INTO booking_status_logs (booking_id, old_status, new_status, changed_by, note)
             VALUES (:booking_id, NULL, 'pending', :changed_by, :note)"
        );
        $insertLog->execute([
            ':booking_id' => $bookingId,
            ':changed_by' => $userId,
            ':note' => 'Pesanan dibuat oleh pelanggan',
        ]);

        $deleteCart = $db->prepare('DELETE FROM cart_items WHERE user_id = :user_id');
        $deleteCart->execute([
            ':user_id' => $userId,
        ]);

        $db->commit();

        return [
            'id' => $bookingId,
            'booking_code' => $bookingCode,
        ];
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            try {
                $db->rollBack();
            } catch (Throwable $rollbackException) {
                error_log('Checkout rollback error: ' . $rollbackException->getMessage());
            }
        }

        foreach ($uploadedFiles as $filename) {
            $path = STORAGE_PATH . $filename;
            if (is_file($path) && !unlink($path)) {
                error_log('Unable to remove uncommitted checkout document: ' . $path);
            }
        }

        throw $exception;
    }
}

function get_user_bookings(PDO $db, $userId)
{
    $statement = $db->prepare(
        'SELECT id, booking_code, pickup_method, delivery_address, total_price,
                status, payment_status, created_at
         FROM bookings
         WHERE user_id = :user_id
         ORDER BY created_at DESC, id DESC'
    );
    $statement->execute([
        ':user_id' => $userId,
    ]);

    return $statement->fetchAll();
}

function get_booking_detail_by_id(PDO $db, $bookingId, $userId)
{
    $isAdmin = isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';
    $sql = 'SELECT b.id, b.booking_code, b.user_id, b.pickup_method, b.delivery_address,
                   b.notes, b.total_price, b.ktp_file, b.sim_file, b.status,
                   b.payment_status, b.cancelled_at, b.cancel_reason, b.created_at, b.updated_at
            FROM bookings b
            WHERE b.id = :booking_id';
    $parameters = [
        ':booking_id' => $bookingId,
    ];
    if (!$isAdmin) {
        $sql .= ' AND b.user_id = :user_id';
        $parameters[':user_id'] = $userId;
    }
    $sql .= ' LIMIT 1';

    $statement = $db->prepare($sql);
    $statement->execute($parameters);
    $booking = $statement->fetch();
    if (!$booking) {
        return null;
    }

    $itemsStatement = $db->prepare(
        'SELECT bi.id AS item_id, bi.motorcycle_id, bi.start_date, bi.end_date,
                bi.total_days, bi.price_per_day, bi.subtotal, m.brand, m.model, m.image,
                m.plate_number
         FROM booking_items bi
         INNER JOIN motorcycles m ON m.id = bi.motorcycle_id
         WHERE bi.booking_id = :booking_id
         ORDER BY bi.id ASC'
    );
    $itemsStatement->execute([
        ':booking_id' => $booking['id'],
    ]);
    $booking['items'] = $itemsStatement->fetchAll();

    return $booking;
}

function get_booking_logs(PDO $db, $bookingId)
{
    $statement = $db->prepare(
        'SELECT id, old_status, new_status, changed_by, note, created_at
         FROM booking_status_logs
         WHERE booking_id = :booking_id
         ORDER BY created_at ASC, id ASC'
    );
    $statement->execute([
        ':booking_id' => $bookingId,
    ]);

    return $statement->fetchAll();
}

function update_booking_item_dates(PDO $db, $bookingId, $userId, $itemId, $newStartDate, $newEndDate)
{
    $bookingId = filter_var($bookingId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $userId = filter_var($userId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $itemId = filter_var($itemId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($bookingId === false || $userId === false || $itemId === false) {
        throw new InvalidArgumentException('Data booking atau item tidak valid.');
    }

    validate_cart_dates($newStartDate, $newEndDate);

    if ($db->inTransaction()) {
        throw new RuntimeException('Perubahan jadwal tidak dapat dimulai di dalam transaksi lain.');
    }

    $db->beginTransaction();
    try {
        $bookingStatement = $db->prepare(
            'SELECT status
             FROM bookings
             WHERE id = :booking_id AND user_id = :user_id
             LIMIT 1
             FOR UPDATE'
        );
        $bookingStatement->execute([
            ':booking_id' => $bookingId,
            ':user_id' => $userId,
        ]);
        $booking = $bookingStatement->fetch();

        if (!$booking) {
            throw new RuntimeException('Pesanan tidak ditemukan atau tidak dapat diakses.');
        }
        if ($booking['status'] !== 'pending') {
            throw new RuntimeException('Jadwal hanya dapat diubah saat pesanan berstatus pending.');
        }

        $itemStatement = $db->prepare(
            'SELECT id, price_per_day
             FROM booking_items
             WHERE id = :item_id AND booking_id = :booking_id
             LIMIT 1
             FOR UPDATE'
        );
        $itemStatement->execute([
            ':item_id' => $itemId,
            ':booking_id' => $bookingId,
        ]);
        $item = $itemStatement->fetch();
        if (!$item) {
            throw new RuntimeException('Unit motor tidak ditemukan pada pesanan ini.');
        }

        $start = new DateTimeImmutable($newStartDate);
        $end = new DateTimeImmutable($newEndDate);
        $totalDays = (int) $start->diff($end)->days + 1;
        $pricePerDay = (int) $item['price_per_day'];
        $subtotal = $totalDays * $pricePerDay;
        if ($totalDays > 65535 || $subtotal > 4294967295) {
            throw new InvalidArgumentException('Durasi atau subtotal melebihi batas sistem.');
        }

        $updateItem = $db->prepare(
            'UPDATE booking_items
             SET start_date = :start_date,
                 end_date = :end_date,
                 total_days = :total_days,
                 subtotal = :subtotal
             WHERE id = :item_id AND booking_id = :booking_id'
        );
        $updateItem->execute([
            ':start_date' => $newStartDate,
            ':end_date' => $newEndDate,
            ':total_days' => $totalDays,
            ':subtotal' => $subtotal,
            ':item_id' => $itemId,
            ':booking_id' => $bookingId,
        ]);

        $totalStatement = $db->prepare(
            'SELECT COALESCE(SUM(subtotal), 0)
             FROM booking_items
             WHERE booking_id = :booking_id'
        );
        $totalStatement->execute([
            ':booking_id' => $bookingId,
        ]);
        $newTotal = (int) $totalStatement->fetchColumn();
        if ($newTotal > 4294967295) {
            throw new InvalidArgumentException('Total biaya melebihi batas sistem.');
        }

        $updateBooking = $db->prepare(
            'UPDATE bookings
             SET total_price = :total_price
             WHERE id = :booking_id AND user_id = :user_id AND status = :status'
        );
        $updateBooking->execute([
            ':total_price' => $newTotal,
            ':booking_id' => $bookingId,
            ':user_id' => $userId,
            ':status' => 'pending',
        ]);
        if ($updateBooking->rowCount() === 0) {
            $verifyStatement = $db->prepare(
                'SELECT status FROM bookings WHERE id = :booking_id AND user_id = :user_id'
            );
            $verifyStatement->execute([
                ':booking_id' => $bookingId,
                ':user_id' => $userId,
            ]);
            $current = $verifyStatement->fetch();
            if (!$current || $current['status'] !== 'pending') {
                throw new RuntimeException('Status pesanan berubah; jadwal tidak dapat diperbarui.');
            }
        }

        $logStatement = $db->prepare(
            'INSERT INTO booking_status_logs
                (booking_id, old_status, new_status, changed_by, note)
             VALUES (:booking_id, :old_status, :new_status, :changed_by, :note)'
        );
        $logStatement->execute([
            ':booking_id' => $bookingId,
            ':old_status' => 'pending',
            ':new_status' => 'pending',
            ':changed_by' => $userId,
            ':note' => 'Pengguna memperbarui tanggal sewa',
        ]);

        $db->commit();

        return [
            'total_days' => $totalDays,
            'subtotal' => $subtotal,
            'total_price' => $newTotal,
        ];
    } catch (Throwable $exception) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }

        throw $exception;
    }
}

function cancel_booking(PDO $db, $bookingId, $userId, $cancelReason)
{
    $bookingId = filter_var($bookingId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $userId = filter_var($userId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($bookingId === false || $userId === false) {
        throw new InvalidArgumentException('Data booking tidak valid.');
    }

    if (!is_string($cancelReason)) {
        throw new InvalidArgumentException('Alasan pembatalan wajib diisi.');
    }
    $cancelReason = trim($cancelReason);
    if ($cancelReason === '') {
        throw new InvalidArgumentException('Alasan pembatalan wajib diisi.');
    }
    if (function_exists('mb_strlen') ? mb_strlen($cancelReason, 'UTF-8') > 255 : strlen($cancelReason) > 255) {
        throw new InvalidArgumentException('Alasan pembatalan maksimal 255 karakter.');
    }

    if ($db->inTransaction()) {
        throw new RuntimeException('Pembatalan tidak dapat dimulai di dalam transaksi lain.');
    }

    $db->beginTransaction();
    try {
        $bookingStatement = $db->prepare(
            'SELECT status
             FROM bookings
             WHERE id = :booking_id AND user_id = :user_id
             LIMIT 1
             FOR UPDATE'
        );
        $bookingStatement->execute([
            ':booking_id' => $bookingId,
            ':user_id' => $userId,
        ]);
        $booking = $bookingStatement->fetch();

        if (!$booking) {
            throw new RuntimeException('Pesanan tidak ditemukan atau tidak dapat diakses.');
        }
        if ($booking['status'] !== 'pending') {
            throw new RuntimeException('Pesanan hanya dapat dibatalkan saat berstatus pending.');
        }

        $updateStatement = $db->prepare(
            "UPDATE bookings
             SET status = 'dibatalkan',
                 cancelled_at = NOW(),
                 cancel_reason = :cancel_reason
             WHERE id = :booking_id AND user_id = :user_id AND status = :old_status"
        );
        $updateStatement->execute([
            ':cancel_reason' => $cancelReason,
            ':booking_id' => $bookingId,
            ':user_id' => $userId,
            ':old_status' => 'pending',
        ]);
        if ($updateStatement->rowCount() !== 1) {
            throw new RuntimeException('Status pesanan berubah; pembatalan tidak dapat dilakukan.');
        }

        $logStatement = $db->prepare(
            'INSERT INTO booking_status_logs
                (booking_id, old_status, new_status, changed_by, note)
             VALUES (:booking_id, :old_status, :new_status, :changed_by, :note)'
        );
        $logStatement->execute([
            ':booking_id' => $bookingId,
            ':old_status' => $booking['status'],
            ':new_status' => 'dibatalkan',
            ':changed_by' => $userId,
            ':note' => 'Dibatalkan oleh pelanggan: ' . $cancelReason,
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
