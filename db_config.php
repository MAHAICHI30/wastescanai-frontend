<?php
// ==========================================
// 统一数据库配置文件 db_config.php
// ==========================================

$host = 'mysql.railway.internal';
$user = 'root';
$pass = 'VpUQTVAAjVaDLhqBcUZMfxoJhHEpPRKx';
$dbname = 'railway';

// MySQLi 连接
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// PDO 连接
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
