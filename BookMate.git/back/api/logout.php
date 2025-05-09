<?php
// Set headers
header("Access-Control-Allow-Origin: http://localhost:4200");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

session_start();

// Unset all session variables
$_SESSION = array();

// If using a cookie to store the session ID, delete it
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Destroy the session
session_destroy();

http_response_code(200);
echo json_encode(['message' => 'Logout successful']);
?>