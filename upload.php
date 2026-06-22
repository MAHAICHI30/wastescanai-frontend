<?php
// =========================================================================
// 1. PHP 后端核心业务逻辑：拦截异步图片 ➡️ 呼叫 Python AI ➔ 写入 MySQL ➡️ 返回 JSON 结果
// =========================================================================

// 🔒 身份双重校验：不仅检查是不是 POST 和有图片，还要确保是来自本页面的 'gallery_upload' 请求
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['waste_image']) && isset($_POST['identity']) && $_POST['identity'] === 'gallery_upload') {
    
    // 💡 强力防御 1：开启缓冲区，拦截一切意外报错，誓死保护 JSON 纯净
    ob_start();
    
    // 声明返回格式为标准的 JSON
    header('Content-Type: application/json');
    
    // 🔌 【核心整合】：直接把数据库连接逻辑注入头部，不再依赖外部 include
    $host = "localhost";
    $db_user = "root";          // XAMPP 默认数据库用户名
    $db_pass = "";              // XAMPP 默认数据库密码
    $db_name = "wastescanaidb"; // 你的数据库名字

    // 建立 MySQL 数据库连接
    $conn = new mysqli($host, $db_user, $db_pass, $db_name);
    
    $upload_dir = 'upload/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_name = time() . '_' . basename($_FILES['waste_image']['name']);
    $target_file = $upload_dir . $file_name;

    if (move_uploaded_file($_FILES['waste_image']['tmp_name'], $target_file)) {
        
        // B. 利用 CURL 将图片转发给 5001 端口的 Python Flask AI 引擎
        $flask_url = 'http://127.0.0.1:5001/predict';
        $cFile = new CURLFile(realpath($target_file));
        $post_data = array('image' => $cFile);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $flask_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        curl_close($ch);

        $result_data = json_decode($response, true);
        
        if (isset($result_data['status']) && $result_data['status'] == 'success') {
            $ai_detected_material = strtolower($result_data['prediction']);
            $box_coordinates = isset($result_data['box']) ? $result_data['box'] : [15, 15, 70, 70];
            
            // 材质名称转换对齐 (Python 的 aluminium ➔ MySQL 的 aluminum)
            if ($ai_detected_material == 'aluminium') {
                $db_material = 'aluminum';
            } else {
                $db_material = $ai_detected_material;
            }

            // 💡 主动式捕获报错机制
            $db_success = true;
            $error_msg = "";

            try {
                // 检查连接是否成功建立
                if ($conn && !$conn->connect_error) {
                    // 设置字符集编码防止乱码
                    $conn->set_charset("utf8mb4");

                    // 🌟 完美同步：将路径 $target_file 一并写入新创建的 image_path 字段！
                    $stmt1 = $conn->prepare("INSERT INTO waste_records (record_type, material_type, image_path) VALUES ('upload', ?, ?)");
                    $stmt1->bind_param("ss", $db_material, $target_file);
                    $stmt1->execute();
                    $stmt1->close();

                    // 2. 更新垃圾桶状态表自增 +1 (供 Doughnut Chart 调用)
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

            // 💡 强力防御 3：彻底擦干缓冲区，倒出纯净 JSON
            ob_end_clean();

            // 打包异步回传给前端
            if ($db_success) {
                echo json_encode([
                    'status' => 'success',
                    'prediction' => $ai_detected_material,
                    'box' => $box_coordinates,
                    'image_path' => $target_file
                ]);
            } else {
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
            echo json_encode(['status' => 'error', 'message' => 'AI Server recognition failed.']);
        }
    } else {
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Failed to save uploaded image.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WasteScan AI - Upload</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* ================= 核心基础样式 ================= */
        body, html {
            margin: 0; padding: 0;
            height: 100%; overflow-x: hidden;
            background-color: #f4f7f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .app-bar-new {
            background-color: #ffffff;
            height: 60px;
            display: flex;
            align-items: center;
            padding: 0 20px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.06);
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 110;
        }
        
        .back-nav-link {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #2E7D32;
            text-decoration: none;
            font-size: 18px;
            font-weight: 700;
            transition: opacity 0.2s;
        }
        
        .back-nav-link:hover { opacity: 0.8; }
        .back-nav-link i { font-size: 16px; }

        .upload-container {
            margin-top: 100px;
            margin-bottom: 90px;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px;
        }

        .file-drop-area {
            width: 100%;
            max-width: 500px;
            min-height: 350px;
            border: 3px dashed #b2dfdb;
            border-radius: 15px;
            background-color: #fff;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            cursor: pointer;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            transition: all 0.3s ease;
        }

        .upload-icon-box {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 1;
            padding: 40px 0;
        }
        .upload-icon { font-size: 55px; margin-bottom: 15px; }
        .upload-text {
            font-size: 16px; color: #555; font-weight: bold;
            text-align: center; padding: 0 20px; line-height: 1.5;
        }

        #hidden-file-input {
            position: absolute; top: 0; left: 0; width: 100%; height: 100%;
            opacity: 0; cursor: pointer; z-index: 10;
        }

        #gallery-preview-img {
            width: 100%;
            height: auto;
            max-height: 500px;
            object-fit: contain;
            display: none;
            position: relative;
            z-index: 5;
        }

        .bottom-bar {
            position: fixed; bottom: 0; left: 0; right: 0;
            padding: 15px; background: white;
            display: flex; justify-content: center;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.05);
            z-index: 100;
        }
        .btn-select {
            background-color: #e0e0e0; border: none;
            padding: 12px 70px; border-radius: 25px;
            font-weight: bold; font-size: 18px; color: #999; cursor: not-allowed;
            box-shadow: 0 2px 5px rgba(0,0,0,0.05);
            transition: all 0.2s ease;
        }
        .btn-select.active {
            background-color: #d1eded; color: #004d40; cursor: pointer;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .result-overlay {
            display: none; 
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: #f4f7f5;
            z-index: 100;
            flex-direction: column; 
            align-items: center; 
            justify-content: flex-start;
            padding: 90px 20px 20px 20px;
            box-sizing: border-box; 
            overflow-y: auto; 
        }
        .scan-wrapper {
            position: relative;
            width: auto;
            max-width: 500px;
            display: inline-block;
        }
        
        .scan-line {
            display: none; position: absolute; top: 0; left: 0; width: 100%; height: 4px;
            background: linear-gradient(to right, transparent, #00ff00, transparent);
            animation: scanMove 2s linear infinite; z-index: 15;
        }
        .scanning .scan-line { display: block; }
        @keyframes scanMove {
            0% { top: 0%; } 50% { top: 100%; } 100% { top: 0%; }
        }

        .captured-image-box {
            width: 100%;
            height: auto;
            border-radius: 8px; overflow: hidden; box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            background: #fff; position: relative;
            display: flex;
        }
        
        #preview-img {
            width: 100%;
            height: auto;
            max-height: 500px;
            object-fit: contain;
        }

        .ai-bounding-box {
            display: none;
            position: absolute;
            border: 3px solid #0056ff;
            box-sizing: border-box;
            z-index: 5;
            transition: all 0.3s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .ai-label {
            position: absolute; top: -25px; left: -3px; background: #0056ff;
            color: white; font-size: 12px; padding: 2px 8px; border-radius: 3px 3px 0 0;
            white-space: nowrap; font-weight: bold;
        }

        .instruction-card {
            display: none;
            width: 100%; max-width: 500px;
            background: #fff; border: 1px solid #ddd;
            border-radius: 8px; padding: 15px; margin-top: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            box-sizing: border-box;
        }
        .info-row { margin-bottom: 12px; font-size: 16px; color: #333; font-weight: bold; }
        .type-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; color: white; font-weight: bold; font-size: 14px; margin-left: 5px; }
        .instruction-banner { border-top: 2px solid #eee; padding-top: 15px; margin-top: 15px; display: flex; align-items: center; justify-content: space-between; }
        .bin-text { font-size: 16px; font-weight: bold; color: #222; line-height: 1.4; }
        
        .bin-icon {
            width: 45px; height: 55px; border-radius: 5px 5px 2px 2px;
            position: relative; display: flex; align-items: center; justify-content: center;
            color: white; font-size: 18px; font-weight: bold; box-shadow: inset 0 -10px rgba(0,0,0,0.2);
        }
        .bin-icon::before { content: ''; position: absolute; top: -6px; left: 10%; width: 80%; height: 6px; background: inherit; border-radius: 3px 3px 0 0; border-bottom: 1px solid rgba(0,0,0,0.1); }

        .back-home-btn { display: none; margin-top: 25px; margin-bottom: 30px; padding: 14px 50px; background-color: #d2ecea; color: #00695c; border: none; border-radius: 25px; font-size: 16px; font-weight: bold; cursor: pointer; box-shadow: 0 3px 6px rgba(0,0,0,0.1); transition: all 0.2s ease; }
        .back-home-btn:hover { background-color: #b2dfdb; }
    </style>
</head>
<body>

    <header class="app-bar-new">
        <a href="home.php" class="back-nav-link">
            <i class="fa-solid fa-chevron-left"></i> Upload
        </a>
    </header>

    <form id="upload-form" onsubmit="event.preventDefault();">
        <div class="upload-container" id="upload-main-view">
            <div class="file-drop-area">
                <div class="upload-icon-box" id="drop-prompt-box">
                    <div class="upload-icon">🖼️</div>
                    <div class="upload-text">Click to open your phone Gallery / Camera roll</div>
                </div>
                <input type="file" id="hidden-file-input" name="waste_image" accept="image/*" onchange="handleFileSelect(this)">
                <img id="gallery-preview-img" alt="local preview">
            </div>
        </div>

        <div class="bottom-bar" id="action-bar">
            <button type="button" class="btn-select" id="submit-btn" onclick="startAIScanFlow()">Select</button>
        </div>
    </form>


    <div id="scan-overlay" class="result-overlay">
        <div class="scan-wrapper" id="display-area">
            <div class="scan-line"></div>
            <div class="captured-image-box">
                <img id="preview-img" src="" alt="preview">
                
                <div class="ai-bounding-box" id="res-bbox">
                    <div class="ai-label" id="res-bbox-label">Detecting...</div>
                </div>
            </div>
        </div>

        <div class="instruction-card" id="res-card">
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

        <button class="back-home-btn" id="res-back-btn" onclick="goToHome()">back to home</button>
    </div>


    <script>
        const configMap = {
            'plastic': { label: 'Plastic ', color: '#ff0000', binName: 'RED Bin (Plastic)' },
            'aluminium': { label: 'Aluminium ', color: '#ffcc00', binName: 'YELLOW Bin (Aluminium)' },
            'paper': { label: 'Paper ', color: '#0056ff', binName: 'BLUE Bin (Paper)' }
        };

        let currentLocalImgURL = "";

        function handleFileSelect(input) {
            const file = input.files[0];
            if (file) {
                currentLocalImgURL = URL.createObjectURL(file);
                const previewElement = document.getElementById('gallery-preview-img');
                previewElement.src = currentLocalImgURL;
                previewElement.style.display = 'block';
                document.getElementById('drop-prompt-box').style.display = 'none';
                const submitBtn = document.getElementById('submit-btn');
                submitBtn.classList.add('active');
            }
        }

        async function startAIScanFlow() {
            const fileInput = document.getElementById('hidden-file-input');
            if (fileInput.files.length === 0) {
                alert("Please select a photo from your gallery first.");
                return;
            }

            document.getElementById('preview-img').src = currentLocalImgURL;
            document.getElementById('upload-main-view').style.display = 'none';
            document.getElementById('action-bar').style.display = 'none';
            document.getElementById('scan-overlay').style.display = 'flex';

            const displayArea = document.getElementById('display-area');
            displayArea.classList.add('scanning');

            const formData = new FormData();
            formData.append('waste_image', fileInput.files[0]);
            // 🌟 核心升级：强制打上独属于上传页面的身份印记，杜绝外部越界污染
            formData.append('identity', 'gallery_upload'); 

            try {
                const response = await fetch('upload.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                setTimeout(() => {
                    displayArea.classList.remove('scanning');

                    if (result.status === 'success') {
                        renderResultData(result.prediction, result.box);
                    } else if (result.status === 'db_error') {
                        alert("⚠️ AI recognition successful but Database Error occurred:\n" + result.message);
                        renderResultData(result.prediction, result.box);
                    } else {
                        alert("AI Engine response: " + result.message);
                        goToHome();
                    }
                }, 1500);

            } catch (error) {
                displayArea.classList.remove('scanning');
                console.error("AJAX Fetch failed:", error);
                alert("Cannot reach the AI Core. Check if XAMPP Apache and Flask Server are fully alive.");
                goToHome();
            }
        }

        function renderResultData(type, boxCoordinates) {
            const config = configMap[type];
            if (!config) return;

            const resBBox = document.getElementById('res-bbox');
            const resBBoxLabel = document.getElementById('res-bbox-label');
            
            resBBox.style.display = 'block';
            resBBox.style.borderColor = config.color;
            resBBox.style.top = boxCoordinates[0] + "%";
            resBBox.style.left = boxCoordinates[1] + "%";
            resBBox.style.height = boxCoordinates[2] + "%";
            resBBox.style.width = boxCoordinates[3] + "%";
            resBBoxLabel.style.backgroundColor = config.color;
            resBBoxLabel.textContent = config.label;

            const resCard = document.getElementById('res-card');
            const resTypeBadge = document.getElementById('res-type-badge');
            const resInstructionText = document.getElementById('res-instruction-text');
            const resBinIcon = document.getElementById('res-bin-icon');
            resCard.style.display = 'block';
            resTypeBadge.textContent = type.toUpperCase();
            resTypeBadge.style.backgroundColor = config.color;
            resInstructionText.innerHTML = `[Throw in <span style="color:${config.color}">${config.binName.split(' ')[0]}</span> Bin<br>(${type.charAt(0).toUpperCase() + type.slice(1)})]`;
            resBinIcon.style.backgroundColor = config.color;
            document.getElementById('res-back-btn').style.display = 'block';
        }

        function goToHome() {
            window.location.href = 'home.php';
        }
    </script>
</body>
</html>