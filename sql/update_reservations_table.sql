-- SQL to update existing reservations table
-- Run this in phpMyAdmin to add missing columns

-- Add vehicle_id column if it doesn't exist
ALTER TABLE reservations 
ADD COLUMN vehicle_id INT NULL AFTER user_id;

-- Add checked_in_at column if it doesn't exist
ALTER TABLE reservations 
ADD COLUMN checked_in_at DATETIME NULL AFTER status;

-- Add foreign key for vehicle_id if it doesn't exist
ALTER TABLE reservations 
ADD CONSTRAINT fk_reservations_vehicle_id 
FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL;

-- Migrate existing active reservations to history
-- This will only insert reservations that don't already exist in history
INSERT INTO reservation_history (reservation_id, user_id, slot_id, vehicle_id, vehicle_no, reservation_name, status, reserved_at, checked_in_at, created_at)
SELECT 
    r.id as reservation_id,
    r.user_id,
    r.slot_id,
    r.vehicle_id,
    r.vehicle_no,
    r.reservation_name,
    r.status,
    r.reserved_at,
    r.checked_in_at,
    NOW() as created_at
FROM reservations r
LEFT JOIN reservation_history rh ON rh.reservation_id = r.id
WHERE rh.id IS NULL;

