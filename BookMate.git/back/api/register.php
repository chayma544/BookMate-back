<?php
session_start();
require '../config/db.php';

// Set CORS headers for all responses
header("Access-Control-Allow-Origin: http://localhost:4200");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Max-Age: 86400"); // Cache preflight response for 24 hours

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit(); // Exit immediately after sending 200 OK
}

// Only allow POST requests for processing
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (empty($input['firstName']) || empty($input['lastName']) || empty($input['email']) || 
    empty($input['password']) || empty($input['address']) || empty($input['age'])) {
    http_response_code(400);
    echo json_encode(['error' => 'All fields are required']);
    exit();
}

// Extract data
$firstName = trim($input['firstName']);
$lastName = trim($input['lastName']);
$email = trim($input['email']);
$password = $input['password'];
$address = trim($input['address']);
$age = intval($input['age']);

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email format']);
    exit();
}

// Check if email exists
$stmt = $pdo->prepare("SELECT * FROM user WHERE email = ?");
$stmt->execute([$email]);
if ($stmt->rowCount() > 0) {
    http_response_code(409);
    echo json_encode(['error' => 'Email already exists']);
    exit();
}

// Validate password
if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['error' => 'Password must be at least 8 characters']);
    exit();
}

// Hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$imageURL = 'default_profile.jpg';

// Insert user
try {
    $stmt = $pdo->prepare("INSERT INTO user (FirstName, LastName, age, address, user_swap_score, email, password, imageURL) 
                          VALUES (?, ?, ?, ?, 0, ?, ?, ?)");
    $stmt->execute([$firstName, $lastName, $age, $address, $email, $hashedPassword, $imageURL]);
    
    $userId = $pdo->lastInsertId();
    
    $_SESSION['user_id'] = $userId;
    setcookie('user_id', $userId, time() + 3600, "/");

    http_response_code(201);
    echo json_encode([
        'message' => 'Registration successful',
        'user_id' => $userId
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Registration failed: ' . $e->getMessage()]);
}
?>