<?php
// WasteScan AI - Reset Password Page
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🌟 线上部署核心修正：完美自适应 Railway 环境变量与内网拓扑结构
$host = $_ENV['MYSQLHOST'] ?? '127.0.0.1';
$port = $_ENV['MYSQLPORT'] ?? 3306;
$dbname = $_ENV['MYSQLDATABASE'] ?? 'wastescanaidb'; // 本地默认，云端会自动被环境变量 railway 覆盖
$user = $_ENV['MYSQLUSER'] ?? 'root';
$pass = $_ENV['MYSQLPASSWORD'] ?? '';

$message = '';
$message_type = 'error'; 

// 建立数据库连接
try {
    // 注入端口变量，保障容器化环境链路畅通
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    $message = "Database connection failed: " . $e->getMessage();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($email) || empty($new_password) || empty($confirm_password)) {
        $message = 'All fields are mandatory.';
    } elseif ($new_password !== $confirm_password) {
        $message = 'The passwords entered twice are inconsistent.';
    } elseif (strlen($new_password) < 6) {
        $message = 'The password length should be at least six characters';
    } else {
        try {
            // 1. 检查该邮箱是否在数据库中存在
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->rowCount() > 0) {
                // 2. 邮箱存在，将新密码进行哈希加密
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // 3. 执行 UPDATE 语句更新数据库中的密码
                $update_stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
                if ($update_stmt->execute([$hashed_password, $email])) {
                    
                    // 重置密码成功！直接秒跳回登录页面，并通过 URL 传达成功讯号
                    header('Location: login.php?status=reset_success');
                    exit; 
                    
                } else {
                    $message = 'Something went wrong. Please try again.';
                }
            } else {
                // 数据库里找不到该邮箱
                $message = 'This email address is not registered.';
            }
        } catch(PDOException $e) {
            $message = "Query failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WasteScan AI - Reset Password</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        body {
            background-color: #f4f6f8;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 16px;
        }

        .reset-card {
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
        
        .logo-image {
            width: 160px;
            height: auto;
            display: block;
            object-fit: contain;
        }

        .form-title {
            text-align: left;
            font-size: 20px;
            font-weight: bold;
            color: #000000;
            margin-bottom: 25px;
            padding-left: 5px;
            width: 100%;
        }

        .input-group {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            margin-bottom: 15px;
            width: 100%;
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

        .password-wrapper {
            position: relative;
            width: 100%;
        }
        .password-wrapper input {
            padding-right: 50px;
        }

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

        .btn-confirm {
            width: 100%;
            padding: 13px 0;
            font-size: 17px;
            color: #1e3046;
            background-color: #dbf1f1;
            border: none;
            border-radius: 30px;
            cursor: pointer;
            margin-top: 15px;
            font-weight: 600;
            transition: background-color 0.2s ease;
            text-align: center;
        }

        .btn-confirm:hover {
            background-color: #caeaea;
        }

        .message {
            margin-bottom: 18px;
            padding: 10px;
            border-radius: 30px;
            font-size: 14px;
            text-align: center;
            width: 100%;
        }
        .message.error { background-color: #ffe6e6; color: #d32f2f; border: 1px solid #ffcdd2; }
        
        @media (max-width: 450px) {
            .reset-card {
                padding: 28px 20px 32px 20px;
                border-radius: 30px;
            }
        }
    </style>
</head>
<body>

    <div class="reset-card">
        <h1 class="welcome-title">Welcome</h1>
        <div class="logo-container">
            <img class="logo-image" src="WasteScan_AI-removebg-preview.png" alt="WasteScan AI Logo">
        </div>

        <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" onsubmit="return validateForm(event)" style="width: 100%;">
            <h2 class="form-title">Reset Password</h2>

            <?php if (!empty($message)): ?>
                <div class="message error">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="input-group">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" placeholder="Enter your email" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" autocomplete="off" required>
            </div>

            <div class="input-group">
                <label for="new-password">New password</label>
                <div class="password-wrapper">
                    <input type="password" id="new-password" name="new_password" placeholder="Enter new password" autocomplete="off" required>
                    <button type="button" class="toggle-password" data-target="new-password" title="Toggle password visibility">
                        <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                    </button>
                </div>
            </div>

            <div class="input-group">
                <label for="confirm-password">Confirm new password</label>
                <div class="password-wrapper">
                    <input type="password" id="confirm-password" name="confirm_password" placeholder="Confirm new password" autocomplete="off" required>
                    <button type="button" class="toggle-password" data-target="confirm-password" title="Toggle password visibility">
                        <svg class="eye-icon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                        </svg>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn-confirm">Confirm</button>
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

        function validateForm(event) {
            const password = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-password').value;

            if (password !== confirmPassword) {
                alert('The passwords entered twice are inconsistent. Please re-enter!');
                event.preventDefault();
                return false;
            }
            if (password.length < 6) {
                alert('The password length should be at least six characters！');
                event.preventDefault();
                return false;
            }
            return true;
        }
    </script>
</body>
</html>
