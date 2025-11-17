-- SQL to add reservation timing columns
-- Run this in phpMyAdmin

-- Add reservation timing columns to reservations table
ALTER TABLE reservations 
ADD COLUMN IF NOT EXISTS reservation_start_time DATETIME NULL AFTER status;

ALTER TABLE reservations 
ADD COLUMN IF NOT EXISTS reservation_end_time DATETIME NULL AFTER reservation_start_time;

-- Add index for efficient auto-release queries
ALTER TABLE reservations 
ADD INDEX IF NOT EXISTS idx_reservation_end_time (reservation_end_time);

-- Add reservation timing columns to reservation_history table
ALTER TABLE reservation_history 
ADD COLUMN IF NOT EXISTS reservation_start_time DATETIME NULL AFTER reserved_at;

ALTER TABLE reservation_history 
ADD COLUMN IF NOT EXISTS reservation_end_time DATETIME NULL AFTER reservation_start_time;

-- Note: If IF NOT EXISTS doesn't work in your MySQL version, use these instead:
-- ALTER TABLE reservations ADD COLUMN reservation_start_time DATETIME NULL AFTER status;
-- ALTER TABLE reservations ADD COLUMN reservation_end_time DATETIME NULL AFTER reservation_start_time;
-- ALTER TABLE reservations ADD INDEX idx_reservation_end_time (reservation_end_time);
-- ALTER TABLE reservation_history ADD COLUMN reservation_start_time DATETIME NULL AFTER reserved_at;
-- ALTER TABLE reservation_history ADD COLUMN reservation_end_time DATETIME NULL AFTER reservation_start_time;

