<?php
$host = 'mysql.railway.internal';
$port = '3306';
$dbname = 'railway';
$user = 'root';
$pass = 'VpUQTVAAjVaDLhqBCuZMfxo3hHEpPRKx';

// 尝试连接
$conn = new mysqli($host, $user, $pass, $dbname, $port);

// 检查连接
if ($conn->connect_error) {
    die("❌ 连接失败: " . $conn->connect_error);
} else {
    echo "✅ 连接成功！数据库连接正常。";
    echo "<br>数据库名: " . $dbname;
    echo "<br>主机: " . $host;
}

$conn->close();
?>
