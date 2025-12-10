<?php
include 'includes/auth_check.php';
include 'includes/db.php';

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT daily_goal_ml FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$goal = $stmt->get_result()->fetch_assoc()['daily_goal_ml'];

$week_offset = isset($_GET['week']) ? intval($_GET['week']) : 0;

$base_monday = strtotime("monday this week");

$week_start = date('Y-m-d', strtotime("$week_offset week", $base_monday));
$week_end   = date('Y-m-d', strtotime("$week_offset week +6 days", $base_monday));

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

$week_records = array_filter($records, function($r) use ($week_start, $week_end) {
    return $r['day'] >= $week_start && $r['day'] <= $week_end;
});

$week_hits = 0;
foreach ($week_records as $r) {
    if ($r['total'] >= $goal) $week_hits++;
}

$currentPage = 'history';
?>
<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>歷史紀錄｜WaterGrow</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .week-nav { text-align:center; margin-top:15px; }
        .week-nav a {
            padding:8px 16px; 
            border-radius:8px; 
            background:#4a90e2; 
            color:white; 
            text-decoration:none; 
            margin:0 10px;
            display:inline-block;
        }
        .week-range { text-align:center; font-weight:bold; margin:10px 0; }
    </style>
</head>
<body>

<?php include 'includes/header.php'; ?>

<div class="auth-page auth-page--with-header">
    <div class="auth-card">
        <div class="auth-app-brand">
            <div class="auth-app-logo-img"><img src="assets/img/logo.png" alt="Logo"></div>
            <div class="auth-app-name">WaterGrow<small>History</small></div>
        </div>

        <h2 class="auth-title">歷史紀錄</h2>
        <p class="week-range">
            <?php echo $week_start . " ~ " . $week_end; ?>
        </p>

        <div class="history-table" style="overflow-x:auto;">
            <table>
                <thead>
                    <tr><th>日期</th><th>總量</th><th>達標</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($week_records)): ?>
                        <tr><td colspan="3">本週無紀錄</td></tr>
                    <?php else: foreach ($week_records as $r): ?>
                        <tr>
                            <td><?php echo $r['day']; ?></td>
                            <td><?php echo (int)$r['total']; ?></td>
                            <td class="<?php echo $r['total'] >= $goal ? 'mood-great' : 'mood-bad'; ?>">
                                <?php echo $r['total'] >= $goal ? '✔' : '✘'; ?>
                            </td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <div class="stats" style="margin-top:18px;text-align:center;">
            <p>本週達標：<strong><?php echo $week_hits; ?> 天</strong></p>
        </div>

        <div class="week-nav">
            <a href="?week=<?php echo $week_offset - 1; ?>">← 上一週</a>
            <a href="?week=<?php echo $week_offset + 1; ?>">下一週 →</a>
        </div>

        <p class="auth-footer-text"><a href="dashboard.php">回到主頁</a></p>
    </div>
</div>

</body>
</html>
