<?php 
// dashboard.php

require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/db.php';

date_default_timezone_set("Asia/Taipei");

$userId    = $_SESSION['user_id'];
$todayDate = date('Y-m-d');

$userName = '小花園用戶';
$stmt = $conn->prepare("SELECT name FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($userName);
$stmt->fetch();
$stmt->close();

// ==================== 資料存取 ====================

// 取得使用者每日目標
function get_user_daily_goal($userId, $conn) {
    $sql = "SELECT daily_goal_ml FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($goal);
    if ($stmt->fetch()) {
        $stmt->close();
        return (int)$goal;
    }
    $stmt->close();
    // 找不到就給一個預設值
    return 2000;
}

// 新增一筆喝水紀錄
function insert_drink_log($userId, $amountMl, $conn) {
    if ($amountMl <= 0) return;

    $sql = "INSERT INTO water_logs (user_id, amount_ml, created_at)
            VALUES (?, ?, NOW())";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $userId, $amountMl);
    $stmt->execute();
    $stmt->close();
}

// 取得今天總喝水量
function get_today_total_water($userId, $todayDate, $conn) {
    $sql = "SELECT COALESCE(SUM(amount_ml), 0)
            FROM water_logs
            WHERE user_id = ?
              AND DATE(created_at) = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $userId, $todayDate);
    $stmt->execute();
    $stmt->bind_result($total);
    $stmt->fetch();
    $stmt->close();
    return (int)$total;
}

// 取得最近 7 天是否達標（回傳 7 個 true/false）
function get_weekly_reach_info($userId, $todayDate, $conn) {
    $startDate = date('Y-m-d', strtotime($todayDate . ' -6 days'));

    $sql = "
        SELECT DATE(w.created_at) AS d,
               SUM(w.amount_ml) AS total,
               u.daily_goal_ml AS goal
        FROM water_logs w
        JOIN users u ON w.user_id = u.id
        WHERE w.user_id = ?
          AND DATE(w.created_at) BETWEEN ? AND ?
        GROUP BY DATE(w.created_at), u.daily_goal_ml
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("iss", $userId, $startDate, $todayDate);
    $stmt->execute();
    $res = $stmt->get_result();

    $result = array_fill(0, 7, false);

    while ($row = $res->fetch_assoc()) {
        $d = $row['d'];
        $total = (int)$row['total'];
        $goal  = (int)$row['goal'];

        $diff = (strtotime($d) - strtotime($startDate)) / 86400; // 0~6
        if ($diff >= 0 && $diff < 7) {
            $result[(int)$diff] = ($total >= $goal);
        }
    }

    $stmt->close();
    return $result;
}

// 統計「一生中」有幾天達標
function get_lifetime_reach_count($userId, $conn) {
    $sql = "
        SELECT COUNT(*) FROM (
            SELECT DATE(w.created_at) AS d,
                   SUM(w.amount_ml) AS total,
                   u.daily_goal_ml AS goal
            FROM water_logs w
            JOIN users u ON w.user_id = u.id
            WHERE w.user_id = ?
            GROUP BY DATE(w.created_at), u.daily_goal_ml
        ) AS t
        WHERE t.total >= t.goal
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($cnt);
    $stmt->fetch();
    $stmt->close();
    return (int)$cnt;
}


// ==================== 邏輯函式 ====================

// 成長階段：回傳 id + 顯示文字
function calc_plant_stage($daysInCycle) {
    if ($daysInCycle < 10) {
        return ['id' => 1, 'label' => '種子'];
    } elseif ($daysInCycle < 20) {
        return ['id' => 2, 'label' => '幼苗期'];
    } elseif ($daysInCycle < 40) {
        return ['id' => 3, 'label' => '成長期'];
    } else {
        return ['id' => 4, 'label' => '開花'];
    }
}

// 植物心情：根據一週達標天數決定 class 和文字 
function calc_plant_mood($weekReachCount) {
    $weekRatio = $weekReachCount / 7;

    if ($weekRatio >= 0.85) {
        return ['class' => 'mood-great', 'text' => '狀態非常好'];
    } elseif ($weekRatio >= 0.6) {
        return ['class' => 'mood-good', 'text' => '看起來不錯'];
    } elseif ($weekRatio >= 0.3) {
        return ['class' => 'mood-poor', 'text' => '有點缺水'];
    } else {
        return ['class' => 'mood-bad', 'text' => '嚴重缺水'];
    }
}

// 根據 stage id 組合圖片路徑
function get_plant_image_path($plantStageId) {
    return "assets/img/plants/stage_{$plantStageId}.png";
}

// ==================== 呼叫函式拿資料 ====================

$dailyGoalMl = get_user_daily_goal($userId, $conn);

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['action']) && $_POST['action'] === 'drink') {

    $added = isset($_POST['amount_ml']) ? (int)$_POST['amount_ml'] : 0;
    insert_drink_log($userId, $added, $conn);

    header("Location: dashboard.php");
    exit;
}

// 拿今天總喝水量 + 這週達標情況 + 累積達標天數
$todayTotalMl       = get_today_total_water($userId, $todayDate, $conn);
$weeklyReach        = get_weekly_reach_info($userId, $todayDate, $conn);
$weekReachCount     = array_sum($weeklyReach);
$lifetimeReachCount = get_lifetime_reach_count($userId, $conn);

// 收成邏輯
$harvestGoalDays = 50; 
$plantGeneration = intdiv(max(0, $lifetimeReachCount - 1), $harvestGoalDays) + 1;
$daysInCycle = (($lifetimeReachCount - 1) % $harvestGoalDays) + 1;
$daysToHarvest = max(0, $harvestGoalDays - $daysInCycle);

// 成長階段
$stageInfo     = calc_plant_stage($daysInCycle);
$plantStageId  = $stageInfo['id'];
$plantStage    = $stageInfo['label'];

// 植物心情
$moodInfo        = calc_plant_mood($weekReachCount);
$plantMoodClass  = $moodInfo['class'];
$plantMoodText   = $moodInfo['text'];

// 植物圖片
$plantImagePath    = get_plant_image_path($plantStageId);

// 水壺圖片 & 每次預設喝水量
$wateringCanImage   = "assets/img/watering_can.png";
$defaultDrinkAmount = 200;
?>

<!DOCTYPE html>
<html lang="zh-Hant">
<head>
    <meta charset="UTF-8">
    <title>WaterGrow 小花園 Dashboard</title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- header（之後抽成 header.php） -->
    <header class="navbar">
        <div class="logo">
          <img src="assets/img/logo.png" alt="WaterGrow Logo" class="logo-img">
          <span class="logo-text">WaterGrow 小花園</span>
        </div>
        <nav>
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="#">設定目標</a>
            <a href="#">歷史紀錄</a>
            <a href="auth/logout.php">登出</a>
        </nav>
        <div class="user">Hi, <?php echo htmlspecialchars($userName); ?></div>
    </header>

    <div class="garden-container">
        <div class="garden-inner">
            <!-- 左：植物日誌 -->
            <section class="diary">
                <!-- 右上角說明 ? -->
                <button type="button" class="help-btn" data-help-target="diary-help">?</button>
                <!-- 內頁 -->
                <div class="diary-inner">
                    <div class="diary-header">
                        <div class="diary-title">植物日誌</div>
                    </div>
                    <div class="diary-body">
                        <div class="diary-row">
                            <span class="diary-label">日期</span>
                            <span class="diary-value"><?php echo htmlspecialchars($todayDate); ?></span>
                        </div>
                        <div class="diary-row">
                            <span class="diary-label">今日喝水</span>
                            <span class="diary-value">
                                <?php echo (int)$todayTotalMl; ?> ml / <?php echo (int)$dailyGoalMl; ?> ml
                            </span>
                        </div>
                        <div class="diary-row">
                            <span class="diary-label">成長階段</span>
                            <span class="diary-value"><?php echo htmlspecialchars($plantStage); ?></span>
                        </div>
                        <div class="diary-row">
                            <span class="diary-label">本週達標</span>
                            <span class="diary-value"><?php echo (int)$weekReachCount; ?> / 7 天</span>
                        </div>
                        <div class="diary-row">
                            <span class="diary-label">植物狀態</span>
                            <span class="diary-value">
                                <?php echo htmlspecialchars($plantMoodText); ?>
                            </span>
                        </div>
                        <div class="diary-row">
                            <span class="diary-label">收成倒數</span>
                            <span class="diary-value">
                                <?php if ($daysToHarvest > 0): ?>
                                    再達標 <?php echo (int)$daysToHarvest; ?> 次可以收成
                                <?php else: ?>
                                    收成！再達標可重新開始
                                <?php endif; ?>
                            </span>
                        </div>
                        <!-- 第幾代 + 累積達標 -->
                        <div class="diary-row">
                            <span class="diary-label">植物世代</span>
                            <span class="diary-value">
                                第 <?php echo (int)$plantGeneration; ?> 代 / 累積達標 <?php echo (int)$lifetimeReachCount; ?> 天
                            </span>
                        </div>
                    </div>
                </div>

                <div class="help-content" id="diary-help">
                    植物狀態由「本週有幾天達標」決定，
                    成長階段則是看累積達標天數，
                    一週一週慢慢把小種子養成大樹 🌳<br>
                    收成倒數則是用「累積達標天數」對照收成門檻計算。
                </div>
            </section>

            <!-- 中：植物 -->
            <section class="garden-center">
                <div class="plant-container">
                    <div class="plant-image-wrapper">
                        <img src="<?php echo htmlspecialchars($plantImagePath); ?>" alt="Plant">
                    </div>
                    <div class="plant-water" id="plantWater"></div>
                </div>
            </section>

            <!-- 右：水壺站在木樁上，告示牌的澆水控制 -->
            <section class="watering-column">
                <!-- 水壺站在木樁上 -->
                <div class="watering-can-area">
                    <div class="watering-stand">
                        <img src="assets/img/stump.png" alt="木樁">
                    </div>
                    <div class="watering-can" id="wateringCan">
                        <img src="<?php echo htmlspecialchars($wateringCanImage); ?>" alt="Watering can">
                    </div>
                </div>

                <!-- 下半：告示牌的澆水控制 -->
                <div class="signboard">
                    <section class="watering-panel">
                        <button type="button" class="help-btn" data-help-target="watering-help">?</button>
                        <div class="watering-controls">
                            <div class="amount-row">
                                <button type="button" class="arrow-btn" id="amountDown">&minus;</button>
                                <div class="amount-display">
                                    <span id="amountText"><?php echo (int)$defaultDrinkAmount; ?></span> ml
                                </div>
                                <button type="button" class="arrow-btn" id="amountUp">+</button>
                            </div>
                            <form method="post" action="" id="drinkForm">
                                <input type="hidden" name="action" value="drink">
                                <input type="hidden" name="amount_ml" id="amountInput"
                                       value="<?php echo (int)$defaultDrinkAmount; ?>">
                                <button type="submit" class="drink-btn">
                                    <span>幫植物澆水</span>
                                </button>
                            </form>
                        </div>
                        <div class="help-content" id="watering-help">
                            左右箭頭可以調整這次要記錄的喝水量，
                            按下「幫植物澆水」後，會新增一杯到今天的喝水紀錄，
                            並幫植物澆水。
                        </div>
                    </section>
                </div>
            </section>
        </div>
    </div>

    <script>
        // JS：控制喝水量 & 澆水動畫
        (function() {
            var amount      = <?php echo (int)$defaultDrinkAmount; ?>;
            var minAmount   = 50;
            var maxAmount   = 1000;
            var step        = 50;

            var amountText  = document.getElementById('amountText');
            var amountInput = document.getElementById('amountInput');
            var btnUp       = document.getElementById('amountUp');
            var btnDown     = document.getElementById('amountDown');
            var wateringCan = document.getElementById('wateringCan');
            var drinkForm   = document.getElementById('drinkForm');
            var plantWater  = document.getElementById('plantWater');

            function updateAmountDisplay() {
                amountText.textContent = amount;
                amountInput.value      = amount;
            }

            btnUp.addEventListener('click', function() {
                amount = Math.min(maxAmount, amount + step);
                updateAmountDisplay();
            });

            btnDown.addEventListener('click', function() {
                amount = Math.max(minAmount, amount - step);
                updateAmountDisplay();
            });

            drinkForm.addEventListener('submit', function(e) {
                // 先擋住表單，讓動畫播完再真的送出
                e.preventDefault();

                wateringCan.classList.add('pouring');
                setTimeout(function() {
                    plantWater.classList.add('active');
                }, 200); // 0.2s 後開始水滴動畫

                setTimeout(function() {
                    wateringCan.classList.remove('pouring');
                    plantWater.classList.remove('active');
                    drinkForm.submit(); // 動畫播完再送出
                }, 1000); // 和 CSS 動畫時間 1s 對齊
            });

            // 右上角 ? 說明的開關
            var helpButtons = document.querySelectorAll('.help-btn');

            helpButtons.forEach(function(btn) {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();

                    var targetId = btn.getAttribute('data-help-target');
                    var panel    = document.getElementById(targetId);
                    if (!panel) return;

                    var isShown = panel.classList.contains('show');

                    // 先把所有說明收起來
                    document.querySelectorAll('.help-content').forEach(function(p) {
                        p.classList.remove('show');
                    });

                    // 如果原本是關的，就打開目標那一個
                    if (!isShown) {
                        panel.classList.add('show');
                    }
                });
            });

            // 點其他地方關掉說明
            document.addEventListener('click', function(e) {
                if (e.target.closest('.help-btn') || e.target.closest('.help-content')) {
                    return;
                }
                document.querySelectorAll('.help-content').forEach(function(p) {
                    p.classList.remove('show');
                });
            });

            updateAmountDisplay();
        })();
    </script>
</body>
</html>
