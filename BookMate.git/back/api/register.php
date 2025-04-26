<?php
require_once '../config/database.php';

$data = json_decode(file_get_contents("php://input"));

$requiredFields = ['firstName', 'lastName', 'age', 'address', 'password'];
$optionalFields = ['email'];

// Check required fields
foreach ($requiredFields as $field) {
    if (empty($data->$field)) {
        http_response_code(400);
        echo json_encode(["message" => "Missing required field: $field"]);
        exit;
    }
}

// Prepare user data
$userData = [
    'first_name' => $data->firstName,
    'last_name' => $data->lastName,
    'age' => $data->age,
    'address' => $data->address,
    'password' => password_hash($data->password, PASSWORD_BCRYPT),
    'user_swap_score' => 0, // Default value
    'email' => $data->email ?? null
];

// Check if email exists (only if provided)
if ($userData['email']) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$userData['email']]);
    
    if ($stmt->rowCount() > 0) {
        http_response_code(400);
        echo json_encode(["message" => "Email already exists"]);
        exit;
    }
}

// Insert user
$columns = implode(', ', array_keys($userData));
$placeholders = implode(', ', array_fill(0, count($userData), '?'));
$values = array_values($userData);

$stmt = $pdo->prepare("INSERT INTO users ($columns) VALUES ($placeholders)");
if ($stmt->execute($values)) {
    $userId = $pdo->lastInsertId();
    
    // Get the newly created user
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    http_response_code(201);
    echo json_encode([
        "message" => "User registered successfully",
        "user" => [
            "user_id" => $user['id'],
            "firstName" => $user['first_name'],
            "lastName" => $user['last_name'],
            "age" => $user['age'],
            "address" => $user['address'],
            "user_swap_score" => $user['user_swap_score'],
            "email" => $user['email']
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(["message" => "Registration failed"]);
}
?>