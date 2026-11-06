<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function respond(int $status, array $payload): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    $pdo = get_db_connection();
} catch (PDOException $e) {
    respond(500, ['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
}

$search = trim($_GET['search'] ?? '');
if ($search !== '') {
    $stmt = $pdo->prepare('SELECT id, name, category, latitude, longitude, distance_km FROM campus_locations WHERE name LIKE :s ORDER BY name ASC');
    $stmt->execute([':s' => '%' . $search . '%']);
} else {
    $stmt = $pdo->query('SELECT id, name, category, latitude, longitude, distance_km FROM campus_locations ORDER BY id ASC');
}

$locations = $stmt->fetchAll();
respond(200, ['success' => true, 'data' => $locations]);

