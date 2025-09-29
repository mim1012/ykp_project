<?php

// Supabase 연결 정보
$host = 'aws-1-ap-southeast-1.pooler.supabase.com';
$port = 5432;
$database = 'postgres';
$username = 'postgres.qwafwqxdcfpqqwpmphkm';
$password = 'rlawlgns2233@';

// 백업 파일 경로
$backupFile = 'C:\Users\PC_1M\Downloads\db_cluster-28-09-2025@17-09-30.backup';

// PostgreSQL 연결 문자열
$connectionString = "host=$host port=$port dbname=$database user=$username password=$password sslmode=require";

echo "Supabase 데이터베이스 복원을 시작합니다...\n\n";

echo "=== 복원 정보 ===\n";
echo "호스트: $host\n";
echo "데이터베이스: $database\n";
echo "백업 파일: $backupFile\n";
echo "백업 파일 크기: " . number_format(filesize($backupFile) / 1024, 2) . " KB\n\n";

echo "⚠️ 주의사항:\n";
echo "1. Supabase 대시보드에서 직접 복원하는 것이 가장 안전합니다.\n";
echo "2. https://supabase.com 로그인 후\n";
echo "3. Database → Backups → Restore from file 선택\n";
echo "4. 백업 파일을 업로드하세요.\n\n";

echo "또는 PostgreSQL 클라이언트가 설치된 환경에서:\n";
echo "psql \"$connectionString\" < \"$backupFile\"\n\n";

// 기본 테이블 복원 확인 (sales 테이블만 체크)
try {
    $pdo = new PDO("pgsql:$connectionString");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "✅ 데이터베이스 연결 성공!\n\n";

    // 현재 테이블 확인
    $stmt = $pdo->query("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "현재 public 스키마의 테이블 목록:\n";
    foreach ($tables as $table) {
        echo "  - $table\n";
    }

    // sales 테이블 레코드 수 확인
    if (in_array('sales', $tables)) {
        $count = $pdo->query("SELECT COUNT(*) FROM sales")->fetchColumn();
        echo "\n📊 sales 테이블 레코드 수: $count\n";
    } else {
        echo "\n⚠️ sales 테이블이 없습니다!\n";
    }

} catch (PDOException $e) {
    echo "❌ 데이터베이스 연결 실패: " . $e->getMessage() . "\n";
}

echo "\n=== 수동 복원 가이드 ===\n";
echo "1. PostgreSQL 클라이언트 설치 (https://www.postgresql.org/download/windows/)\n";
echo "2. 명령 프롬프트에서 실행:\n";
echo "   psql -h $host -p $port -U $username -d $database < \"$backupFile\"\n";
echo "3. 비밀번호 입력: $password\n";