<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/config.php';

try {
    $pdo = get_db_connection();

    // Prefer hosting SQL if present (safe for shared hosting without CREATE DATABASE permissions)
    $sqlFile = __DIR__ . '/../sql/taracommute_hosting.sql';
    if (!file_exists($sqlFile)) {
        $sqlFile = __DIR__ . '/../sql/taracommute_schema.sql';
    }

    if (file_exists($sqlFile)) {
        $sql = file_get_contents($sqlFile);
    } else {
        // Embedded SQL Fallback for Remote Shared Hosting (x10hosting / cPanel)
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS users (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  student_no VARCHAR(50) NOT NULL UNIQUE,
  username VARCHAR(50) NOT NULL UNIQUE,
  password VARCHAR(255) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  email VARCHAR(150) NOT NULL UNIQUE,
  course VARCHAR(100) NOT NULL,
  school VARCHAR(100) DEFAULT 'Araullo University (Main Campus)',
  year_level TINYINT UNSIGNED NOT NULL DEFAULT 1,
  avatar VARCHAR(255) DEFAULT 'assets/icon/quenie-avatar.png',
  auth_token VARCHAR(255) DEFAULT NULL,
  token_expires_at DATETIME DEFAULT NULL,
  failed_attempts INT UNSIGNED NOT NULL DEFAULT 0,
  lockout_until DATETIME DEFAULT NULL,
  last_login DATETIME DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS students (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  student_no VARCHAR(50) NOT NULL UNIQUE,
  full_name VARCHAR(150) NOT NULL,
  course VARCHAR(100) NOT NULL,
  year_level TINYINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS commute_history (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  student_no VARCHAR(50) NOT NULL,
  mode ENUM('jeep', 'tricycle', 'bus', 'minibus', 'car', 'bicycle', 'walk') NOT NULL DEFAULT 'jeep',
  origin VARCHAR(150) NOT NULL,
  destination VARCHAR(150) NOT NULL,
  distance_km DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
  fare_amount DECIMAL(6, 2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_commute_student_no (student_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS campus_locations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  category VARCHAR(50) NOT NULL DEFAULT 'Campus',
  latitude DECIMAL(10, 8) NOT NULL,
  longitude DECIMAL(11, 8) NOT NULL,
  distance_km DECIMAL(5, 2) NOT NULL DEFAULT 0.00,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO campus_locations (name, category, latitude, longitude, distance_km) VALUES
('Araullo University (Main Campus)', 'Campus', 15.4915, 120.9720, 0.00),
('NEUST Sumacab Campus', 'Campus', 15.4740, 120.9570, 2.50),
('SM City Cabanatuan', 'Mall / Transit Hub', 15.4785, 120.9592, 3.20),
('Pacific Mall Cabanatuan', 'Mall', 15.4880, 120.9680, 1.80),
('Wesleyan University - Philippines', 'Campus', 15.4935, 120.9750, 3.80),
('Cabanatuan Central Transport Terminal', 'Terminal', 15.4850, 120.9630, 4.10),
('SM Mega Center Cabanatuan', 'Mall', 15.4860, 120.9670, 6.10),
('Santa Rosa Junction', 'Transit Hub', 15.4200, 120.9380, 3.40);
SQL;
    }
    $pdo->exec($sql);

    // Verify created tables in the current connected database
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Count rows
    $counts = [];
    foreach ($tables as $t) {
        $cntStmt = $pdo->query("SELECT COUNT(*) FROM `$t`");
        $counts[$t] = (int)$cntStmt->fetchColumn();
    }

    echo json_encode([
        'success' => true,
        'message' => 'TaraCommute schema and tables applied successfully!',
        'tables' => $tables,
        'row_counts' => $counts
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_PRETTY_PRINT);
}
