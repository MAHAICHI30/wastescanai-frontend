<?php
// 1. 配置数据库连接参数
$servername = "localhost";
$username = "root";       // XAMPP 默认用户名
$password = "";           // XAMPP 默认密码为空
$dbname = "wastescanaidb"; // 你的数据库名称

// 创建连接
$conn = new mysqli($servername, $username, $password, $dbname);

// 检查连接是否成功
if ($conn->connect_error) {
    die("数据库连接失败: " . $conn->connect_error);
}

// 2. 编写 SQL 查询语句（包含最新的 last_active 字段）
$sql = "SELECT id, username, email, last_active FROM users";
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
            width: 150px; /* 较小的展示尺寸 */
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
            height: 30px; /* 确保空格子也有高度 */
            color: #444;
        }

        /* 斑马纹效果 */
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
            <a href="welcome.php">Logout</a>
        </div>
    </nav>

    <header class="page-header">
        <img src="WasteScan_AI-removebg-preview.png" alt="WasteScan AI Logo" class="dashboard-logo">
        <div class="page-title"></div>
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
                echo "<tr><td colspan='4' style='text-align:center;'>暂无用户数据</td></tr>";
            }
            
            // 关闭数据库连接
            $conn->close();
            ?>
        </tbody>
    </table>

</body>
</html>
