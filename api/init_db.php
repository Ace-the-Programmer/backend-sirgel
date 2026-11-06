<?php
header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = new PDO('mysql:host=localhost;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sqlFile = __DIR__ . '/../sql/taracommute_schema.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception("SQL file not found at: $sqlFile");
    }

    $sql = file_get_contents($sqlFile);
    $pdo->exec($sql);

    // Verify created tables
    $pdo->exec("USE ionic_students_api");
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
