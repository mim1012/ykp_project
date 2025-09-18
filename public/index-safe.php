<?php
// Temporary safe index for debugging
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <title>YKP Dashboard - Maintenance</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            margin: 0;
        }
        .container {
            text-align: center;
            padding: 2rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }
        h1 { margin-bottom: 0.5rem; }
        p { opacity: 0.9; }
        .info {
            margin-top: 2rem;
            font-size: 0.9rem;
            opacity: 0.7;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>YKP Dashboard</h1>
        <p>시스템 점검 중입니다 / System Under Maintenance</p>
        <div class="info">
            PHP <?php echo PHP_VERSION; ?><br>
            Server: <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?><br>
            Time: <?php echo date('Y-m-d H:i:s'); ?>
        </div>
    </div>
</body>
</html>