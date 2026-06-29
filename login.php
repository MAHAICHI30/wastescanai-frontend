<?php
// WasteScan AI User Login Page
session_start();

// ==========================================
// 【配置项】请替换为你自己在 Google Console 申请的凭证
// ==========================================
define('GOOGLE_CLIENT_ID', '801797539566-r5ltjl5m3fj79hr4mt4b4sl5v8pli1nd.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');

// 🌟 线上部署重大突破：动态感知当前环境的域名（自动适配 localhost 或 Railway 公网网址）
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
$current_domain = $protocol . $_SERVER['HTTP_HOST'];
// 这样在本地就是 http://localhost/WasteScan%20AI/login.php，在线上就是你的公网 URL/login.php
define('GOOGLE_REDIRECT_URI', strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ? 'http://localhost/WasteScan%20AI/login.php' : $current_domain . '/login.php'); 

// 🌟 线上部署核心修正：完美对接 Railway 云端内网 MySQL 数据库的环境变量
$host = $_ENV['MYSQLHOST'] ?? '127.0.0.1';
$port = $_ENV['MYSQLPORT'] ?? 3306;
$dbname = $_ENV['MYSQLDATABASE'] ?? 'wastescanaidb'; // 本地默认，云端会自动被环境变量 railway 覆盖
$user = $_ENV['MYSQLUSER'] ?? 'root';
$pass = $_ENV['MYSQLPASSWORD'] ?? ''; 

$error = '';
$success = '';

// 建立数据库连接
try {
    // 注入端口支持，保证环境无缝切换
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass);
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
            
            // 🌟 健壮性优化：云端服务器使用 curl 获取用户信息，避免受 php.ini 允许外网抓取限制的影响
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $userinfo_url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            $userinfo_response = curl_exec($ch);
            curl_close($ch);

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
        body { margin: 0; padding: 0; background-color: #f4f6f8; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 16px; }
        .login-card { max-width: 400px; width: 100%; background: #ffffff; border-radius: 40px; box-shadow: 0 25px 45px -12px rgba(0, 0, 0, 0.12); padding: 36px 28px 40px 28px; transition: all 0.3s ease; }
        .welcome-title { font-size: 32px; font-weight: 500; margin-bottom: 15px; color: #0a1c2f; text-align: center; }
        .logo-container { margin-bottom: 35px; display: flex; justify-content: center; align-items: center; }
        .logo-img { width: 160px; height: auto; object-fit: contain; }
        .input-group { margin-bottom: 15px; text-align: left; display: flex; flex-direction: column; width: 100%; }
        .input-group label { display: inline-block; font-size: 14px; color: #333; margin-bottom: 6px; font-weight: 500; margin-left: 5px; }
        .input-group input { width: 100%; padding: 14px 18px; font-size: 15px; border: 1px solid #ddd; border-radius: 30px; outline: none; background-color: #fff; transition: border-color 0.2s; }
        .password-wrapper { position: relative; width: 100%; }
        .password-wrapper input { padding-right: 50px; }
        .password-wrapper input:focus { border-color: #49cdd2; }
        .input-group input:focus { border-color: #49cdd2; }
        .toggle-password { position: absolute; right: 18px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; padding: 0; display: flex; align-items: center; justify-content: center; color: #777; transition: color 0.2s; }
        .toggle-password:hover { color: #49cdd2; }
        .toggle-password svg { width: 22px; height: 22px; }
        .forget-pwd-container { width: 100%; display: flex; justify-content: flex-end; margin-top: 6px; }
        .forget-pwd-link { font-size: 13px; color: #4344d7; text-decoration: none; font-weight: 500; margin-right: 12px; }
        .forget-pwd-link:hover { text-decoration: underline; }
        .btn-submit { background-color: #dbf1f1; border: none; color: #1e3046; font-size: 17px; cursor: pointer; width: 100%; padding: 13px 0; border-radius: 30px; margin-top: 15px; font-weight: 600; transition: background 0.2s; }
        .btn-submit:hover { background-color: #caeaea; }
        .no-account-section { margin-top: 30px; display: flex; flex-direction: column; align-items: center; gap: 8px; }
        .no-account-text { font-size: 15px; color: #48566c; font-weight: 500; }
        .btn-register { background-color: #caf5e6; border: none; color: #000000; font-size: 15px; cursor: pointer; padding: 10px 30px; border-radius: 30px; font-weight: 600; text-decoration: none; transition: background-color 0.2s; }
        .btn-register:hover { background-color: #b7ebd8; }
        .or-text { font-size: 16px; color: #333; margin: 22px 0 15px; text-align: center; font-weight: 400; }
        .btn-google { background-color: #dbf1f1; border: none; color: #1e3046; font-size: 17px; cursor: pointer; width: 100%; padding: 13px 0; border-radius: 30px; font-weight: 600; transition: background 0.2s; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-google:hover { background-color: #caeaea; }
        .google-icon { width: 30px; height: 30px; object-fit: contain; }
        .message { margin-bottom: 18px; padding: 10px; border-radius: 30px; font-size: 14px; text-align: center; }
        .error-message { background-color: #ffe6e6; color: #d32f2f; border: 1px solid #ffcdd2; }
        @media (max-width: 450px) { .login-card { padding: 28px 20px 32px 20px; border-radius: 30px; } }
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
            <input type="email" id="email" name="email" placeholder="Enter your email" required 
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" autocomplete="off">
        </div>

        <div class="input-group">
            <label for="password">Password</label>
            <div class="password-wrapper">
                <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="off">
                
                <button type="button" id="togglePassword" class="toggle-password" title="Toggle password visibility">
                    <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                    </svg>
                </button>
            </div>
            
            <div class="forget-pwd-container">
                <a href="reset_password.php" class="forget-pwd-link">forget password ?</a>
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

<script>
    const togglePassword = document.querySelector('#togglePassword');
    const passwordInput = document.querySelector('#password');
    const eyeIcon = document.querySelector('#eyeIcon');

    const eyeClosePath = `<path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />`;
    const eyeOpenPath = `<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />`;

    togglePassword.addEventListener('click', function (e) {
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
</html><?php
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
        
        /* 密码输入框外层包裹容器，右边留出空位放眼睛图标 */
        .password-wrapper {
            position: relative;
            width: 100%;
        }
        .password-wrapper input {
            padding-right: 50px; /* 留出空间防止文字遮挡眼睛图标 */
        }
        .password-wrapper input:focus { border-color: #49cdd2; }
        .input-group input:focus { border-color: #49cdd2; }
        
        /* 切换眼睛按钮的样式 */
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
            color: #49cdd2; /* 悬浮时变成主题青色 */
        }
        .toggle-password svg {
            width: 22px;
            height: 22px;
        }
        
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
            gap: 10px; 
        }
        .btn-google:hover { background-color: #caeaea; }
        
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
            <input type="email" id="email" name="email" placeholder="Enter your email" required 
                   value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" autocomplete="off">
        </div>

        <div class="input-group">
            <label for="password">Password</label>
            <div class="password-wrapper">
                <input type="password" id="password" name="password" placeholder="Enter your password" required autocomplete="off">
                
                <button type="button" id="togglePassword" class="toggle-password" title="Toggle password visibility">
                    <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                    </svg>
                </button>
            </div>
            
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

<script>
    const togglePassword = document.querySelector('#togglePassword');
    const passwordInput = document.querySelector('#password');
    const eyeIcon = document.querySelector('#eyeIcon');

    // 定义睁眼和闭眼的 SVG path
    const eyeClosePath = `<path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />`;
    const eyeOpenPath = `<path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />`;

    togglePassword.addEventListener('click', function (e) {
        // 切换输入框类型
        const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
        passwordInput.setAttribute('type', type);
        
        // 根据当前状态切换眼睛图标
        if (type === 'password') {
            eyeIcon.innerHTML = eyeClosePath;
        } else {
            eyeIcon.innerHTML = eyeOpenPath;
        }
    });
</script>

</body>
</html>
