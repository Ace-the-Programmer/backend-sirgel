<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Development CORS allow-list. Add your app's exact origin if needed.
$allowedOrigins = [
    'http://localhost',
    'http://localhost:8100',
    'http://127.0.0.1:8100',
    'http://localhost:4200',
    'https://localhost',
    'capacitor://localhost',
    'ionic://localhost',
];

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Origin, Accept');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
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
        respond(400, ['success' => false, 'message' => 'Invalid JSON request body.']);
    }

    return $decoded;
}

function validate_student(array $input): array
{
    $studentNo = trim((string)($input['student_no'] ?? ''));
    $fullName = trim((string)($input['full_name'] ?? ''));
    $course = trim((string)($input['course'] ?? ''));
    $yearLevel = filter_var($input['year_level'] ?? null, FILTER_VALIDATE_INT);

    $errors = [];
    if ($studentNo === '') $errors['student_no'] = 'Student number is required.';
    if ($fullName === '') $errors['full_name'] = 'Full name is required.';
    if ($course === '') $errors['course'] = 'Course is required.';
    if ($yearLevel === false || $yearLevel === null || $yearLevel < 1 || $yearLevel > 6) {
        $errors['year_level'] = 'Year level must be an integer from 1 to 6.';
    }

    if ($errors) {
        respond(422, [
            'success' => false,
            'message' => 'Validation failed.',
            'errors' => $errors,
        ]);
    }

    return [
        'student_no' => $studentNo,
        'full_name' => $fullName,
        'course' => $course,
        'year_level' => $yearLevel,
    ];
}

try {
    $pdo = get_db_connection();
    $method = $_SERVER['REQUEST_METHOD'];
    $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

    if (isset($_GET['id']) && ($id === false || $id === null || $id < 1)) {
        respond(400, ['success' => false, 'message' => 'The id parameter must be a positive integer.']);
    }

    switch ($method) {
        case 'GET':
            if ($id !== null && $id !== false) {
                $stmt = $pdo->prepare(
                    'SELECT id, student_no, full_name, course, year_level, created_at, updated_at
                     FROM students WHERE id = :id'
                );
                $stmt->execute(['id' => $id]);
                $student = $stmt->fetch();

                if (!$student) {
                    respond(404, ['success' => false, 'message' => 'Student not found.']);
                }

                $student['id'] = (int)$student['id'];
                $student['year_level'] = (int)$student['year_level'];
                respond(200, ['success' => true, 'data' => $student]);
            }

            $stmt = $pdo->query(
                'SELECT id, student_no, full_name, course, year_level, created_at, updated_at
                 FROM students ORDER BY id DESC'
            );
            $students = $stmt->fetchAll();

            foreach ($students as &$student) {
                $student['id'] = (int)$student['id'];
                $student['year_level'] = (int)$student['year_level'];
            }
            unset($student);

            respond(200, ['success' => true, 'data' => $students]);

        case 'POST':
            $student = validate_student(read_json_body());
            $stmt = $pdo->prepare(
                'INSERT INTO students (student_no, full_name, course, year_level)
                 VALUES (:student_no, :full_name, :course, :year_level)'
            );
            $stmt->execute($student);

            $newId = (int)$pdo->lastInsertId();
            $stmt = $pdo->prepare(
                'SELECT id, student_no, full_name, course, year_level, created_at, updated_at
                 FROM students WHERE id = :id'
            );
            $stmt->execute(['id' => $newId]);
            $created = $stmt->fetch();
            $created['id'] = (int)$created['id'];
            $created['year_level'] = (int)$created['year_level'];

            respond(201, ['success' => true, 'message' => 'Student created.', 'data' => $created]);

        case 'PUT':
            if ($id === null || $id === false) {
                respond(400, ['success' => false, 'message' => 'Provide a student id in the query string, e.g. ?id=1.']);
            }

            $student = validate_student(read_json_body());
            $student['id'] = $id;

            $check = $pdo->prepare('SELECT id FROM students WHERE id = :id');
            $check->execute(['id' => $id]);
            if (!$check->fetch()) {
                respond(404, ['success' => false, 'message' => 'Student not found.']);
            }

            $stmt = $pdo->prepare(
                'UPDATE students
                 SET student_no = :student_no, full_name = :full_name,
                     course = :course, year_level = :year_level
                 WHERE id = :id'
            );
            $stmt->execute($student);

            $stmt = $pdo->prepare(
                'SELECT id, student_no, full_name, course, year_level, created_at, updated_at
                 FROM students WHERE id = :id'
            );
            $stmt->execute(['id' => $id]);
            $updated = $stmt->fetch();
            $updated['id'] = (int)$updated['id'];
            $updated['year_level'] = (int)$updated['year_level'];

            respond(200, ['success' => true, 'message' => 'Student updated.', 'data' => $updated]);

        case 'DELETE':
            if ($id === null || $id === false) {
                respond(400, ['success' => false, 'message' => 'Provide a student id in the query string, e.g. ?id=1.']);
            }

            $stmt = $pdo->prepare('DELETE FROM students WHERE id = :id');
            $stmt->execute(['id' => $id]);

            if ($stmt->rowCount() === 0) {
                respond(404, ['success' => false, 'message' => 'Student not found.']);
            }

            respond(200, ['success' => true, 'message' => 'Student deleted.']);

        default:
            header('Allow: GET, POST, PUT, DELETE, OPTIONS');
            respond(405, ['success' => false, 'message' => 'Method not allowed.']);
    }
} catch (PDOException $e) {
    if ($e->getCode() === '23000') {
        respond(409, ['success' => false, 'message' => 'That student number already exists.']);
    }

    error_log('Students API database error: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Throwable $e) {
    error_log('Students API unexpected error: ' . $e->getMessage());
    respond(500, ['success' => false, 'message' => 'Internal server error.']);
}
