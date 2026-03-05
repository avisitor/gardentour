-- Maui Garden Tour Map - Database Schema
-- Run this SQL to create the required tables

-- Create database if it doesn't exist
CREATE DATABASE IF NOT EXISTS garden_tour 
    CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;

USE garden_tour;

-- Confirmed, visible submissions
CREATE TABLE IF NOT EXISTS submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NULL,
    address VARCHAR(500) NULL,
    email VARCHAR(255) NOT NULL,
    description TEXT NULL,
    image_path VARCHAR(500) NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_coords (latitude, longitude),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pending submissions awaiting email confirmation
CREATE TABLE IF NOT EXISTS pending_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    token VARCHAR(64) NOT NULL UNIQUE,
    name VARCHAR(255) NULL,
    address VARCHAR(500) NULL,
    email VARCHAR(255) NOT NULL,
    description TEXT NULL,
    image_path VARCHAR(500) NULL,
    latitude DECIMAL(10, 8) NOT NULL,
    longitude DECIMAL(11, 8) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    INDEX idx_token (token),
    INDEX idx_expires (expires_at),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: Event to auto-cleanup expired pending submissions
-- Uncomment if you want automatic cleanup (requires EVENT privilege)
-- 
-- DELIMITER //
-- CREATE EVENT IF NOT EXISTS cleanup_expired_pending
-- ON SCHEDULE EVERY 1 HOUR
-- DO
-- BEGIN
--     DELETE FROM pending_submissions WHERE expires_at < NOW();
-- END //
-- DELIMITER ;
