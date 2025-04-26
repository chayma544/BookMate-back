<?php
require_once '../config/database.php';
require_once '../config/jwt.php'; // Include JWT functionality

// Sanitize input
function sanitizeInput($data) {
    if (is_object($data)) {
        $data = (array)$data;
        return (object)array_map('sanitizeInput', $data);
    } elseif (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

// Parse and sanitize input data
$rawData = json_decode(file_get_contents("php://input"));
$data = sanitizeInput($rawData);

// Set response header
header('Content-Type: application/json');

// Allow login with either email or firstName/lastName combination
if ((!empty($data->email) || (!empty($data->firstName) && !empty($data->lastName))) && !empty($data->password)) {
    try {
        if (!empty($data->email)) {
            // Email-based login
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$data->email]);
        } else {
            // Name-based login
            $stmt = $pdo->prepare("SELECT * FROM users WHERE first_name = ? AND last_name = ?");
            $stmt->execute([$data->firstName, $data->lastName]);
        }
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($data->password, $user['password'])) {
            // Generate access and refresh tokens
            $accessToken = generateJWT($user['id'], $user['email'], 'access');
            $refreshToken = generateJWT($user['id'], $user['email'], 'refresh');
            
            // Optional: Log successful login
            // $logStmt = $pdo->prepare("INSERT INTO login_logs (user_id, login_time, status) VALUES (?, NOW(), 'success')");
            // $logStmt->execute([$user['id']]);
            
            http_response_code(200);
            echo json_encode([
                "message" => "Login successful",
                "token" => $accessToken,
                "refresh_token" => $refreshToken,
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
            // Optional: Log failed login attempt
            // if ($user) {
            //     $logStmt = $pdo->prepare("INSERT INTO login_logs (user_id, login_time, status) VALUES (?, NOW(), 'failed')");
            //     $logStmt->execute([$user['id']]);
            // }
            
            http_response_code(401);
            echo json_encode(["message" => "Invalid credentials"]);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode([
            "message" => "Database error",
            "error" => (defined('ENVIRONMENT') && ENVIRONMENT === 'development') ? $e->getMessage() : 'Internal server error'
        ]);
    }
} else {
    http_response_code(400);
    echo json_encode(["message" => "Incomplete data"]);
}
?>