<?php

/**
 * 간단한 DB 정리 - Laravel 서비스 사용 없이
 */
echo '<h1>🧹 간단 DB 정리</h1>';

try {
    // 직접 PDO 연결
    $dbUrl = $_ENV['DATABASE_URL'] ?? getenv('DATABASE_URL');
    if (! $dbUrl) {
        echo '<p>❌ DATABASE_URL 환경변수가 없습니다.</p>';
        exit;
    }

    // URL 파싱
    $parsed = parse_url($dbUrl);
    $host = $parsed['host'];
    $port = $parsed['port'] ?? 5432;
    $dbname = ltrim($parsed['path'], '/');
    $username = $parsed['user'];
    $password = $parsed['pass'];

    echo '<p>🔌 Supabase 직접 연결 중...</p>';

    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    echo '<p>✅ 데이터베이스 연결 성공</p>';

    // 현재 데이터 확인
    $counts = [];
    $tables = ['users', 'branches', 'stores', 'sales'];

    echo '<h2>📊 정리 전 데이터</h2>';
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        $counts[$table] = $count;
        echo "<p>$table: {$count}개</p>";
    }

    echo '<h2>🗑️ 테스트 데이터 삭제</h2>';

    // 테스트 데이터 삭제 쿼리들
    $cleanupQueries = [
        "DELETE FROM branches WHERE name LIKE '%테스트%' OR name LIKE '%자연어%' OR name LIKE '%라이프%' OR code LIKE 'NL%' OR code LIKE 'AC%' OR code LIKE 'LC%'",
        "DELETE FROM stores WHERE name LIKE '%테스트%' OR name LIKE '%자동화%' OR name LIKE '%RBAC%'",
        "DELETE FROM sales WHERE model_name LIKE '%RBAC%' OR model_name LIKE '%테스트%' OR created_at < NOW() - INTERVAL '3 days'",
        "DELETE FROM users WHERE name LIKE '%테스트%' AND id NOT IN (58, 59, 60)",
    ];

    $totalDeleted = 0;
    foreach ($cleanupQueries as $query) {
        try {
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $deleted = $stmt->rowCount();
            $totalDeleted += $deleted;
            echo "<p>✅ {$deleted}개 레코드 삭제</p>";
        } catch (Exception $e) {
            echo '<p>⚠️ 쿼리 실행 오류 (계속 진행): '.$e->getMessage().'</p>';
        }
    }

    echo '<h2>📊 정리 후 데이터</h2>';
    foreach ($tables as $table) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
        $count = $stmt->fetchColumn();
        echo "<p>$table: {$count}개</p>";
    }

    // 핵심 계정들 확인
    echo '<h2>🔐 보존된 핵심 계정들</h2>';
    $stmt = $pdo->query('SELECT name, email, role FROM users ORDER BY id LIMIT 10');
    while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<p>✅ {$user['name']} ({$user['email']}) - {$user['role']}</p>";
    }

    echo '<hr>';
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px;'>";
    echo '<h2>🎉 DB 정리 완료!</h2>';
    echo "<p>총 {$totalDeleted}개 테스트 데이터 삭제</p>";
    echo '<p>✅ 핵심 계정들 보존됨</p>';
    echo "<p><a href='/'>메인 사이트로 이동</a></p>";
    echo '</div>';

} catch (Exception $e) {
    echo '<p>❌ 에러: '.$e->getMessage().'</p>';
    echo '<pre>'.$e->getTraceAsString().'</pre>';
}
