<?php
// header.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
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
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="goal.php">設定目標</a>
            <a href="history.php">歷史紀錄</a>
            <a href="auth/logout.php">登出</a>
        </nav>

        <div class="user">
            Hi, <?php echo isset($userName) ? htmlspecialchars($userName) : '使用者'; ?>
        </div>
    </header>
</body>

</html>
