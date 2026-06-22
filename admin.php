<?php
// 1. 启动全局会话控制
session_start();

// 2. 真实数据库连接配置
$host = '127.0.0.1';
$dbname = 'wastescanaidb';
$user = 'root';
$pass = ''; // 如果你的 XAMPP/WAMP 数据库没有设置密码就留空

$error_message = '';

// 3. 安全防护：如果管理员已经处于登录状态，直接跳到实际的管理员主页 dashboard.php
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

// 4. 建立数据库连接
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    $error_message = "Database connection failed: " . $e->getMessage();
}

// 5. 处理表单登录提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 接收参数并过滤首尾空格
    $admin_user = trim($_POST['username'] ?? '');
    $admin_pass = $_POST['password'] ?? '';

    if (empty($admin_user) || empty($admin_pass)) {
        $error_message = "Please fill in both username and password.";
    } else {
        try {
            // 精准查询 admins 表
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
            $stmt->execute([$admin_user]);
            $admin_data = $stmt->fetch(PDO::FETCH_ASSOC);

            // 验证管理员是否存在，并比对哈希密码
            if ($admin_data && password_verify($admin_pass, $admin_data['password'])) {
                
                // 登录成功，写入管理员专属的 Session 凭证
                $_SESSION['admin_id'] = $admin_data['id'];
                $_SESSION['admin_username'] = $admin_data['username'];
                $_SESSION['admin_logged_in'] = true;
                
                // 成功后跳转到正确的管理员后台页面 dashboard.php
                header('Location: dashboard.php');
                exit;
            } else {
                // 用户名不存在或密码错误统一提示
                $error_message = "Invalid Admin username or password.";
            }
        } catch(PDOException $e) {
            $error_message = "Query failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WasteScan AI - Admin Portal</title>
    <style>
        /* 基础样式重置 */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        /* 统一大背景为高质感淡灰 */
        body {
            background-color: #f4f6f8;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 16px;
        }

        /* 🌟 与其它表单页完全对齐的白色立体卡片外框 */
        .login-card {
            max-width: 400px;
            width: 100%;
            background: #ffffff;
            border-radius: 40px;
            box-shadow: 0 25px 45px -12px rgba(0, 0, 0, 0.12);
            padding: 36px 28px 40px 28px;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* 顶层欢迎语 */
        .welcome-title {
            font-size: 32px;
            font-weight: 500;
            color: #0a1c2f;
            margin-bottom: 15px;
            text-align: center;
        }

        /* Logo 样式 */
        .logo-box {
            margin-bottom: 35px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .logo-box img {
            width: 160px;
            height: auto;
            display: block;
            object-fit: contain;
        }

        /* 后台门户标题 */
        .portal-title {
            text-align: left;
            font-size: 20px;
            font-weight: bold;
            color: #000000;
            margin-bottom: 25px;
            padding-left: 5px;
            width: 100%;
        }

        /* 表单样式 */
        .login-form {
            width: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* 输入框容器组 */
        .input-group {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            margin-bottom: 15px;
            width: 100%;
        }

        /* 输入框前的固定文本标签 */
        .input-label {
            font-size: 14px;
            color: #333333;
            margin-bottom: 6px;
            margin-left: 5px;
            font-weight: 500;
        }

        /* 真正的输入文本框 */
        .input-field {
            width: 100%;
            padding: 14px 18px;
            font-size: 15px;
            border: 1px solid #ddd;
            border-radius: 30px;
            background-color: #ffffff;
            outline: none;
            color: #333;
            transition: border-color 0.2s;
        }

        .input-field:focus {
            border-color: #49cdd2;
        }

        /* 🌟 精修对齐后的提交按钮，完美复刻系统级视觉比例 */
        .login-btn {
            width: 100%;
            background-color: #5ce1e6; /* 亮青色 */
            color: #081c2c;
            border: none;
            padding: 13px 0;
            font-size: 17px;
            border-radius: 30px;
            cursor: pointer;
            margin-top: 15px;
            font-weight: 600;
            transition: background-color 0.2s ease, transform 0.2s;
            text-align: center;
            box-shadow: 0 3px 6px rgba(92, 225, 230, 0.2);
        }

        .login-btn:hover {
            background-color: #49cdd2;
            transform: translateY(-1px);
            box-shadow: 0 6px 14px rgba(92, 225, 230, 0.25);
        }

        .login-btn:active {
            transform: scale(0.97);
            background-color: #3cbcbf;
        }

        /* 错误动态提示小红框 */
        .error-alert {
            background-color: #ffe6e6;
            color: #d32f2f;
            border: 1px solid #ffcdd2;
            padding: 10px 15px;
            border-radius: 30px;
            font-size: 14px;
            margin-bottom: 18px;
            width: 100%;
            text-align: center;
        }

        @media (max-width: 450px) {
            .login-card {
                padding: 28px 20px 32px 20px;
                border-radius: 30px;
            }
        }
    </style>
</head>
<body>

    <div class="login-card">
        <h1 class="welcome-title">Welcome</h1>
        
        <div class="logo-box">
            <img src="WasteScan_AI-removebg-preview.png" alt="WasteScan AI Logo">
        </div>
        
        <form class="login-form" action="admin.php" method="POST">
            <h2 class="portal-title">Admin Portal</h2>
            
            <?php if (!empty($error_message)): ?>
                <div class="error-alert"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>
            
            <div class="input-group">
                <label class="input-label" for="username">Username</label>
                <input type="text" id="username" class="input-field" name="username" required autocomplete="off" placeholder="Enter admin username">
            </div>
            
            <div class="input-group">
                <label class="input-label" for="password">Password</label>
                <input type="password" id="password" class="input-field" name="password" required autocomplete="off" placeholder="Enter admin password">
            </div>
            
            <button type="submit" class="login-btn">Login</button>
        </form>
    </div>

</body>
</html>