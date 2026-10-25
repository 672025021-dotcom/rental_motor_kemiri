

CREATE DATABASE IF NOT EXISTS rent_motor_kemiri CHARACTER
SET
    utf8mb4 COLLATE utf8mb4_unicode_ci;

USE rent_motor_kemiri;

-- ------------------------------------------------------------
-- 1. USERS  (Register, lihat profil, update profil)
-- ------------------------------------------------------------
CREATE TABLE users (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  full_name     VARCHAR(100) NOT NULL,
  email         VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,            -- isi dengan password_hash() PHP
  phone_wa      VARCHAR(20)  NOT NULL,            -- nomor WhatsApp
  address       TEXT NULL,
  role          ENUM('customer','admin') NOT NULL DEFAULT 'customer',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB;
 
-- ------------------------------------------------------------
-- 2. MOTORCYCLES  (Katalog & filter)
--    Satu baris = satu unit motor (punya plat nomor sendiri).
--    "status" = kondisi unit secara umum. Ketersediaan pada
--    tanggal tertentu dihitung dari booking_items + bookings.
-- ------------------------------------------------------------
CREATE TABLE motorcycles (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  brand         VARCHAR(50)  NOT NULL,
  model         VARCHAR(80)  NOT NULL,
  plate_number  VARCHAR(15)  NOT NULL,
  type          ENUM('matic','manual','sport') NOT NULL,
  engine_cc     SMALLINT UNSIGNED NULL,
  year          SMALLINT UNSIGNED NULL,
  color         VARCHAR(30) NULL,
  description   VARCHAR(255) NULL,                -- spesifikasi singkat
  price_per_day INT UNSIGNED NOT NULL,            -- rupiah, tanpa desimal
  image         VARCHAR(255) NULL,                -- nama file foto
  status        ENUM('tersedia','maintenance','nonaktif') NOT NULL DEFAULT 'tersedia',
  created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_motor_plate (plate_number),
  KEY idx_motor_type_status (type, status)
) ENGINE=InnoDB;
 
-- ------------------------------------------------------------
-- 3. CART_ITEMS  (Keranjang sewa / draft booking)
--    - Create : INSERT saat klik "Tambah ke Keranjang"
--    - Read   : SELECT per user_id, JOIN motorcycles untuk harga
--    - Delete : HARD DELETE (DELETE FROM cart_items WHERE id=:id AND user_id=:uid)
--    Estimasi biaya TIDAK disimpan, dihitung saat ditampilkan:
--      (DATEDIFF(end_date, start_date) + 1) * motorcycles.price_per_day
--    Setelah checkout berhasil, baris keranjang yang diproses dihapus.
-- ------------------------------------------------------------
CREATE TABLE cart_items (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id          INT UNSIGNED NOT NULL,
  motorcycle_id    INT UNSIGNED NOT NULL,
  start_date       DATE NOT NULL,                 -- estimasi tanggal mulai
  end_date         DATE NOT NULL,                 -- estimasi tanggal selesai
  pickup_method    ENUM('ambil_sendiri','diantar') NOT NULL DEFAULT 'ambil_sendiri',
  delivery_address TEXT NULL,                     -- isi jika 'diantar'
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cart_user_motor (user_id, motorcycle_id),   -- 1 motor hanya 1x di keranjang
  KEY idx_cart_motor (motorcycle_id),
  CONSTRAINT fk_cart_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_cart_motor
    FOREIGN KEY (motorcycle_id) REFERENCES motorcycles(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT chk_cart_dates CHECK (end_date >= start_date)
) ENGINE=InnoDB;
 
-- ------------------------------------------------------------
-- 4. BOOKINGS  (Checkout -> pesanan resmi; header pesanan)
--    - Satu pesanan bisa berisi lebih dari satu motor
--      (detailnya ada di booking_items).
--    - Pembatalan = soft delete: status 'dibatalkan' + cancelled_at.
-- ------------------------------------------------------------
CREATE TABLE bookings (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_code     VARCHAR(20)  NOT NULL,         -- contoh: RM-20261025-0001 (dibuat di PHP)
  user_id          INT UNSIGNED NOT NULL,
 
  pickup_method    ENUM('ambil_sendiri','diantar') NOT NULL DEFAULT 'ambil_sendiri',
  delivery_address TEXT NULL,                     -- wajib diisi jika 'diantar'
  notes            VARCHAR(255) NULL,
 
  total_price      INT UNSIGNED NOT NULL,         -- jumlah semua subtotal item (dihitung server)
 
  ktp_file         VARCHAR(255) NOT NULL,         -- nama file KTP
  sim_file         VARCHAR(255) NOT NULL,         -- nama file SIM
 
  status           ENUM('pending','dikonfirmasi','berjalan','selesai','dibatalkan')
                   NOT NULL DEFAULT 'pending',
  payment_status   ENUM('belum_bayar','lunas','dikembalikan')
                   NOT NULL DEFAULT 'belum_bayar',
 
  cancelled_at     DATETIME NULL,
  cancel_reason    VARCHAR(255) NULL,
 
  created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 
  PRIMARY KEY (id),
  UNIQUE KEY uq_booking_code (booking_code),
  KEY idx_booking_user (user_id, status),
  CONSTRAINT fk_booking_user
    FOREIGN KEY (user_id) REFERENCES users(id)
    ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB;
 
-- ------------------------------------------------------------
-- 5. BOOKING_ITEMS  (rincian motor per pesanan)
--    Harga disalin saat checkout (snapshot), jadi perubahan harga
--    motor tidak mengubah pesanan lama.
--    Tanggal sewa ada di sini, sehingga edit tanggal = UPDATE
--    booking_items, lalu hitung ulang total_price di bookings.
-- ------------------------------------------------------------
CREATE TABLE booking_items (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id     INT UNSIGNED NOT NULL,
  motorcycle_id  INT UNSIGNED NOT NULL,
  start_date     DATE NOT NULL,
  end_date       DATE NOT NULL,
  total_days     SMALLINT UNSIGNED NOT NULL,      -- DATEDIFF(end,start)+1
  price_per_day  INT UNSIGNED NOT NULL,           -- snapshot harga
  subtotal       INT UNSIGNED NOT NULL,           -- total_days * price_per_day
  PRIMARY KEY (id),
  KEY idx_item_booking (booking_id),
  KEY idx_item_motor_dates (motorcycle_id, start_date, end_date),  -- cek bentrok jadwal
  CONSTRAINT fk_item_booking
    FOREIGN KEY (booking_id) REFERENCES bookings(id)
    ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT fk_item_motor
    FOREIGN KEY (motorcycle_id) REFERENCES motorcycles(id)
    ON DELETE RESTRICT ON UPDATE CASCADE,
  CONSTRAINT chk_item_dates CHECK (end_date >= start_date)
) ENGINE=InnoDB;
 
-- ------------------------------------------------------------
-- 6. BOOKING_STATUS_LOGS  (timeline status pesanan)
-- ------------------------------------------------------------
CREATE TABLE booking_status_logs (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  booking_id  INT UNSIGNED NOT NULL,
  old_status  VARCHAR(20) NULL,
  new_status  VARCHAR(20) NOT NULL,
  changed_by  INT UNSIGNED NULL,                  -- users.id (customer/admin)
  note        VARCHAR(255) NULL,
  created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_log_booking (booking_id),
  CONSTRAINT fk_log_booking
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
  CONSTRAINT fk_log_user
    FOREIGN KEY (changed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
 
-- ------------------------------------------------------------
-- DATA CONTOH
-- ------------------------------------------------------------
INSERT INTO motorcycles
  (brand, model, plate_number, type, engine_cc, year, color, description, price_per_day, image)
VALUES
  ('Honda',  'Beat',      'H 1001 AA', 'matic',  110, 2022, 'Putih', 'Irit, ringan, cocok dalam kota', 70000,  'honda_beat.webp'),
  ('Honda',  'Vario 160', 'H 1002 AB', 'matic',  160, 2023, 'Hitam', 'Nyaman untuk jarak menengah',    100000, 'honda_vario160.webp'),
  ('Yamaha', 'NMAX',      'H 1003 AC', 'matic',  155, 2023, 'Abu',   'Bagasi luas, cocok touring',     150000, 'yamaha_nmax155.webp'),
  ('Honda',  'CRF150L',   'H 1004 AD', 'manual', 150, 2022, 'Merah', 'Trail untuk jalur menanjak',     175000, 'honda_crf150l.jpeg'),
  ('Yamaha', 'R15',       'H 1005 AE', 'sport',  155, 2023, 'Biru',  'Motor sport harian',             200000, 'yamaha_r15.jpg');
