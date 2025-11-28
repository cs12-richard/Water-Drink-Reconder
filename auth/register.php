<?php
session_start();
include '../includes/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../../dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = $_POST['name'];
    $password = $_POST['password'];
    $password_hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $conn->prepare("INSERT INTO users (name, password_hash) VALUES (?, ?)");
    $stmt->bind_param("ss", $name, $password_hash);

    if ($stmt->execute()) {
        header("Location: login.php");
        exit;
    } else {
        $error = "使用者名稱已存在";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>註冊</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <h1>註冊</h1>
    <?php if ($error): ?>
        <p style="color: red;"><?php echo $error; ?></p>
    <?php endif; ?>
    <form method="POST">
        <label>使用者名稱:</label><br>
        <input type="text" name="name" required><br>
        <label>密碼:</label><br>
        <input type="password" name="password" required><br>
        <button type="submit">註冊</button>
    </form>
    <p>已有帳號？<a href="login.php">登入</a></p>
</body>
</html>