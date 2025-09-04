<?php
/**
 * sessions 테이블 수동 생성
 */

echo "<h1>🔧 Sessions 테이블 생성</h1>";

try {
    // 직접 PDO 연결
    $host = $_ENV['DB_HOST'] ?? getenv('DB_HOST');
    $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?? 6543;
    $dbname = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE') ?? 'postgres';
    $username = $_ENV['DB_USERNAME'] ?? getenv('DB_USERNAME');
    $password = $_ENV['DB_PASSWORD'] ?? getenv('DB_PASSWORD');
    
    echo "<p>🔌 DB 연결 중... ($host:$port/$dbname)</p>";
    
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";
    $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    
    echo "<p>✅ Supabase 연결 성공</p>";
    
    // sessions 테이블 생성 SQL
    $createSessionsTable = "
    CREATE TABLE IF NOT EXISTS sessions (
        id VARCHAR(255) NOT NULL PRIMARY KEY,
        user_id BIGINT NULL,
        ip_address VARCHAR(45) NULL,
        user_agent TEXT NULL,
        payload LONGTEXT NOT NULL,
        last_activity INT NOT NULL
    );
    
    CREATE INDEX IF NOT EXISTS sessions_user_id_index ON sessions (user_id);
    CREATE INDEX IF NOT EXISTS sessions_last_activity_index ON sessions (last_activity);
    ";
    
    echo "<h2>📝 sessions 테이블 생성 중...</h2>";
    $pdo->exec($createSessionsTable);
    echo "<p>✅ sessions 테이블 생성 완료</p>";
    
    // cache 테이블도 생성
    $createCacheTable = "
    CREATE TABLE IF NOT EXISTS cache (
        key VARCHAR(255) NOT NULL PRIMARY KEY,
        value MEDIUMTEXT NOT NULL,
        expiration INT NOT NULL
    );
    
    CREATE INDEX IF NOT EXISTS cache_expiration_index ON cache (expiration);
    ";
    
    echo "<h2>📝 cache 테이블 생성 중...</h2>";
    $pdo->exec($createCacheTable);
    echo "<p>✅ cache 테이블 생성 완료</p>";
    
    // 생성된 테이블 확인
    $tables = $pdo->query("
        SELECT table_name 
        FROM information_schema.tables 
        WHERE table_schema = 'public' 
        ORDER BY table_name
    ")->fetchAll(PDO::FETCH_COLUMN);
    
    echo "<h2>📊 현재 테이블 목록</h2>";
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>📄 $table</li>";
    }
    echo "</ul>";
    
    echo "<hr>";
    echo "<div style='background: #d4edda; padding: 20px; border-radius: 8px;'>";
    echo "<h2>🎉 필수 테이블 생성 완료!</h2>";
    echo "<p><strong>이제 Laravel이 정상 작동할 것입니다.</strong></p>";
    echo "<p><a href='/' style='background: #28a745; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; font-weight: bold;'>메인 사이트로 이동</a></p>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<p>❌ 에러: " . $e->getMessage() . "</p>";
}
?>