<?php
// WasteScan AI - Admin Recycle Bin Monitor
// 1. 开启全局会话控制
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🔒 权限安全拦截：检查管理员是否正常登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin.php');
    exit;
}

// 🔌 线上部署核心修正：使用 getenv() 完美自适应 Railway 环境变量与内网拓扑结构
$host = getenv('MYSQLHOST') ?: 'mysql.railway.internal';
$port = getenv('MYSQLPORT') ?: 3306;
$dbname = getenv('MYSQLDATABASE') ?: 'railway'; 
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: 'asMgnFdMgJUNIekzFfCVeBpSWyzfJmDp'; 

$bin_data = [
    'Plastic'  => ['capacity' => 0, 'status' => 'Normal'],
    'Aluminium' => ['capacity' => 0, 'status' => 'Normal'],
    'Paper'    => ['capacity' => 0, 'status' => 'Normal']
];

try {
    // 🌟 修正：改用标准 PDO 风格连接，规避 mysqli 扩展缺失导致的崩溃
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "SELECT bin_name, current_volume, status FROM recycle_bins";
    $stmt = $pdo->query($sql);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $raw_name = strtolower($row['bin_name']);
        
        // 规整映射，防止拼写和大小写产生数据滑坡
        $type = 'Plastic';
        if ($raw_name === 'aluminium' || $raw_name === 'aluminium') {
            $type = 'Aluminium';
        } elseif ($raw_name === 'paper') {
            $type = 'Paper';
        } elseif ($raw_name === 'plastic') {
            $type = 'Plastic';
        } else {
            continue;
        }

        if (isset($bin_data[$type])) {
            $bin_data[$type]['capacity'] = (int)$row['current_volume'];
            if (isset($row['status'])) {
                $bin_data[$type]['status'] = $row['status'];
            }
        }
    }
    
    // 关闭 PDO 连接
    $pdo = null;

} catch (PDOException $e) {
    // 如果数据库连接有误，在此处捕获
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recycle Bin Status - WasteScan AI</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            margin: 0; padding: 0;
            font-family: 'Arial', sans-serif;
            background-color: #f8f8f8;
            display: flex; flex-direction: column; align-items: center;
        }
        .top-nav {
            width: 100%; background-color: #f2e1c1; padding: 12px 25px;
            display: flex; justify-content: space-between; align-items: center; box-sizing: border-box;
        }
        .breadcrumb { font-size: 15px; font-weight: bold; color: #333; }
        .breadcrumb a { text-decoration: none; color: #333; }
        .breadcrumb a:hover { text-decoration: underline; }
        .nav-logout a { text-decoration: none; color: #333; font-weight: bold; font-size: 15px; }
        .page-header { text-align: center; margin-top: 50px; }
        .dashboard-logo { width: 150px; height: auto; margin-bottom: 20px; }
        .page-title { color: #b08d57; font-size: 30px; font-weight: bold; margin-bottom: 50px; }
        .content-area { display: flex; justify-content: center; gap: 40px; padding: 20px; max-width: 1200px; width: 100%; box-sizing: border-box; }
        
        .bin-card {
            background: #ffffff; border: 1px solid #e3e3e3; border-radius: 20px;
            padding: 30px 24px; width: 280px; text-align: center;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.04); box-sizing: border-box;
        }
        .bin-card h3 { margin-top: 0; margin-bottom: 25px; font-size: 22px; color: #000000; font-weight: bold; }
        .chart-wrapper { position: relative; width: 180px; height: 180px; margin: 0 auto 30px auto; }
        .chart-text { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); display: flex; flex-direction: column; align-items: center; pointer-events: none; }
        .percentage { font-size: 34px; font-weight: bold; color: #000000; line-height: 1.1; }
        .percentage-symbol { font-size: 20px; font-weight: bold; }
        .label { font-size: 14px; color: #555555; margin-bottom: 4px; font-weight: 500; }
        .capacity { font-size: 13px; color: #777777; }
        
        .cleared-btn { border: none; border-radius: 8px; padding: 10px 24px; font-size: 14px; font-weight: bold; transition: all 0.2s; width: 100%; box-sizing: border-box; }
        .status-empty { background-color: #e9ecef; color: #adb5bd; cursor: not-allowed; }
        .status-full { background-color: #d4edda; color: #155724; cursor: pointer; box-shadow: 0 0 10px rgba(40, 167, 69, 0.2); }
        .status-full:hover { background-color: #c3e6cb; }
    </style>
</head>
<body>

    <nav class="top-nav">
        <div class="breadcrumb"><a href="dashboard.php">Dashboard</a> &rarr; Recycle Bin Status</div>
        <div class="nav-logout"><a href="dashboard.php?action=logout">Logout</a></div>
    </nav> 

    <header class="page-header">
        <img src="WasteScan_AI-removebg-preview.png" alt="WasteScan AI Logo" class="dashboard-logo">
        <div class="page-title">Recycle Bin Status</div>
    </header>

    <main class="content-area">
        <div class="bin-card" id="plasticCard">
            <h3>Plastic Bin</h3>
            <div class="chart-wrapper">
                <canvas id="plasticChart"></canvas>
                <div class="chart-text">
                    <span class="percentage" id="plasticPercent"><?php echo $bin_data['Plastic']['capacity']; ?><span class="percentage-symbol">%</span></span>
                    <span class="label">Full</span>
                    <span class="capacity" id="plasticLitre">Capacity: <?php echo $bin_data['Plastic']['capacity']; ?>L / 100L</span>
                </div>
            </div>
            <button id="plasticBtn" class="cleared-btn <?php echo ($bin_data['Plastic']['capacity'] >= 95 || $bin_data['Plastic']['status'] == 'Full' || $bin_data['Plastic']['status'] == 'Dispatched') ? 'status-full' : 'status-empty'; ?>" <?php echo ($bin_data['Plastic']['capacity'] >= 95 || $bin_data['Plastic']['status'] == 'Full' || $bin_data['Plastic']['status'] == 'Dispatched') ? '' : 'disabled'; ?>>Cleared</button>
        </div>

        <div class="bin-card" id="aluminumCard">
            <h3>Aluminium Bin</h3>
            <div class="chart-wrapper">
                <canvas id="aluminiumChart"></canvas>
                <div class="chart-text">
                    <span class="percentage" id="aluminiumPercent"><?php echo $bin_data['Aluminum']['capacity']; ?><span class="percentage-symbol">%</span></span>
                    <span class="label">Full</span>
                    <span class="capacity" id="aluminiumLitre">Capacity: <?php echo $bin_data['Aluminum']['capacity']; ?>L / 100L</span>
                </div>
            </div>
            <button id="aluminiumBtn" class="cleared-btn <?php echo ($bin_data['Aluminium']['capacity'] >= 95 || $bin_data['Aluminium']['status'] == 'Full' || $bin_data['Aluminium']['status'] == 'Dispatched') ? 'status-full' : 'status-empty'; ?>" <?php echo ($bin_data['Aluminium']['capacity'] >= 95 || $bin_data['Aluminium']['status'] == 'Full' || $bin_data['Aluminium']['status'] == 'Dispatched') ? '' : 'disabled'; ?>>Cleared</button>
        </div>

        <div class="bin-card" id="paperCard">
            <h3>Paper Bin</h3>
            <div class="chart-wrapper">
                <canvas id="paperChart"></canvas>
                <div class="chart-text">
                    <span class="percentage" id="paperPercent"><?php echo $bin_data['Paper']['capacity']; ?><span class="percentage-symbol">%</span></span>
                    <span class="label">Full</span>
                    <span class="capacity" id="paperLitre">Capacity: <?php echo $bin_data['Paper']['capacity']; ?>L / 100L</span>
                </div>
            </div>
            <button id="paperBtn" class="cleared-btn <?php echo ($bin_data['Paper']['capacity'] >= 95 || $bin_data['Paper']['status'] == 'Full' || $bin_data['Paper']['status'] == 'Dispatched') ? 'status-full' : 'status-empty'; ?>" <?php echo ($bin_data['Paper']['capacity'] >= 95 || $bin_data['Paper']['status'] == 'Full' || $bin_data['Paper']['status'] == 'Dispatched') ? '' : 'disabled'; ?>>Cleared</button>
        </div>
    </main>

    <script>
        const chartInstances = {};
        let isResetting = false; 

        function generateChartOptions(percentage, mainColor) {
            return {
                type: 'doughnut',
                data: {
                    datasets: [{
                        data: [percentage, 100 - percentage], 
                        backgroundColor: [mainColor, '#f0f0f0'], 
                        borderWidth: 0, cutout: '83%'   
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: true,
                    plugins: { tooltip: { enabled: false }, legend: { display: false } },
                    borderRadius: percentage > 0 ? 10 : 0
                }
            };
        }

        chartInstances['Plastic'] = new Chart(document.getElementById('plasticChart'), generateChartOptions(<?php echo $bin_data['Plastic']['capacity']; ?>, '#e60000'));
        chartInstances['Aluminium'] = new Chart(document.getElementById('aluminiumChart'), generateChartOptions(<?php echo $bin_data['Aluminium']['capacity']; ?>, '#ffcc00'));
        chartInstances['Paper'] = new Chart(document.getElementById('paperChart'), generateChartOptions(<?php echo $bin_data['Paper']['capacity']; ?>, '#0066cc'));

        function updateDataAutomatically() {
            if (isResetting) return; 

            fetch('get_bin_status.php')
                .then(res => res.json())
                .then(resData => {
                    if (resData.success) {
                        const dbData = resData.data;
                        
                        Object.keys(dbData).forEach(binType => {
                            const capacity = dbData[binType].capacity;
                            const status = dbData[binType].status;
                            const chart = chartInstances[binType];

                            chart.data.datasets[0].data = [capacity, 100 - capacity];
                            chart.options.borderRadius = capacity > 0 ? 10 : 0;
                            chart.update();

                            document.getElementById(`${binType.toLowerCase()}Percent`).innerHTML = `${capacity}<span class="percentage-symbol">%</span>`;
                            document.getElementById(`${binType.toLowerCase()}Litre`).innerText = `Capacity: ${capacity}L / 100L`;

                            const btn = document.getElementById(`${binType.toLowerCase()}Btn`);
                            if (capacity >= 95 || status === 'Full' || status === 'Dispatched') {
                                if (btn.classList.contains('status-empty')) {
                                    btn.className = 'cleared-btn status-full';
                                    btn.disabled = false;
                                }
                            } else {
                                if (btn.innerText === 'Cleared') {
                                    btn.className = 'cleared-btn status-empty';
                                    btn.disabled = true;
                                }
                            }
                        });
                    }
                })
                .catch(err => console.error("Polling error:", err));
        }

        setInterval(updateDataAutomatically, 2000);

        document.querySelectorAll('.cleared-btn').forEach(button => {
            button.addEventListener('click', function() {
                const btn = this;
                if (btn.classList.contains('status-empty')) return;

                const binType = btn.id.replace('Btn', '');
                let formattedType = binType.charAt(0).toUpperCase() + binType.slice(1);
                if (formattedType.toLowerCase() === 'aluminum') {
                    formattedType = 'aluminium';
                }

                if (confirm(`Confirm that the ${formattedType} Bin has been emptied? This will reset the volume to 0%.`)) {
                    isResetting = true; 
                    btn.disabled = true;
                    btn.innerText = 'Resetting...';

                    // 🌟 核心突破修正：将原本的外网 URL 彻底换成 Railway 绝对内网高速网关！
                    fetch('http://wastescanai-backend.railway.internal:8080/api/reset_bin', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ bin_type: formattedType })
                    })
                    .then(res => res.json())
                    .then(data => {
                        const displayType = formattedType === 'aluminium' ? 'Aluminium' : formattedType;
                        if (data.success) {
                            const chart = chartInstances[displayType];
                            chart.data.datasets[0].data = [0, 100]; 
                            chart.options.borderRadius = 0; 
                            chart.update(); 

                            document.getElementById(`${binType}Percent`).innerHTML = `0<span class="percentage-symbol">%</span>`;
                            document.getElementById(`${binType}Litre`).innerText = `Capacity: 0L / 100L`;

                            btn.innerText = 'Cleared';
                            btn.className = 'cleared-btn status-empty';
                            btn.disabled = true; 

                            alert(`${displayType} Bin has been successfully reset!`);
                        } else {
                            alert('Failed to reset: ' + data.message);
                            btn.disabled = false;
                            btn.innerText = 'Cleared';
                        }
                    })
                    .catch(err => {
                        console.error('Error:', err);
                        alert('Failed to contact cloud AI backend server.');
                        btn.disabled = false;
                        btn.innerText = 'Cleared';
                    })
                    .finally(() => {
                        isResetting = false; 
                    });
                }
            });
        });
    </script>
</body>
</html>
