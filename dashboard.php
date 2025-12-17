<?php 

include 'includes/auth_check.php';
include 'includes/db.php';

date_default_timezone_set("Asia/Taipei");

$userId    = $_SESSION['user_id'];
$todayDate = date('Y-m-d');

function get_user_daily_goal($userId, $conn) {
    $stmt = $conn->prepare("SELECT daily_goal_ml FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($goal);
    $res = $stmt->fetch() ? (int)$goal : 2000;
    $stmt->close();
    return $res;
}

function insert_drink_log($userId, $amountMl, $conn) {
    if ($amountMl <= 0) return;
    $stmt = $conn->prepare("INSERT INTO water_logs (user_id, amount_ml, created_at) VALUES (?, ?, NOW())");
    $stmt->bind_param("ii", $userId, $amountMl);
    $stmt->execute();
    $stmt->close();
}

function get_today_total_water($userId, $todayDate, $conn) {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount_ml), 0) FROM water_logs WHERE user_id = ? AND DATE(created_at) = ?");
    $stmt->bind_param("is", $userId, $todayDate);
    $stmt->execute();
    $stmt->bind_result($total);
    $stmt->fetch();
    $stmt->close();
    return (int)$total;
}

function get_weekly_reach_info($userId, $todayDate, $conn) {
    $startDate = date('Y-m-d', strtotime($todayDate . ' -6 days'));
    $sql = "SELECT DATE(w.created_at) AS d, SUM(w.amount_ml) AS total, u.daily_goal_ml AS goal
            FROM water_logs w JOIN users u ON w.user_id = u.id
            WHERE w.user_id = ? AND DATE(w.created_at) BETWEEN ? AND ?
            GROUP BY DATE(w.created_at), u.daily_goal_ml";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $userId, $startDate, $todayDate);
    $stmt->execute();
    $res = $stmt->get_result();
    $result = array_fill(0, 7, false);
    while ($row = $res->fetch_assoc()) {
        $diff = (strtotime($row['d']) - strtotime($startDate)) / 86400;
        if ($diff >= 0 && $diff < 7) $result[(int)$diff] = ($row['total'] >= $row['goal']);
    }
    $stmt->close();
    return $result;
}

function get_lifetime_reach_count($userId, $conn) {
    $sql = "SELECT COUNT(*) FROM (SELECT SUM(w.amount_ml) as t, u.daily_goal_ml as g FROM water_logs w JOIN users u ON w.user_id=u.id WHERE w.user_id=? GROUP BY DATE(w.created_at)) as tmp WHERE t >= g";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($cnt);
    $stmt->fetch();
    $stmt->close();
    return (int)$cnt;
}

function calc_plant_stage($days) {
    if ($days < 10) return ['id' => 1, 'label' => '種子'];
    if ($days < 20) return ['id' => 2, 'label' => '幼苗期'];
    if ($days < 40) return ['id' => 3, 'label' => '成長期'];
    return ['id' => 4, 'label' => '開花'];
}
function calc_plant_mood($cnt) {
    $r = $cnt / 7;
    if ($r >= 0.85) return ['class' => 'mood-great', 'text' => '狀態非常好'];
    if ($r >= 0.6) return ['class' => 'mood-good', 'text' => '看起來不錯'];
    if ($r >= 0.3) return ['class' => 'mood-poor', 'text' => '有點缺水'];
    return ['class' => 'mood-bad', 'text' => '嚴重缺水'];
}

$dailyGoalMl = get_user_daily_goal($userId, $conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'drink') {
    insert_drink_log($userId, (int)($_POST['amount_ml'] ?? 0), $conn);
    header("Location: dashboard.php");
    exit;
}

$todayTotalMl = get_today_total_water($userId, $todayDate, $conn);
$weekReachCount = array_sum(get_weekly_reach_info($userId, $todayDate, $conn));
$lifetimeReachCount = get_lifetime_reach_count($userId, $conn);

$harvestGoalDays = 50;
$adjustedCount = max(0, $lifetimeReachCount - 1);
$plantGeneration = intdiv($adjustedCount, $harvestGoalDays) + 1;
$daysInCycle = $lifetimeReachCount == 0 ? 0 : ($adjustedCount % $harvestGoalDays) + 1;
$daysToHarvest = max(0, $harvestGoalDays - $daysInCycle);

$stageInfo = calc_plant_stage($daysInCycle);
$moodInfo = calc_plant_mood($weekReachCount);

$plantImagePath = "assets/img/plants/stage_{$stageInfo['id']}.png";
$defaultDrinkAmount = 200;

$currentPage = 'dashboard';
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>主頁｜WaterGrow</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<?php include 'includes/header.php'; ?>

<div class="garden-container">
    <div class="garden-inner">
        <section class="diary">
            <button type="button" class="help-btn" data-help-target="diary-help">?</button>
            <div class="diary-inner">
                <div class="diary-header"><div class="diary-title">植物日誌</div></div>
                <div class="diary-body">
                    <div class="diary-row"><span class="diary-label">日期</span><span class="diary-value"><?php echo htmlspecialchars($todayDate); ?></span></div>
                    <div class="diary-row"><span class="diary-label">今日喝水</span><span class="diary-value"><?php echo (int)$todayTotalMl; ?> / <?php echo (int)$dailyGoalMl; ?> ml</span></div>
                    <div class="diary-row"><span class="diary-label">成長階段</span><span class="diary-value"><?php echo htmlspecialchars($stageInfo['label']); ?></span></div>
                    <div class="diary-row"><span class="diary-label">近期達標</span><span class="diary-value"><?php echo (int)$weekReachCount; ?> / 7 天</span></div>
                    <div class="diary-row"><span class="diary-label">植物狀態</span><span class="diary-value"><?php echo htmlspecialchars($moodInfo['text']); ?></span></div>
                    <div class="diary-row"><span class="diary-label">收成倒數</span><span class="diary-value"><?php echo $daysToHarvest > 0 ? "再達標 {$daysToHarvest} 次" : "可收成！"; ?></span></div>
                    <div class="diary-row"><span class="diary-label">世代</span><span class="diary-value">第 <?php echo $plantGeneration; ?> 代 / 累積 <?php echo $lifetimeReachCount; ?> 天</span></div>
                </div>
            </div>
            <div class="help-content" id="diary-help">植物狀態由本週達標天數決定，成長階段由累積達標天數決定。</div>
        </section>

        <section class="garden-center">
            <div class="plant-container">
                <div class="plant-image-wrapper"><img src="<?php echo htmlspecialchars($plantImagePath); ?>" alt="Plant"></div>
                <div class="plant-water" id="plantWater"></div>
            </div>
        </section>

        <section class="watering-column">
            <div class="watering-can-area">
                <div class="watering-stand"><img src="assets/img/stump.png" alt="木樁"></div>
                <div class="watering-can" id="wateringCan"><img src="assets/img/watering_can.png" alt="Can"></div>
            </div>
            <div class="signboard">
                <section class="watering-panel">
                    <button type="button" class="help-btn" data-help-target="watering-help">?</button>
                    <div class="watering-controls">
                        <div class="amount-row">
                            <button type="button" class="arrow-btn" id="amountDown">&minus;</button>
                            <div class="amount-display"><span id="amountText"><?php echo $defaultDrinkAmount; ?></span> ml</div>
                            <button type="button" class="arrow-btn" id="amountUp">+</button>
                        </div>
                        <form method="post" id="drinkForm">
                            <input type="hidden" name="action" value="drink">
                            <input type="hidden" name="amount_ml" id="amountInput" value="<?php echo $defaultDrinkAmount; ?>">
                            <button type="submit" class="drink-btn"><span>幫植物澆水</span></button>
                        </form>
                    </div>
                    <div class="help-content" id="watering-help">調整水量後，按下按鈕即可記錄並澆水。</div>
                </section>
            </div>
        </section>
    </div>
</div>

<script>
    (function() {
        var amount = <?php echo $defaultDrinkAmount; ?>;
        var amountText = document.getElementById('amountText');
        var amountInput = document.getElementById('amountInput');
        
        function update() { amountText.textContent = amount; amountInput.value = amount; }
        document.getElementById('amountUp').onclick = function() { amount = Math.min(1000, amount + 50); update(); };
        document.getElementById('amountDown').onclick = function() { amount = Math.max(50, amount - 50); update(); };

        document.getElementById('drinkForm').onsubmit = function(e) {
            e.preventDefault();
            document.getElementById('wateringCan').classList.add('pouring');
            setTimeout(() => document.getElementById('plantWater').classList.add('active'), 200);
            setTimeout(() => this.submit(), 1000);
        };
        
        document.querySelectorAll('.help-btn').forEach(btn => btn.onclick = e => {
            e.stopPropagation();
            var t = document.getElementById(btn.dataset.helpTarget);
            if(t) {
                document.querySelectorAll('.help-content').forEach(el => el.classList.remove('show'));
                t.classList.add('show');
            }
        });
        document.onclick = () => document.querySelectorAll('.help-content').forEach(el => el.classList.remove('show'));
    })();
</script>
</body>
</html>