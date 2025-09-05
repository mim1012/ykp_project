<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>🏪 매장 관리 - YKP ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <!-- 헤더 -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-gray-900">🏪 매장 관리</h1>
                    <span class="ml-2 px-2 py-1 text-xs bg-green-100 text-green-800 rounded">본사 전용</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/management/branches" class="text-blue-600 hover:text-blue-900">지사 관리</a>
                    <a href="/admin/accounts" class="text-purple-600 hover:text-purple-900">계정 관리</a>
                    <a href="/dashboard" class="text-gray-600 hover:text-gray-900">대시보드</a>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- 매장 통계 -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-medium mb-4">📊 매장 현황</h2>
            <div class="grid grid-cols-3 gap-4">
                <div class="bg-green-50 p-4 rounded text-center">
                    <div class="text-2xl font-bold text-green-600" id="total-stores">-</div>
                    <div class="text-sm text-gray-600">전체 매장</div>
                </div>
                <div class="bg-blue-50 p-4 rounded text-center">
                    <div class="text-2xl font-bold text-blue-600" id="stores-with-accounts">-</div>
                    <div class="text-sm text-gray-600">계정 생성 완료</div>
                </div>
                <div class="bg-orange-50 p-4 rounded text-center">
                    <div class="text-2xl font-bold text-orange-600" id="total-branches">-</div>
                    <div class="text-sm text-gray-600">소속 지사 수</div>
                </div>
            </div>
        </div>

        <!-- 매장 목록 (지사별 구조화) -->
        <div id="stores-container">
            <div class="text-center py-8">
                <div class="text-gray-500">매장 목록 로딩 중...</div>
            </div>
        </div>
    </main>

    <script>
        // 페이지 로드 시 매장 목록 바로 로드
        document.addEventListener('DOMContentLoaded', function() {
            loadStoresGrouped();
        });

        // 지사별 매장 목록 로드
        function loadStoresGrouped() {
            console.log('매장 목록 로딩 시작...');
            
            fetch('/test-api/stores')
                .then(response => {
                    console.log('매장 API 응답:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('매장 데이터:', data);
                    
                    if (data.success && data.data) {
                        renderStoresByBranch(data.data);
                        updateStoreStatistics(data.data);
                    } else {
                        showError('매장 목록을 불러올 수 없습니다.');
                    }
                })
                .catch(error => {
                    console.error('매장 로딩 오류:', error);
                    showError('매장 목록 로드 중 오류가 발생했습니다.');
                });
        }

        // 지사별 매장 렌더링
        function renderStoresByBranch(stores) {
            const container = document.getElementById('stores-container');
            
            // 지사별 그룹화
            const storesByBranch = {};
            stores.forEach(store => {
                const branchName = store.branch?.name || '미배정 매장';
                if (!storesByBranch[branchName]) {
                    storesByBranch[branchName] = [];
                }
                storesByBranch[branchName].push(store);
            });
            
            console.log('지사별 그룹화:', Object.keys(storesByBranch));
            
            let html = '<div class="space-y-6">';
            
            Object.entries(storesByBranch).forEach(([branchName, branchStores]) => {
                html += `
                    <div class="bg-white rounded-lg shadow">
                        <div class="px-6 py-4 border-b border-gray-200 bg-blue-50">
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg font-medium text-gray-900">
                                    🏢 ${branchName} (${branchStores.length}개 매장)
                                </h3>
                                <button onclick="addStoreForBranch('${branchName}')" 
                                        class="bg-green-500 text-white px-3 py-1 text-sm rounded hover:bg-green-600">
                                    ➕ 매장 추가
                                </button>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                `;
                
                branchStores.forEach(store => {
                    html += `
                        <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                            <div class="flex justify-between items-start mb-3">
                                <h4 class="text-base font-semibold text-gray-900">${store.name}</h4>
                                <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">운영중</span>
                            </div>
                            <div class="space-y-2 text-sm text-gray-600">
                                <div>👤 <strong>점주:</strong> ${store.owner_name || store.manager_name || '미등록'}</div>
                                <div>📞 <strong>연락처:</strong> ${store.phone || store.contact_number || '-'}</div>
                                <div>🏷️ <strong>코드:</strong> ${store.code || '-'}</div>
                            </div>
                            <div class="mt-4 flex space-x-2">
                                <button onclick="editStore(${store.id})" 
                                        class="text-xs bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600">
                                    ✏️ 수정
                                </button>
                                <button onclick="createAccount(${store.id})" 
                                        class="text-xs bg-green-500 text-white px-2 py-1 rounded hover:bg-green-600">
                                    👤 계정생성
                                </button>
                                <button onclick="viewStats(${store.id})" 
                                        class="text-xs bg-purple-500 text-white px-2 py-1 rounded hover:bg-purple-600">
                                    📊 성과
                                </button>
                            </div>
                        </div>
                    `;
                });
                
                html += '</div></div></div>'; // grid, p-6, bg-white 닫기
            });
            
            html += '</div>'; // space-y-6 닫기
            
            container.innerHTML = html;
            console.log('매장 렌더링 완료');
        }

        // 통계 업데이트
        function updateStoreStatistics(stores) {
            const total = stores.length;
            const withAccounts = stores.filter(s => s.has_account).length; // 임시
            const branches = [...new Set(stores.map(s => s.branch?.name).filter(Boolean))].length;

            document.getElementById('total-stores').textContent = total;
            document.getElementById('stores-with-accounts').textContent = withAccounts;
            document.getElementById('total-branches').textContent = branches;
        }

        // 에러 표시
        function showError(message) {
            document.getElementById('stores-container').innerHTML = `
                <div class="bg-white rounded-lg shadow p-8 text-center text-red-500">
                    ❌ ${message}
                </div>
            `;
        }

        // 임시 함수들 (2차 구현 예정)
        function editStore(storeId) {
            alert('매장 수정 기능은 2차 Release에서 구현 예정입니다.');
        }

        function createAccount(storeId) {
            alert('계정 생성 기능은 현재 계정 관리 대시보드에서 확인하세요.');
        }

        function viewStats(storeId) {
            alert('매장 통계 기능은 2차 Release에서 구현 예정입니다.');
        }

        function addStoreForBranch(branchName) {
            alert(`${branchName}에 새 매장을 추가하는 기능은 2차 Release에서 구현 예정입니다.`);
        }
    </script>
</body>
</html>