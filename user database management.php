<?php
// WasteScan AI - User Database Management
// 1. 启动全局会话控制
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🔒 权限安全拦截：检查管理员是否正常登录
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin.php');
    exit;
}

// 🔌 线上部署核心修正：完美自适应 Railway 环境变量与内网拓扑结构
$host = $_ENV['MYSQLHOST'] ?? 'mysql.railway.internal';
$port = $_ENV['MYSQLPORT'] ?? 3306;
$dbname = $_ENV['MYSQLDATABASE'] ?? 'railway'; // 本地默认，云端自动被覆盖为 railway
$user = $_ENV['MYSQLUSER'] ?? 'root';
$pass = $_ENV['MYSQLPASSWORD'] ?? 'asMgnFdMgJUNIekzFfCVeBpSWyzfJmDp'; 

// 创建数据库连接（注入端口支持）
$conn = new mysqli($host, $user, $pass, $dbname, $port);

// 检查连接是否成功
if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// 2. 编写 SQL 查询语句（只拉取注册的用户数据）
$sql = "SELECT id, username, email, last_active FROM users ORDER BY id ASC";
$result = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Database Management - WasteScan AI</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Arial', sans-serif;
            background-color: #f8f8f8;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* 顶部导航栏 */
        .top-nav {
            width: 100%;
            background-color: #f2e1c1; /* 浅米黄色 */
            padding: 12px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-sizing: border-box;
        }

        .breadcrumb {
            font-size: 15px;
            font-weight: bold;
            color: #333;
        }

        .breadcrumb a {
            text-decoration: none;
            color: #333;
        }

        .breadcrumb a:hover {
            text-decoration: underline;
        }

        .nav-logout a {
            text-decoration: none;
            color: #333;
            font-weight: bold;
            font-size: 15px;
        }

        /* 页面头部 */
        .page-header {
            text-align: center;
            margin-top: 40px;
        }

        .dashboard-logo {
            width: 150px; 
            height: auto;
            margin-bottom: 20px;
        }

        .page-title {
            color: #b08d57; /* 金棕色 */
            font-size: 30px;
            font-weight: bold;
            margin-bottom: 30px;
        }

        /* 表格样式 */
        .database-table {
            width: 90%;
            max-width: 1000px;
            border-collapse: collapse;
            background-color: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 50px;
        }

        .database-table th {
            background-color: #eeeeee;
            color: #333;
            font-weight: bold;
            padding: 15px;
            border: 1px solid #ddd;
            text-align: left;
        }

        .database-table td {
            padding: 15px;
            border: 1px solid #ddd;
            height: 30px; 
            color: #444;
        }

        .database-table tr:nth-child(even) {
            background-color: #fafafa;
        }
    </style>
</head>
<body>

    <nav class="top-nav">
        <div class="breadcrumb">
            <a href="dashboard.php">Dashboard</a> &rarr; User Database Management
        </div>
        <div class="nav-logout">
            <a href="dashboard.php?action=logout">Logout</a>
        </div>
    </nav>

    <header class="page-header">
        <img src="WasteScan_AI-removebg-preview.png" alt="WasteScan AI Logo" class="dashboard-logo">
        <div class="page-title">User Database Management</div>
    </header>

    <table class="database-table">
        <thead>
            <tr>
                <th>USER ID</th>
                <th>USERNAME</th>
                <th>EMAIL</th>
                <th>LAST ACTIVE</th>
            </tr>
        </thead>
        <tbody>
            <?php
            // 3. 循环遍历数据库，动态渲染表格行
            if ($result && $result->num_rows > 0) {
                while($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row["id"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["username"]) . "</td>";
                    echo "<td>" . htmlspecialchars($row["email"]) . "</td>";
                    
                    // 判断是否有最后活跃时间，如果没有则显示 Never Logged In
                    $last_active = !empty($row["last_active"]) ? $row["last_active"] : "Never Logged In";
                    echo "<td>" . htmlspecialchars($last_active) . "</td>";
                    
                    echo "</tr>";
                }
            } else {
                echo "<tr><td colspan='4' style='text-align:center;'>No user records found.</td></tr>";
            }
            
            // 关闭数据库连接
            $conn->close();
            ?>
        </tbody>
    </table>

</body>
</html>
