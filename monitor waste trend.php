<?php
// WasteScan AI - Admin Analytics Board
// 1. 开启会话并建立真实的 MySQL 数据库连接
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🔒 权限安全拦截：检查管理员是否正常登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin.php');
    exit;
}

// 🔌 线上部署核心修正：使用 getenv() 完美自适应 Railway 环境变量
$host = getenv('MYSQLHOST') ?: 'mysql.railway.internal';
$port = getenv('MYSQLPORT') ?: 3306;
$dbname = getenv('MYSQLDATABASE') ?: 'railway'; 
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: 'asMgnFdMgJUNIekzFfCVeBpSWyzfJmDp'; 

try {
    // 🌟 修正：改用 PDO 风格连接，规避 mysqli 扩展缺失导致的崩溃
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // 🌟【核心突破】：强行让统计大屏会话对齐本地 +8 时区，防止过去 24h 的计算发生 8 小时漂移！
    $pdo->exec("SET time_zone = '+08:00';");
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// =======================================================
// 2. 核心数据解析：过去 24 小时内精准扫描上传时间数据
// =======================================================
$today_data = ['Plastic' => [], 'Aluminum' => [], 'Paper' => []];

try {
    // 🌟 此时的 NOW() 会完美基于 GMT+8 运行，确保时间轴范围分秒不差
    $today_query = "SELECT material_type, created_at 
                    FROM waste_records 
                    WHERE created_at >= NOW() - INTERVAL 24 HOUR
                    ORDER BY created_at ASC";

    $today_stmt = $pdo->query($today_query);
    $running_counts = ['Plastic' => 0, 'Aluminum' => 0, 'Paper' => 0];
    
    while ($row = $today_stmt->fetch(PDO::FETCH_ASSOC)) {
        $raw_type = strtolower($row['material_type']);
        
        $b_type = 'Plastic';
        if ($raw_type === 'aluminum' || $raw_type === 'aluminium') {
            $b_type = 'Aluminum';
        } elseif ($raw_type === 'paper') {
            $b_type = 'Paper';
        } elseif ($raw_type === 'plastic') {
            $b_type = 'Plastic';
        } else {
            continue; 
        }

        if (isset($running_counts[$b_type])) {
            $running_counts[$b_type]++; 
            
            $today_data[$b_type][] = [
                'x' => $row['created_at'], 
                'y' => $running_counts[$b_type]
            ];
        }
    }
} catch(PDOException $e) {
    // 异常处理
}

// =======================================================
// 3. 核心数据解析：本周七天趋势 (Weekly Trend)
// =======================================================
$weekly_labels = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
$weekly_data = [
    'Plastic'  => [0,0,0,0,0,0,0], 
    'Aluminum' => [0,0,0,0,0,0,0],
    'Paper'    => [0,0,0,0,0,0,0]
];

try {
    // 🌟 此时的 WEEK() 与 CURDATE() 同样基于本地 +8 结算，杜绝了清晨时段的数据偏差
    $weekly_query = "SELECT 
                        CASE 
                            WHEN LOWER(material_type) = 'aluminium' THEN 'aluminum'
                            ELSE LOWER(material_type)
                        END as unified_material, 
                        DAYNAME(created_at) as day_name,
                        COUNT(id) as total_count
                     FROM waste_records 
                     WHERE WEEK(created_at, 1) = WEEK(CURDATE(), 1) AND YEAR(created_at) = YEAR(CURDATE())
                     GROUP BY unified_material, day_name";

    $weekly_stmt = $pdo->query($weekly_query);
    $day_map = ['Monday'=>'Mon', 'Tuesday'=>'Tue', 'Wednesday'=>'Wed', 'Thursday'=>'Thu', 'Friday'=>'Fri', 'Saturday'=>'Sat', 'Sunday'=>'Sun'];

    while ($row = $weekly_stmt->fetch(PDO::FETCH_ASSOC)) {
        $raw_mat = $row['unified_material'];
        $b_type = ($raw_mat === 'aluminum') ? 'Aluminum' : ucfirst($raw_mat);
        $full_day = $row['day_name'];
        
        if (isset($day_map[$full_day])) {
            $short_day = $day_map[$full_day];
            $idx = array_search($short_day, $weekly_labels);
            if ($idx !== false && isset($weekly_data[$b_type])) {
                $weekly_data[$b_type][$idx] = (int)$row['total_count'];
            }
        }
    }
} catch(PDOException $e) {
    // 处理异常
}

$pdo = null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitor Waste Trend - WasteScan AI</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns"></script>
    <style>
        body {
            margin: 0; padding: 0;
            background-color: #f8f8f8;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            display: flex; flex-direction: column; align-items: center;
        }
        .top-nav {
            width: 100%; background-color: #f2e1c1; padding: 15px 30px;
            display: flex; justify-content: space-between; box-sizing: border-box;
        }
        .top-nav a { text-decoration: none; color: #333; font-weight: bold; font-size: 14px; }
        .breadcrumb { font-size: 14px; font-weight: bold; color: #333; }
        .page-header { text-align: center; margin-top: 40px; }
        .dashboard-logo { width: 150px; height: auto; margin-bottom: 20px; }
        .page-title { color: #b08d57; font-size: 28px; font-weight: bold; margin-top: 10px; }
        .tab-container { display: flex; gap: 12px; margin-top: 20px; }
        .tab-btn {
            background-color: #fff; border: 2px solid #b08d57; color: #b08d57;
            padding: 8px 24px; font-size: 14px; font-weight: bold; border-radius: 20px;
            cursor: pointer; transition: all 0.3s ease;
        }
        .tab-btn:hover { background-color: #f2e1c1; }
        .tab-btn.active { background-color: #b08d57; color: #fff; }
        .content-area {
            width: 85%; max-width: 850px; margin-top: 25px; margin-bottom: 50px;
            background-color: #ffffff; padding: 20px; border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05); box-sizing: border-box;
        }
    </style>
</head>
<body>

    <nav class="top-nav">
        <div class="breadcrumb">
            <a href="dashboard.php">Dashboard</a> &rarr; Monitor Waste Trend
        </div>
        <div class="nav-logout">
            <a href="dashboard.php?action=logout">Logout</a>
        </div>
    </nav>

    <header class="page-header">
        <img src="WasteScan_AI-removebg-preview.png" alt="WasteScan AI Logo" class="dashboard-logo">
        <div class="page-title">Monitor Waste Trend</div>
    </header>

    <div class="tab-container">
        <button class="tab-btn active" onclick="switchView('today', event)">Today (24h)</button>
        <button class="tab-btn" onclick="switchView('weekly', event)">Weekly Trend</button>
    </div>

    <main class="content-area">
        <canvas id="wasteTrendChart"></canvas>
    </main>

    <script>
        const todayDatasets = [
            { label: 'Plastic', data: <?php echo json_encode($today_data['Plastic']); ?>, borderColor: '#ff4d4d', backgroundColor: 'rgba(255, 77, 77, 0.1)', borderWidth: 3, tension: 0.1 },
            { label: 'Aluminum', data: <?php echo json_encode($today_data['Aluminum']); ?>, borderColor: '#ffcc00', backgroundColor: 'rgba(255, 204, 0, 0.1)', borderWidth: 3, tension: 0.1 },
            { label: 'Paper', data: <?php echo json_encode($today_data['Paper']); ?>, borderColor: '#3399ff', backgroundColor: 'rgba(51, 153, 255, 0.1)', borderWidth: 3, tension: 0.1 }
        ];

        const weeklyDatasets = [
            { label: 'Plastic', data: <?php echo json_encode($weekly_data['Plastic']); ?>, borderColor: '#ff4d4d', backgroundColor: 'rgba(255, 77, 77, 0.1)', borderWidth: 3, tension: 0.4 },
            { label: 'Aluminum', data: <?php echo json_encode($weekly_data['Aluminum']); ?>, borderColor: '#ffcc00', backgroundColor: 'rgba(255, 204, 0, 0.1)', borderWidth: 3, tension: 0.4 },
            { label: 'Paper', data: <?php echo json_encode($weekly_data['Paper']); ?>, borderColor: '#3399ff', backgroundColor: 'rgba(51, 153, 255, 0.1)', borderWidth: 3, tension: 0.4 }
        ];

        const viewOptions = {
            today: {
                scales: {
                    x: {
                        type: 'time', 
                        time: {
                            unit: 'hour', 
                            // 🌟 核心突破：将 Chart.js X 轴的时间气泡渲染格式直接锁定为 24 小时制（HH:mm）
                            displayFormats: { hour: 'HH:mm' } 
                        },
                        min: new Date(Date.now() - 24 * 60 * 60 * 1000).toISOString(), 
                        max: new Date().toISOString(), 
                        title: { display: true, text: 'Exact Scan & Upload Time (Past 24 Hours)' }
                    },
                    y: { 
                        beginAtZero: true, 
                        title: { display: true, text: 'Scanned Items Count' },
                        ticks: {
                            stepSize: 1,
                            precision: 0,
                            callback: function(value) {
                                if (Math.floor(value) === value) { return value; }
                            }
                        }
                    }
                }
            },
            weekly: {
                scales: {
                    x: { type: 'category', title: { display: true, text: 'Days of the Week' } }, 
                    y: { 
                        beginAtZero: true, 
                        title: { display: true, text: 'Total Bins Collected / Day' },
                        ticks: { stepSize: 1, precision: 0 }
                    }
                }
            }
        };

        const ctx = document.getElementById('wasteTrendChart').getContext('2d');
        let currentView = 'today';

        const wasteTrendChart = new Chart(ctx, {
            type: 'line',
            data: { datasets: todayDatasets },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top', labels: { font: { size: 14 } } }
                },
                scales: viewOptions[currentView].scales
            }
        });

        function switchView(view, event) {
            currentView = view;

            const buttons = document.querySelectorAll('.tab-btn');
            buttons.forEach(btn => btn.classList.remove('active'));
            event.target.classList.add('active');

            if (view === 'today') {
                wasteTrendChart.data.labels = undefined; 
                wasteTrendChart.data.datasets = todayDatasets;
            } else {
                wasteTrendChart.data.labels = <?php echo json_encode($weekly_labels); ?>;
                wasteTrendChart.data.datasets = weeklyDatasets;
            }

            wasteTrendChart.options.scales = viewOptions[view].scales;
            wasteTrendChart.update();
        }
    </script>
</body>
</html>
