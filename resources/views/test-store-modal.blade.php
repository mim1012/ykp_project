<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>매장 생성 테스트</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 p-8">
    <div class="max-w-4xl mx-auto">
        <h1 class="text-2xl font-bold mb-6">매장 생성 및 계정 모달 테스트</h1>

        @auth
        <div class="bg-green-100 p-4 rounded mb-4">
            ✅ 로그인됨: {{ auth()->user()->email }} ({{ auth()->user()->role }})
        </div>

        <button onclick="testStoreCreation()" class="bg-blue-500 text-white px-6 py-3 rounded hover:bg-blue-600">
            매장 생성 테스트
        </button>
        @else
        <div class="bg-red-100 p-4 rounded">
            ❌ 로그인이 필요합니다. <a href="/login" class="text-blue-500 underline">로그인 페이지로 이동</a>
        </div>
        @endauth
    </div>

    <script>
        async function testStoreCreation() {
            const testData = {
                branch_id: 17, // 이원영 지역장
                name: '테스트 매장 ' + new Date().toLocaleTimeString(),
                owner_name: '최사장',
                phone: '010-' + Math.floor(Math.random() * 9000 + 1000) + '-' + Math.floor(Math.random() * 9000 + 1000),
                address: '테스트 주소'
            };

            console.log('Sending request with data:', testData);

            try {
                const response = await fetch('/api/stores', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify(testData)
                });

                const result = await response.json();
                console.log('Response:', result);

                if (result.success) {
                    if (result.account) {
                        showAccountModal(result.account, result.data);
                    } else {
                        alert('매장이 생성되었지만 계정 정보가 없습니다.');
                    }
                } else {
                    alert('오류: ' + (result.error || '알 수 없는 오류'));
                }
            } catch (error) {
                console.error('Error:', error);
                alert('요청 중 오류 발생: ' + error.message);
            }
        }

        function showAccountModal(account, store) {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            modal.innerHTML = `
                <div class="bg-white p-8 rounded-xl max-w-lg w-full mx-4 shadow-2xl">
                    <div class="text-center mb-6">
                        <div class="text-6xl mb-4">🎉</div>
                        <h3 class="text-2xl font-bold text-green-600 mb-2">매장과 계정이 생성되었습니다!</h3>
                    </div>

                    <div class="space-y-4 bg-gray-50 p-6 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <span class="text-2xl">📍</span>
                            <div>
                                <span class="font-semibold">매장:</span>
                                <span class="ml-2 font-bold text-blue-600">${store.name} (${store.code})</span>
                            </div>
                        </div>

                        <div class="flex items-center space-x-3">
                            <span class="text-2xl">👤</span>
                            <div class="flex-1">
                                <span class="font-semibold">이메일:</span>
                                <div class="mt-1">
                                    <code class="bg-white px-3 py-2 rounded border text-blue-600 font-mono block">${account.email}</code>
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center space-x-3">
                            <span class="text-2xl">🔑</span>
                            <div class="flex-1">
                                <span class="font-semibold">비밀번호:</span>
                                <div class="mt-1">
                                    <code class="bg-white px-3 py-2 rounded border text-green-600 font-mono block">${account.password}</code>
                                </div>
                            </div>
                        </div>

                        <div class="mt-6 p-4 bg-orange-50 rounded-lg border-l-4 border-orange-400">
                            <p class="text-orange-800 font-semibold">⚠️ 중요 안내</p>
                            <p class="text-orange-700 text-sm mt-1">이 정보는 지금만 표시됩니다. 반드시 안전한 곳에 저장하세요!</p>
                        </div>
                    </div>

                    <button onclick="this.closest('.fixed').remove()" class="mt-6 w-full px-8 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 font-semibold">
                        ✅ 확인
                    </button>
                </div>
            `;
            document.body.appendChild(modal);
        }
    </script>
</body>
</html>