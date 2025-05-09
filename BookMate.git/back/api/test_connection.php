<?php
require_once __DIR__ . '/../config/db.php';

if ($pdo) {
    echo "Database connection successful!";
} else {
    echo "Database connection failed!";
}
?>