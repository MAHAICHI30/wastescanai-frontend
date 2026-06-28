<?php
// WasteScan AI - Welcome Page
// This PHP file serves as the landing page for the WasteScan AI application.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle = "WasteScan AI - Welcome";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title><?php echo $pageTitle; ?></title>
    <style>
        /* -------------------------------
            RESET & BASE STYLES 
        ------------------------------- */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #f4f6f8;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 16px;
        }

        /* Mobile-first container: mimics a realistic phone/app card */
        .app-container {
            max-width: 400px;
            width: 100%;
            background: #ffffff;
            border-radius: 40px;
            box-shadow: 0 25px 45px -12px rgba(0, 0, 0, 0.2);
            overflow: hidden;
            padding: 40px 28px 35px 28px;
            transition: all 0.2s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        /* 🌟 1. Welcome 标题提升到最顶部，并收紧了底部间距 */
        .welcome-heading {
            font-size: 44px;
            font-weight: 500;
            letter-spacing: -0.3px;
            text-align: center;
            color: #0a1c2f;
            margin: 10px 0 35px 0;
            font-family: inherit;
        }

        /* 🌟 2. Logo 容器：确保图片在卡片垂直正中央绝对居中 */
        .logo-wrapper {
            display: flex;
            justify-content: center;
            align-items: center;
            flex: 1;
            width: 100%;
            margin: 20px 0 45px 0;
        }

        .logo {
            max-width: 220px;
            width: 100%;
            height: auto;
            display: block;
            object-fit: contain;
        }

        /* 底部操作区控制 */
        .action-block {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            width: 100%;
        }

        /* 基础按钮样式 */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            text-align: center;
            cursor: pointer;
            border: none;
            background: none;
        }

        /* 🌟 3. 精致微调版的 Skip 按钮：字号下调到 15px，缩减边距使其小巧精致 */
        .btn-primary {
            background: #5ce1e6;
            color: #081c2c;
            padding: 9px 38px; /* 缩减了上下与横向内边距，让按钮更加秀气 */
            font-weight: 600;
            font-size: 15px;    /* 字号微调小一号 */
            border-radius: 40px;
            box-shadow: 0 3px 6px rgba(92, 225, 230, 0.15);
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background: #49cdd2;
            transform: translateY(-1px);
            box-shadow: 0 6px 14px rgba(92, 225, 230, 0.2);
        }

        .btn-primary:active {
            transform: scale(0.97);
            background: #3cbcbf;
        }

        /* 自适应屏幕适配 */
        @media (max-width: 450px) {
            .app-container {
                padding: 30px 20px 30px 20px;
            }
            .welcome-heading {
                font-size: 38px;
                margin: 5px 0 25px 0;
            }
            .logo-wrapper {
                margin: 15px 0 35px 0;
            }
        }

        /* 无障碍焦点框 */
        .btn:focus-visible {
            outline: 2px solid #0a6b6f;
            outline-offset: 2px;
        }
    </style>
</head>
<body>
<div class="app-container">
    <div class="welcome-heading">Welcome</div>

    <div class="logo-wrapper">
        <img src="WasteScan_AI-removebg-preview.png" alt="WasteScan AI logo" class="logo">
    </div>

    <div class="action-block">
        <a href="login.php" class="btn btn-primary">Skip</a>
    </div>
</div>
</body>
</html>
