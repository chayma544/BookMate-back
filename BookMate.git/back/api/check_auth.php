<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['authenticated' => false]);
    exit();
}

require '../db.php';

$userId = $_SESSION['user_id'];
$stmt = $pdo->prepare("SELECT user_id, FirstName, LastName, email, address, age, imageURL, user_swap_score FROM user WHERE user_id = ?");
$stmt->execute([$userId]);
$user = $stmt->fetch();

if (!$user) {
    http_response_code(401);
    echo json_encode(['authenticated' => false]);
    exit();
}

http_response_code(200);
echo json_encode([
    'authenticated' => true,
    'user' => $user
]);
?>