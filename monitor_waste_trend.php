<?php
// =======================================================
// 1. 建立数据库连接 (XAMPP MySQL)
// =======================================================
$host = 'mysql.railway.internal';
$dbname = 'railway';
$user = 'root';
$pass = 'VpUQTVAAjVaDLhqBcUZMfxoJhHEpPRKx'; 

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

// =======================================================
// 2. 核心数据解析：过去 24 小时内精准扫描上传时间数据
// =======================================================
$today_data = ['Plastic' => [], 'Aluminum' => [], 'Paper' => []];

// 🌟 自动化修改点：直接取出过去 24 小时内的精确时间戳 (created_at)
$today_query = "SELECT material_type, created_at 
                FROM waste_records 
                WHERE created_at >= NOW() - INTERVAL 24 HOUR
                ORDER BY created_at ASC";

$today_res = $conn->query($today_query);

if ($today_res && $today_res->num_rows > 0) {
    // 动态累加计数器，用来形成趋势
    $running_counts = ['Plastic' => 0, 'Aluminum' => 0, 'Paper' => 0];
    
    while ($row = $today_res->fetch_assoc()) {
        $b_type = ucfirst(strtolower($row['material_type'])); 
        if (isset($running_counts[$b_type])) {
            $running_counts[$b_type]++; // 每扫描上传一个，数量递增
            
            // 将精确的上传时间与当时的累计数量存入数组
            $today_data[$b_type][] = [
                'x' => $row['created_at'], // 完美的格式：YYYY-MM-DD HH:MM:SS
                'y' => $running_counts[$b_type]
            ];
        }
    }
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

$weekly_query = "SELECT material_type, 
                        DAYNAME(created_at) as day_name,
                        COUNT(id) as total_count
                 FROM waste_records 
                 WHERE WEEK(created_at, 1) = WEEK(CURDATE(), 1) AND YEAR(created_at) = YEAR(CURDATE())
                 GROUP BY material_type, day_name";

$weekly_res = $conn->query($weekly_query);
$day_map = ['Monday'=>'Mon', 'Tuesday'=>'Tue', 'Wednesday'=>'Wed', 'Thursday'=>'Thu', 'Friday'=>'Fri', 'Saturday'=>'Sat', 'Sunday'=>'Sun'];

if ($weekly_res && $weekly_res->num_rows > 0) {
    while ($row = $weekly_res->fetch_assoc()) {
        $b_type = ucfirst(strtolower($row['material_type']));
        $full_day = $row['day_name'];
        if (isset($day_map[$full_day])) {
            $short_day = $day_map[$full_day];
            $idx = array_search($short_day, $weekly_labels);
            if ($idx !== false && isset($weekly_data[$b_type])) {
                $weekly_data[$b_type][$idx] = (int)$row['total_count'];
            }
        }
    }
}

$conn->close();
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
            font-family: 'Arial', sans-serif;
            background-color: #f8f8f8;
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
            <a href="welcome.php">Logout</a>
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
        // 从 PHP 获取精准时间点数据
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

        // 🌟 核心配置：动态定义两套不同的坐标轴表现（包含强制整数轴修复）
        const viewOptions = {
            today: {
                scales: {
                    x: {
                        type: 'time', 
                        time: {
                            unit: 'hour', 
                            displayFormats: { hour: 'HH:mm' } 
                        },
                        min: new Date(Date.now() - 24 * 60 * 60 * 1000).toISOString(), 
                        max: new Date().toISOString(), 
                        title: { display: true, text: 'Exact Scan & Upload Time (Past 24 Hours)' }
                    },
                    y: { 
                        beginAtZero: true, 
                        title: { display: true, text: 'Scanned Items Count' },
                        // 🌟 这里加入了强制整数刻度的控制逻辑
                        ticks: {
                            stepSize: 1,
                            precision: 0,
                            callback: function(value) {
                                if (Math.floor(value) === value) {
                                    return value;
                                }
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
                        // 🌟 周视图也同步加上整数限制
                        ticks: {
                            stepSize: 1,
                            precision: 0
                        }
                    }
                }
            }
        };

        const ctx = document.getElementById('wasteTrendChart').getContext('2d');
        let currentView = 'today';

        // 初始化图表
        const wasteTrendChart = new Chart(ctx, {
            type: 'line',
            data: {
                datasets: todayDatasets 
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'top', labels: { font: { size: 14 } } }
                },
                scales: viewOptions[currentView].scales
            }
        });

        // 视图切换逻辑
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

            // 动态更新轴控制策略
            wasteTrendChart.options.scales = viewOptions[view].scales;
            wasteTrendChart.update();
        }
    </script>
</body>
</html>
