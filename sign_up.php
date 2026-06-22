<?php
// 数据库连接
$host = '127.0.0.1';
$dbname = 'wastescanaidb';
$user = 'root';
$pass = ''; // 如果没有设置密码就留空

$error_message = '';
// 初始化变量，确保首次进入页面时绝对为空
$username = '';
$email = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    $error_message = "Database connection failed: " . $e->getMessage();
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // 验证
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $error_message = "Please fill in all fields.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Please enter a valid email address.";
    } else {
        // 检查用户名或邮箱是否已存在
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $email]);
        
        if ($stmt->rowCount() > 0) {
            $error_message = "Username or email already exists.";
        } else {
            // 哈希密码并插入数据库
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, created_at) VALUES (?, ?, ?, NOW())");
            
            if ($stmt->execute([$username, $email, $hashed_password])) {
                // 注册成功，跳转到登录页面
                header("Location: login.php");
                exit();
            } else {
                $error_message = "Something went wrong. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WasteScan AI - Sign Up</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
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

        /* 🌟 与 login.php 完美对齐的白色立体卡片边框外框 */
        .signup-card {
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

        .welcome-title {
            font-size: 32px;
            font-weight: 500;
            color: #0a1c2f;
            margin-bottom: 15px;
            text-align: center;
        }

        .logo-container {
            margin-bottom: 35px;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .logo-img {
            width: 160px;
            height: auto;
            display: block;
            object-fit: contain;
        }

        .input-group {
            width: 100%;
            margin-bottom: 15px;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
        }

        .input-group label {
            font-size: 14px;
            color: #333;
            margin-bottom: 6px;
            margin-left: 5px;
            font-weight: 500;
        }

        .input-group input {
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

        .input-group input:focus {
            border-color: #49cdd2;
        }

        .input-group input::placeholder {
            color: #aaa;
            opacity: 1;
        }

        /* 🌟 精修对齐后的提交注册按钮，完美复刻系统级视觉比例 */
        .signup-btn {
            width: 100%;
            background-color: #dbf1f1;
            color: #1e3046;
            border: none;
            padding: 13px 0;
            font-size: 17px;
            border-radius: 30px;
            cursor: pointer;
            margin-top: 15px;
            font-weight: 600;
            transition: background-color 0.2s ease;
        }

        .signup-btn:hover {
            background-color: #caeaea;
        }

        .error-message {
            background-color: #ffe6e6;
            color: #d32f2f;
            padding: 10px 15px;
            border: 1px solid #ffcdd2;
            border-radius: 30px;
            font-size: 14px;
            margin-bottom: 18px;
            width: 100%;
            text-align: center;
        }

        @media (max-width: 450px) {
            .signup-card {
                padding: 28px 20px 32px 20px;
                border-radius: 30px;
            }
        }
    </style>
</head>
<body>

    <div class="signup-card">
        <h1 class="welcome-title">Welcome</h1>

        <div class="logo-container">
            <img src="WasteScan_AI-removebg-preview.png" alt="WasteScan AI Logo" class="logo-img">
        </div>

        <?php if ($error_message): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form method="POST" action="" style="width: 100%; display: flex; flex-direction: column; align-items: center;">
            
            <div class="input-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Enter your username" value="<?php echo htmlspecialchars($username); ?>" autocomplete="off" required>
            </div>

            <div class="input-group">
                <label for="email">Email</label>
                <input type="text" id="email" name="email" placeholder="Enter your email" value="<?php echo htmlspecialchars($email); ?>" autocomplete="off" required onfocus="this.type='email';">
            </div>

            <div class="input-group">
                <label for="password">Password</label>
                <input type="text" id="password" name="password" placeholder="Enter your password" autocomplete="off" required onfocus="this.type='password';">
            </div>

            <div class="input-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="text" id="confirm_password" name="confirm_password" placeholder="Confirm your password" autocomplete="off" required onfocus="this.type='password';">
            </div>

            <button type="submit" class="signup-btn">Sign up</button>
        </form>
        
    </div>

</body>
</html>