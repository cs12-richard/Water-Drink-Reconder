<?php
include 'includes/auth_check.php';
include 'includes/db.php';

$user_id = $_SESSION['user_id'];

// 取得使用者目標
$stmt = $conn->prepare("SELECT daily_goal_ml FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$goal = $result->fetch_assoc()['daily_goal_ml'];

// 取得過去 N 天的總量
$history_stmt = $conn->prepare("
    SELECT DATE(created_at) as day, SUM(amount_ml) as total
    FROM water_logs
    WHERE user_id=?
    GROUP BY DATE(created_at)
    ORDER BY day DESC
");
$history_stmt->bind_param("i", $user_id);
$history_stmt->execute();
$history_result = $history_stmt->get_result();

$records = [];
while($row = $history_result->fetch_assoc()) {
    $records[] = $row;
}

// 計算本週達標天數
$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end = date('Y-m-d', strtotime('sunday this week'));
$week_hits = 0;
foreach ($records as $r) {
    if ($r['day'] >= $week_start && $r['day'] <= $week_end && $r['total'] >= $goal) {
        $week_hits++;
    }
}

// 計算 streak
$streak = 0;
foreach ($records as $r) {
    if ($r['total'] >= $goal) $streak++;
    else break;
}
?>

<?php include 'includes/header.php'; ?>

<div class="auth-page">
    <div class="auth-card">
        <div class="auth-app-brand">
            <div class="auth-app-logo-img">
                <img src="assets/img/logo.png" alt="Logo">
            </div>
            <div class="auth-app-name">喝水小花園<small>History</small></div>
        </div>

        <h2 class="auth-title">歷史紀錄</h2>
        <p class="auth-subtitle">查看每日喝水量與達標情況，保持小花園健康成長</p>

        <div class="history-table" style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>日期</th>
                        <th>總喝水量 (ml)</th>
                        <th>是否達標</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($records as $r): 
                        $achieved = $r['total'] >= $goal;
                    ?>
                        <tr>
                            <td><?php echo $r['day']; ?></td>
                            <td><?php echo $r['total']; ?></td>
                            <td class="<?php echo $achieved ? 'mood-great' : 'mood-bad'; ?>">
                                <?php echo $achieved ? '✔' : '✘'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="stats" style="margin-top: 18px; text-align:center;">
            <p>本週達標天數：<strong><?php echo $week_hits; ?></strong></p>
            <p>連續達標天數：<strong><?php echo $streak; ?></strong></p>
        </div>

        <p class="auth-footer-text">
            <a href="dashboard.php">回到主頁</a>
        </p>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
