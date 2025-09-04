<?php
/**
 * DB에 있는 사용자 계정들 확인
 */

echo "<h1>👥 현재 DB 계정 확인</h1>";

try {
    // 직접 PDO 연결
    $dbUrl = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
    if (!$dbUrl) {
        echo "<p>❌ DATABASE_URL이 없습니다.</p>";
        exit;
    }
    
    $parsed = parse_url($dbUrl);
    $host = $parsed['host'] ?? '';
    $port = $parsed['port'] ?? 6543;
    $dbname = isset($parsed['path']) ? ltrim($parsed['path'], '/') : 'postgres';
    $username = $parsed['user'] ?? '';
    $password = $parsed['pass'] ?? '';
    
    echo "<p>🔌 연결 정보: $host:$port/$dbname</p>";
    
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    echo "<p>✅ 데이터베이스 연결 성공</p>";
    
    // 모든 사용자 조회
    echo "<h2>📋 현재 사용자 목록</h2>";
    $stmt = $pdo->query("SELECT id, name, email, role, store_id, branch_id, created_at FROM users ORDER BY id");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table style='border-collapse: collapse; width: 100%;'>";
    echo "<tr style='background: #f8f9fa;'>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>ID</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>이름</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>이메일</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>역할</th>";
    echo "<th style='border: 1px solid #ddd; padding: 8px;'>소속</th>";
    echo "</tr>";
    
    foreach ($users as $user) {
        $bgColor = '';
        if ($user['role'] === 'headquarters') $bgColor = 'background: #e8f5e8;';
        if ($user['role'] === 'branch') $bgColor = 'background: #e3f2fd;';
        if ($user['role'] === 'store') $bgColor = 'background: #fff3e0;';
        
        echo "<tr style='$bgColor'>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$user['id']}</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>{$user['name']}</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px; font-family: monospace;'>{$user['email']}</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>";
        
        switch ($user['role']) {
            case 'headquarters': echo "🏢 본사"; break;
            case 'branch': echo "🏬 지사"; break; 
            case 'store': echo "🏪 매장"; break;
            default: echo $user['role']; break;
        }
        
        echo "</td>";
        echo "<td style='border: 1px solid #ddd; padding: 8px;'>매장:{$user['store_id']} / 지사:{$user['branch_id']}</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    echo "<h2>📊 데이터 요약</h2>";
    $summary = $pdo->query("
        SELECT 
            role,
            COUNT(*) as count
        FROM users 
        GROUP BY role 
        ORDER BY role
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($summary as $role) {
        echo "<p>📍 {$role['role']}: {$role['count']}명</p>";
    }
    
    // 기본 계정 정보 표시
    echo "<h2>🔐 로그인 테스트용 기본 계정</h2>";
    echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px;'>";
    echo "<h3>🏢 본사 관리자</h3>";
    echo "<p>이메일: <code>admin@ykp.com</code></p>";
    echo "<p>비밀번호: <code>password</code> 또는 <code>admin123!</code></p>";
    echo "</div>";
    
    echo "<p><a href='/login' style='background: #007bff; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>로그인 페이지로 이동</a></p>";
    
} catch (Exception $e) {
    echo "<p>❌ 에러: " . $e->getMessage() . "</p>";
}
?>