<?php
// WasteScan AI User Registration Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🌟 线上部署核心修正：完美自适应 Railway 环境变量与内网拓扑结构
$host = $_ENV['MYSQLHOST'] ?? '127.0.0.1';
$port = $_ENV['MYSQLPORT'] ?? 3306;
$dbname = $_ENV['MYSQLDATABASE'] ?? 'wastescanaidb'; // 本地默认，云端会自动被环境变量 railway 覆盖
$user = $_ENV['MYSQLUSER'] ?? 'root';
$pass = $_ENV['MYSQLPASSWORD'] ?? ''; 

$error_message = '';
$username = '';
$email = '';

try {
    // 🌟 完美绑定端口变量，保障容器化环境链路畅通
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass);
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
        try {
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
        } catch (PDOException $e) {
            $error_message = "Query operation failed: " . $e->getMessage();
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
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        body { background-color: #f4f6f8; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 16px; }
        .signup-card { max-width: 400px; width: 100%; background: #ffffff; border-radius: 40px; box-shadow: 0 25px 45px -12px rgba(0, 0, 0, 0.12); padding: 36px 28px 40px 28px; transition: all 0.3s ease; display: flex; flex-direction: column; align-items: center; }
        .welcome-title { font-size: 32px; font-weight: 500; color: #0a1c2f; margin-bottom: 15px; text-align: center; }
        .logo-container { margin-bottom: 35px; display: flex; justify-content: center; align-items: center; }
        .logo-img { width: 160px; height: auto; display: block; object-fit: contain; }
        .input-group { width: 100%; margin-bottom: 15px; position: relative; display: flex; flex-direction: column; align-items: flex-start; }
        .input-group label { font-size: 14px; color: #333; margin-bottom: 6px; margin-left: 5px; font-weight: 500; }
        .input-group input { width: 100%; padding: 14px 18px; font-size: 15px; border: 1px solid #ddd; border-radius: 30px; background-color: #ffffff; outline: none; color: #333; transition: border-color 0.2s; }
        .input-group input:focus { border-color: #49cdd2; }
        .input-group input::placeholder { color: #aaa; opacity: 1; }
        .password-wrapper { position: relative; width: 100%; }
        .password-wrapper input { padding-right: 50px; }
        .toggle-password { position: absolute; right: 18px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; padding: 0; display: flex; align-items: center; justify-content: center; color: #777; transition: color 0.2s; }
        .toggle-password:hover { color: #49cdd2; }
        .toggle-password svg { width: 22px; height: 22px; }
        .signup-btn { width: 100%; background-color: #dbf1f1; color: #1e3046; border: none; padding: 13px 0; font-size: 17px; border-radius: 30px; cursor: pointer; margin-top: 15px; font-weight: 600; transition: background-color 0.2s ease; }
        .signup-btn:hover { background-color: #caeaea; }
        .error-message { background-color: #ffe6e6; color: #d32f2f; padding: 10px 15px; border: 1px solid #ffcdd2; border-radius: 30px; font-size: 14px; margin-bottom: 18px; width: 100%; text-align: center; }
        @media (max-width: 450px) { .signup-card { padding: 28px 20px 32px 20px; border-radius: 30px; } }
    </style>
</head>
<body>

    <div class="signup-card">
        <h1 class="welcome-title">Welcome</h1>
        <div class="logo-container">
            <img src="WasteScan_AI-removebg-preview.png" alt="WasteScan AI Logo" class="logo-img" onerror="this.src='https://placehold.co/180x80?text=WasteScan+AI'">
        </div>

        <?php if ($error_message): ?>
            <div class="error-message"><?php echo htmlspecialchars($error_message); ?></div>
        <?php endif; ?>

        <form method="POST" action="sign_up.php" style="width: 100%; display: flex; flex-direction: column; align-items: center;">
            
            <div class="input-group">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" placeholder="Enter your username" value="<?php echo htmlspecialchars($username); ?>" autocomplete="off" required>
            </div>

            <div class="input-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" value="<?php echo htmlspecialchars($email); ?>" autocomplete="off" required>
            </div>

            <div class="input-group">
                <label for="password">Password</label>
                <div class="password-wrapper">
                    <input type="password" id="password" name="password" placeholder="Enter your password" autocomplete="off" required>
                    <button type="button" class="toggle-password" data-target="password" title="Toggle password visibility">
                        <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="input-group">
                <label for="confirm_password">Confirm Password</label>
                <div class="password-wrapper">
                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" autocomplete="off" required>
                    <button type="button" class="toggle-password" data-target="confirm_password" title="Toggle password visibility">
                        <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="signup-btn">Sign up</button>
        </form>
    </div>

<script>
    const eyeClosePath = `<path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />`;
    const eyeOpenPath = `<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />`;

    document.querySelectorAll('.toggle-password').forEach(button => {
        button.addEventListener('click', function() {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);
            const svg = this.querySelector('.eye-icon');
            
            const currentType = input.getAttribute('type') === 'password' ? 'text' : 'password';
            input.setAttribute('type', currentType);
            
            if (currentType === 'password') {
                svg.innerHTML = eyeClosePath;
            } else {
                svg.innerHTML = eyeOpenPath;
            }
        });
    });
</script>
</body>
</html>
