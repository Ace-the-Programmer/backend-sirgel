-- ============================================================
-- TaraCommute Database Schema (Shared Hosting / x10hosting Version)
-- Note: Do NOT include CREATE DATABASE or USE statements here.
-- First create your database in cPanel / DirectAdmin, select it
-- in phpMyAdmin, then import this SQL file.
-- ============================================================

-- ------------------------------------------------------------
-- 1. Table: users (Full User Commuter Accounts with Auth)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_no VARCHAR(50) NOT NULL,
  username VARCHAR(50) NOT NULL,
  password VARCHAR(255) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL,
  course VARCHAR(100) NOT NULL,
  year_level TINYINT UNSIGNED NOT NULL DEFAULT 1,
  avatar VARCHAR(255) DEFAULT 'assets/icon/quenie-avatar.png',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_student_no (student_no),
  UNIQUE KEY uq_users_username (username),
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 2. Table: students (Required by Teacher's REST API Endpoint)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS students (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_no VARCHAR(50) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  course VARCHAR(100) NOT NULL,
  year_level TINYINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_students_student_no (student_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 3. Table: commute_history (Trips & Fare Estimates per User)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS commute_history (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  student_no VARCHAR(50) NOT NULL,
  mode ENUM('car', 'tricycle', 'bicycle', 'walk') NOT NULL DEFAULT 'tricycle',
  origin VARCHAR(150) NOT NULL,
  destination VARCHAR(150) NOT NULL,
  distance_km DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
  fare_amount DECIMAL(6, 2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_commute_student_no (student_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ------------------------------------------------------------
-- 4. Table: campus_locations (Searchable Campus & Transit Hubs)
-- ------------------------------------------------------------
CREATE TABLE IF NOT EXISTS campus_locations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  category VARCHAR(50) NOT NULL DEFAULT 'Campus',
  latitude DECIMAL(10, 8) NOT NULL,
  longitude DECIMAL(11, 8) NOT NULL,
  distance_km DECIMAL(5, 2) NOT NULL DEFAULT 1.00,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- Seed Sample Data
-- ============================================================

-- Seed Users
INSERT INTO users (student_no, username, password, full_name, email, course, year_level, avatar) VALUES
('2026-0001', 'juandc', 'password123', 'Juan Dela Cruz', 'juan.delacruz@campus.edu.ph', 'BSIT', 4, 'assets/icon/quenie-avatar.png'),
('2026-0002', 'mariasantos', 'password123', 'Maria Santos', 'maria.santos@campus.edu.ph', 'BSCS', 3, 'assets/icon/quenie-avatar.png'),
('2026-0003', 'pedro_reyes', 'password123', 'Pedro Reyes', 'pedro.reyes@campus.edu.ph', 'BSIS', 2, 'assets/icon/quenie-avatar.png')
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

-- Sync into students table for teacher's API compatibility
INSERT INTO students (student_no, full_name, course, year_level) VALUES
('2026-0001', 'Juan Dela Cruz', 'BSIT', 4),
('2026-0002', 'Maria Santos', 'BSCS', 3),
('2026-0003', 'Pedro Reyes', 'BSIS', 2)
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name);

-- Seed Commute History
INSERT INTO commute_history (student_no, mode, origin, destination, distance_km, fare_amount) VALUES
('2026-0001', 'car', 'Sumacab Campus', 'SM Mega Center', 6.1, 130.00),
('2026-0001', 'tricycle', 'Sumacab Campus', 'Santa Rosa Crossing', 3.4, 80.00),
('2026-0001', 'bicycle', 'Sumacab Campus', 'Pacific Mall NE', 2.3, 80.00),
('2026-0002', 'tricycle', 'Araullo University', 'Cabanatuan Central Terminal', 4.5, 95.00),
('2026-0003', 'walk', 'AU Gate 1', 'University Library', 0.5, 0.00);

-- Seed Campus Locations
INSERT INTO campus_locations (name, category, latitude, longitude, distance_km) VALUES
('Araullo University (Main Campus)', 'Campus', 15.491500, 120.972000, 0.00),
('Sumacab Campus', 'Campus', 15.474000, 120.957000, 2.50),
('SM City Cabanatuan', 'Mall', 15.478500, 120.959200, 3.20),
('Pacific Mall Cabanatuan', 'Mall', 15.488000, 120.968000, 1.80),
('NE Pacific Mall', 'Mall', 15.493500, 120.975000, 2.30),
('Cabanatuan Central Transport Terminal', 'Terminal', 15.485000, 120.963000, 4.10),
('SM Mega Center Cabanatuan', 'Mall', 15.486000, 120.967000, 6.10),
('Santa Rosa Junction', 'Transit Hub', 15.420000, 120.938000, 3.40);
