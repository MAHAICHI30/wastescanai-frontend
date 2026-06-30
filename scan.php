<?php
// =========================================================================
// 1. PHP 后端核心业务逻辑：拦截异步图片 ➡️ 呼叫 Python AI ➡️ 写入 MySQL ➡️ 返回 JSON 结果
// =========================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['username'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'User is not logged in.']);
    exit;
}
$username = $_SESSION['username']; 

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['waste_image'])) {
    
    ob_start();
    header('Content-Type: application/json');
    
    $host = $_ENV['MYSQLHOST'] ?? 'mysql.railway.internal';
    $port = $_ENV['MYSQLPORT'] ?? 3306;
    $dbname = $_ENV['MYSQLDATABASE'] ?? 'railway'; 
    $user = $_ENV['MYSQLUSER'] ?? 'root';
    $pass = $_ENV['MYSQLPASSWORD'] ?? 'asMgnFdMgJUNIekzFfCVeBpSWyzfJmDp'; 

    $conn = new mysqli($host, $user, $pass, $dbname, $port);
    
    $upload_dir = 'upload/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_name = time() . '_' . basename($_FILES['waste_image']['name']);
    $target_file = $upload_dir . $file_name;

    if (move_uploaded_file($_FILES['waste_image']['tmp_name'], $target_file)) {
        
        // 🌟 终极防御变量获取
        $flask_url = getenv('AI_URL') ?: ($_SERVER['AI_URL'] ?: ($_ENV['AI_URL'] ?? 'http://127.0.0.1:8080/predict'));
        
        $cFile = new CURLFile(realpath($target_file));
        $post_data = array('image' => $cFile);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $flask_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // 🌟 注入 120 秒超长响应超时，防止首次加载 YOLO 超时
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60); 
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);        
        
        $response = curl_exec($ch);
        curl_close($ch);

        $result_data = json_decode($response, true);
        
        if (isset($result_data['status']) && $result_data['status'] == 'success') {
            $ai_detected_material = strtolower($result_data['prediction']); 
            $box_coordinates = isset($result_data['box']) ? $result_data['box'] : [15, 15, 70, 70]; 
            
            if ($ai_detected_material == 'aluminium') {
                $db_material = 'aluminum';
            } else {
                $db_material = $ai_detected_material;
            }

            $db_success = true;
            $error_msg = "";

            try {
                if ($conn && !$conn->connect_error) {
                    $conn->set_charset("utf8mb4");

                    $stmt1 = $conn->prepare("INSERT INTO waste_records (username, record_type, material_type, image_path) VALUES (?, 'scan', ?, ?)");
                    $stmt1->bind_param("sss", $username, $db_material, $target_file);
                    $stmt1->execute();
                    $stmt1->close();

                    $stmt2 = $conn->prepare("UPDATE recycle_bins SET current_volume = current_volume + 1 WHERE bin_name = ?");
                    $stmt2->bind_param("s", $db_material);
                    $stmt2->execute();
                    $stmt2->close();
                    
                    $conn->close();
                } else {
                    $db_success = false;
                    $error_msg = "Database connection initialization failed";
                }
            } catch (Exception $db_error) {
                $db_success = false;
                $error_msg = "MySQL Execution Error: " . $db_error->getMessage();
            }

            ob_end_clean();

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
            echo json_encode(['status' => 'error', 'message' => 'AI Server recognition failed or timed out.']);
        }
    } else {
        if ($conn && !$conn->connect_error) $conn->close();
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
    <title>WasteScan AI - Scan</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body, html { margin: 0; padding: 0; height: 100%; overflow: hidden; background-color: #000; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .app-bar-new { background-color: #ffffff; height: 60px; display: flex; align-items: center; padding: 0 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.06); position: relative; z-index: 30; }
        .back-nav-link { display: flex; align-items: center; gap: 10px; color: #2E7D32; text-decoration: none; font-size: 18px; font-weight: 700; transition: opacity 0.2s; }
        .back-nav-link:hover { opacity: 0.8; }
        .back-nav-link i { font-size: 16px; }
        .camera-container { position: absolute; top: 60px; bottom: 0; left: 0; right: 0; display: flex; justify-content: center; align-items: center; background: #222; }
        #webcam { width: 100%; height: 100%; object-fit: cover; }
        .scan-wrapper { position: relative; width: 100%; height: 100%; }
        .scan-line { display: none; position: absolute; top: 0; left: 0; width: 100%; height: 4px; background: linear-gradient(to right, transparent, #00ff00, transparent); animation: scanMove 2s linear infinite; z-index: 10; }
        .scanning .scan-line { display: block; }
        @keyframes scanMove { 0% { top: 0%; } 50% { top: 100%; } 100% { top: 0%; } }
        .controls { position: absolute; bottom: 40px; left: 0; right: 0; display: flex; justify-content: center; align-items: center; z-index: 20; }
        .scan-circle-btn { width: 80px; height: 80px; background-color: white; border: 5px solid rgba(255, 255, 255, 0.3); border-radius: 50%; cursor: pointer; box-shadow: 0 0 15px rgba(0,0,0,0.5); transition: transform 0.2s; }
        .scan-circle-btn:active { transform: scale(0.9); background-color: #ddd; }
        .result-overlay { display: none; position: absolute; top: 60px; left: 0; right: 0; bottom: 0; background: rgba(245, 245, 245, 0.98); z-index: 40; flex-direction: column; align-items: center; justify-content: flex-start; padding: 20px; box-sizing: border-box; overflow-y: auto; }
        .captured-image-box { position: relative; width: 100%; max-width: 320px; height: 320px; border-radius: 8px; overflow: hidden; margin-top: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.2); background: #fff; }
        #captured-canvas-preview { width: 100%; height: 100%; object-fit: cover; }
        .ai-bounding-box { position: absolute; left: 10%; top: 15%; width: 80%; height: 75%; border: 3px solid #0056ff; box-sizing: border-box; }
        .ai-label { position: absolute; top: -25px; left: -3px; background: #0056ff; color: white; font-size: 12px; padding: 2px 8px; border-radius: 3px 3px 0 0; white-space: nowrap; font-weight: bold; }
        .instruction-card { width: 100%; max-width: 320px; background: #fff; border: 1px solid #ddd; border-radius: 8px; padding: 15px; margin-top: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.05); box-sizing: border-box; }
        .info-row { margin-bottom: 12px; font-size: 16px; color: #333; font-weight: bold; }
        .type-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; color: white; font-weight: bold; font-size: 14px; margin-left: 5px; }
        .instruction-banner { border-top: 2px solid #eee; padding-top: 15px; margin-top: 15px; display: flex; align-items: center; justify-content: space-between; }
        .bin-text { font-size: 16px; font-weight: bold; color: #222; line-height: 1.4; }
        .bin-icon { width: 45px; height: 55px; border-radius: 5px 5px 2px 2px; position: relative; display: flex; align-items: center; justify-content: center; color: white; font-size: 18px; font-weight: bold; box-shadow: inset 0 -10px rgba(0,0,0,0.2); }
        .bin-icon::before { content: ''; position: absolute; top: -6px; left: 10%; width: 80%; height: 6px; background: inherit; border-radius: 3px 3px 0 0; border-bottom: 1px solid rgba(0,0,0,0.1); }
        .back-home-btn { margin-top: 25px; margin-bottom: 30px; padding: 14px 50px; background-color: #d2ecea; color: #00695c; border: none; border-radius: 25px; font-size: 16px; font-weight: bold; cursor: pointer; box-shadow: 0 3px 6px rgba(0,0,0,0.1); transition: all 0.2s ease; z-index: 50; }
        .back-home-btn:hover { background-color: #b2dfdb; transform: translateY(-1px); }
        .back-home-btn:active { transform: translateY(1px); }
    </style>
</head>
<body>
    <header class="app-bar-new"><a href="home.php" class="back-nav-link"><i class="fa-solid fa-chevron-left"></i> Scan</a></header>
    <div class="camera-container" id="camera-viewport"><div class="scan-wrapper" id="display-area"><div class="scan-line"></div><video id="webcam" autoplay playsinline></video></div></div>
    <div class="controls" id="controls-bar"><div class="scan-circle-btn" onclick="startScanning()"></div></div>
    <div class="result-overlay" id="result-screen">
        <div class="captured-image-box"><canvas id="captured-canvas-preview"></canvas><div class="ai-bounding-box" id="res-bbox"><div class="ai-label" id="res-bbox-label">Detecting...</div></div></div>
        <div class="instruction-card"><div class="info-row">CURRENT Type: <span id="res-type-badge" class="type-badge">-</span></div><div class="instruction-banner"><div class="bin-text">INSTRUCTION:<br><span id="res-instruction-text">-</span></div><div id="res-bin-icon" class="bin-icon">♻</div></div></div>
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
        const configMap = { 'plastic': { label: 'Plastic ', color: '#ff0000', binName: 'RED Bin (Plastic)' }, 'aluminium': { label: 'Aluminium ', color: '#ffcc00', binName: 'YELLOW Bin (Aluminium)' }, 'paper': { label: 'Paper ', color: '#0056ff', binName: 'BLUE Bin (Paper)' } };
        async function startCamera() { try { const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } }); video.srcObject = stream; } catch (err) { console.error("The camera cannot be accessed: ", err); } }
        function startScanning() {
            displayArea.classList.add('scanning');
            previewCanvas.width = video.videoWidth || 640; previewCanvas.height = video.videoHeight || 480;
            const ctx = previewCanvas.getContext('2d');
            try { ctx.drawImage(video, 0, 0, previewCanvas.width, previewCanvas.height); } catch(e) { ctx.fillStyle = "#ccc"; ctx.fillRect(0, 0, previewCanvas.width, previewCanvas.height); }
            previewCanvas.toBlob(async function(blob) {
                const formData = new FormData(); formData.append('waste_image', blob, 'webcam_capture.jpg');
                try {
                    const response = await fetch('scan.php', { method: 'POST', body: formData });
                    const result = await response.json();
                    setTimeout(() => {
                        displayArea.classList.remove('scanning');
                        if (result.status === 'success' || result.status === 'db_error') {
                            if (result.status === 'db_error') { console.warn("Database sync warning: " + result.message); }
                            renderResultData(result.prediction, result.box);
                            resultScreen.style.display = 'flex'; controlsBar.style.display = 'none';
                        } else { alert("AI Engine response error: " + result.message); displayArea.classList.remove('scanning'); }
                    }, 1500);
                } catch (error) { displayArea.classList.remove('scanning'); console.error("AJAX Fetch failed:", error); alert("Cannot reach the cloud backend server. Please verify your internet connection."); }
            }, 'image/jpeg', 0.9);
        }
        function renderResultData(type, boxCoordinates) {
            const config = configMap[type]; if (!config) return;
            resBBox.style.borderColor = config.color; resBBox.style.top = boxCoordinates[0] + "%"; resBBox.style.left = boxCoordinates[1] + "%"; resBBox.style.height = boxCoordinates[2] + "%"; resBBox.style.width = boxCoordinates[3] + "%";
            resBBoxLabel.style.backgroundColor = config.color; resBBoxLabel.textContent = config.label;
            resTypeBadge.textContent = type.toUpperCase(); resTypeBadge.style.backgroundColor = config.color;
            resInstructionText.innerHTML = `[Throw in <span style="color:${config.color}">${config.binName.split(' ')[0]}</span> Bin<br>(${type.charAt(0).toUpperCase() + type.slice(1)})]`; resBinIcon.style.backgroundColor = config.color;
        }
        function goToHomepage() { window.location.href = "home.php"; }
        startCamera();
    </script>
</body>
</html>
