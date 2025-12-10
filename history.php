<?php
include 'includes/auth_check.php';
include 'includes/db.php';

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT daily_goal_ml FROM users WHERE id=?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$goal = $stmt->get_result()->fetch_assoc()['daily_goal_ml'];

$history_stmt = $conn->prepare("SELECT DATE(created_at) as day, SUM(amount_ml) as total FROM water_logs WHERE user_id=? GROUP BY DATE(created_at) ORDER BY day DESC");
$history_stmt->bind_param("i", $user_id);
$history_stmt->execute();
$history_result = $history_stmt->get_result();

$records = [];
while($row = $history_result->fetch_assoc()) $records[] = $row;

$week_start = date('Y-m-d', strtotime('monday this week'));
$week_end   = date('Y-m-d', strtotime('sunday this week'));
$week_hits  = 0;
foreach ($records as $r) {
    if ($r['day'] >= $week_start && $r['day'] <= $week_end && $r['total'] >= $goal) $week_hits++;
}

$streak = 0;
if (!empty($records)) {
    $today = new DateTime();
    $yesterday = (clone $today)->modify('-1 day');
    $latestDate = new DateTime($records[0]['day']);
    
    if (($latestDate->format('Y-m-d') === $today->format('Y-m-d') || $latestDate->format('Y-m-d') === $yesterday->format('Y-m-d'))) {
        $checkDate = $latestDate;
        foreach ($records as $r) {
            $recordDate = new DateTime($r['day']);
            if ($recordDate->format('Y-m-d') === $checkDate->format('Y-m-d')) {
                if ($r['total'] >= $goal) {
                    $streak++;
                    $checkDate->modify('-1 day');
                } else break;
            } else break;
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="auth-page">
    <div class="auth-card">
        <div class="auth-app-brand">
            <div class="auth-app-logo-img"><img src="assets/img/logo.png" alt="Logo"></div>
            <div class="auth-app-name">喝水小花園<small>History</small></div>
        </div>

        <h2 class="auth-title">歷史紀錄</h2>
        <div class="history-table" style="overflow-x:auto;">
            <table>
                <thead><tr><th>日期</th><th>總量</th><th>達標</th></tr></thead>
                <tbody>
                    <?php if (empty($records)): ?><tr><td colspan="3">無紀錄</td></tr>
                    <?php else: foreach ($records as $r): $achieved = $r['total'] >= $goal; ?>
                        <tr>
                            <td><?php echo htmlspecialchars($r['day']); ?></td>
                            <td><?php echo (int)$r['total']; ?></td>
                            <td class="<?php echo $achieved ? 'mood-great' : 'mood-bad'; ?>"><?php echo $achieved ? '✔' : '✘'; ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <div class="stats" style="margin-top:18px;text-align:center;">
            <p>本週達標：<strong><?php echo $week_hits; ?></strong></p>
            <p>連續達標：<strong><?php echo $streak; ?> 天</strong></p>
        </div>
        <p class="auth-footer-text"><a href="dashboard.php">回到主頁</a></p>
    </div>
</div>

</body>
</html>