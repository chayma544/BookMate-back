<?php
// Set headers before any output
header("Access-Control-Allow-Origin: http://localhost:4200");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");
header("Access-Control-Max-Age: 86400");

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

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response = ['error' => 'Method not allowed'];
    http_response_code(405);
    echo json_encode($response);
    exit();
}

// Process the login request
$input = json_decode(file_get_contents('php://input'), true);
error_log('Decoded input: ' . json_encode($input));

if (empty($input['email']) || empty($input['password'])) {
    $response = ['error' => 'Email and password are required'];
    error_log('Validation failed: ' . json_encode($response));
    http_response_code(400);
    echo json_encode($response);
    exit();
}

$email = trim($input['email']);
$password = $input['password'];
error_log("Processing login for email: $email");

try {
    require_once __DIR__ . '/../config/db.php';
    $stmt = $pdo->prepare("SELECT * FROM user WHERE LOWER(email) = LOWER(?)");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    error_log('Database query result: ' . json_encode($user));

    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['user_id'];
        session_regenerate_id(true);
        setcookie('PHPSESSID', session_id(), [
            'expires' => time() + 3600,
            'path' => '/',
            'domain' => '',
            'secure' => false, // Set to true in production with HTTPS
            'httponly' => true,
            'samesite' => 'None' // Required for cross-origin with credentials
        ]);
        error_log('Login successful, user_id: ' . $user['user_id']);
        error_log('Session ID after login: ' . session_id());

        $response = [
            'message' => 'Login successful',
            'user_id' => $user['user_id']
        ];
        http_response_code(200);
        echo json_encode($response);
    } else {
        $response = ['error' => 'Invalid email or password'];
        error_log('Login failed: ' . json_encode($response) . ' for email: ' . $email);
        http_response_code(401);
        echo json_encode($response);
    }
} catch (PDOException $e) {
    $response = ['error' => 'Login failed: Database error'];
    error_log('Database error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode($response);
}
?>