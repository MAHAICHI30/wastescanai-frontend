<?php
// WasteScan AI - Admin Hub Dashboard Portal
// 1. 启动全局会话控制
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. 权限安全拦截：检查管理员是否正常登录。如果没有登录凭证，直接拦截并驱逐回 admin.php
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin.php');
    exit;
}

// 3. 退出登录处理逻辑：当点击 Logout 链接捕捉到 ?action=logout 时执行
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // 释放当前管理员会话的所有变量
    $_SESSION = array();
    
    // 清理客户端基于 Cookie 的 Session 凭证
    if (ini_get("session_use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // 彻底摧毁服务器端的会话数据
    session_destroy();
    
    // Session 彻底销毁后，安全重定向跳回管理员登录页
    header('Location: admin.php');
    exit;
}

// 🔔【新增】检查是否有回收桶需要提醒admin
$host = getenv('MYSQLHOST') ?: 'mysql.railway.internal';
$port = getenv('MYSQLPORT') ?: 3306;
$dbname = getenv('MYSQLDATABASE') ?: 'railway';
$user = getenv('MYSQLUSER') ?: 'root';
$pass = getenv('MYSQLPASSWORD') ?: 'asMgnFdMgJUNIekzFfCVeBpSWyzfJmDp';

$threshold = 80;
$alert_count = 0;
$alert_bins = [];

try {
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $sql = "SELECT bin_name, current_volume, status FROM recycle_bins";
    $stmt = $pdo->query($sql);

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $vol = (int)$row['current_volume'];
        if ($vol >= $threshold || $row['status'] === 'Full') {
            $alert_count++;
            $alert_bins[] = ucfirst(strtolower($row['bin_name'])) . ' (' . $vol . '%)';
        }
    }
    $pdo = null;
} catch (PDOException $e) {
    // 连接失败就静默跳过，不影响dashboard正常显示
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - WasteScan AI</title>
    <style>
        /* 全局样式设定 */
        body {
            margin: 0;
            padding: 0;
            font-family: 'Arial', sans-serif;
            background-color: #f8f8f8; /* 浅灰色背景 */
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* 顶部导航栏 */
        .top-nav {
            width: 100%;
            background-color: #f2e1c1; /* 浅米黄色 */
            padding: 15px 50px;
            display: flex;
            box-sizing: border-box;
            align-items: center;
        }

        /* 🌟 优化：在 RUP 界面结构中加入更高级的分布式自适应两端分散对齐 */
        .nav-links {
            flex: 1;
            text-align: left;
        }

        .nav-logout {
            text-align: right;
        }

        .top-nav a {
            text-decoration: none;
            color: #333;
            font-weight: bold;
            font-size: 14px;
        }

        /* 🔔【新增】顶部警示banner */
        .dashboard-banner {
            width: 100%;
            background: #fff3f3;
            color: #cc0000;
            border-bottom: 3px solid #ff4444;
            padding: 14px 20px;
            font-weight: 700;
            text-align: center;
            box-sizing: border-box;
            font-size: 15px;
        }

        /* Logo 区域 */
        .dashboard-header {
            margin-top: 60px;
            margin-bottom: 50px;
            text-align: center;
        }

        .dashboard-logo {
            width: 180px;
            height: auto;
            display: block;
            margin: 0 auto;
        }

        /* 按钮容器 */
        .action-grid {
            display: flex;
            gap: 30px;
            justify-content: center;
            flex-wrap: wrap;
            padding: 0 20px;
        }

        /* 胶囊按钮样式 */
        .action-card {
            background-color: #fff9f0;
            color: #000;
            text-decoration: none;
            padding: 25px 45px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: bold;
            text-align: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
            width: 240px;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 60px;
            position: relative; /* 🔔【新增】给红色角标定位用 */
        }

        .action-card:hover {
            background-color: #f0e6d8;
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(0,0,0,0.1);
        }

        /* 🔔【新增】红色数字角标 */
        .badge-dot {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #ff4444;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            box-shadow: 0 2px 6px rgba(0,0,0,0.2);
        }
    </style>
</head>
<body>

    <nav class="top-nav">
        <div class="nav-links">
            <a href="dashboard.php" class="active">Dashboard</a>
        </div>
        <div class="nav-logout">
            <a href="?action=logout">Logout</a>
        </div>
    </nav>

    <?php if ($alert_count > 0): ?>
    <div class="dashboard-banner">
        ⚠️ <?php echo $alert_count; ?> bin(s) need attention: <?php echo implode(', ', $alert_bins); ?>
    </div>
    <?php endif; ?>

    <header class="dashboard-header">
        <div class="brand-logo-container">
            <img src="WasteScan_AI-removebg-preview.png" alt="WasteScan AI Logo" class="dashboard-logo">
        </div>
    </header>

    <main class="dashboard-container">
        <div class="action-grid">
            <a href="monitor%20waste%20trend.php" class="action-card">
                Monitor Waste Trend
            </a>
            
            <a href="recycle%20bin%20status.php" class="action-card">
                View Recycle Bin Status
                <?php if ($alert_count > 0): ?>
                    <span class="badge-dot"><?php echo $alert_count; ?></span>
                <?php endif; ?>
            </a>
            
            <a href="user%20database%20management.php" class="action-card">
                Manage User Database
            </a>
        </div>
    </main>

</body>
</html>
