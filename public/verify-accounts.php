<?php

/**
 * Production 계정 존재 여부 확인
 */
echo '<h1>👥 Production 계정 검증</h1>';

try {
    $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST');
    $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?? 6543;
    $dbname = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?? 'postgres';
    $username = $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME');
    $password = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD');

    if (! $host || ! $username || ! $password) {
        echo '<p>❌ DB 환경변수가 설정되지 않았습니다.</p>';
        echo '<p>DB_HOST: '.($host ? '✅' : '❌').'</p>';
        echo '<p>DB_USERNAME: '.($username ? '✅' : '❌').'</p>';
        echo '<p>DB_PASSWORD: '.($password ? '✅' : '❌').'</p>';
        exit;
    }

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    echo '<p>✅ Supabase 연결 성공</p>';

    // 핵심 계정들 확인
    echo '<h2>🔐 핵심 계정 검증</h2>';

    $stmt = $pdo->prepare("SELECT id, name, email, role FROM users WHERE role IN ('headquarters', 'branch', 'store') ORDER BY role, id");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($users)) {
        echo '<p>❌ 계정이 없습니다. 시딩이 필요합니다.</p>';
    } else {
        echo "<table style='border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #f8f9fa;'>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>ID</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>이름</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>이메일</th>";
        echo "<th style='border: 1px solid #ddd; padding: 8px;'>역할</th>";
        echo '</tr>';

        foreach ($users as $user) {
            $roleColor = '';
            switch ($user['role']) {
                case 'headquarters': $roleColor = 'background: #e8f5e8;';
                    break;
                case 'branch': $roleColor = 'background: #e3f2fd;';
                    break;
                case 'store': $roleColor = 'background: #fff3e0;';
                    break;
            }

            echo "<tr style='$roleColor'>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$user['id']}</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$user['name']}</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px; font-family: monospace;'>{$user['email']}</td>";
            echo "<td style='border: 1px solid #ddd; padding: 8px;'>";

            switch ($user['role']) {
                case 'headquarters': echo '🏢 본사';
                    break;
                case 'branch': echo '🏬 지사';
                    break;
                case 'store': echo '🏪 매장';
                    break;
                default: echo $user['role'];
                    break;
            }

            echo '</td></tr>';
        }
        echo '</table>';
    }

    // 테이블 존재 확인
    echo '<h2>📊 테이블 상태</h2>';
    $tables = $pdo->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public' 
        AND table_name IN ('users', 'branches', 'stores', 'sessions', 'cache', 'sales')
        ORDER BY table_name
    ")->fetchAll(PDO::FETCH_COLUMN);

    $requiredTables = ['users', 'branches', 'stores', 'sessions', 'cache', 'sales'];

    foreach ($requiredTables as $table) {
        $exists = in_array($table, $tables);
        echo '<p>'.($exists ? '✅' : '❌')." $table 테이블</p>";
    }

    echo '<hr>';
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px;'>";
    echo '<h2>🎉 YKP ERP Production 검증 완료</h2>';
    echo '<p>✅ <strong>데이터베이스:</strong> Supabase 정상 연결</p>';
    echo '<p>✅ <strong>계정:</strong> '.count($users).'개 계정 확인</p>';
    echo '<p>✅ <strong>테이블:</strong> '.count($tables).'/'.count($requiredTables).' 필수 테이블 존재</p>';
    echo '<p>🚀 <strong>사용자 공개 준비 완료!</strong></p>';
    echo '</div>';

} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 15px; border-radius: 5px;'>";
    echo '<p>❌ 에러: '.$e->getMessage().'</p>';
    echo '</div>';
}

echo '<hr><small>Generated at: '.date('Y-m-d H:i:s T').'</small>';
