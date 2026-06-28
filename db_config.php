<?php
// db_config.php
$host = 'mysql.railway.internal';
$dbname = 'railway';
$user = 'root';
$pass = 'VpUQTVAAjVaDLhqBcUZMfxoJhHEpPRKx'; 

// PDO 连接
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// MySQLi 连接
$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>
