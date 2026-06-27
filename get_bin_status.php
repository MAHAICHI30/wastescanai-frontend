<?php
header('Content-Type: application/json');

$host = 'mysql.railway.internal';
$dbname = 'railway';
$user = 'root';
$pass = 'VpUQTVAAjVaDLhqBcUZMfxoJhHEpPRKx'; 
$error_message = '';

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    echo json_encode(["success" => false, "message" => "Database connection failed"]);
    exit();
}

$sql = "SELECT bin_name, current_volume, status FROM recycle_bins";
$result = $conn->query($sql);

// 兜底默认值
$bin_data = [
    'Plastic'  => ['capacity' => 0, 'status' => 'Normal'],
    'Aluminum' => ['capacity' => 0, 'status' => 'Normal'],
    'Paper'    => ['capacity' => 0, 'status' => 'Normal']
];

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $type = ucfirst(strtolower($row['bin_name'])); 
        if (isset($bin_data[$type])) {
            $bin_data[$type]['capacity'] = (int)$row['current_volume'];
            if (isset($row['status'])) {
                $bin_data[$type]['status'] = $row['status'];
            }
        }
    }
}

$conn->close();

// 输出标准 JSON 格式供前端 JS 读取
echo json_encode(["success" => true, "data" => $bin_data]);
?>
