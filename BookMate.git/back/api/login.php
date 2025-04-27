<?php
require '../config/db.php';

session_start();

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);

// Validate input
if (empty($input['email']) || empty($input['password'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Email and password are required']);
    exit();
}

$email = trim($input['email']);
$password = $input['password'];

// Check user exists
$stmt = $pdo->prepare("SELECT * FROM user WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Invalid email or password']);
    exit();
}

// Login successful
$_SESSION['user_id'] = $user['user_id'];
$_SESSION['email'] = $user['email'];
$_SESSION['firstName'] = $user['FirstName'];
$_SESSION['lastName'] = $user['LastName'];

http_response_code(200);
echo json_encode([
    'message' => 'Login successful',
    'user' => [
        'user_id' => $user['user_id'],
        'firstName' => $user['FirstName'],
        'lastName' => $user['LastName'],
        'email' => $user['email'],
        'address' => $user['address'],
        'age' => $user['age'],
        'imageURL' => $user['imageURL'],
        'user_swap_score' => $user['user_swap_score']
    ]
]);
?>