<?php
session_start();
include '../includes/db.php';

if (isset($_SESSION['user_id'])) {
    header("Location: ../dashboard.php");
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name     = trim($_POST['name'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($name === '' || $password === '') {
        $error = "帳號與密碼不可為空";
    } else {
        $check = $conn->prepare("SELECT id FROM users WHERE name = ?");
        $check->bind_param("s", $name);
        $check->execute();
        $checkResult = $check->get_result();

        if ($checkResult->num_rows > 0) {
            $error = "使用者名稱已存在，請換一個名稱";
            $check->close();
        } else {
            $check->close();

            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $conn->prepare("INSERT INTO users (name, password_hash) VALUES (?, ?)");
            $stmt->bind_param("ss", $name, $password_hash);

            if ($stmt->execute()) {
                $stmt->close();
                header("Location: login.php");
                exit;
            } else {
                $error = "註冊失敗，請稍後再試";
                $stmt->close();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
    <meta charset="UTF-8">
    <title>註冊｜WaterGrow 小花園</title>
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

      <h1 class="auth-title">建立新帳號</h1>
      <p class="auth-subtitle">設定一個帳號，從今天開始好好喝水</p>

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
            autocomplete="new-password"
          >
        </div>

        <div class="auth-actions">
          <button type="submit" class="btn-primary">
            建立帳號
          </button>
        </div>
      </form>

      <p class="auth-footer-text">
        已經有帳號了？
        <a href="login.php">直接登入</a>
      </p>
    </div>
  </div>
</body>
</html>
