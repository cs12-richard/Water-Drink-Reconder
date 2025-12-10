<?php
session_start();
include '../includes/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name     = $_POST['name'] ?? '';
    $password = $_POST['password'] ?? '';

    $stmt = $conn->prepare("SELECT id, password_hash FROM users WHERE name = ?");
    $stmt->bind_param("s", $name);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows == 1) {
        $user = $result->fetch_assoc();
        if (password_verify($password, $user['password_hash'])) {
            $_SESSION['user_id'] = $user['id'];
            header("Location: ../dashboard.php");
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
    <title>登入｜WaterGrow</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
  <div class="auth-page">
    <div class="auth-card">
      <div class="auth-app-brand">
        <div class="auth-app-logo-img">
          <img src="../assets/img/logo.png" alt="WaterGrow Logo">
        </div>
        <div class="auth-app-name">
          WaterGrow
          <small>喝水習慣小花園</small>
        </div>
      </div>

      <h1 class="auth-title">歡迎回來</h1>
      <p class="auth-subtitle">登入來幫植物澆水吧！</p>

      <?php if (!empty($error)): ?>
        <div class="msg err"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <form method="POST" action="">
        <div class="auth-form-group">
          <label class="auth-label" for="name">帳號</label>
          <input
            class="auth-input"
            type="text"
            id="name"
            name="name"
            required
            autocomplete="username"
          >
        </div>

        <div class="auth-form-group">
          <label class="auth-label" for="password">密碼</label>
          <input
            class="auth-input"
            type="password"
            id="password"
            name="password"
            required
            autocomplete="current-password"
          >
        </div>

        <div class="auth-actions">
          <button type="submit" class="btn-primary">
            登入小花園
          </button>
        </div>
      </form>

      <p class="auth-footer-text">
        還沒有帳號嗎？
        <a href="register.php">建立一個新小花園</a>
      </p>
    </div>
  </div>
</body>
</html>
