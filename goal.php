<?php
include 'includes/auth_check.php';
include 'includes/db.php';

$user_id = $_SESSION['user_id'];

// 取得目前目標
$stmt = $conn->prepare("SELECT daily_goal_ml FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$row = $result->fetch_assoc();
$current_goal = $row['daily_goal_ml'];

// 處理表單
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_goal = intval($_POST['daily_goal_ml']);
    if ($new_goal > 0) {
        $update_stmt = $conn->prepare("UPDATE users SET daily_goal_ml=? WHERE id=?");
        $update_stmt->bind_param("ii", $new_goal, $user_id);
        $update_stmt->execute();
        header("Location: dashboard.php");
        exit;
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="auth-page">
    <div class="auth-card">
        <div class="auth-app-brand">
            <div class="auth-app-logo-img">
                <img src="assets/img/logo.png" alt="Logo">
            </div>
            <div class="auth-app-name">喝水小花園<small>Goal Setting</small></div>
        </div>

        <h2 class="auth-title">設定每日喝水目標</h2>
        <p class="auth-subtitle">輸入你今天的飲水目標，保持小花園健康成長</p>

        <form method="post">
            <div class="auth-form-group">
                <label for="daily_goal_ml" class="auth-label">每日目標 (ml)</label>
                <input type="number" class="auth-input" id="daily_goal_ml" name="daily_goal_ml" value="<?php echo $current_goal; ?>" required>
            </div>

            <div class="auth-actions">
                <button type="submit" class="btn-primary">更新目標</button>
            </div>
        </form>

        <p class="auth-footer-text">
            <a href="dashboard.php">回到主頁</a>
        </p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
