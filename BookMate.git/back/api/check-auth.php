<?php
// Set headers
header("Access-Control-Allow-Origin: http://localhost:4200");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Start session
session_start();
error_log('Session ID in check-auth: ' . session_id());
error_log('SESSION data: ' . json_encode($_SESSION));

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    try {
        require_once __DIR__ . '/../config/db.php';
        
        // Get user data from database
        $stmt = $pdo->prepare("SELECT user_id, firstName, lastName, email, address, age, user_swap_score FROM user WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            // Return user data without sensitive information
            http_response_code(200);
            echo json_encode([
                'authenticated' => true,
                'user_id' => $user['user_id'],
                'firstName' => $user['firstName'],
                'lastName' => $user['lastName'],
                'email' => $user['email'],
                'address' => $user['address'],
                'age' => $user['age'],
                'user_swap_score' => $user['user_swap_score']
            ]);
        } else {
            // User ID in session doesn't match any user in database
            http_response_code(401);
            echo json_encode(['authenticated' => false, 'message' => 'Invalid session']);
        }
    } catch (PDOException $e) {
        error_log('Database error in check-auth.php: ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['authenticated' => false, 'message' => 'Database error']);
    }
} else {
    // No user ID in session
    http_response_code(401);
    echo json_encode(['authenticated' => false, 'message' => 'Not authenticated']);
}
?>