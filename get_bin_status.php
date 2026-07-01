<?php
header('Content-Type: application/json');

// 🔌 线上部署核心修正：使用真实 Railway 环境变量，彻底告别 localhost 本地硬编码
$host = getenv('MYSQLHOST') ?: 'mysql.railway.internal';
$port = getenv('MYSQLPORT') ?: 3306;
$dbname = getenv('MYSQLDATABASE') ?: 'railway'; 
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: 'asMgnFdMgJUNIekzFfCVeBpSWyzfJmDp'; 

// 兜底默认值字典
$bin_data = [
    'Plastic'  => ['capacity' => 0, 'status' => 'Normal'],
    'Aluminium' => ['capacity' => 0, 'status' => 'Normal'],
    'Paper'    => ['capacity' => 0, 'status' => 'Normal']
];

try {
    // 🌟 统一采用标准安全 PDO 驱动
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $sql = "SELECT bin_name, current_volume, status FROM recycle_bins";
    $stmt = $pdo->query($sql);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $raw_name = strtolower($row['bin_name']);
        
        // 与大屏主页的规范完全对齐，防止因大小写导致数据滑坡
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
    
    $pdo = null;
    echo json_encode(["success" => true, "data" => $bin_data]);

} catch (PDOException $e) {
    echo json_encode(["success" => false, "message" => "Database link error: " . $e->getMessage()]);
    exit();
}
?>
