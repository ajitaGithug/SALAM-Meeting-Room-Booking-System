-- =========================================================
-- SALAM Meeting Room Booking System — Database Schema
-- Run this once on a fresh database to recreate the full structure
-- =========================================================

CREATE DATABASE IF NOT EXISTS salam_meeting_room_booking
    CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;

USE salam_meeting_room_booking;

-- ---------------------------------------------------------
-- Table: users
-- ---------------------------------------------------------
CREATE TABLE users (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    staff_name  VARCHAR(100) NOT NULL,
    staff_id    VARCHAR(20) NOT NULL UNIQUE,
    password    VARCHAR(255) NOT NULL,
    role        ENUM('staff','admin') NOT NULL DEFAULT 'staff',
    status      ENUM('active','inactive') NOT NULL DEFAULT 'active',
    created_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------
-- Table: bookings
-- ---------------------------------------------------------
CREATE TABLE bookings (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    staff_name    VARCHAR(100) NOT NULL,
    staff_id      VARCHAR(20) NOT NULL,
    purpose       VARCHAR(255) NOT NULL,
    booking_date  DATE NOT NULL,
    start_time    TIME NOT NULL,
    end_time      TIME NOT NULL,
    room          ENUM('Bilik Meeting','Bilik Seminar') NOT NULL,
    created_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- ---------------------------------------------------------
-- Foreign key: bookings.staff_id → users.staff_id
-- Added after both tables exist, and after data was cleaned
-- to ensure no orphaned staff_id values remained.
-- ---------------------------------------------------------
ALTER TABLE bookings
    ADD CONSTRAINT fk_staff
    FOREIGN KEY (staff_id) REFERENCES users(staff_id);

-- ---------------------------------------------------------
-- Seed data: first admin account
-- Replace the values below before running.
-- The password hash below must be generated with PHP's
-- password_hash() function — do NOT type a plain password here.
--
-- Example (run in a PHP script or php -a):
--   echo password_hash('yourpassword', PASSWORD_DEFAULT);
-- ---------------------------------------------------------
-- INSERT INTO users (staff_name, staff_id, password, role, status)
-- VALUES ('Admin Name', 'ADMIN1', '<paste_generated_hash_here>', 'admin', 'active');