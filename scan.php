<?php
// =========================================================================
// 1. PHP 后端核心业务逻辑：拦截异步图片 ➡️ 呼叫 Python AI ➡️ 写入 MySQL ➡️ 返回 JSON 结果
// =========================================================================

// 🆕 开启 Session 拦截机制，抓取当前登录的用户名
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['username'])) {
    // 如果没有登录，返回错误 JSON，防止未登录用户刷接口
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'User is not logged in.']);
    exit;
}
$username = $_SESSION['username']; // 成功抓取到当前登录的用户名！

// 检查是否是一个来自摄像头异步 Fetch 的图片上传请求
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['waste_image'])) {
    
    // 💡 强力防御 1：开启缓冲区，拦截一切意外报错或警告，誓死保护 JSON 纯净
    ob_start();
    
    // 声明返回格式为标准的 JSON，方便前端 JavaScript 解析
    header('Content-Type: application/json');
    
    // 🔌 线上部署核心修正：完美自适应 Railway 环境变量与内网拓扑结构
    $host = $_ENV['MYSQLHOST'] ?? 'mysql.railway.internal';
    $port = $_ENV['MYSQLPORT'] ?? 3306;
    $dbname = $_ENV['MYSQLDATABASE'] ?? 'railway'; // 本地默认，云端自动被覆盖为 railway
    $user = $_ENV['MYSQLUSER'] ?? 'root';
    $pass = $_ENV['MYSQLPASSWORD'] ?? 'asMgnFdMgJUNIekzFfCVeBpSWyzfJmDp'; 

    // 建立 MySQL 数据库连接
    $conn = new mysqli($host, $user, $pass, $dbname, $port);
    
    $upload_dir = 'upload/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_name = time() . '_' . basename($_FILES['waste_image']['name']);
    $target_file = $upload_dir . $file_name;

    // A. 先把摄像头截取的图片安全保存到云端前端服务器
    if (move_uploaded_file($_FILES['waste_image']['tmp_name'], $target_file)) {
        
        // B. 🌟 终极防御：采用 getenv() 与 $_SERVER 组合拳，誓死拿到 Railway 注入的真实 AI_URL 变量
        $flask_url = getenv('AI_URL') ?: ($_SERVER['AI_URL'] ?: ($_ENV['AI_URL'] ?? 'http://127.0.0.1:8080/predict'));
        
        $cFile = new CURLFile(realpath($target_file));
        $post_data = array('image' => $cFile);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $flask_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        # =========================================================================
        # 🌟 核心突破注入：正面拉长 cURL 响应超时，防止低配云端加载 YOLO 模型时因超时崩溃
        # =========================================================================
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60); # 允许建立连接的最长等待时间（秒）
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);        # 允许 cURL 函数执行的最长秒数（2分钟上限）
        # =========================================================================
        
        $response = curl_exec($ch);
        curl_close($ch);

        // C. 解析 Python 吐回来的 JSON 智慧结晶
        $result_data = json_decode($response, true);
        
        if (isset($result_data['status']) && $result_data['status'] == 'success') {
            $ai_detected_material = strtolower($result_data['prediction']); // plastic, paper, aluminium
            $box_coordinates = isset($result_data['box']) ? $result_data['box'] : [15, 15, 70, 70]; // 实时接住四维空间百分比坐标
            
            // 材质名称转换对齐 (Python 的 aluminium ➔ MySQL 的 aluminum)
            if ($ai_detected_material == 'aluminium') {
                $db_material = 'aluminum';
            } else {
                $db_material = $ai_detected_material;
            }

            // 💡 主动式捕获数据库报错机制
            $db_success = true;
            $error_msg = "";

            try {
                // 检查数据库连接是否成功
                if ($conn && !$conn->connect_error) {
                    $conn->set_charset("utf8mb4");

                    // 🆕 1. 写入历史总表（现在包含了 username 和 image_path 字段，并使用安全预处理）
                    $stmt1 = $conn->prepare("INSERT INTO waste_records (username, record_type, material_type, image_path) VALUES (?, 'scan', ?, ?)");
                    // "sss" 代表 3 个参数均为字符串：$username, $db_material, $target_file
                    $stmt1->bind_param("sss", $username, $db_material, $target_file);
                    $stmt1->execute();
                    $stmt1->close();

                    // 2. 更新对应智能垃圾桶的状态容量，计数 +1
                    $stmt2 = $conn->prepare("UPDATE recycle_bins SET current_volume = current_volume + 1 WHERE bin_name = ?");
                    $stmt2->bind_param("s", $db_material);
                    $stmt2->execute();
                    $stmt2->close();
                    
                    $conn->close();
                } else {
                    $db_success = false;
                    $error_msg = "Database connection initialization failed: " . ($conn ? $conn->connect_error : "Connection object is null");
                }
            } catch (Exception $db_error) {
                $db_success = false;
                $error_msg = "MySQL Execution Error: " . $db_error->getMessage();
            }

            // 💡 强力防御 2：彻底擦干缓冲区，倒出纯净 JSON
            ob_end_clean();

            // 打包异步回传给前端 JavaScript
            if ($db_success) {
                echo json_encode([
                    'status' => 'success',
                    'prediction' => $ai_detected_material,
                    'box' => $box_coordinates,
                    'image_path' => $target_file
                ]);
            } else {
                // 就算数据库写入被卡住或有异常，也把 db_error 抛出，同时仍允许前端拿到 AI 框正常渲染，保证用户体验
                echo json_encode([
                    'status' => 'db_error',
                    'message' => $error_msg,
                    'prediction' => $ai_detected_material,
                    'box' => $box_coordinates
                ]);
            }
        } else {
            if ($conn && !$conn->connect_error) $conn->close();
            ob_end_clean();
            echo json_encode(['status' => 'error', 'message' => 'AI Server recognition failed or timed out.']);
        }
    } else {
        if ($conn && !$conn->connect_error) $conn->close();
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Failed to save uploaded image.']);
    }
    exit; // 切断请求，防止其继续向下渲染 HTML
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WasteScan AI - Scan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ================= 全局基础样式 ================= */
        body, html {
            margin: 0; padding: 0;
            height: 100%; overflow: hidden;
            background-color: #000;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        /* 🆕 替换后的新型顶部导航栏：1:1 像素复刻 UI 规范 */
        .app-bar-new {
            background-color: #ffffff;
            height: 60px;
            display: flex;
            align-items: center;
            padding: 0 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            position: relative;
            z-index: 30;
        }
        
        .back-nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #2E7D32; /* 经典的 WasteScan 绿色 */
            text-decoration: none;
            font-size: 18px;
            font-weight: 700;
            transition: opacity 0.2s;
        }
        
        .back-nav-link:hover {
            opacity: 0.8;
        }
        
        .back-nav-link i {
            font-size: 16px;
        }

        /* 相机视窗区域自适应调整 */
        .camera-container {
            position: absolute;
            top: 60px; bottom: 0; left: 0; right: 0;
            display: flex; justify-content: center; align-items: center;
            background: #222;
        }

        #webcam {
            width: 100%; height: 100%; object-fit: cover;
        }

        /* 扫描绿线动画 */
        .scan-wrapper { relative; width: 100%; height: 100%; }
        .scan-line {
            display: none; position: absolute; top: 0; left: 0; width: 100%; height: 4px;
            background: linear-gradient(to right, transparent, #00ff00, transparent);
            animation: scanMove 2s linear infinite; z-index: 10;
        }
        .scanning .scan-line { display: block; }
        @keyframes scanMove {
            0% { top: 0%; } 50% { top: 100%; } 100% { top: 0%; }
        }

        /* 底部拍摄按钮区 */
        .controls {
            position: absolute; bottom: 40px; left: 0; right: 0;
            display: flex; justify-content: center; align-items: center; z-index: 20;
        }
        .scan-circle-btn {
            width: 80px; height: 80px; background-color: white;
            border: 5px solid rgba(255, 255, 255, 0.3); border-radius: 50%;
            cursor: pointer; box-shadow: 0 0 15px rgba(0,0,0,0.5); transition: transform 0.2s;
        }
        .scan-circle-btn:active { transform: scale(0.9); background-color: #ddd; }


        /* ================= 结果覆盖面板样式 ================= */
        .result-overlay {
            display: none; /* 初始隐藏 */
            position: absolute; top: 60px; left: 0; right: 0; bottom: 0;
            background: rgba(245, 245, 245, 0.98); z-index: 40;
            flex-direction: column; align-items: center; justify-content: flex-start;
            padding: 20px; box-sizing: border-box; overflow-y: auto;
        }

        /* 捕捉后的相片预览框 */
        .captured-image-box {
            position: relative; width: 100%; max-width: 320px; height: 320px;
            border-radius: 8px; overflow: hidden; margin-top: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            background: #fff;
        }
        #captured-canvas-preview { width: 100%; height: 100%; object-fit: cover; }

        /* AI 目标检测框 */
        .ai-bounding-box {
            position: absolute; 
            left: 10%; top: 15%; width: 80%; height: 75%;
            border: 3px solid #0056ff; box-sizing: border-box;
        }
        .ai-label {
            position: absolute; top: -25px; left: -3px; background: #0056ff;
            color: white; font-size: 12px; padding: 2px 8px; border-radius: 3px 3px 0 0;
            white-space: nowrap; font-weight: bold;
        }

        /* 指示牌卡片区域 */
        .instruction-card {
            width: 100%; max-width: 320px; background: #fff; border: 1px solid #ddd;
            border-radius: 8px; padding: 15px; margin-top: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            box-sizing: border-box;
        }
        .info-row { margin-bottom: 12px; font-size: 16px; color: #333; font-weight: bold; }
        
        .type-badge {
            display: inline-block; padding: 4px 12px; border-radius: 20px;
            color: white; font-weight: bold; font-size: 14px; margin-left: 5px;
        }
        
        .instruction-banner {
            border-top: 2px solid #eee; padding-top: 15px; margin-top: 15px;
            display: flex; align-items: center; justify-content: space-between;
        }
        .bin-text { font-size: 16px; font-weight: bold; color: #222; line-height: 1.4; }
        
        .bin-icon {
            width: 45px; height: 55px; border-radius: 5px 5px 2px 2px;
            position: relative; display: flex; align-items: center; justify-content: center;
            color: white; font-size: 18px; font-weight: bold; box-shadow: inset 0 -10px rgba(0,0,0,0.2);
        }
        .bin-icon::before { 
            content: ''; position: absolute; top: -6px; left: 10%; width: 80%; height: 6px;
            background: inherit; border-radius: 3px 3px 0 0; border-bottom: 1px solid rgba(0,0,0,0.1);
        }

        /* 返回首页按钮 */
        .back-home-btn {
            margin-top: 25px; margin-bottom: 30px; padding: 14px 50px; background-color: #d2ecea;
            color: #00695c; border: none; border-radius: 25px; font-size: 16px;
            font-weight: bold; cursor: pointer; box-shadow: 0 3px 6px rgba(0,0,0,0.1);
            transition: all 0.2s ease;
            z-index: 50;
        }
        .back-home-btn:hover { background-color: #b2dfdb; transform: translateY(-1px); }
        .back-home-btn:active { transform: translateY(1px); }
    </style>
</head>
<body>

    <header class="app-bar-new">
        <a href="home.php" class="back-nav-link">
            <i class="fa-solid fa-chevron-left"></i> Scan
        </a>
    </header>

    <div class="camera-container" id="camera-viewport">
        <div class="scan-wrapper" id="display-area">
            <div class="scan-line"></div> 
            <video id="webcam" autoplay playsinline></video>
        </div>
    </div>

    <div class="controls" id="controls-bar">
        <div class="scan-circle-btn" onclick="startScanning()"></div>
    </div>


    <div class="result-overlay" id="result-screen">
        
        <div class="captured-image-box">
            <canvas id="captured-canvas-preview"></canvas>
            <div class="ai-bounding-box" id="res-bbox">
                <div class="ai-label" id="res-bbox-label">Detecting...</div>
            </div>
        </div>

        <div class="instruction-card">
            <div class="info-row">
                CURRENT Type: <span id="res-type-badge" class="type-badge">-</span>
            </div>
            
            <div class="instruction-banner">
                <div class="bin-text">
                    INSTRUCTION:<br>
                    <span id="res-instruction-text">-</span>
                </div>
                <div id="res-bin-icon" class="bin-icon">♻</div>
            </div>
        </div>

        <button class="back-home-btn" onclick="goToHomepage()">back to home</button>
    </div>


    <script>
        const video = document.getElementById('webcam');
        const displayArea = document.getElementById('display-area');
        const resultScreen = document.getElementById('result-screen');
        const controlsBar = document.getElementById('controls-bar');
        const previewCanvas = document.getElementById('captured-canvas-preview');
        
        const resBBox = document.getElementById('res-bbox');
        const resBBoxLabel = document.getElementById('res-bbox-label');
        const resTypeBadge = document.getElementById('res-type-badge');
        const resInstructionText = document.getElementById('res-instruction-text');
        const resBinIcon = document.getElementById('res-bin-icon');

        // 垃圾桶配置信息映射表
        const configMap = {
            'plastic': { label: 'Plastic ', color: '#ff0000', binName: 'RED Bin (Plastic)' },
            'aluminium': { label: 'Aluminium ', color: '#ffcc00', binName: 'YELLOW Bin (Aluminium)' },
            'paper': { label: 'Paper ', color: '#0056ff', binName: 'BLUE Bin (Paper)' }
        };

        // 启动摄像头
        async function startCamera() {
            try {
                const stream = await navigator.mediaDevices.getUserMedia({
                    video: { facingMode: "environment" }
                });
                video.srcObject = stream;
            } catch (err) {
                console.error("The camera cannot be accessed: ", err);
            }
        }

        // 🎯 触发拍照、捕捉 Canvas 并异步传送给后端 PHP
        function startScanning() {
            displayArea.classList.add('scanning');

            previewCanvas.width = video.videoWidth || 640;
            previewCanvas.height = video.videoHeight || 480;
            const ctx = previewCanvas.getContext('2d');
            
            try {
                ctx.drawImage(video, 0, 0, previewCanvas.width, previewCanvas.height);
            } catch(e) {
                ctx.fillStyle = "#ccc";
                ctx.fillRect(0, 0, previewCanvas.width, previewCanvas.height);
            }

            // 将画布上的帧画面转换为 JPEG Blob 二进制流
            previewCanvas.toBlob(async function(blob) {
                const formData = new FormData();
                formData.append('waste_image', blob, 'webcam_capture.jpg');

                try {
                    // 🌟 核心突破：直接向同服务器下的 scan.php 发送 POST 异步表单
                    const response = await fetch('scan.php', {
                        method: 'POST',
                        body: formData
                    });

                    const result = await response.json();

                    // 延迟 1.5 秒移除扫描特效，维持操作流程的科技感体验
                    setTimeout(() => {
                        displayArea.classList.remove('scanning');
                        
                        if (result.status === 'success' || result.status === 'db_error') {
                            if (result.status === 'db_error') {
                                // 🛠️ 语法修正：将 JavaScript 中的 "." 拼接修改为正常的 "+" 拼接
                                console.warn("Database sync warning: " + result.message);
                            }
                            renderResultData(result.prediction, result.box);
                            resultScreen.style.display = 'flex';
                            controlsBar.style.display = 'none';
                        } else {
                            alert("AI Engine response error: " + result.message);
                            displayArea.classList.remove('scanning');
                        }
                    }, 1500);

                } catch (error) {
                    displayArea.classList.remove('scanning');
                    console.error("AJAX Fetch failed:", error);
                    alert("Cannot reach the cloud backend server. Please verify your internet connection.");
                }
            }, 'image/jpeg', 0.9);
        }

        // 渲染检测数据
        function renderResultData(type, boxCoordinates) {
            const config = configMap[type];
            if (!config) return;

            resBBox.style.borderColor = config.color;
            
            // 基于 YOLO 传回的百分比坐标进行自适应定位
            resBBox.style.top = boxCoordinates[0] + "%";
            resBBox.style.left = boxCoordinates[1] + "%";
            resBBox.style.height = boxCoordinates[2] + "%";
            resBBox.style.width = boxCoordinates[3] + "%";

            resBBoxLabel.style.backgroundColor = config.color;
            resBBoxLabel.textContent = config.label;
            
            resTypeBadge.textContent = type.toUpperCase();
            resTypeBadge.style.backgroundColor = config.color;
            
            resInstructionText.innerHTML = `[Throw in <span style="color:${config.color}">${config.binName.split(' ')[0]}</span> Bin<br>(${type.charAt(0).toUpperCase() + type.slice(1)})]`;
            resBinIcon.style.backgroundColor = config.color;
        }

        function goToHomepage() {
            window.location.href = "home.php";
        }

        startCamera();
    </script>
</body>
</html>
