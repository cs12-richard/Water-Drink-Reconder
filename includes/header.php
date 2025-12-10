<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db.php';

$userName = '小花園用戶';
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT name FROM users WHERE id=?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $userName = $row['name'];
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WaterGrow 小花園</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>

<body>
    <header class="navbar">
        <div class="logo">
            <img src="assets/img/logo.png" alt="WaterGrow Logo" class="logo-img">
            <span class="logo-text">WaterGrow 小花園</span>
        </div>

        <nav>
            <a href="dashboard.php">Dashboard</a>
            <a href="goal.php">設定目標</a>
            <a href="history.php">歷史紀錄</a>
            <a href="auth/logout.php">登出</a>
        </nav>

        <div class="user">
            Hi, <?php echo isset($userName) ? htmlspecialchars($userName) : '使用者'; ?>
        </div>
    </header>