<?php
// WasteScan AI User Login Page
session_start();

// ==========================================
// 【配置项】请替换为你自己在 Google Console 申请的凭证
// ==========================================
define('GOOGLE_CLIENT_ID', '801797539566-r5ltjl5m3fj79hr4mt4b4sl5v8pli1nd.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');
define('GOOGLE_REDIRECT_URI', 'http://localhost/WasteScan%20AI/login.php'); // 必须与 Google 控制台完全一致

$host = '127.0.0.1';
$dbname = 'wastescanaidb';
$user = 'root';
$pass = ''; 

$error = '';
$success = '';

// 建立数据库连接
try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    $error = "Database connection failed: " . $e->getMessage();
}

// ==========================================
// 核心操作 1：处理普通表单登录请求 (Manual Login)
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error = 'Please fill in both email and password';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user_data && $user_data['password'] && password_verify($password, $user_data['password'])) {
                
                // 更新数据库的 last_active 时间
                $update_stmt = $pdo->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
                $update_stmt->execute([$user_data['id']]);

                // Session 赋值名称完全对齐 home.php 的读取逻辑
                $_SESSION['user_id']    = $user_data['id']; 
                $_SESSION['username']   = $user_data['username']; 
                $_SESSION['user_email'] = $user_data['email'];
                $_SESSION['status']     = "Account Active";       
                $_SESSION['logged_in']  = true;
                
                header('Location: home.php');
                exit;
            } else {
                $error = 'Invalid email or password';
            }
        } catch(PDOException $e) {
            $error = "Query failed: " . $e->getMessage();
        }
    }
}

// ==========================================
// 核心操作 2：处理点击 "Login with Google" 按钮的重定向
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'google') {
    $google_auth_url = "https://accounts.google.com/o/oauth2/v2/auth?" . http_build_query([
        'client_id'     => GOOGLE_CLIENT_ID,
        'redirect_uri'  => GOOGLE_REDIRECT_URI,
        'response_type' => 'code',
        'scope'         => 'openid profile email',
        'access_type'   => 'online',
        'prompt'        => 'select_account',
    ]);
    header("Location: " . $google_auth_url);
    exit;
}

// ==========================================
// 核心操作 3：处理从 Google 授权页带着 ?code=xxx 回调回来的请求
// ==========================================
if (isset($_GET['code'])) {
    $code = $_GET['code'];

    try {
        $token_url = "https://oauth2.googleapis.com/token";
        $token_data = [
            'code'          => $code,
            'client_id'     => GOOGLE_CLIENT_ID,
            'client_secret' => GOOGLE_CLIENT_SECRET,
            'redirect_uri'  => GOOGLE_REDIRECT_URI,
            'grant_type'    => 'authorization_code'
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $token_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($token_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
        $response = curl_exec($ch);
        curl_close($ch);

        $token_result = json_decode($response, true);

        if (isset($token_result['access_token'])) {
            $access_token = $token_result['access_token'];

            $userinfo_url = "https://www.googleapis.com/oauth2/v3/userinfo?access_token=" . $access_token;
            $userinfo_response = file_get_contents($userinfo_url);
            $google_user = json_decode($userinfo_response, true);

            if (isset($google_user['sub'])) {
                $google_id = $google_user['sub']; 
                $email = $google_user['email'] ?? '';
                $name = $google_user['name'] ?? 'Google User';

                $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ?");
                $stmt->execute([$google_id]);
                $local_user = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$local_user) {
                    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    $existing_email_user = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($existing_email_user) {
                        $stmt = $pdo->prepare("UPDATE users SET google_id = ? WHERE id = ?");
                        $stmt->execute([$google_id, $existing_email_user['id']]);
                        $local_user = $existing_email_user;
                    } else {
                        $stmt = $pdo->prepare("INSERT INTO users (username, email, google_id, password, created_at) VALUES (?, ?, ?, NULL, NOW())");
                        $stmt->execute([$name, $email, $google_id]);

                        $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ?");
                        $stmt->execute([$google_id]);
                        $local_user = $stmt->fetch(PDO::FETCH_ASSOC);
                    }
                }

                $update_stmt = $pdo->prepare("UPDATE users SET last_active = NOW() WHERE id = ?");
                $update_stmt->execute([$local_user['id']]);

                $_SESSION['user_id']     = $local_user['id'];
                $_SESSION['username']    = $local_user['username']; 
                $_SESSION['user_email']  = $local_user['email'];
                $_SESSION['status']      = "Account Active";        
                $_SESSION['logged_in']   = true;
                $_SESSION['google_auth'] = true;

                header('Location: home.php');
                exit;
            }
        }
        $error = "Google authentication failed. Please try again.";
    } catch (Exception $e) {
        $error = "Google Login Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WasteScan AI - Login</title>
    <style>
        * { box-sizing: border-box; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        
        body { 
            margin: 0; padding: 0; 
            background-color: #f4f6f8; 
            display: flex; justify-content: center; align-items: center; 
            min-height: 100vh; 
            padding: 16px;
        }
        
        .login-card {
            max-width: 400px;
            width: 100%;
            background: #ffffff;
            border-radius: 40px;
            box-shadow: 0 25px 45px -12px rgba(0, 0, 0, 0.12);
            padding: 36px 28px 40px 28px;
            transition: all 0.3s ease;
        }
        
        .welcome-title { font-size: 32px; font-weight: 500; margin-bottom: 15px; color: #0a1c2f; text-align: center; }
        .logo-container { margin-bottom: 35px; display: flex; justify-content: center; align-items: center; }
        .logo-img { width: 160px; height: auto; object-fit: contain; }
        
        .input-group { margin-bottom: 15px; text-align: left; display: flex; flex-direction: column; width: 100%; }
        .input-group label { display: inline-block; font-size: 14px; color: #333; margin-bottom: 6px; font-weight: 500; margin-left: 5px; }
        .input-group input { width: 100%; padding: 14px 18px; font-size: 15px; border: 1px solid #ddd; border-radius: 30px; outline: none; background-color: #fff; transition: border-color 0.2s; }
        .input-group input:focus { border-color: #49cdd2; }
        
        .forget-pwd-container {
            width: 100%;
            display: flex;
            justify-content: flex-end;
            margin-top: 6px;
        }

        .forget-pwd-link {
            font-size: 13px; 
            color: #4344d7; 
            text-decoration: none; 
            font-weight: 500;
            margin-right: 12px; 
        }
        .forget-pwd-link:hover { text-decoration: underline; }

        .btn-submit { background-color: #dbf1f1; border: none; color: #1e3046; font-size: 17px; cursor: pointer; width: 100%; padding: 13px 0; border-radius: 30px; margin-top: 15px; font-weight: 600; transition: background 0.2s; }
        .btn-submit:hover { background-color: #caeaea; }
        
        .no-account-section { margin-top: 30px; display: flex; flex-direction: column; align-items: center; gap: 8px; }
        .no-account-text { font-size: 15px; color: #48566c; font-weight: 500; }
        
        .btn-register { 
            background-color: #caf5e6; 
            border: none; 
            color: #000000; 
            font-size: 15px; 
            cursor: pointer; 
            padding: 10px 30px; 
            border-radius: 30px; 
            font-weight: 600; 
            text-decoration: none; 
            transition: background-color 0.2s; 
        }
        .btn-register:hover { background-color: #b7ebd8; }

        .or-text { font-size: 16px; color: #333; margin: 22px 0 15px; text-align: center; font-weight: 400; }
        
        /* 🌟 核心升级：将 Google 提交按钮变成 Flex 布局，使图标与文本完美水平居中排列 */
        .btn-google { 
            background-color: #dbf1f1; 
            border: none; 
            color: #1e3046; 
            font-size: 17px; 
            cursor: pointer; 
            width: 100%; 
            padding: 13px 0; 
            border-radius: 30px; 
            font-weight: 600; 
            transition: background 0.2s; 
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px; /* 图标与文本之间的舒适间距 */
        }
        .btn-google:hover { background-color: #caeaea; }
        
        /* 🌟 Google 经典彩色 G 图标的大小和圆形微调 */
        .google-icon {
            width: 30px;
            height: 30px;
            object-fit: contain;
        }
        
        .message { margin-bottom: 18px; padding: 10px; border-radius: 30px; font-size: 14px; text-align: center; }
        .error-message { background-color: #ffe6e6; color: #d32f2f; border: 1px solid #ffcdd2; }
        
        @media (max-width: 450px) {
            .login-card { padding: 28px 20px 32px 20px; border-radius: 30px; }
        }
    </style>
</head>
<body>

<div class="login-card">
    <h1 class="welcome-title">Welcome</h1>
    <div class="logo-container">
        <img src="WasteScan_AI-removebg-preview.png" alt="WasteScan AI" class="logo-img" onerror="this.src='https://placehold.co/180x80?text=WasteScan+AI'">
    </div>

    <?php if ($error): ?>
        <div class="message error-message"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <input type="hidden" name="action" value="login">
        
        <div class="input-group">
            <label for="email">Email</label>
            <input type="text" id="email" name="email" placeholder="Enter your email" required 
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                   autocomplete="off" onfocus="this.type='email';">
        </div>

        <div class="input-group">
            <label for="password">Password</label>
            <input type="text" id="password" name="password" placeholder="Enter your password" required
                   autocomplete="off" onfocus="this.type='password';">
            
            <div class="forget-pwd-container">
                <a href="reset password.php" class="forget-pwd-link">forget password ?</a>
            </div>
        </div>

        <button type="submit" class="btn-submit">Login</button>
        
        <div class="no-account-section">
            <div class="no-account-text">Don't have an account?</div>
            <a href="sign_up.php" class="btn-register">Create an account</a>
        </div>

        <div class="or-text">or</div>
        
        <button type="submit" name="action" value="google" class="btn-google" formnovalidate>
            <img src="google_picture.png" alt="Google" class="google-icon">
            Continue with Google
        </button>
    </form>
</div>

</body>
</html>