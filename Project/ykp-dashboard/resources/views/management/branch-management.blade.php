<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>🏢 지사 관리 - YKP ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <!-- 헤더 -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-gray-900">🏢 지사 관리</h1>
                    <span class="ml-2 px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded">본사 전용</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/management/stores" class="text-blue-600 hover:text-blue-900">매장 관리</a>
                    <a href="/admin/accounts" class="text-purple-600 hover:text-purple-900">계정 관리</a>
                    <a href="/dashboard" class="text-gray-600 hover:text-gray-900">대시보드</a>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- 지사 통계 -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-medium mb-4">📊 지사 현황</h2>
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-blue-50 p-4 rounded text-center">
                    <div class="text-2xl font-bold text-blue-600" id="total-branches">-</div>
                    <div class="text-sm text-gray-600">전체 지사</div>
                </div>
                <div class="bg-green-50 p-4 rounded text-center">
                    <div class="text-2xl font-bold text-green-600" id="active-branches">-</div>
                    <div class="text-sm text-gray-600">✅ 운영중</div>
                </div>
                <div class="bg-orange-50 p-4 rounded text-center">
                    <div class="text-2xl font-bold text-orange-600" id="stores-count">-</div>
                    <div class="text-sm text-gray-600">총 매장 수</div>
                </div>
            </div>
        </div>

        <!-- 지사 추가 -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-lg font-medium">➕ 새 지사 추가</h2>
                <button onclick="showAddForm()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                    새 지사 등록
                </button>
            </div>
            
            <div id="add-form" class="hidden border-t pt-4">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">지사명</label>
                        <input type="text" id="branch-name" placeholder="예: 대구지사" 
                               class="w-full border border-gray-300 rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">지사코드</label>
                        <input type="text" id="branch-code" placeholder="예: DG001" 
                               class="w-full border border-gray-300 rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">관리자명</label>
                        <input type="text" id="manager-name" placeholder="예: 김지사장" 
                               class="w-full border border-gray-300 rounded px-3 py-2">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">연락처</label>
                        <input type="tel" id="contact-phone" placeholder="053-123-4567" 
                               class="w-full border border-gray-300 rounded px-3 py-2">
                    </div>
                </div>
                
                <div class="mt-4 flex space-x-3">
                    <button onclick="submitBranch()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        ✅ 지사 추가
                    </button>
                    <button onclick="hideAddForm()" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">
                        취소
                    </button>
                </div>
                
                <div class="mt-3 bg-blue-50 p-3 rounded text-sm text-blue-800">
                    <strong>📝 자동 생성:</strong> 지사 관리자 계정이 자동으로 생성됩니다<br>
                    <strong>이메일:</strong> branch_{지사코드}@ykp.com<br>
                    <strong>비밀번호:</strong> 123456
                </div>
            </div>
        </div>

        <!-- 지사 목록 -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium">🏢 지사 목록</h3>
                <p class="text-sm text-gray-500">모든 지사의 상태와 매장 현황을 확인할 수 있습니다.</p>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">지사명</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">코드</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">관리자</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">매장 수</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">🔑 로그인 정보</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">관리</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="branches-tbody">
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                지사 목록 로딩 중...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        // 페이지 로드 시 지사 목록 바로 로드
        document.addEventListener('DOMContentLoaded', function() {
            loadBranches();
        });

        // 지사 목록 로드 (간단 버전)
        function loadBranches() {
            console.log('지사 목록 로딩 시작...');
            
            fetch('/test-api/branches')
                .then(response => {
                    console.log('지사 API 응답:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('지사 데이터:', data);
                    
                    if (data.success) {
                        renderBranches(data.data);
                        updateStatistics(data.data);
                    } else {
                        showError('지사 목록을 불러올 수 없습니다.');
                    }
                })
                .catch(error => {
                    console.error('지사 로딩 오류:', error);
                    showError('지사 목록 로드 중 오류가 발생했습니다.');
                });
        }

        // 지사 목록 렌더링
        function renderBranches(branches) {
            const tbody = document.getElementById('branches-tbody');
            
            if (!branches || branches.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                            등록된 지사가 없습니다.
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = branches.map(branch => `
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">${branch.name}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <code class="text-sm bg-gray-100 px-2 py-1 rounded">${branch.code}</code>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${branch.manager_name || '미등록'}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="text-sm font-medium text-gray-900">${branch.stores_count || 0}개</span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900 font-mono">branch_${branch.code.toLowerCase()}@ykp.com</div>
                        <div class="text-xs text-blue-600">비밀번호: 123456</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm space-x-2">
                        <button onclick="editBranch(${branch.id})" 
                                class="text-blue-600 hover:text-blue-900">✏️ 수정</button>
                        <button onclick="manageBranchStores(${branch.id})" 
                                class="text-green-600 hover:text-green-900">🏪 매장관리</button>
                        <button onclick="viewBranchStats(${branch.id})" 
                                class="text-purple-600 hover:text-purple-900">📊 통계</button>
                    </td>
                </tr>
            `).join('');
            
            console.log('지사 목록 렌더링 완료:', branches.length, '개');
        }

        // 통계 업데이트
        function updateStatistics(branches) {
            const total = branches.length;
            const active = branches.filter(b => b.status === 'active' || !b.status).length;
            const totalStores = branches.reduce((sum, b) => sum + (b.stores_count || 0), 0);

            document.getElementById('total-branches').textContent = total;
            document.getElementById('active-branches').textContent = active;
            document.getElementById('stores-count').textContent = totalStores;
        }

        // 에러 표시
        function showError(message) {
            document.getElementById('branches-tbody').innerHTML = `
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-red-500">
                        ❌ ${message}
                    </td>
                </tr>
            `;
        }

        // 지사 추가 폼 표시/숨김
        function showAddForm() {
            document.getElementById('add-form').classList.remove('hidden');
        }

        function hideAddForm() {
            document.getElementById('add-form').classList.add('hidden');
            // 폼 초기화
            document.getElementById('branch-name').value = '';
            document.getElementById('branch-code').value = '';
            document.getElementById('manager-name').value = '';
            document.getElementById('contact-phone').value = '';
        }

        // 지사 추가
        function submitBranch() {
            const name = document.getElementById('branch-name').value.trim();
            const code = document.getElementById('branch-code').value.trim();
            const manager = document.getElementById('manager-name').value.trim();
            const phone = document.getElementById('contact-phone').value.trim();

            if (!name || !code || !manager) {
                alert('지사명, 지사코드, 관리자명은 필수 입력입니다.');
                return;
            }

            const branchData = {
                name: name,
                code: code,
                manager_name: manager,
                contact_number: phone
            };

            fetch('/test-api/branches/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(branchData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`✅ 지사가 성공적으로 추가되었습니다!\n\n📧 관리자 계정: branch_${code.toLowerCase()}@ykp.com\n🔑 비밀번호: 123456\n\n이 정보를 ${manager}님에게 전달하세요.`);
                    hideAddForm();
                    loadBranches(); // 목록 새로고침
                } else {
                    alert('❌ 지사 추가 실패: ' + (data.error || '알 수 없는 오류'));
                }
            })
            .catch(error => {
                console.error('지사 추가 오류:', error);
                alert('지사 추가 중 오류가 발생했습니다.');
            });
        }

        // 지사별 매장 관리로 이동
        function manageBranchStores(branchId) {
            window.location.href = `/management/stores?branch=${branchId}`;
        }

        // 지사 통계 (추후 구현)
        function viewBranchStats(branchId) {
            alert('지사 통계 기능은 2차 Release에서 구현 예정입니다.');
        }

        // 지사 수정 (추후 구현)
        function editBranch(branchId) {
            alert('지사 수정 기능은 2차 Release에서 구현 예정입니다.');
        }
    </script>
</body>
</html>