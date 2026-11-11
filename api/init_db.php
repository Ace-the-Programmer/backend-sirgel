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

    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found at: $sqlFile");
    }

    $sql = file_get_contents($sqlFile);
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
