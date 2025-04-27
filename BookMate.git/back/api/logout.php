<?php
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session
session_destroy();

http_response_code(200);
echo json_encode(['message' => 'Logout successful']);
?>