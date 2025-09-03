<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>YKP ERP - 수동 로그인</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center">
        <div class="max-w-md w-full bg-white rounded-lg shadow-md p-6">
            <h2 class="text-2xl font-bold text-center mb-6">🔧 수동 DB 테스트</h2>
            
            <div class="space-y-4">
                <button onclick="checkDB()" class="w-full bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                    📊 DB 계정 확인
                </button>
                
                <button onclick="testLogin()" class="w-full bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                    🔐 로그인 테스트
                </button>
                
                <button onclick="cleanupDB()" class="w-full bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">
                    🗑️ DB 정리 실행
                </button>
            </div>
            
            <div id="result" class="mt-6 p-4 bg-gray-50 rounded min-h-[100px]">
                결과가 여기에 표시됩니다...
            </div>
        </div>
    </div>

    <script>
        async function checkDB() {
            const result = document.getElementById('result');
            result.innerHTML = '📊 DB 계정 확인 중...';
            
            try {
                const response = await fetch('/check-users.php');
                const html = await response.text();
                result.innerHTML = html;
            } catch (error) {
                result.innerHTML = `❌ 에러: ${error.message}`;
            }
        }
        
        async function testLogin() {
            const result = document.getElementById('result');
            result.innerHTML = '🔐 로그인 테스트 중...';
            
            try {
                // admin@ykp.com으로 로그인 시도
                const response = await fetch('/api/login', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify({
                        email: 'admin@ykp.com',
                        password: 'password'
                    })
                });
                
                const data = await response.json();
                
                if (response.ok) {
                    result.innerHTML = `✅ 로그인 성공!<br>사용자: ${data.user?.name || '알 수 없음'}`;
                } else {
                    result.innerHTML = `❌ 로그인 실패 (${response.status}): ${data.message || '알 수 없는 오류'}`;
                }
            } catch (error) {
                result.innerHTML = `❌ 로그인 에러: ${error.message}`;
            }
        }
        
        async function cleanupDB() {
            const result = document.getElementById('result');
            result.innerHTML = '🗑️ DB 정리 중...';
            
            try {
                const response = await fetch('/simple-cleanup.php');
                const html = await response.text();
                result.innerHTML = html;
            } catch (error) {
                result.innerHTML = `❌ 에러: ${error.message}`;
            }
        }
    </script>
</body>
</html>