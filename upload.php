<?php
// =========================================================================
// WasteScan AI - Gallery Upload Gateway (Pure Interface Control Flow)
// =========================================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🔒 安全守卫拦截：确保未登录用户不能伪造数据流转
if (!isset($_SESSION['username'])) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'User is not logged in. Danger alert.']);
    exit;
}
$username = $_SESSION['username']; 

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['waste_image'])) {
    
    ob_start();
    header('Content-Type: application/json');
    
    $upload_dir = 'upload/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $file_name = time() . '_' . basename($_FILES['waste_image']['name']);
    $target_file = $upload_dir . $file_name;

    if (move_uploaded_file($_FILES['waste_image']['tmp_name'], $target_file)) {
        
        // 🌟 自动化定位 Railway 内部高速网络中的 Python 后端服务
        $flask_url = getenv('AI_URL') ?: ($_SERVER['AI_URL'] ?: ($_ENV['AI_URL'] ?? 'http://wastescanai-backend.railway.internal:8080/predict'));
        
        $cFile = new CURLFile(realpath($target_file));
        
        // 🌟【隔离核心修复】：动态获取前端表单身份，默认为 gallery_upload
        $identity = isset($_POST['identity']) ? $_POST['identity'] : 'gallery_upload';
        
        $post_data = array(
            'image' => $cFile,
            'username' => $username,
            'identity' => $identity
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $flask_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        // 🌟 注入超长响应超时机制，保证高负载或权重首次加载不会引发崩溃
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60); 
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);        
        
        $response = curl_exec($ch);
        curl_close($ch);

        $result_data = json_decode($response, true);
        
        ob_end_clean();

        // 🌟【数据完全解耦】：交由本地 +8 时区的 Python 自动执行入库，PHP 只管呈现
        if (isset($result_data['status']) && $result_data['status'] == 'success') {
            echo json_encode([
                'status' => 'success',
                'prediction' => strtolower($result_data['prediction']),
                'box' => isset($result_data['box']) ? $result_data['box'] : [15, 15, 70, 70],
                'image_path' => $target_file,
                'username' => $username 
            ]);
        } else {
            echo json_encode(['status' => 'error', 'message' => 'AI Server processing failed or database sync error inside backend.']);
        }
    } else {
        ob_end_clean();
        echo json_encode(['status' => 'error', 'message' => 'Failed to save uploaded gallery image on PHP server.']);
    }
    exit;
}
?>
