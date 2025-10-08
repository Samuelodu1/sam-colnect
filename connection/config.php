<?php
declare(strict_types=1);

$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'colnect';

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]));
}