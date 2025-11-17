CREATE DATABASE IF NOT EXISTS parking_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE parking_db;

-- roles
CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE
);

-- users
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role_id INT DEFAULT 2,
  user_type ENUM('normal','faculty','hod') DEFAULT 'normal',
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (role_id) REFERENCES roles(id)
);

-- parking lots and slots
CREATE TABLE IF NOT EXISTS parking_lots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  location VARCHAR(255),
  total_slots INT DEFAULT 0
);

CREATE TABLE IF NOT EXISTS parking_slots (
  id INT AUTO_INCREMENT PRIMARY KEY,
  parking_lot_id INT NOT NULL,
  slot_number VARCHAR(50) NOT NULL,
  slot_type VARCHAR(50) DEFAULT 'regular',
  is_active TINYINT(1) DEFAULT 1,
  svg_id VARCHAR(50) NULL,
  label VARCHAR(50) NULL,
  access_group ENUM('hod','faculty','general') DEFAULT 'general',
  FOREIGN KEY (parking_lot_id) REFERENCES parking_lots(id),
  UNIQUE KEY svg_id (svg_id)
);

-- reservations
CREATE TABLE IF NOT EXISTS reservations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slot_id INT NOT NULL,
  reservation_name VARCHAR(100) NULL,
  user_id INT NOT NULL,
  vehicle_id INT NULL,
  vehicle_no VARCHAR(20) NULL,
  status ENUM('reserved','checked_in') DEFAULT 'reserved',
  reservation_start_time DATETIME NULL,
  reservation_end_time DATETIME NULL,
  checked_in_at DATETIME NULL,
  reserved_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_res_slot (slot_id),
  UNIQUE KEY uq_res_user (user_id),
  INDEX idx_reservation_end_time (reservation_end_time),
  FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL
);

-- activity logs
CREATE TABLE IF NOT EXISTS activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(255),
  details TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- reservation_history: PERMANENT history of all reservations (only deleted when user account is deleted)
-- This table stores complete parking history with dates, slots, timings, and vehicle information
-- IMPORTANT: History is permanent and persists even after slots are released/cancelled
CREATE TABLE IF NOT EXISTS reservation_history (
  id INT AUTO_INCREMENT PRIMARY KEY,
  reservation_id INT NOT NULL,
  user_id INT NOT NULL,
  slot_id INT NOT NULL,
  vehicle_id INT NULL,
  vehicle_no VARCHAR(20) NULL,
  reservation_name VARCHAR(100) NULL,
  status ENUM('reserved','checked_in','cancelled','completed') DEFAULT 'reserved',
  reserved_at DATETIME NOT NULL,
  reservation_start_time DATETIME NULL,
  reservation_end_time DATETIME NULL,
  checked_in_at DATETIME NULL,
  checked_out_at DATETIME NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_id (user_id),
  INDEX idx_reservation_id (reservation_id),
  INDEX idx_reserved_at (reserved_at),
  INDEX idx_status (status),
  INDEX idx_vehicle_id (vehicle_id),
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (slot_id) REFERENCES parking_slots(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- seed data
INSERT IGNORE INTO roles (id, name) VALUES (1,'admin'),(2,'user');
INSERT IGNORE INTO parking_lots (id, name, location, total_slots) VALUES (1,'Main Lot','Department Basement',27);
INSERT IGNORE INTO parking_slots (id, parking_lot_id, slot_number, slot_type, is_active, svg_id, label, access_group) VALUES
 (1, 1, 'G1', 'regular', 1, 'slot-1', 'Slot 1', 'faculty'),
 (2, 1, 'G2', 'regular', 1, 'slot-2', 'Slot 2', 'faculty'),
 (3, 1, 'G3', 'regular', 1, 'slot-3', 'Slot 3', 'faculty'),
 (4, 1, 'G4', 'regular', 1, 'slot-4', 'Slot 4', 'faculty'),
 (5, 1, 'G5', 'regular', 1, 'slot-5', 'Slot 5', 'faculty'),
 (6, 1, 'G6', 'regular', 1, 'slot-6', 'Slot 6', 'faculty'),
 (7, 1, 'G7', 'regular', 1, 'slot-7', 'Slot 7', 'faculty'),
 (8, 1, 'HOD1', 'regular', 1, 'slot-8', 'Slot 8', 'hod'),
 (9, 1, 'R1', 'regular', 1, 'slot-9', 'Slot 9', 'general'),
 (10, 1, 'R2', 'regular', 1, 'slot-10', 'Slot 10', 'general'),
 (11, 1, 'R3', 'regular', 1, 'slot-11', 'Slot 11', 'general'),
 (12, 1, 'R4', 'regular', 1, 'slot-12', 'Slot 12', 'general'),
 (13, 1, 'R5', 'regular', 1, 'slot-13', 'Slot 13', 'general'),
 (14, 1, 'R6', 'regular', 1, 'slot-14', 'Slot 14', 'general'),
 (15, 1, 'R14', 'regular', 1, 'slot-15', 'Slot 15', 'general'),
 (16, 1, 'R15', 'regular', 1, 'slot-16', 'Slot 16', 'general'),
 (17, 1, 'R16', 'regular', 1, 'slot-17', 'Slot 17', 'general'),
 (18, 1, 'R17', 'regular', 1, 'slot-18', 'Slot 18', 'general'),
 (19, 1, 'R18', 'regular', 1, 'slot-19', 'Slot 19', 'general'),
 (20, 1, 'R7', 'regular', 1, 'slot-20', 'Slot 20', 'general'),
 (21, 1, 'R8', 'regular', 1, 'slot-21', 'Slot 21', 'general'),
 (22, 1, 'R9', 'regular', 1, 'slot-22', 'Slot 22', 'general'),
 (23, 1, 'R10', 'regular', 1, 'slot-23', 'Slot 23', 'general'),
 (24, 1, 'R11', 'regular', 1, 'slot-24', 'Slot 24', 'general'),
 (25, 1, 'R12', 'regular', 1, 'slot-25', 'Slot 25', 'general'),
 (26, 1, 'R13', 'regular', 1, 'slot-26', 'Slot 26', 'general'),
 (27, 1, 'R14b', 'regular', 1, 'slot-27', 'Slot 27', 'general');
