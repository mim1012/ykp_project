<?php
/**
 * Laravel APP_KEY 생성 후 Railway 환경변수 설정 안내
 */

echo "<h1>🔑 Railway APP_KEY 설정 가이드</h1>";

// Laravel APP_KEY 생성
$key = 'base64:' . base64_encode(random_bytes(32));

echo "<div style='background: #f0f8f0; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h2>✅ 생성된 APP_KEY</h2>";
echo "<p><strong>다음 값을 복사하세요:</strong></p>";
echo "<code style='background: white; padding: 10px; display: block; font-size: 14px; border: 1px solid #ddd;'>$key</code>";
echo "</div>";

echo "<div style='background: #fff8e1; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h2>🚀 Railway 설정 방법</h2>";
echo "<ol>";
echo "<li><strong>Railway 대시보드</strong>로 이동</li>";
echo "<li>프로젝트 → <strong>Variables</strong> 탭 클릭</li>";
echo "<li><strong>New Variable</strong> 버튼 클릭</li>";
echo "<li>Name: <code>APP_KEY</code></li>";
echo "<li>Value: 위에서 복사한 키 붙여넣기</li>";
echo "<li><strong>Add</strong> 클릭</li>";
echo "<li><strong>Deploy</strong> 버튼으로 재배포</li>";
echo "</ol>";
echo "</div>";

echo "<div style='background: #e3f2fd; padding: 20px; border-radius: 8px; margin: 20px 0;'>";
echo "<h2>📝 추가로 설정할 환경변수들</h2>";
echo "<p>Railway 환경변수에 다음도 추가하세요:</p>";
echo "<ul>";
echo "<li><code>APP_ENV=production</code></li>";
echo "<li><code>APP_DEBUG=false</code></li>";
echo "<li><code>DATABASE_URL=your_supabase_connection_string</code></li>";
echo "</ul>";
echo "</div>";

echo "<hr>";
echo "<p>🔄 Railway 재배포 완료 후: <a href='/'>메인 사이트 테스트</a></p>";
echo "<p>🔍 현재 환경변수 확인: <a href='/env-check.php'>env-check.php</a></p>";

echo "<hr><small>Generated at: " . date('Y-m-d H:i:s T') . "</small>";
?>