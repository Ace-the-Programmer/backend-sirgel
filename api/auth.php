<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

$allowedOrigins = [
    'http://localhost',
    'http://localhost:8100',
    'http://127.0.0.1:8100',
    'http://localhost:4200',
    'https://localhost',
    'capacitor://localhost',
    'ionic://localhost',
];

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '' && in_array($origin, $allowedOrigins, true)) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
} else {
    header('Access-Control-Allow-Origin: *');
}

header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
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
$action = $_GET['action'] ?? '';

if ($method === 'GET') {
    if ($action === 'users' || $action === '') {
        $stmt = $pdo->query('SELECT id, student_no, username, full_name, email, course, year_level, avatar, created_at FROM users ORDER BY id ASC');
        $users = $stmt->fetchAll();
        respond(200, ['success' => true, 'data' => $users]);
    }
    
    if ($action === 'user' && !empty($_GET['student_no'])) {
        $stmt = $pdo->prepare('SELECT id, student_no, username, full_name, email, course, year_level, avatar, created_at FROM users WHERE student_no = :student_no LIMIT 1');
        $stmt->execute([':student_no' => $_GET['student_no']]);
        $user = $stmt->fetch();
        if (!$user) {
            respond(404, ['success' => false, 'message' => 'User not found.']);
        }
        respond(200, ['success' => true, 'data' => $user]);
    }
    
    respond(400, ['success' => false, 'message' => 'Invalid GET action.']);
}

if ($method === 'POST') {
    $body = read_json_body();

    if ($action === 'login') {
        $identifier = trim((string)($body['username'] ?? $body['student_no'] ?? ''));
        $password = (string)($body['password'] ?? '');

        if ($identifier === '' || $password === '') {
            respond(400, ['success' => false, 'message' => 'Username/Student No and password are required.']);
        }

        $stmt = $pdo->prepare('SELECT * FROM users WHERE username = :id1 OR student_no = :id2 OR email = :id3 LIMIT 1');
        $stmt->execute([':id1' => $identifier, ':id2' => $identifier, ':id3' => $identifier]);
        $user = $stmt->fetch();

        if (!$user) {
            respond(401, ['success' => false, 'message' => 'Account does not exist. Please check your username/student number.']);
        }

        // Verify password (plain check or password_verify fallback)
        $passwordMatches = ($password === $user['password']) || password_verify($password, $user['password']);
        if (!$passwordMatches) {
            respond(401, ['success' => false, 'message' => 'Incorrect password.']);
        }

        unset($user['password']);
        respond(200, ['success' => true, 'message' => 'Login successful', 'data' => $user]);
    }

    if ($action === 'register') {
        $studentNo = trim((string)($body['student_no'] ?? ''));
        $username = trim((string)($body['username'] ?? ''));
        $fullName = trim((string)($body['full_name'] ?? ''));
        $email = trim((string)($body['email'] ?? ''));
        $password = (string)($body['password'] ?? 'password123');
        $course = trim((string)($body['course'] ?? 'BSIT'));
        $yearLevel = (int)($body['year_level'] ?? 1);
        $avatar = (string)($body['avatar'] ?? 'assets/icon/quenie-avatar.png');

        if ($studentNo === '' || $fullName === '') {
            respond(400, ['success' => false, 'message' => 'student_no and full_name are required.']);
        }

        if ($username === '') {
            $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode(' ', $fullName)[0] . substr($studentNo, -4)));
        }
        if ($email === '') {
            $email = $username . '@campus.edu.ph';
        }

        // Check if student_no already exists
        $checkStmt = $pdo->prepare('SELECT id FROM users WHERE student_no = :s OR username = :u LIMIT 1');
        $checkStmt->execute([':s' => $studentNo, ':u' => $username]);
        if ($checkStmt->fetch()) {
            respond(409, ['success' => false, 'message' => 'Student number or username already exists.']);
        }

        // Insert into users
        $insertUser = $pdo->prepare(
            'INSERT INTO users (student_no, username, password, full_name, email, course, year_level, avatar)
             VALUES (:student_no, :username, :password, :full_name, :email, :course, :year_level, :avatar)'
        );
        $insertUser->execute([
            ':student_no' => $studentNo,
            ':username' => $username,
            ':password' => $password,
            ':full_name' => $fullName,
            ':email' => $email,
            ':course' => $course,
            ':year_level' => $yearLevel,
            ':avatar' => $avatar
        ]);

        // Sync into students table for teacher's REST API requirements
        $syncStudent = $pdo->prepare(
            'INSERT INTO students (student_no, full_name, course, year_level)
             VALUES (:student_no, :full_name, :course, :year_level)
             ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), course = VALUES(course), year_level = VALUES(year_level)'
        );
        $syncStudent->execute([
            ':student_no' => $studentNo,
            ':full_name' => $fullName,
            ':course' => $course,
            ':year_level' => $yearLevel
        ]);

        $createdUser = [
            'id' => (int)$pdo->lastInsertId(),
            'student_no' => $studentNo,
            'username' => $username,
            'full_name' => $fullName,
            'email' => $email,
            'course' => $course,
            'year_level' => $yearLevel,
            'avatar' => $avatar
        ];

        respond(201, ['success' => true, 'message' => 'User registered successfully!', 'data' => $createdUser]);
    }

    respond(400, ['success' => false, 'message' => 'Invalid POST action.']);
}

respond(405, ['success' => false, 'message' => 'Method not allowed.']);

