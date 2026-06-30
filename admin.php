<?php
// WasteScan AI - Admin Portal Login Page
// 1. 启动全局会话控制
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🌟 线上部署核心修正：完美自适应 Railway 环境变量与内网拓扑结构
$host = $_ENV['MYSQLHOST'] ?? '127.0.0.1';
$port = $_ENV['MYSQLPORT'] ?? 3306;
$dbname = $_ENV['MYSQLDATABASE'] ?? 'wastescanaidb'; 
$user = $_ENV['MYSQLUSER'] ?? 'root';
$pass = $_ENV['MYSQLPASSWORD'] ?? ''; 

$error_message = '';

// 3. 安全防护：如果管理员已经处于登录状态，直接跳到实际的管理员主页 dashboard.php
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

// 4. 建立数据库连接
try {
    // 注入端口支持，保证容器环境无缝切换
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 🔄 【全自动强制修复拦截器】
    // 查询当前 admin 的数据，确保即使之前数据库弄乱了也能被程序内部完美纠正
    $fix_stmt = $pdo->prepare("SELECT * FROM admins WHERE username = 'admin'");
    $fix_stmt->execute();
    $current_admin = $fix_stmt->fetch(PDO::FETCH_ASSOC);

    if ($current_admin) {
        // 如果数据库里的密码开头不是 $2y$（说明仍是明文），或者你手动填的加密串不对
        if (strpos($current_admin['password'], '$2y$') !== 0) {
            $secured_hash = password_hash('admin369', PASSWORD_DEFAULT);
            $update_stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE username = 'admin'");
            $update_stmt->execute([$secured_hash]);
        }
    }
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

        /* 与其它表单页完全对齐的白色立体卡片外框 */
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

        /* 密码输入框包裹容器 */
        .password-wrapper {
            position: relative;
            width: 100%;
        }
        .password-wrapper .input-field {
            padding-right: 50px;
        }

        /* 小眼睛按钮定位与样式 */
        .toggle-password {
            position: absolute;
            right: 18px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #777;
            transition: color 0.2s;
        }
        .toggle-password:hover {
            color: #49cdd2;
        }
        .toggle-password svg {
            width: 22px;
            height: 22px;
        }

        /* 精修对齐后的提交按钮 */
        .login-btn {
            width: 100%;
            background-color: #5ce1e6;
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
        
        <form class="login-form" action="" method="POST">
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
                <div class="password-wrapper">
                    <input type="password" id="password" class="input-field" name="password" required autocomplete="off" placeholder="Enter admin password">
                    <button type="button" id="togglePassword" class="toggle-password" title="Toggle password visibility">
                        <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                    </button>
                </div>
            </div>
            
            <button type="submit" class="login-btn">Login</button>
        </form>
    </div>

    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const passwordInput = document.querySelector('#password');
        const eyeIcon = document.querySelector('#eyeIcon');

        const eyeClosePath = `<path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />`;
        const eyeOpenPath = `<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />`;

        togglePassword.addEventListener('click', function () {
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            if (type === 'password') {
                eyeIcon.innerHTML = eyeClosePath;
            } else {
                eyeIcon.innerHTML = eyeOpenPath;
            }
        });
    </script>
</body>
</html>
