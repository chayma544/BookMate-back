<?php
header("Access-Control-Allow-Origin: http://localhost:4200");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Max-Age: 86400");
header("Content-Type: application/json");

session_start();
require_once __DIR__ . '/../config/db.php';

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
error_log('Received input: ' . json_encode($input));

// Validate input
if (empty($input['email']) || empty($input['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Email and password are required']);
    exit();
}

$email = trim($input['email']);
$password = $input['password'];
error_log("Email: $email, Password: [REDACTED]");

// Verify user
try {
    // Case-insensitive email lookup
    $stmt = $pdo->prepare("SELECT * FROM user WHERE LOWER(email) = LOWER(?)");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log('Fetched user: ' . json_encode($user ? ['user_id' => $user['user_id'], 'email' => $user['email']] : null));

    if ($user) {
        error_log('Stored hash: ' . $user['password']);
        error_log('Password verify result: ' . (password_verify($password, $user['password']) ? 'true' : 'false'));
    }

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['user_id'];
        session_regenerate_id(true); // Prevent session fixation
        error_log('Login successful, user_id: ' . $user['user_id']);
        error_log('Session ID: ' . session_id());

        http_response_code(200);
        echo json_encode([
            'message' => 'Login successful',
            'user_id' => $user['user_id']
        ]);
    } else {
        error_log('Login failed: Invalid email or password');
        http_response_code(401);
        echo json_encode(['error' => 'Invalid email or password']);
    }
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Login failed: ' . $e->getMessage()]);
}
?>