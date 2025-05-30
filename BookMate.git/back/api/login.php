<?php
header("Access-Control-Allow-Origin: http://localhost:4200");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Ensure session is started after headers
// Ensure session is started
session_start();

// Set session data
$_SESSION['user_id'] = $user['user_id'];

// Set cookie with session ID for cross-origin requests
setcookie('PHPSESSID', session_id(), [
    'expires' => time() + 3600,  // Session expiration time
    'path' => '/',               // Available for the entire domain
    'domain' => '',              // Leave empty for local testing
    'secure' => false,           // Set to true when using HTTPS
    'httponly' => true,          // Prevent JavaScript access
    'samesite' => 'None'         // Required for cross-origin with credentials
]);

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['email']) || empty($input['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Email and password are required']);
    exit();
}

$email = trim($input['email']);
$password = $input['password'];

try {
    $stmt = $pdo->prepare("SELECT * FROM user WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['role'] = $user['role'];

        http_response_code(200);
        echo json_encode([
            'message' => 'Login successful',
            'user_id' => $user['user_id'],
            'role' => $user['role']
        ]);
    } else {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid email or password']);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Login failed: ' . $e->getMessage()]);
}
?>