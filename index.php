<?php
// 获取请求路径
$request = $_SERVER['REQUEST_URI'];
$path = parse_url($request, PHP_URL_PATH);
$path = trim($path, '/');

// 如果访问根目录，跳转到 login.php
if (empty($path)) {
    $path = 'welcome.php';
}

// 检查文件是否存在
if (file_exists($path) && !is_dir($path)) {
    if (pathinfo($path, PATHINFO_EXTENSION) === 'php') {
        include $path;
    } else {
        readfile($path);
    }
} else {
    http_response_code(404);
    echo "Page not found";
}
