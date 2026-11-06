<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
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

function read_json_body(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || trim($raw) === '') {
        respond(400, ['success' => false, 'message' => 'Request body is required.']);
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        respond(400, ['success' => false, 'message' => 'Invalid JSON body.']);
    }
    return $decoded;
}

try {
    $pdo = get_db_connection();
} catch (PDOException $e) {
    respond(500, ['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
}

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $studentNo = $_GET['student_no'] ?? '';
    if ($studentNo !== '') {
        $stmt = $pdo->prepare('SELECT id, student_no, mode, origin, destination, distance_km, fare_amount, created_at FROM commute_history WHERE student_no = :student_no ORDER BY id DESC');
        $stmt->execute([':student_no' => $studentNo]);
    } else {
        $stmt = $pdo->query('SELECT id, student_no, mode, origin, destination, distance_km, fare_amount, created_at FROM commute_history ORDER BY id DESC LIMIT 50');
    }
    $trips = $stmt->fetchAll();
    respond(200, ['success' => true, 'data' => $trips]);
}

if ($method === 'POST') {
    $body = read_json_body();
    $studentNo = trim((string)($body['student_no'] ?? ''));
    $mode = trim((string)($body['mode'] ?? 'tricycle'));
    $origin = trim((string)($body['origin'] ?? ''));
    $destination = trim((string)($body['destination'] ?? ''));
    $distance = (float)($body['distance_km'] ?? 0.0);
    $fare = (float)($body['fare_amount'] ?? 0.0);

    if ($studentNo === '' || $origin === '' || $destination === '') {
        respond(400, ['success' => false, 'message' => 'student_no, origin, and destination are required.']);
    }

    $stmt = $pdo->prepare(
        'INSERT INTO commute_history (student_no, mode, origin, destination, distance_km, fare_amount)
         VALUES (:student_no, :mode, :origin, :destination, :distance_km, :fare_amount)'
    );
    $stmt->execute([
        ':student_no' => $studentNo,
        ':mode' => $mode,
        ':origin' => $origin,
        ':destination' => $destination,
        ':distance_km' => $distance,
        ':fare_amount' => $fare
    ]);

    $id = (int)$pdo->lastInsertId();
    respond(201, [
        'success' => true,
        'message' => 'Trip logged successfully!',
        'data' => [
            'id' => $id,
            'student_no' => $studentNo,
            'mode' => $mode,
            'origin' => $origin,
            'destination' => $destination,
            'distance_km' => $distance,
            'fare_amount' => $fare,
            'created_at' => date('Y-m-d H:i:s')
        ]
    ]);
}

if ($method === 'DELETE') {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        respond(400, ['success' => false, 'message' => 'Valid trip id is required.']);
    }

    $stmt = $pdo->prepare('DELETE FROM commute_history WHERE id = :id');
    $stmt->execute([':id' => $id]);
    respond(200, ['success' => true, 'message' => 'Trip deleted successfully.']);
}

respond(405, ['success' => false, 'message' => 'Method not allowed.']);

