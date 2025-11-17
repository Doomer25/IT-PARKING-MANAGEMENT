-- SQL to fix reservation_history table
-- Run this in phpMyAdmin to add missing columns

-- Check if reservation_history table exists, if not create it
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

-- Add checked_in_at column if it doesn't exist
ALTER TABLE reservation_history 
ADD COLUMN IF NOT EXISTS checked_in_at DATETIME NULL AFTER reserved_at;

-- Add checked_out_at column if it doesn't exist  
ALTER TABLE reservation_history 
ADD COLUMN IF NOT EXISTS checked_out_at DATETIME NULL AFTER checked_in_at;

-- Note: If ADD COLUMN IF NOT EXISTS doesn't work, use these instead:
-- ALTER TABLE reservation_history ADD COLUMN checked_in_at DATETIME NULL AFTER reserved_at;
-- ALTER TABLE reservation_history ADD COLUMN checked_out_at DATETIME NULL AFTER checked_in_at;

