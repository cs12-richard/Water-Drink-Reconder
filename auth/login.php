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

    $stmt = $conn->prepare("SELECT id, password_hash FROM users WHERE name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            header("Location: ../../dashboard.php");
            exit;
        } else {
            $error = "密碼錯誤";
        }
    } else {
        $error = "使用者不存在";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>登入</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <h1>登入</h1>
    <?php if ($error): ?>
        <p style="color: red;"><?php echo $error; ?></p>
    <?php endif; ?>
    <form method="POST">
        <label>使用者名稱:</label><br>
        <input type="text" name="name" required><br>
        <label>密碼:</label><br>
        <input type="password" name="password" required><br>
        <button type="submit">登入</button>
    </form>
    <p>還沒有帳號？<a href="register.php">註冊</a></p>
</body>
</html>