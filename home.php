<?php
// ==========================================================================
// 1. 开启 Session 会话拦截机制（确保用户必须登录才能进入主页）
// ==========================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🔒 安全守卫：如果检测到没有真实的登录 Session 记录，强制拦截并重定向退回登录页
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

// 动态捞出当前系统成功登录的真实账户信息
$username = $_SESSION['username']; 
$account_status = isset($_SESSION['status']) ? $_SESSION['status'] : "Account Active";

// ==========================================================================
// 2. 建立真实的 MySQL 数据库连接并抓取当前登录用户的最新 2 条历史记录
// ==========================================================================
$host = 'mysql.railway.internal';
$dbname = 'railway';
$user = 'root';
$pass = 'VpUQTVAAjVaDLhqBcUZMfxoJhHEpPRKx'; 

$conn = new mysqli($host, $user, $pass, $dbname);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

/**
 * 💡 核心 SQL 查询优化：
 * 1. 增加 WHERE username = ? 过滤，确保只拉取当前登录账号的专属数据！
 * 2. 过滤掉 'unknown' 的无效识别数据。
 * 3. 使用 LIMIT 2 限制数量，保持主页仪表盘的紧凑清爽。
 */
$sql = "SELECT id, record_type, material_type, image_path, 
        DATE_FORMAT(created_at, '%h:%i %p') AS formatted_time
        FROM waste_records 
        WHERE username = ? AND material_type != 'unknown'
        ORDER BY created_at DESC 
        LIMIT 2";

// 预处理执行流程，绑定当前登录的 $username 变量
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $username); 
$stmt->execute();
$result = $stmt->get_result(); 

$recent_activities = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $recent_activities[] = $row;
    }
}

$stmt->close();
$conn->close(); // 释放连接

// 建立视觉颜色字典映射
$material_colors = [
    'plastic'   => '#D32F2F', // 红色
    'aluminum'  => '#FBC02D', // 黄色/金
    'aluminium' => '#FBC02D', // 兼容防错
    'paper'     => '#1565C0'  // 蓝色
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WasteScan AI - Home</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ==========================================================================
           3. 全局重置与基础样式 (Mobile-First Reset)
           ========================================================================== */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #F4F7F5; /* 统一的浅灰绿背景 */
            color: #333333;
            padding-bottom: 30px;
            overflow-x: hidden; /* 防止抽屉滑出时产生水平滚动条 */
            -webkit-tap-highlight-color: transparent;
        }

        /* ==========================================================================
           4. 顶部导航栏 (Top App Bar)
           ========================================================================== */
        .app-bar {
            background-color: #ffffff;
            height: 70px; /* 给 55px 的大 Logo 留出充裕的上下呼吸空间 */
            display: flex;
            align-items: center;
            justify-content: space-between; /* 确保 Menu 在左，Logo 在中，头像在右 */
            padding: 0 16px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        .menu-btn {
            font-size: 24px; 
            color: #2E7D32; 
            cursor: pointer;
            width: 40px;
            display: flex;
            align-items: center;
            justify-content: flex-start;
        }

        .user-avatar-top {
            font-size: 26px; 
            color: #2E7D32;
            cursor: pointer;
            width: 40px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
        }

        .logo-container {
            display: flex;
            align-items: center;
            justify-content: center;
            flex: 1; /* 让 Logo 容器自动撑满中间的全部剩余空间 */
        }

        .app-logo {
            height: 55px; 
            width: auto;
            object-fit: contain;
            display: block;
        }

        /* ==========================================================================
           5. 侧边抽屉导航栏 (Navigation Drawer) - 【移动端弹性自适应优化版】
           ========================================================================== */
        .drawer-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            height: 100vh;
            background-color: rgba(0, 0, 0, 0.4);
            z-index: 1001;
            display: none;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .nav-drawer {
            position: fixed;
            top: 0;
            left: -280px;
            width: 280px;
            height: 100vh;
            /* 🌟 核心升级：利用 dvh 动态计算手机浏览器控制栏弹出后的真实可视高度 */
            height: 100dvh; 
            background-color: #ffffff;
            z-index: 1002;
            box-shadow: 5px 0 15px rgba(0,0,0,0.1);
            transition: left 0.3s ease;
            display: flex;
            flex-direction: column; /* 纵向 Flex 容器结构 */
        }

        .drawer-open .nav-drawer { left: 0; }
        .drawer-open .drawer-overlay { display: block; opacity: 1; }

        .drawer-header {
            background: linear-gradient(135deg, #2E7D32, #1B5E20);
            color: #ffffff;
            padding: 35px 20px 22px 20px;
            position: relative;
            flex-shrink: 0; /* 🌟 保护头部高度，绝不参与内容压缩 */
        }

        .close-btn {
            position: absolute;
            top: 15px;
            right: 15px;
            color: rgba(255,255,255,0.7);
            font-size: 18px;
            cursor: pointer;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 10px;
        }

        .user-info .avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background-color: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }

        .user-text h4 { font-size: 16px; font-weight: 600; }
        .user-text p { font-size: 12px; color: #E8F5E9; margin-top: 4px; opacity: 0.9; }

        /* 🌟 核心升级：菜单区域自适应撑开并允许内部滚动 */
        .drawer-menu { 
            flex: 1; 
            padding: 15px 0; 
            overflow-y: auto; 
            -webkit-overflow-scrolling: touch; /* 让 iOS 滚动更滑溜 */
        }
        
        .menu-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 14px 24px;
            color: #444444;
            text-decoration: none;
            font-size: 15px;
            font-weight: 500;
            transition: background 0.2s, color 0.2s;
        }

        .menu-item i { font-size: 18px; color: #666666; width: 24px; text-align: center; }
        .menu-item:hover, .menu-item.active { background-color: #E8F5E9; color: #2E7D32; }
        .menu-item:hover i, .menu-item.active i { color: #2E7D32; }
        
        .drawer-divider { height: 1px; background-color: #EEEEEE; margin: 10px 0; }
        
        /* 🌟 核心升级：底部固定区域，永远封底，并留出手机屏幕下缘安全边距 */
        .drawer-footer { 
            padding: 10px 0; 
            border-top: 1px solid #EEEEEE; 
            flex-shrink: 0; /* 拒绝缩水 */
            background-color: #ffffff;
            /* 智能读取全面屏手机下方的触控小黑条安全区域高度，留出合适呼吸空间 */
            margin-bottom: max(10px, env(safe-area-inset-bottom)); 
        }
        
        .logout-item { color: #d32f2f; }
        .logout-item i { color: #d32f2f; }
        .logout-item:hover { background-color: #FFEBEE; }

        /* ==========================================================================
           6. 主体布局容器与核心组件
           ========================================================================== */
        .main-container {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }

        .welcome-section { margin-bottom: 20px; }
        .welcome-section h2 { font-size: 24px; color: #1A301F; }
        .welcome-section p { font-size: 14px; color: #666666; margin-top: 4px; }

        .tip-card {
            background: linear-gradient(135deg, #E8F5E9, #C8E6C9);
            border-left: 5px solid #2E7D32;
            padding: 15px;
            border-radius: 12px;
            display: flex;
            gap: 12px;
            align-items: center;
            margin-bottom: 25px;
        }
        .tip-card i { font-size: 22px; color: #2E7D32; }
        .tip-card p { font-size: 13px; color: #2E7D32; font-weight: 500; line-height: 1.4; }

        .section-title {
            font-size: 16px;
            font-weight: 700;
            color: #444444;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        /* ==========================================================================
           7. 功能快捷区网格 (Action Grid)
           ========================================================================== */
        .action-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 25px;
        }

        .action-card {
            background-color: #ffffff;
            text-decoration: none;
            color: inherit;
            border-radius: 16px;
            padding: 20px 15px;
            text-align: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
            transition: transform 0.2s, box-shadow 0.2s;
            border: 1px solid rgba(0,0,0,0.01);
        }

        .action-card:active, .action-card:hover {
            transform: scale(0.98);
            box-shadow: 0 2px 5px rgba(0,0,0,0.02);
        }

        .icon-box {
            width: 55px;
            height: 55px;
            border-radius: 50%;
            margin: 0 auto 12px auto;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
        }
        .scan-card .icon-box { background-color: #E8F5E9; color: #2E7D32; }
        .upload-card .icon-box { background-color: #E3F2FD; color: #1565C0; }
        .action-card h3 { font-size: 16px; font-weight: 600; margin-bottom: 4px; }
        .action-card p { font-size: 11px; color: #888888; }

        /* ==========================================================================
           8. 指南流程卡片 (How To Use)
           ========================================================================== */
        .how-to-use-card {
            background-color: #ffffff;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 4px 10px rgba(0,0,0,0.03);
            margin-bottom: 25px;
        }

        .step-list { display: flex; flex-direction: column; gap: 16px; margin-top: 12px; }
        .step-item { display: flex; align-items: flex-start; gap: 15px; }
        .step-number {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            background: linear-gradient(135deg, #A5D6A7, #2E7D32);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            flex-shrink: 0;
            margin-top: 2px;
        }
        .step-content h4 { font-size: 14px; font-weight: 600; color: #333333; display: flex; align-items: center; gap: 6px; }
        .step-content p { font-size: 12px; color: #777777; margin-top: 3px; line-height: 1.4; }

        /* ==========================================================================
           9. 动态数据流样式 (Activity Stream)
           ========================================================================== */
        .activity-card-stream {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .activity-item {
            background-color: #ffffff;
            border-radius: 14px;
            padding: 12px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            border: 1px solid rgba(0,0,0,0.01);
            cursor: default; 
        }

        .item-left {
            display: flex;
            align-items: center;
            gap: 14px;
            flex: 1;
            min-width: 0;
        }

        .item-thumbnail {
            width: 75px;  
            height: 75px; 
            border-radius: 10px;
            object-fit: cover;
            flex-shrink: 0;
            background-color: #EAEAEA;
            border: 1px solid rgba(0, 0, 0, 0.04);
        }

        .item-text-box { min-width: 0; }
        .item-name { font-size: 16px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; } 
        .item-time-label { font-size: 11px; color: #999999; text-transform: uppercase; margin-top: 6px; }
        .item-time { font-size: 13px; color: #555555; font-weight: 500; margin-top: 1px; }

        .item-right-content { flex-shrink: 0; margin-left: 10px; }
        .badge { font-size: 12px; padding: 5px 14px; border-radius: 20px; font-weight: 700; letter-spacing: 0.3px; display: flex; align-items: center; gap: 5px; }
        .method-scan { background-color: #E8F5E9; color: #2E7D32; }
        .method-upload { background-color: #E3F2FD; color: #1565C0; }
    </style>
</head>
<body>

    <div class="drawer-overlay" id="drawerOverlay" onclick="toggleDrawer()"></div>
    
    <nav class="nav-drawer" id="navDrawer">
        <div class="drawer-header">
            <div class="close-btn" onclick="toggleDrawer()"><i class="fa-solid fa-xmark"></i></div>
            <div class="user-info">
                <div class="avatar"><i class="fa-solid fa-user"></i></div>
                <div class="user-text">
                    <h4><?php echo htmlspecialchars($username); ?></h4>
                    <p>Status: <?php echo htmlspecialchars($account_status); ?></p>
                </div>
            </div>
        </div>
        <div class="drawer-menu">
            <a href="home.php" class="menu-item active"><i class="fa-solid fa-house"></i> Dashboard</a>
            <a href="scan.php" class="menu-item"><i class="fa-solid fa-camera"></i> Camera Scan</a>
            <a href="upload.php" class="menu-item"><i class="fa-solid fa-cloud-arrow-up"></i> Upload Photo</a>
            <div class="drawer-divider"></div>
            <a href="history.php" class="menu-item"><i class="fa-solid fa-clock-rotate-left"></i> Scan History</a>
        </div>
        <div class="drawer-footer">
            <a href="Welcome.php" class="menu-item logout-item"><i class="fa-solid fa-right-from-bracket"></i> Logout Account</a>
        </div>
    </nav>

    <header class="app-bar">
        <div class="menu-btn" onclick="toggleDrawer()"><i class="fa-solid fa-bars"></i></div>
        <div class="logo-container">
            <img class="app-logo" src="WasteScan_AI-removebg-preview.png" alt="WasteScan AI Logo">
        </div>
        <div class="user-avatar-top" onclick="toggleDrawer()"><i class="fa-solid fa-circle-user"></i></div>
    </header>

    <main class="main-container">
        
        <section class="welcome-section">
            <h2>Hello, <?php echo htmlspecialchars($username); ?>! 👋</h2>
            <p>Let's make the environment cleaner today.</p>
        </section>

        <div class="tip-card">
            <i class="fa-solid fa-lightbulb"></i>
            <p><strong>Eco-Tip:</strong> Ensure containers are completely free of food waste before recycling!</p>
        </div>

        <h3 class="section-title">⚡ Quick Identify</h3>
        <section class="action-grid">
            <a href="scan.php" class="action-card scan-card">
                <div class="icon-box"><i class="fa-solid fa-camera"></i></div>
                <h3>Camera Scan</h3>
                <p>Use live camera</p>
            </a>
            <a href="upload.php" class="action-card upload-card">
                <div class="icon-box"><i class="fa-solid fa-cloud-arrow-up"></i></div>
                <h3>Upload Photo</h3>
                <p>Select from gallery</p>
            </a>
        </section>

        <h3 class="section-title">📖 How to Use</h3>
        <section class="how-to-use-card">
            <div class="step-list">
                <div class="step-item">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h4>Scan or Upload Waste <i class="fa-solid fa-image" style="color: #888; font-size: 13px;"></i></h4>
                        <p>Upload or scan waste images — such as plastic, aluminium, or glass.</p>
                    </div>
                </div>
                <div class="step-item">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h4>AI Recognition <i class="fa-solid fa-brain" style="color: #ea4c89; font-size: 13px;"></i></h4>
                        <p>AI automatically identifies the specific type of waste from your image.</p>
                    </div>
                </div>
                <div class="step-item">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h4>Classification <i class="fa-solid fa-recycle" style="color: #2E7D32; font-size: 13px;"></i></h4>
                        <p>Follow the AI suggestions and put it into the correct recycle bin smoothly.</p>
                    </div>
                </div>
            </div>
        </section>

        <div class="section-title">
            <span>📜 Recent Activity</span>
            <a href="history.php?v=<?php echo time(); ?>" style="font-size: 13px; color: #2E7D32; text-decoration: none; font-weight: 600;">
                View All <i class="fa-solid fa-chevron-right" style="font-size: 10px;"></i>
            </a>
        </div>
        
        <section class="activity-card-stream">
            
            <?php 
            if (!empty($recent_activities)):
                foreach ($recent_activities as $activity): 
                    $raw_material = $activity['material_type'];
                    
                    $display_name = ucfirst($raw_material);
                    if ($raw_material === 'aluminum') {
                        $display_name = 'Aluminium';
                    }

                    $text_color = isset($material_colors[$raw_material]) ? $material_colors[$raw_material] : '#333333';

                    $db_path = $activity['image_path'];
                    if (!empty($db_path) && file_exists($db_path)) {
                        $final_img_src = $db_path;
                    } else {
                        $fallback_name = ($raw_material === 'aluminum') ? 'aluminium' : $raw_material;
                        $final_img_src = "uploads/test_" . $fallback_name . ".jpg";
                    }
                    
                    $is_scan   = ($activity['record_type'] === 'scan');
                    $badge_cls = $is_scan ? 'method-scan' : 'method-upload';
                    $icon_cls  = $is_scan ? 'fa-camera' : 'fa-cloud-arrow-up';
                    $btn_text  = $is_scan ? 'Scan' : 'Upload';
            ?>
                <div class="activity-item">
                    <div class="item-left">
                        <img class="item-thumbnail" src="<?php echo htmlspecialchars($final_img_src); ?>" alt="Waste Image">
                        <div class="item-text-box">
                            <div class="item-name" style="color: <?php echo $text_color; ?>;">
                                <?php echo htmlspecialchars($display_name); ?>
                            </div>
                            <div class="item-time-label">Time</div>
                            <div class="item-time"><?php echo htmlspecialchars($activity['formatted_time']); ?></div>
                        </div>
                    </div>
                    <div class="item-right-content">
                        <span class="badge <?php echo $badge_cls; ?>">
                            <i class="fa-solid <?php echo $icon_cls; ?>" style="font-size: 10px;"></i> 
                            <?php echo $btn_text; ?>
                        </span>
                    </div>
                </div>
            <?php 
                endforeach; 
            else:
            ?>
                <div class="activity-item" style="justify-content: center; color: #999; padding: 20px;">
                    No recent activity found for this account.
                </div>
            <?php endif; ?>

        </section>

    </main>

    <script>
        function toggleDrawer() {
            document.body.classList.toggle('drawer-open');
        }
    </script>
</body>
</html>
