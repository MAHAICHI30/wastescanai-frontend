<?php
// ==========================================================================
// 1. 开启会话并建立自适应的 MySQL PDO 连接（过滤当前登录用户的记录）
// ==========================================================================
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🔒 安全守卫：检查用户是否登录，如果没有登录，强制重定向退回登录页
if (!isset($_SESSION['username'])) {
    header("Location: login.php");
    exit;
}

$username = $_SESSION['username'];

// 🔌 线上部署核心修正：完美自适应 Railway 环境变量与内网拓扑结构
$host = $_ENV['MYSQLHOST'] ?? '127.0.0.1';
$port = $_ENV['MYSQLPORT'] ?? 3306;
$dbname = $_ENV['MYSQLDATABASE'] ?? 'wastescanaidb'; // 本地默认，云端自动被覆盖为 railway
$user = $_ENV['MYSQLUSER'] ?? 'root';
$pass = $_ENV['MYSQLPASSWORD'] ?? ''; 

$grouped_activities = [];

try {
    // 🌟 升级为标准 PDO 驱动，完美保持技术栈一致与安全防注入
    $pdo = new PDO("mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 🌟【隔离机制】：添加 username = ? 绑定，只拉取当前登录账号自己的数据
    $sql = "SELECT id, record_type, material_type, image_path, created_at,
            CASE 
                WHEN DATE(created_at) = CURDATE() THEN 'Today'
                WHEN DATE(created_at) = SUBDATE(CURDATE(), 1) THEN 'Yesterday'
                ELSE DATE_FORMAT(created_at, '%M %d, %Y')
            END AS date_group,
            DATE_FORMAT(created_at, '%h:%i %p') AS formatted_time
            FROM waste_records 
            WHERE material_type != 'unknown' AND username = ?
            ORDER BY created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$username]);
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($results)) {
        foreach ($results as $row) {
            $group_name = $row['date_group'];
            $grouped_activities[$group_name][] = $row;
        }
    }

} catch (PDOException $e) {
    // 调试期可开启：echo "Error: " . $e->getMessage();
}

// 🎨 建立国家标准视觉颜色字典映射 (PHP 端控制)
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
    <title>WasteScan AI - Recent Activity</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        /* ==========================================================================
           2. 全局重置与基础样式
           ========================================================================== */
        * {
            margin: 0; padding: 0; box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        body {
            background-color: #F4F7F5; color: #333333; padding-bottom: 40px;
        }

        .app-bar {
            background-color: #ffffff; height: 60px; display: flex;
            align-items: center; padding: 0 16px; box-shadow: 0 2px 5px rgba(0,0,0,0.04);
            position: sticky; top: 0; z-index: 1000;
        }

        .back-btn { font-size: 18px; color: #2E7D32; text-decoration: none; padding: 5px; }
        .app-title { font-weight: 700; font-size: 18px; color: #2E7D32; margin-left: 12px; }
        .main-container { max-width: 600px; margin: 0 auto; padding: 16px; }
        .page-header { margin-bottom: 20px; margin-top: 5px; }
        .page-header h2 { font-size: 24px; color: #1A301F; font-weight: 700; }
        .page-header p { font-size: 13px; color: #777777; margin-top: 4px; }
        .date-group-title { font-size: 13px; font-weight: 700; color: #888888; margin: 20px 0 10px 4px; text-transform: uppercase; }

        /* ==========================================================================
           3. 独立卡片布局
           ========================================================================== */
        .activity-card-stream { display: flex; flex-direction: column; gap: 12px; }
        
        .activity-item {
            background-color: #ffffff; border-radius: 14px; padding: 12px;
            display: flex; align-items: center; justify-content: space-between;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03); border: 1px solid rgba(0,0,0,0.01);
        }

        .item-left { display: flex; align-items: center; gap: 14px; flex: 1; min-width: 0; }
        
        .item-thumbnail {
            width: 75px; height: 75px; border-radius: 10px;
            object-fit: cover; flex-shrink: 0; background-color: #EAEAEA;
            border: 1px solid rgba(0, 0, 0, 0.04);
        }

        .item-text-box { min-width: 0; }
        .item-name { font-size: 16px; font-weight: 700; } 
        .item-time-label { font-size: 11px; color: #999999; text-transform: uppercase; margin-top: 6px; }
        .item-time { font-size: 13px; color: #555555; font-weight: 500; margin-top: 1px; }

        .item-right-content { flex-shrink: 0; margin-left: 10px; }
        .badge { font-size: 12px; padding: 5px 14px; border-radius: 20px; font-weight: 700; display: flex; align-items: center; gap: 5px; }
        .method-scan { background-color: #E8F5E9; color: #2E7D32; }
        .method-upload { background-color: #E3F2FD; color: #1565C0; }
    </style>
</head>
<body>

    <header class="app-bar">
        <a href="home.php" class="back-btn"><i class="fa-solid fa-chevron-left"></i></a>
        <span class="app-title">History</span>
    </header>

    <main class="main-container">
        <div class="page-header">
            <h2>Recent Activity (<?php echo htmlspecialchars($username); ?>)</h2>
            <p>Review and track your AI waste identification records.</p>
        </div>

        <?php 
        if (!empty($grouped_activities)):
            foreach ($grouped_activities as $date_label => $items):
        ?>
            <div class="date-group-title"><?php echo htmlspecialchars($date_label); ?></div>
            
            <section class="activity-card-stream">
                <?php 
                foreach ($items as $activity): 
                    $raw_material = strtolower($activity['material_type']);
                    
                    // 1. 标准化材质显示文字
                    $display_name = ucfirst($raw_material);
                    if ($raw_material === 'aluminum' || $raw_material === 'aluminium') {
                        $display_name = 'Aluminium';
                        $raw_material = 'aluminium'; // 规整化映射配色
                    }
                    
                    // 动态从字典中读取对应的配色
                    $text_color = isset($material_colors[$raw_material]) ? $material_colors[$raw_material] : '#333333';
                    
                    // 2. 检查图片文件路径
                    $db_path = $activity['image_path'];
                    
                    if (!empty($db_path) && file_exists($db_path)) {
                        $final_img_src = $db_path;
                    } else {
                        $final_img_src = "uploads/test_" . $raw_material . ".jpg";
                    }
                    
                    // 完美兼容你后端写入的 'scan' 和 'AI_Scan' 记录类型
                    $is_scan   = ($activity['record_type'] === 'scan' || $activity['record_type'] === 'AI_Scan');
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
                <?php endforeach; ?>
            </section>

        <?php 
            endforeach;
        else:
        ?>
            <div style="text-align: center; color: #999; padding: 40px 0; font-size: 14px;">
                <i class="fa-solid fa-folder-open" style="font-size: 30px; color: #ccc; margin-bottom: 10px;"></i>
                <p>No waste identification history records found for this account.</p>
            </div>
        <?php endif; ?>
    </main>

</body>
</html>
