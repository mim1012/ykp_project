<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>매장 추가 및 계정 관리 - YKP ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendardvariable/dist/web/variable/pretendardvariable.css" rel="stylesheet">
    <style>
        .modal-overlay { background: rgba(0, 0, 0, 0.5); }
        .fade-in { animation: fadeIn 0.3s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
    </style>
</head>
<body class="bg-gray-50 font-pretendard">
    <!-- 헤더 -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-gray-900">매장 추가 및 계정 관리</h1>
                    <span class="ml-2 px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded">1순위 기능</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/dashboard" class="text-gray-600 hover:text-gray-900">대시보드</a>
                    <button onclick="location.reload()" class="text-gray-600 hover:text-gray-900">새로고침</button>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto py-6 px-4">
        <!-- 빠른 작업 카드 -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-6 rounded-lg shadow-lg cursor-pointer hover:shadow-xl transition-all" onclick="openStoreModal()">
                <div class="flex items-center">
                    <div class="text-3xl mr-4">🏪</div>
                    <div>
                        <h3 class="text-lg font-semibold">새 매장 추가</h3>
                        <p class="text-blue-100 text-sm">지사 선택 후 매장 정보 입력</p>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-r from-green-500 to-green-600 text-white p-6 rounded-lg shadow-lg cursor-pointer hover:shadow-xl transition-all" onclick="openBulkAccountModal()">
                <div class="flex items-center">
                    <div class="text-3xl mr-4">👥</div>
                    <div>
                        <h3 class="text-lg font-semibold">일괄 계정 생성</h3>
                        <p class="text-green-100 text-sm">여러 매장의 계정을 한번에</p>
                    </div>
                </div>
            </div>

            <div class="bg-gradient-to-r from-purple-500 to-purple-600 text-white p-6 rounded-lg shadow-lg cursor-pointer hover:shadow-xl transition-all" onclick="showStatistics()">
                <div class="flex items-center">
                    <div class="text-3xl mr-4">📊</div>
                    <div>
                        <h3 class="text-lg font-semibold">현황 보기</h3>
                        <p class="text-purple-100 text-sm">매장 및 계정 현황</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- 현황 통계 -->
        <div class="bg-white rounded-lg shadow mb-6" id="statistics-panel" style="display: none;">
            <div class="p-6">
                <h2 class="text-lg font-semibold mb-4">📊 매장 및 계정 현황</h2>
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600" id="total-stores">-</div>
                        <div class="text-sm text-gray-600">총 매장 수</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600" id="total-accounts">-</div>
                        <div class="text-sm text-gray-600">총 계정 수</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-purple-600" id="active-stores">-</div>
                        <div class="text-sm text-gray-600">활성 매장</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-orange-600" id="total-branches">-</div>
                        <div class="text-sm text-gray-600">총 지사 수</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 매장 목록 -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-medium">매장 목록</h2>
                    <div class="flex space-x-2">
                        <select id="branch-filter" class="border rounded px-3 py-1 text-sm">
                            <option value="">모든 지사</option>
                        </select>
                        <button onclick="loadStores()" class="bg-gray-100 text-gray-700 px-3 py-1 rounded text-sm hover:bg-gray-200">
                            새로고침
                        </button>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full" id="stores-table">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">매장명</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">지사</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">매장코드</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">사장님</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">연락처</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">상태</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">계정</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">작업</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="stores-tbody">
                            <!-- 동적으로 로드됨 -->
                        </tbody>
                    </table>
                </div>

                <div id="loading" class="text-center py-8" style="display: none;">
                    <div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
                    <p class="mt-2 text-gray-600">매장 목록을 불러오는 중...</p>
                </div>

                <div id="no-data" class="text-center py-8" style="display: none;">
                    <p class="text-gray-500">매장 데이터가 없습니다.</p>
                </div>
            </div>
        </div>
    </main>

    <!-- 매장 추가 모달 -->
    <div id="store-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold">새 매장 추가</h3>
            </div>
            <form id="store-form">
                <div class="px-6 py-4 space-y-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">지사 선택 *</label>
                        <select id="store-branch-id" required class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">지사를 선택하세요</option>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">매장명 *</label>
                        <input type="text" id="store-name" required class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="예: 강남점">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">사장님 성함</label>
                        <input type="text" id="store-owner-name" class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="사장님 성함">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">연락처</label>
                        <input type="tel" id="store-phone" class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="010-1234-5678">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">주소</label>
                        <input type="text" id="store-address" class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="매장 주소">
                    </div>

                    <div class="border-t pt-4">
                        <div class="flex items-center">
                            <input type="checkbox" id="create-account" class="mr-2">
                            <label for="create-account" class="text-sm font-medium text-gray-700">매장 관리자 계정도 함께 생성</label>
                        </div>
                    </div>

                    <div id="account-fields" class="space-y-3 pl-4 border-l-2 border-gray-200" style="display: none;">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">관리자 이름</label>
                            <input type="text" id="account-name" class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="관리자 이름">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">이메일</label>
                            <input type="email" id="account-email" class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="manager@store.com">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">비밀번호</label>
                            <input type="password" id="account-password" class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500" placeholder="6자리 이상">
                        </div>
                    </div>
                </div>
                
                <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                    <button type="button" onclick="closeStoreModal()" class="px-4 py-2 text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
                        취소
                    </button>
                    <button type="submit" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                        매장 추가
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- 일괄 계정 생성 모달 -->
    <div id="bulk-account-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold">일괄 계정 생성</h3>
            </div>
            <div class="px-6 py-4">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">계정이 없는 매장 목록</label>
                    <div id="stores-without-accounts" class="max-h-60 overflow-y-auto border rounded">
                        <!-- 동적으로 로드됨 -->
                    </div>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">기본 비밀번호</label>
                    <input type="password" id="bulk-password" value="123456" class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                <button type="button" onclick="closeBulkAccountModal()" class="px-4 py-2 text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
                    취소
                </button>
                <button type="button" onclick="createBulkAccounts()" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
                    선택한 매장 계정 생성
                </button>
            </div>
        </div>
    </div>

    <!-- 메시지 토스트 -->
    <div id="toast" class="fixed top-4 right-4 z-50" style="display: none;">
        <div class="bg-white border border-gray-200 rounded-lg shadow-lg p-4 min-w-64">
            <div class="flex items-center">
                <div id="toast-icon" class="mr-3"></div>
                <div id="toast-message" class="text-sm font-medium"></div>
            </div>
        </div>
    </div>

    <script>
        // 전역 변수
        let branches = [];
        let stores = [];
        
        // 페이지 로드시 초기화
        document.addEventListener('DOMContentLoaded', function() {
            loadBranches();
            loadStores();
            setupEventListeners();
        });

        // 이벤트 리스너 설정
        function setupEventListeners() {
            // 계정 생성 체크박스
            document.getElementById('create-account').addEventListener('change', function() {
                const accountFields = document.getElementById('account-fields');
                accountFields.style.display = this.checked ? 'block' : 'none';
                
                if (this.checked) {
                    // 자동으로 이메일 생성
                    const storeName = document.getElementById('store-name').value;
                    if (storeName) {
                        generateDefaultEmail(storeName);
                    }
                }
            });

            // 매장명 입력시 이메일 자동 생성
            document.getElementById('store-name').addEventListener('input', function() {
                if (document.getElementById('create-account').checked) {
                    generateDefaultEmail(this.value);
                }
            });

            // 지사 필터 변경
            document.getElementById('branch-filter').addEventListener('change', function() {
                filterStoresByBranch(this.value);
            });

            // 매장 추가 폼 제출
            document.getElementById('store-form').addEventListener('submit', function(e) {
                e.preventDefault();
                addStore();
            });
        }

        // 지사 목록 로드
        async function loadBranches() {
            try {
                const response = await fetch('/test-api/branches');
                const result = await response.json();
                
                if (result.success) {
                    branches = result.data;
                    populateBranchSelects();
                }
            } catch (error) {
                showToast('지사 목록 로드 실패', 'error');
            }
        }

        // 지사 선택박스 채우기
        function populateBranchSelects() {
            const storeSelect = document.getElementById('store-branch-id');
            const filterSelect = document.getElementById('branch-filter');
            
            // 기존 옵션 제거
            storeSelect.innerHTML = '<option value="">지사를 선택하세요</option>';
            filterSelect.innerHTML = '<option value="">모든 지사</option>';
            
            branches.forEach(branch => {
                storeSelect.innerHTML += `<option value="${branch.id}">${branch.name} (${branch.code})</option>`;
                filterSelect.innerHTML += `<option value="${branch.id}">${branch.name}</option>`;
            });
        }

        // 매장 목록 로드
        async function loadStores() {
            document.getElementById('loading').style.display = 'block';
            document.getElementById('stores-tbody').innerHTML = '';
            
            try {
                const response = await fetch('/test-api/stores');
                const result = await response.json();
                
                if (result.success) {
                    stores = result.data;
                    renderStoresTable(stores);
                    updateStatistics();
                } else {
                    document.getElementById('no-data').style.display = 'block';
                }
            } catch (error) {
                showToast('매장 목록 로드 실패', 'error');
                document.getElementById('no-data').style.display = 'block';
            }
            
            document.getElementById('loading').style.display = 'none';
        }

        // 매장 테이블 렌더링
        function renderStoresTable(storeData) {
            const tbody = document.getElementById('stores-tbody');
            tbody.innerHTML = '';

            storeData.forEach(store => {
                const row = document.createElement('tr');
                row.className = 'hover:bg-gray-50';
                
                const statusBadge = store.status === 'active' 
                    ? '<span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">운영중</span>'
                    : '<span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded">중단</span>';
                
                const branchName = store.branch ? store.branch.name : '미분류';
                
                row.innerHTML = `
                    <td class="px-4 py-3 font-medium text-gray-900">${store.name}</td>
                    <td class="px-4 py-3 text-gray-600">${branchName}</td>
                    <td class="px-4 py-3 text-gray-600 font-mono">${store.code}</td>
                    <td class="px-4 py-3 text-gray-600">${store.owner_name || '-'}</td>
                    <td class="px-4 py-3 text-gray-600">${store.phone || '-'}</td>
                    <td class="px-4 py-3">${statusBadge}</td>
                    <td class="px-4 py-3">
                        <button onclick="checkStoreAccount(${store.id})" class="text-blue-600 hover:text-blue-800 text-sm">
                            계정 확인
                        </button>
                    </td>
                    <td class="px-4 py-3">
                        <div class="flex space-x-2">
                            <button onclick="createStoreAccount(${store.id})" class="text-green-600 hover:text-green-800 text-sm">
                                계정생성
                            </button>
                            <button onclick="editStore(${store.id})" class="text-blue-600 hover:text-blue-800 text-sm">
                                수정
                            </button>
                        </div>
                    </td>
                `;
                
                tbody.appendChild(row);
            });
        }

        // 지사별 매장 필터링
        function filterStoresByBranch(branchId) {
            const filteredStores = branchId ? 
                stores.filter(store => store.branch_id == branchId) : stores;
            renderStoresTable(filteredStores);
        }

        // 통계 업데이트
        function updateStatistics() {
            document.getElementById('total-stores').textContent = stores.length;
            document.getElementById('active-stores').textContent = stores.filter(s => s.status === 'active').length;
            document.getElementById('total-branches').textContent = branches.length;
        }

        // 매장 추가 모달 열기
        function openStoreModal() {
            document.getElementById('store-modal').classList.remove('hidden');
            document.getElementById('store-modal').classList.add('flex', 'fade-in');
            document.getElementById('store-form').reset();
            document.getElementById('account-fields').style.display = 'none';
        }

        // 매장 추가 모달 닫기
        function closeStoreModal() {
            document.getElementById('store-modal').classList.add('hidden');
            document.getElementById('store-modal').classList.remove('flex', 'fade-in');
        }

        // 이메일 자동 생성
        function generateDefaultEmail(storeName) {
            if (!storeName) return;
            
            const cleanName = storeName.replace(/[^가-힣a-zA-Z0-9]/g, '').toLowerCase();
            const email = `${cleanName}@ykp.com`;
            document.getElementById('account-email').value = email;
        }

        // 매장 추가
        async function addStore() {
            const formData = {
                name: document.getElementById('store-name').value,
                branch_id: document.getElementById('store-branch-id').value,
                owner_name: document.getElementById('store-owner-name').value,
                phone: document.getElementById('store-phone').value,
                address: document.getElementById('store-address').value
            };

            try {
                const response = await fetch('/test-api/stores/add', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(formData)
                });

                const result = await response.json();

                if (result.success) {
                    showToast('매장이 성공적으로 추가되었습니다!', 'success');
                    
                    // 계정도 함께 생성하는 경우
                    if (document.getElementById('create-account').checked) {
                        await createAccountForStore(result.data.id);
                    }
                    
                    closeStoreModal();
                    loadStores();
                } else {
                    showToast(result.error || '매장 추가에 실패했습니다', 'error');
                }
            } catch (error) {
                showToast('네트워크 오류가 발생했습니다', 'error');
            }
        }

        // 매장 계정 생성
        async function createAccountForStore(storeId) {
            const accountData = {
                name: document.getElementById('account-name').value,
                email: document.getElementById('account-email').value,
                password: document.getElementById('account-password').value
            };

            try {
                const response = await fetch(`/test-api/stores/${storeId}/create-user`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(accountData)
                });

                const result = await response.json();

                if (result.success) {
                    showToast('매장 계정도 생성되었습니다!', 'success');
                } else {
                    showToast('매장 계정 생성 실패: ' + result.error, 'warning');
                }
            } catch (error) {
                showToast('계정 생성 중 오류 발생', 'warning');
            }
        }

        // 매장 계정 직접 생성
        function createStoreAccount(storeId) {
            const store = stores.find(s => s.id === storeId);
            if (!store) return;

            const name = prompt('관리자 이름을 입력하세요:', store.owner_name || store.name + ' 관리자');
            if (!name) return;

            const email = prompt('이메일을 입력하세요:', `${store.name.replace(/[^가-힣a-zA-Z0-9]/g, '').toLowerCase()}@ykp.com`);
            if (!email) return;

            const password = prompt('비밀번호를 입력하세요 (6자리 이상):', '123456');
            if (!password || password.length < 6) {
                showToast('비밀번호는 6자리 이상이어야 합니다', 'error');
                return;
            }

            createAccount(storeId, { name, email, password });
        }

        // 계정 생성 API 호출
        async function createAccount(storeId, accountData) {
            try {
                const response = await fetch(`/test-api/stores/${storeId}/create-user`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(accountData)
                });

                const result = await response.json();

                if (result.success) {
                    showToast('계정이 성공적으로 생성되었습니다!', 'success');
                } else {
                    showToast(result.error || '계정 생성에 실패했습니다', 'error');
                }
            } catch (error) {
                showToast('네트워크 오류가 발생했습니다', 'error');
            }
        }

        // 일괄 계정 생성 모달 열기
        function openBulkAccountModal() {
            document.getElementById('bulk-account-modal').classList.remove('hidden');
            document.getElementById('bulk-account-modal').classList.add('flex', 'fade-in');
            loadStoresWithoutAccounts();
        }

        // 일괄 계정 생성 모달 닫기
        function closeBulkAccountModal() {
            document.getElementById('bulk-account-modal').classList.add('hidden');
            document.getElementById('bulk-account-modal').classList.remove('flex', 'fade-in');
        }

        // 계정이 없는 매장 목록 로드
        function loadStoresWithoutAccounts() {
            const container = document.getElementById('stores-without-accounts');
            container.innerHTML = stores.map(store => `
                <label class="flex items-center p-3 hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" class="mr-3" value="${store.id}">
                    <div>
                        <div class="font-medium">${store.name}</div>
                        <div class="text-sm text-gray-500">${store.branch ? store.branch.name : '미분류'} - ${store.code}</div>
                    </div>
                </label>
            `).join('');
        }

        // 일괄 계정 생성
        function createBulkAccounts() {
            const checkboxes = document.querySelectorAll('#stores-without-accounts input[type="checkbox"]:checked');
            const password = document.getElementById('bulk-password').value;

            if (checkboxes.length === 0) {
                showToast('매장을 선택해주세요', 'warning');
                return;
            }

            if (!password || password.length < 6) {
                showToast('비밀번호는 6자리 이상이어야 합니다', 'error');
                return;
            }

            let created = 0;
            let failed = 0;

            checkboxes.forEach(async (checkbox) => {
                const storeId = checkbox.value;
                const store = stores.find(s => s.id == storeId);
                
                if (store) {
                    const accountData = {
                        name: store.owner_name || `${store.name} 관리자`,
                        email: `${store.name.replace(/[^가-힣a-zA-Z0-9]/g, '').toLowerCase()}@ykp.com`,
                        password: password
                    };

                    try {
                        const response = await fetch(`/test-api/stores/${storeId}/create-user`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify(accountData)
                        });

                        const result = await response.json();
                        if (result.success) {
                            created++;
                        } else {
                            failed++;
                        }
                    } catch (error) {
                        failed++;
                    }
                }
            });

            setTimeout(() => {
                closeBulkAccountModal();
                showToast(`${created}개 계정 생성 완료, ${failed}개 실패`, created > 0 ? 'success' : 'warning');
            }, 1000);
        }

        // 통계 보기
        function showStatistics() {
            const panel = document.getElementById('statistics-panel');
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
            
            if (panel.style.display === 'block') {
                loadAccountCount();
            }
        }

        // 계정 수 로드
        async function loadAccountCount() {
            try {
                const response = await fetch('/test-api/users/count');
                const result = await response.json();
                
                if (result.success) {
                    document.getElementById('total-accounts').textContent = result.count;
                }
            } catch (error) {
                document.getElementById('total-accounts').textContent = '?';
            }
        }

        // 토스트 메시지 표시
        function showToast(message, type = 'info') {
            const toast = document.getElementById('toast');
            const icon = document.getElementById('toast-icon');
            const messageEl = document.getElementById('toast-message');
            
            // 아이콘 설정
            const icons = {
                success: '✅',
                error: '❌',
                warning: '⚠️',
                info: 'ℹ️'
            };
            
            icon.textContent = icons[type] || icons.info;
            messageEl.textContent = message;
            
            // 토스트 색상 설정
            const colors = {
                success: 'border-green-200 bg-green-50',
                error: 'border-red-200 bg-red-50',
                warning: 'border-yellow-200 bg-yellow-50',
                info: 'border-blue-200 bg-blue-50'
            };
            
            toast.firstElementChild.className = `bg-white border rounded-lg shadow-lg p-4 min-w-64 ${colors[type] || colors.info}`;
            toast.style.display = 'block';
            
            // 3초 후 자동 숨김
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3000);
        }

        // 매장 수정 (간단한 구현)
        function editStore(storeId) {
            showToast('매장 수정 기능은 준비 중입니다', 'info');
        }

        // 매장 계정 확인
        function checkStoreAccount(storeId) {
            showToast('계정 확인 기능은 준비 중입니다', 'info');
        }
    </script>
</body>
</html>