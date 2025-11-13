CREATE DATABASE IF NOT EXISTS parking_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE parking_db;

-- roles
CREATE TABLE IF NOT EXISTS roles (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE
);

-- users (minimal for now)
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100),
  email VARCHAR(150) UNIQUE,
  password_hash VARCHAR(255),
  role_id INT DEFAULT 2,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
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
  slot_type ENUM('compact','regular','large') DEFAULT 'regular',
  is_active TINYINT(1) DEFAULT 1,
  svg_id VARCHAR(100) NULL,
  svg_path TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (parking_lot_id) REFERENCES parking_lots(id),
  UNIQUE (parking_lot_id, slot_number)
);

-- reservations
CREATE TABLE IF NOT EXISTS reservations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  vehicle_number VARCHAR(50) NOT NULL,
  vehicle_type_id INT NULL,
  parking_slot_id INT NULL,
  parking_lot_id INT NOT NULL,
  start_time DATETIME NOT NULL,
  end_time DATETIME NOT NULL,
  status ENUM('booked','checked_in','checked_out','cancelled') DEFAULT 'booked',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- activity logs
CREATE TABLE IF NOT EXISTS activity_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NULL,
  action VARCHAR(255),
  details TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- seed data
INSERT IGNORE INTO roles (id, name) VALUES (1,'admin'),(2,'user');
INSERT INTO parking_lots (id, name, location, total_slots) VALUES (1,'Main Lot','Department Basement',5);
INSERT INTO parking_slots (parking_lot_id, slot_number, slot_type, svg_id) VALUES
 (1,'A1','regular','slot-1'),
 (1,'A2','regular','slot-2'),
 (1,'A3','regular','slot-3'),
 (1,'B1','compact','slot-4'),
 (1,'B2','compact','slot-5');
