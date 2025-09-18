<!DOCTYPE html>
<html>
<head>
    <title>간단 로그인 - YKP</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: Arial; padding: 50px; background: #f5f5f5;">
    <div style="max-width: 400px; margin: 0 auto; background: white; padding: 30px; border-radius: 8px;">
        <h2>🔐 YKP 간단 로그인</h2>
        
        <!-- 테스트 계정 정보 제거됨 (보안상 이유) -->
        <div style="margin: 20px 0;">
            <p style="color: #666;">관리자에게 계정 정보를 문의하세요.</p>
        </div>

        <hr style="margin: 20px 0;">
        
        <form method="GET" action="/quick-login/headquarters">
            <button type="submit" style="width: 100%; padding: 15px; background: #007bff; color: white; border: none; border-radius: 4px; font-size: 16px; cursor: pointer;">
                🚀 본사 대시보드로 바로 가기
            </button>
        </form>
        
        <p style="text-align: center; margin-top: 15px; color: #666; font-size: 12px;">
            개발/테스트용 - CSRF 우회 로그인
        </p>
    </div>
</body>
</html>