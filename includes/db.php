<?php
$host = 'localhost';
$dbname = 'water_tracker';
$username = 'CVML';
$password = '114DWP2025';

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("連線失敗: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");
?>
