<?php
header("Access-Control-Allow-Origin: *");
$host = 'localhost';
$dbname = 'bookmate';
$user = 'root';
$pass = '';  

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("database not found!!!!!!!!!!!!!!!!!");
    die(json_encode(['error' => $e->getMessage()]));
}
?>