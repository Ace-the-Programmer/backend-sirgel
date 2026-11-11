<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

// Security & CORS Headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Origin, Accept');
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

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
        respond(400, ['success' => false, 'message' => 'Invalid JSON body.']);
    }
    return $decoded;
}

function get_bearer_token(): ?string
{
    $headers = null;
    if (isset($_SERVER['Authorization'])) {
        $headers = trim($_SERVER['Authorization']);
    } elseif (isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $headers = trim($_SERVER['HTTP_AUTHORIZATION']);
    } elseif (function_exists('apache_request_headers')) {
        $requestHeaders = apache_request_headers();
        $requestHeaders = array_combine(array_map('ucwords', array_keys($requestHeaders)), array_values($requestHeaders));
        if (isset($requestHeaders['Authorization'])) {
            $headers = trim($requestHeaders['Authorization']);
        }
    }
    if (!empty($headers) && preg_match('/Bearer\s(\S+)/', $headers, $matches)) {
        return $matches[1];
    }
    return $_GET['token'] ?? null;
}

try {
    $pdo = get_db_connection();

    // 1. Ensure table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            student_no VARCHAR(50) NOT NULL UNIQUE,
            username VARCHAR(50) NOT NULL UNIQUE,
            password VARCHAR(255) NOT NULL,
            full_name VARCHAR(150) NOT NULL,
            email VARCHAR(150) NOT NULL UNIQUE,
            course VARCHAR(100) NOT NULL DEFAULT 'BSIT',
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
    ");

    // 2. Auto-migrate missing columns if users table was created with an older/minimal schema
    try {
        $existingCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);
        $neededCols = [
            'course'           => "VARCHAR(100) NOT NULL DEFAULT 'BSIT'",
            'school'           => "VARCHAR(100) DEFAULT 'Araullo University (Main Campus)'",
            'year_level'       => "TINYINT UNSIGNED NOT NULL DEFAULT 1",
            'avatar'           => "VARCHAR(255) DEFAULT 'assets/icon/quenie-avatar.png'",
            'auth_token'       => "VARCHAR(255) DEFAULT NULL",
            'token_expires_at' => "DATETIME DEFAULT NULL",
            'failed_attempts'  => "INT UNSIGNED NOT NULL DEFAULT 0",
            'lockout_until'    => "DATETIME DEFAULT NULL",
            'last_login'       => "DATETIME DEFAULT NULL",
            'updated_at'       => "TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP"
        ];
        foreach ($neededCols as $col => $def) {
            if (!in_array($col, $existingCols, true)) {
                $pdo->exec("ALTER TABLE users ADD COLUMN `$col` $def");
            }
        }
    } catch (Throwable $migrationErr) {
        // Continue gracefully
    }

    $existingCols = $pdo->query("SHOW COLUMNS FROM users")->fetchAll(PDO::FETCH_COLUMN);

} catch (Throwable $e) {
    respond(500, ['success' => false, 'message' => 'Database connection failed: ' . $e->getMessage()]);
}

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';

try {
    // ==========================================
    // GET ACTIONS
    // ==========================================
    if ($method === 'GET') {
        // 1. Verify active token / session
        if ($action === 'verify') {
            $token = get_bearer_token();
            if (!$token) {
                respond(401, ['success' => false, 'message' => 'Missing authorization token.', 'authenticated' => false]);
            }
            if (!in_array('auth_token', $existingCols, true)) {
                respond(200, ['success' => true, 'authenticated' => true, 'message' => 'Session active']);
            }

            $stmt = $pdo->prepare('SELECT * FROM users WHERE auth_token = :token LIMIT 1');
            $stmt->execute([':token' => $token]);
            $user = $stmt->fetch();
            if (!$user) {
                respond(401, ['success' => false, 'message' => 'Session token is invalid or expired. Please sign in again.', 'authenticated' => false]);
            }
            unset($user['password'], $user['failed_attempts'], $user['lockout_until']);
            respond(200, ['success' => true, 'authenticated' => true, 'data' => $user]);
        }

        // 2. Directory list
        if ($action === 'users' || $action === '') {
            $stmt = $pdo->query('SELECT * FROM users ORDER BY id ASC');
            $users = $stmt->fetchAll();
            foreach ($users as &$u) {
                unset($u['password'], $u['failed_attempts'], $u['lockout_until']);
            }
            respond(200, ['success' => true, 'data' => $users]);
        }

        // 3. Single user by student_no
        if ($action === 'user' && !empty($_GET['student_no'])) {
            $stmt = $pdo->prepare('SELECT * FROM users WHERE student_no = :student_no LIMIT 1');
            $stmt->execute([':student_no' => $_GET['student_no']]);
            $user = $stmt->fetch();
            if (!$user) {
                respond(404, ['success' => false, 'message' => 'User not found.']);
            }
            unset($user['password'], $user['failed_attempts'], $user['lockout_until']);
            respond(200, ['success' => true, 'data' => $user]);
        }

        respond(400, ['success' => false, 'message' => 'Invalid GET action.']);
    }

    // ==========================================
    // POST ACTIONS (LOGIN, REGISTER, LOGOUT)
    // ==========================================
    if ($method === 'POST') {
        // 1. LOGOUT
        if ($action === 'logout') {
            $token = get_bearer_token();
            if ($token && in_array('auth_token', $existingCols, true)) {
                $stmt = $pdo->prepare('UPDATE users SET auth_token = NULL WHERE auth_token = :token');
                $stmt->execute([':token' => $token]);
            }
            respond(200, ['success' => true, 'message' => 'Signed out successfully.']);
        }

        $body = read_json_body();

        // 2. LOGIN (Strict authentication with BCrypt verification)
        if ($action === 'login') {
            $identifier = trim(strip_tags((string)($body['username'] ?? $body['student_no'] ?? '')));
            $password = (string)($body['password'] ?? '');

            if ($identifier === '' || strlen($identifier) < 3) {
                respond(400, ['success' => false, 'message' => 'Username or student ID must be at least 3 characters long.']);
            }
            if ($password === '' || strlen($password) < 4) {
                respond(400, ['success' => false, 'message' => 'Password must be at least 4 characters long.']);
            }

            // Find user in users table by username, student_no, or email
            $stmt = $pdo->prepare('
                SELECT * FROM users 
                WHERE username = :u1 
                   OR student_no = :s1 
                   OR email = :e1 
                LIMIT 1
            ');
            $stmt->execute([
                ':u1' => $identifier,
                ':s1' => $identifier,
                ':e1' => $identifier,
            ]);
            $user = $stmt->fetch();

            if (!$user) {
                respond(401, ['success' => false, 'message' => "Account '$identifier' not found. Please check your username or register."]);
            }

            // Account Lockout check if column exists
            if (in_array('lockout_until', $existingCols, true) && !empty($user['lockout_until'])) {
                $lockTime = strtotime($user['lockout_until']);
                if ($lockTime > time()) {
                    $remainingSecs = $lockTime - time();
                    $remainingMins = (int)ceil($remainingSecs / 60);
                    respond(429, [
                        'success' => false,
                        'message' => "Security Lockout: Too many failed login attempts for '$identifier'. Please wait {$remainingMins} minute(s).",
                        'locked' => true
                    ]);
                }
            }

            // Password Verification: Strictly verify password
            $storedPassword = (string)($user['password'] ?? '');
            $passwordMatches = false;

            if (password_verify($password, $storedPassword)) {
                $passwordMatches = true;
            } elseif ($storedPassword === $password) {
                // Legacy plaintext match: automatically upgrade to BCrypt
                $passwordMatches = true;
                $newHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare('UPDATE users SET password = :p WHERE id = :id')->execute([':p' => $newHash, ':id' => $user['id']]);
            }

            if (!$passwordMatches) {
                // Record failed attempt if column exists
                if (in_array('failed_attempts', $existingCols, true)) {
                    $fails = (int)($user['failed_attempts'] ?? 0) + 1;
                    if ($fails >= 5 && in_array('lockout_until', $existingCols, true)) {
                        $pdo->prepare('UPDATE users SET failed_attempts = :f, lockout_until = DATE_ADD(NOW(), INTERVAL 5 MINUTE) WHERE id = :id')
                            ->execute([':f' => $fails, ':id' => $user['id']]);
                        respond(429, [
                            'success' => false,
                            'message' => "Security Alert: 5 consecutive failed login attempts. Account '$identifier' locked for 5 minutes.",
                            'locked' => true
                        ]);
                    } else {
                        $pdo->prepare('UPDATE users SET failed_attempts = :f WHERE id = :id')->execute([':f' => $fails, ':id' => $user['id']]);
                    }
                }
                respond(401, ['success' => false, 'message' => 'Invalid password. Please check your credentials and try again.']);
            }

            // Password matches: generate session token
            $token = bin2hex(random_bytes(32));

            // Update user record with session token if columns exist
            $updates = [];
            $updateParams = [':id' => $user['id']];
            if (in_array('auth_token', $existingCols, true)) {
                $updates[] = 'auth_token = :token';
                $updateParams[':token'] = $token;
            }
            if (in_array('token_expires_at', $existingCols, true)) {
                $updates[] = 'token_expires_at = DATE_ADD(NOW(), INTERVAL 30 DAY)';
            }
            if (in_array('failed_attempts', $existingCols, true)) {
                $updates[] = 'failed_attempts = 0';
            }
            if (in_array('lockout_until', $existingCols, true)) {
                $updates[] = 'lockout_until = NULL';
            }
            if (in_array('last_login', $existingCols, true)) {
                $updates[] = 'last_login = NOW()';
            }

            if (!empty($updates)) {
                $pdo->prepare('UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = :id')->execute($updateParams);
            }

            unset($user['password'], $user['failed_attempts'], $user['lockout_until']);
            $user['auth_token'] = $token;

            respond(200, [
                'success' => true,
                'message' => 'Login successful',
                'token' => $token,
                'token_type' => 'Bearer',
                'data' => $user
            ]);
        }

        // 3. REGISTER (Stores account in MySQL users table with BCrypt)
        if ($action === 'register') {
            $username = trim(strip_tags((string)($body['username'] ?? '')));
            $fullName = trim(strip_tags((string)($body['full_name'] ?? '')));
            $school = trim(strip_tags((string)($body['school'] ?? $body['course'] ?? 'Araullo University (Main Campus)')));
            $course = trim(strip_tags((string)($body['course'] ?? 'BSIT')));
            $password = (string)($body['password'] ?? '');
            $email = trim((string)($body['email'] ?? ''));
            $studentNo = trim((string)($body['student_no'] ?? ''));
            $yearLevel = (int)($body['year_level'] ?? 1);
            $avatar = (string)($body['avatar'] ?? 'assets/icon/quenie-avatar.png');

            if ($username === '' || strlen($username) < 3 || strlen($username) > 30) {
                respond(400, ['success' => false, 'message' => 'Username must be between 3 and 30 characters long.']);
            }
            if (!preg_match('/^[a-zA-Z0-9_.-]{3,30}$/', $username)) {
                respond(400, ['success' => false, 'message' => 'Username can only contain letters, numbers, dots, and underscores (no spaces).']);
            }
            if ($fullName === '' || strlen($fullName) < 3) {
                respond(400, ['success' => false, 'message' => 'Full Name must be at least 3 characters long.']);
            }
            if ($password === '' || strlen($password) < 6) {
                respond(400, ['success' => false, 'message' => 'Password must be at least 6 characters long.']);
            }
            if (!preg_match('/(?=.*[a-zA-Z])(?=.*[0-9])/', $password)) {
                respond(400, ['success' => false, 'message' => 'Password must contain at least one letter and one number.']);
            }

            // Defaults if missing
            if ($studentNo === '') {
                $studentNo = 'STU-' . date('Y') . '-' . mt_rand(10000, 99999);
            }
            if ($email === '') {
                $email = strtolower(preg_replace('/[^a-z0-9]/', '', $username)) . '@campus.edu.ph';
            }

            // Check if username already exists
            $stmt = $pdo->prepare('SELECT id FROM users WHERE username = :u LIMIT 1');
            $stmt->execute([':u' => $username]);
            if ($stmt->fetch()) {
                respond(409, ['success' => false, 'message' => "Username '$username' is already taken. Please choose another."]);
            }

            // Check if student_no already exists
            $stmt = $pdo->prepare('SELECT id FROM users WHERE student_no = :s LIMIT 1');
            $stmt->execute([':s' => $studentNo]);
            if ($stmt->fetch()) {
                $studentNo = 'STU-' . date('Y') . '-' . mt_rand(100000, 999999);
            }

            // Hash password with BCrypt
            $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            $token = bin2hex(random_bytes(32));

            // Dynamically construct INSERT matching only columns that exist in the database table
            $insertData = [
                'student_no' => $studentNo,
                'username'   => $username,
                'password'   => $passwordHash,
                'full_name'  => $fullName,
                'email'      => $email,
            ];

            if (in_array('course', $existingCols, true)) $insertData['course'] = $course;
            if (in_array('school', $existingCols, true)) $insertData['school'] = $school;
            if (in_array('year_level', $existingCols, true)) $insertData['year_level'] = $yearLevel;
            if (in_array('avatar', $existingCols, true)) $insertData['avatar'] = $avatar;
            if (in_array('auth_token', $existingCols, true)) $insertData['auth_token'] = $token;
            if (in_array('token_expires_at', $existingCols, true)) $insertData['token_expires_at'] = date('Y-m-d H:i:s', time() + (86400 * 30));
            if (in_array('last_login', $existingCols, true)) $insertData['last_login'] = date('Y-m-d H:i:s');

            $colsList = '`' . implode('`, `', array_keys($insertData)) . '`';
            $paramsList = ':' . implode(', :', array_keys($insertData));

            $insertStmt = $pdo->prepare("INSERT INTO users ($colsList) VALUES ($paramsList)");
            $insertStmt->execute($insertData);
            $newId = (int)$pdo->lastInsertId();

            // Also sync into students table for backward compatibility
            try {
                $syncStmt = $pdo->prepare('
                    INSERT INTO students (student_no, full_name, course, year_level)
                    VALUES (:s, :n, :c, :y)
                    ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), course = VALUES(course)
                ');
                $syncStmt->execute([
                    ':s' => $studentNo,
                    ':n' => $fullName,
                    ':c' => $course,
                    ':y' => $yearLevel,
                ]);
            } catch (Throwable $syncErr) {
                // Ignore sync error
            }

            $newUser = [
                'id'         => $newId,
                'student_no' => $studentNo,
                'username'   => $username,
                'full_name'  => $fullName,
                'email'      => $email,
                'course'     => $course,
                'school'     => $school,
                'year_level' => $yearLevel,
                'avatar'     => $avatar,
                'auth_token' => $token,
            ];

            respond(201, [
                'success'    => true,
                'message'    => 'Account created and saved to MySQL database successfully!',
                'token'      => $token,
                'token_type' => 'Bearer',
                'data'       => $newUser
            ]);
        }

        respond(400, ['success' => false, 'message' => 'Invalid POST action.']);
    }

    respond(405, ['success' => false, 'message' => 'Method not allowed.']);

} catch (Throwable $e) {
    respond(500, [
        'success' => false,
        'message' => 'Server database error: ' . $e->getMessage()
    ]);
}
