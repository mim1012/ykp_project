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
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-6 rounded-lg shadow-lg cursor-pointer hover:shadow-xl transition-all" onclick="console.log('카드 클릭됨'); if(typeof openStoreModal === 'function') { openStoreModal(); } else { alert('openStoreModal 함수를 찾을 수 없습니다'); }">
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

            <div class="bg-gradient-to-r from-orange-500 to-red-500 text-white p-6 rounded-lg shadow-lg cursor-pointer hover:shadow-xl transition-all" onclick="goToBranchManagement()">
                <div class="flex items-center">
                    <div class="text-3xl mr-4">🏢</div>
                    <div>
                        <h3 class="text-lg font-semibold">지사 관리</h3>
                        <p class="text-orange-100 text-sm">지사 추가 및 관리자 계정</p>
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
                        <button onclick="openStoreModal()" class="bg-blue-500 text-white px-4 py-1 rounded text-sm hover:bg-blue-600">
                            ➕ 매장 추가
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
                    <div class="text-gray-400 mb-4">
                        <svg class="mx-auto w-16 h-16" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 3a1 1 0 011 1v5h5a1 1 0 110 2h-5v5a1 1 0 11-2 0v-5H4a1 1 0 110-2h5V4a1 1 0 011-1z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                    <p class="text-gray-500 mb-2">아직 등록된 매장이 없습니다</p>
                    <button onclick="openStoreModal()" class="text-blue-600 hover:text-blue-800 underline">첫 번째 매장을 추가해보세요</button>
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
                    @if(auth()->user()->role === 'headquarters')
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">지사 선택 *</label>
                        <select id="store-branch-id" required class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">지사를 선택하세요</option>
                        </select>
                    </div>
                    @elseif(auth()->user()->role === 'branch')
                    <!-- 지사 계정: 자동 지정 (드롭다운 숨김) -->
                    <div class="mb-4 p-3 bg-blue-50 border border-blue-200 rounded">
                        <div class="flex items-center">
                            <span class="text-blue-600 mr-2">🏢</span>
                            <span class="font-medium text-blue-800">{{ auth()->user()->branch->name ?? '소속 지사' }}</span>
                            <span class="ml-2 text-blue-600 text-sm">(자동 지정)</span>
                        </div>
                    </div>
                    <input type="hidden" id="store-branch-id" value="{{ auth()->user()->branch_id }}" required>
                    @endif
                    
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

                    @if(auth()->user()->role === 'headquarters')
                    <div class="border-t pt-4">
                        <div class="flex items-center">
                            <input type="checkbox" id="create-account" class="mr-2">
                            <label for="create-account" class="text-sm font-medium text-gray-700">매장 관리자 계정도 함께 생성</label>
                        </div>
                    </div>

                    <div id="account-fields" class="space-y-3 pl-4 border-l-2 border-gray-200" style="display: none;">
                    @else
                    <!-- 지사 계정: 매장 관리자 계정 항상 생성 -->
                    <div class="border-t pt-4">
                        <div class="bg-blue-50 border border-blue-200 rounded p-3">
                            <div class="flex items-center">
                                <div class="text-blue-500 mr-2">ℹ️</div>
                                <span class="text-sm font-medium text-blue-800">매장 관리자 계정이 자동으로 생성됩니다</span>
                            </div>
                        </div>
                        <input type="hidden" id="create-account" value="true">
                    </div>

                    <div id="account-fields" class="space-y-3 pl-4 border-l-2 border-blue-200">
                    @endif
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

    <!-- 계정 관리 모달 -->
    <div id="account-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold" id="account-modal-title">매장 계정 관리</h3>
            </div>
            <div class="px-6 py-4">
                <!-- 매장 정보 -->
                <div class="mb-4 p-4 bg-gray-50 rounded">
                    <h4 class="font-medium text-gray-800 mb-2">매장 정보</h4>
                    <div class="grid grid-cols-2 gap-2 text-sm">
                        <div><span class="text-gray-500">매장명:</span> <span id="account-store-name">-</span></div>
                        <div><span class="text-gray-500">매장코드:</span> <span id="account-store-code">-</span></div>
                        <div><span class="text-gray-500">지사:</span> <span id="account-branch-name">-</span></div>
                        <div><span class="text-gray-500">사장님:</span> <span id="account-owner-name">-</span></div>
                    </div>
                </div>

                <!-- 계정 정보 -->
                <div id="account-info-section">
                    <h4 class="font-medium text-gray-800 mb-3">계정 정보</h4>
                    <div id="account-exists" style="display: none;">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">관리자 이름</label>
                                <input type="text" id="edit-account-name" class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">이메일</label>
                                <input type="email" id="edit-account-email" class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">새 비밀번호 (변경 시에만)</label>
                                <input type="password" id="edit-account-password" class="w-full border border-gray-300 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="새 비밀번호 입력">
                            </div>
                            <div class="flex items-center">
                                <span class="text-sm text-gray-500">생성일: </span>
                                <span id="account-created-date" class="text-sm text-gray-700 ml-1">-</span>
                            </div>
                        </div>
                    </div>
                    <div id="account-not-exists" style="display: none;">
                        <div class="text-center py-4">
                            <div class="text-gray-400 mb-2">
                                <svg class="mx-auto w-12 h-12" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3z"></path>
                                </svg>
                            </div>
                            <p class="text-gray-600 mb-3">이 매장에는 아직 계정이 없습니다</p>
                            <button onclick="createAccountForStore()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                                계정 생성하기
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                <button type="button" onclick="closeAccountModal()" class="px-4 py-2 text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
                    닫기
                </button>
                <button type="button" id="save-account-btn" onclick="saveAccountChanges()" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600" style="display: none;">
                    변경사항 저장
                </button>
                <button type="button" id="reset-password-btn" onclick="resetAccountPassword()" class="px-4 py-2 bg-orange-500 text-white rounded hover:bg-orange-600" style="display: none;">
                    비밀번호 리셋
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
            console.log('페이지 로드 완료');
            
            // 전역 함수들을 window에 명시적으로 등록
            window.openStoreModal = openStoreModal;
            window.closeStoreModal = closeStoreModal;
            window.loadStores = loadStores;
            window.addStore = addStore;
            window.openBulkAccountModal = openBulkAccountModal;
            window.closeBulkAccountModal = closeBulkAccountModal;
            window.createBulkAccounts = createBulkAccounts;
            window.showStatistics = showStatistics;
            window.createStoreAccount = createStoreAccount;
            window.editStore = editStore;
            window.checkStoreAccount = checkStoreAccount;
            window.openAccountModal = openAccountModal;
            window.closeAccountModal = closeAccountModal;
            window.saveAccountChanges = saveAccountChanges;
            window.resetAccountPassword = resetAccountPassword;
            window.createAccountForStore = createAccountForStore;
            window.deleteStore = deleteStore;
            window.generateDeleteButton = generateDeleteButton;
            window.goToBranchManagement = goToBranchManagement;
            
            loadBranches();
            loadStores();
            setupEventListeners();
            
            // 5초 후 테스트 버튼 추가
            setTimeout(() => {
                addTestButton();
            }, 5000);
        });

        // 테스트 버튼 추가 (디버그용)
        function addTestButton() {
            const testButton = document.createElement('button');
            testButton.innerText = '🔧 테스트 모달';
            testButton.className = 'fixed bottom-4 right-4 bg-red-500 text-white px-4 py-2 rounded z-50';
            testButton.onclick = function() {
                console.log('테스트 버튼 클릭됨');
                openStoreModal();
            };
            document.body.appendChild(testButton);
        }

        // 이벤트 리스너 설정
        function setupEventListeners() {
            const userRole = '{{ auth()->user()->role }}';
            const createAccountEl = document.getElementById('create-account');
            
            if (userRole === 'headquarters') {
                // 본사 계정: 체크박스 기능 유지
                createAccountEl.addEventListener('change', function() {
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
            } else if (userRole === 'branch') {
                // 지사 계정: 계정 필드 항상 표시
                const accountFields = document.getElementById('account-fields');
                accountFields.style.display = 'block';
            }

            // 매장명 입력시 이메일 자동 생성
            document.getElementById('store-name').addEventListener('input', function() {
                const createAccountEl = document.getElementById('create-account');
                if (userRole === 'branch' || createAccountEl.checked) {
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

        // 지사 선택박스 채우기 (본사 계정 전용)
        function populateBranchSelects() {
            const userRole = '{{ auth()->user()->role }}';
            
            // 본사 계정만 지사 선택박스 채우기
            if (userRole === 'headquarters') {
                const storeSelect = document.getElementById('store-branch-id');
                const filterSelect = document.getElementById('branch-filter');
                
                if (storeSelect && filterSelect) {
                    storeSelect.innerHTML = '<option value="">지사를 선택하세요</option>';
                    filterSelect.innerHTML = '<option value="">모든 지사</option>';
                    
                    branches.forEach(branch => {
                        storeSelect.innerHTML += `<option value="${branch.id}">${branch.name} (${branch.code})</option>`;
                        filterSelect.innerHTML += `<option value="${branch.id}">${branch.name}</option>`;
                    });
                    
                    console.log('✅ 본사 계정: 전체 지사 목록 로드됨 -', branches.length, '개');
                }
            } else {
                // 지사 계정: hidden input으로 처리됨 (별도 처리 불필요)
                console.log('✅ 지사 계정: 자동 지정 모드 (hidden input 사용)');
                
                // 필터 선택박스만 채우기
                const filterSelect = document.getElementById('branch-filter');
                if (filterSelect && branches.length > 0) {
                    const userBranch = branches.find(b => b.id === {{ auth()->user()->branch_id ?? 'null' }});
                    if (userBranch) {
                        filterSelect.innerHTML = `<option value="">모든 지사</option><option value="${userBranch.id}">${userBranch.name}</option>`;
                    }
                }
            }
        }

        // 매장 목록 로드
        async function loadStores() {
            const loadingEl = document.getElementById('loading');
            const noDataEl = document.getElementById('no-data');
            const tbodyEl = document.getElementById('stores-tbody');
            
            // 로딩 시작
            loadingEl.style.display = 'block';
            noDataEl.style.display = 'none';
            tbodyEl.innerHTML = '';
            
            try {
                const response = await fetch('/api/stores');
                const result = await response.json();
                
                console.log('매장 데이터 응답:', result);
                
                if (result.success && result.data && result.data.length > 0) {
                    stores = result.data;
                    console.log(`${stores.length}개 매장 로드됨`);
                    renderStoresTable(stores);
                    updateStatistics();
                } else {
                    stores = [];
                    console.log('매장 데이터 없음');
                    noDataEl.style.display = 'block';
                }
            } catch (error) {
                console.error('매장 로드 오류:', error);
                showToast('매장 목록 로드 실패: ' + error.message, 'error');
                stores = [];
                noDataEl.style.display = 'block';
            }
            
            // 로딩 완료
            loadingEl.style.display = 'none';
        }

        // 매장 테이블 렌더링
        function renderStoresTable(storeData) {
            const tbody = document.getElementById('stores-tbody');
            tbody.innerHTML = '';

            // 데이터가 있을 때만 렌더링하고 no-data 숨김
            if (storeData && storeData.length > 0) {
                document.getElementById('no-data').style.display = 'none';
                
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
                            ${generateDeleteButton(store.id)}
                        </div>
                    </td>
                `;
                
                tbody.appendChild(row);
                });
            } else {
                // 데이터가 없을 때 no-data 메시지 표시
                document.getElementById('no-data').style.display = 'block';
            }
        }

        // 권한별 삭제 버튼 생성 (본사만 삭제 가능)
        function generateDeleteButton(storeId) {
            const userRole = '{{ auth()->user()->role }}';
            
            if (userRole === 'headquarters') {
                return `<button onclick="deleteStore(${storeId})" class="text-red-600 hover:text-red-800 text-sm">
                    삭제
                </button>`;
            } else {
                return ''; // 지사 계정에서는 삭제 버튼 숨김
            }
        }

        // 매장 삭제 (본사 전용)
        async function deleteStore(storeId) {
            const userRole = '{{ auth()->user()->role }}';
            
            if (userRole !== 'headquarters') {
                showToast('삭제 권한이 없습니다. 본사 관리자만 삭제 가능합니다.', 'error');
                return;
            }
            
            if (!confirm('정말로 이 매장을 삭제하시겠습니까?\n\n삭제된 매장과 관련된 모든 데이터가 삭제됩니다.')) {
                return;
            }
            
            try {
                const response = await fetch(`/test-api/stores/${storeId}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('매장이 삭제되었습니다', 'success');
                    loadStores(); // 목록 새로고침
                } else {
                    showToast('매장 삭제 실패: ' + (result.error || '알 수 없는 오류'), 'error');
                }
                
            } catch (error) {
                console.error('매장 삭제 중 오류:', error);
                showToast('매장 삭제 중 오류가 발생했습니다', 'error');
            }
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
            console.log('openStoreModal 함수 호출됨'); // 디버그 로그
            try {
                const modal = document.getElementById('store-modal');
                if (!modal) {
                    console.error('store-modal 요소를 찾을 수 없습니다');
                    alert('모달 요소를 찾을 수 없습니다. 페이지를 새로고침하세요.');
                    return;
                }
                
                modal.classList.remove('hidden');
                modal.classList.add('flex', 'fade-in');
                
                const form = document.getElementById('store-form');
                if (form) {
                    form.reset();
                }
                
                // 권한별 계정 필드 표시 설정
                const userRole = '{{ auth()->user()->role }}';
                const accountFields = document.getElementById('account-fields');
                if (accountFields) {
                    if (userRole === 'branch') {
                        // 지사 계정: 계정 필드 항상 표시
                        accountFields.style.display = 'block';
                    } else {
                        // 본사 계정: 체크박스에 따라 표시
                        accountFields.style.display = 'none';
                    }
                }
                
                console.log('모달이 성공적으로 열렸습니다');
            } catch (error) {
                console.error('모달 열기 중 오류:', error);
                alert('모달 열기 중 오류가 발생했습니다: ' + error.message);
            }
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
                const response = await fetch('/api/stores', {
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
                    
                    const userRole = '{{ auth()->user()->role }}';
                    
                    // 계정 생성 로직: 본사는 체크박스, 지사는 무조건 생성
                    let shouldCreateAccount = false;
                    if (userRole === 'headquarters') {
                        shouldCreateAccount = document.getElementById('create-account').checked;
                    } else if (userRole === 'branch') {
                        shouldCreateAccount = true; // 지사는 항상 계정 생성
                    }
                    
                    if (shouldCreateAccount) {
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
            const userRole = '{{ auth()->user()->role }}';
            
            let accountData;
            if (userRole === 'headquarters') {
                // 본사: 사용자가 입력한 계정 정보 사용
                accountData = {
                    name: document.getElementById('account-name').value,
                    email: document.getElementById('account-email').value,
                    password: document.getElementById('account-password').value
                };
            } else {
                // 지사: 자동 생성된 계정 정보 사용 (서버에서 생성)
                accountData = {};
            }

            try {
                // 우선 정식 API 엔드포인트 시도
                let response = await fetch(`/api/stores/${storeId}/account`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(accountData)
                });

                // API 엔드포인트 실패 시 fallback
                if (!response.ok && response.status === 404) {
                    console.log('정식 API 실패, fallback으로 test-api 사용');
                    response = await fetch(`/test-api/stores/${storeId}/create-user`, {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify(accountData)
                    });
                }

                const result = await response.json();

                if (result.success) {
                    // 계정 정보가 있으면 모달로 표시
                    if (result.data && result.data.account) {
                        const account = result.data.account;
                        const store = result.data.store;
                        showAccountCreatedModal(account.email, account.password, store);
                    } else {
                        showToast('매장 계정이 생성되었습니다!', 'success');
                    }
                } else {
                    showToast('매장 계정 생성 실패: ' + result.error, 'warning');
                }
            } catch (error) {
                console.error('계정 생성 오류:', error);
                showToast('계정 생성 중 오류 발생', 'warning');
            }
        }

        // 생성된 계정 정보 표시 모달 (PM 요구사항 반영)
        function showAccountCreatedModal(email, password, storeData) {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            modal.innerHTML = `
                <div class="bg-white p-8 rounded-xl max-w-lg w-full mx-4 shadow-2xl">
                    <div class="text-center mb-6">
                        <div class="text-6xl mb-4">🎉</div>
                        <h3 class="text-2xl font-bold text-green-600 mb-2">매장과 매장 계정이 생성되었습니다!</h3>
                    </div>
                    
                    <div class="space-y-4 bg-gray-50 p-6 rounded-lg">
                        <div class="flex items-center space-x-3">
                            <span class="text-2xl">📍</span>
                            <div>
                                <span class="font-semibold text-gray-700">매장명:</span>
                                <span class="ml-2 font-bold text-blue-600">${storeData?.name || '매장'} (${storeData?.code || 'CODE'})</span>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-3">
                            <span class="text-2xl">👤</span>
                            <div class="flex-1">
                                <span class="font-semibold text-gray-700">계정:</span>
                                <div class="flex items-center space-x-2 mt-1">
                                    <code class="bg-white px-3 py-2 rounded border text-blue-600 font-mono flex-1">${email}</code>
                                    <button onclick="copyToClipboard('${email}')" class="px-3 py-2 bg-blue-500 text-white rounded text-sm hover:bg-blue-600">복사</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-3">
                            <span class="text-2xl">🔑</span>
                            <div class="flex-1">
                                <span class="font-semibold text-gray-700">비밀번호:</span>
                                <div class="flex items-center space-x-2 mt-1">
                                    <code class="bg-white px-3 py-2 rounded border text-green-600 font-mono flex-1">${password}</code>
                                    <button onclick="copyToClipboard('${password}')" class="px-3 py-2 bg-green-500 text-white rounded text-sm hover:bg-green-600">복사</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-3 mt-6 p-4 bg-orange-50 rounded-lg border-l-4 border-orange-400">
                            <span class="text-2xl">⚠️</span>
                            <div>
                                <p class="text-orange-800 font-semibold">중요 안내</p>
                                <p class="text-orange-700 text-sm mt-1">이 비밀번호는 최초 로그인 시 반드시 변경하세요.</p>
                                <p class="text-orange-600 text-xs mt-1">💡 이 정보는 1회성으로만 표시됩니다.</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mt-8 text-center">
                        <button onclick="this.closest('.fixed').remove()" class="px-6 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 font-semibold">
                            ✅ 확인완료
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }

        // 클립보드 복사 함수
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                showToast('복사되었습니다!', 'success');
            }).catch(() => {
                showToast('복사에 실패했습니다', 'error');
            });
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

        // 매장 계정 확인 (새로운 전용 API 사용)
        async function checkStoreAccount(storeId) {
            try {
                const response = await fetch(`/test-api/stores/${storeId}/account`);
                const result = await response.json();
                
                if (!result.success) {
                    showToast('매장 계정 정보를 불러올 수 없습니다: ' + (result.error || '알 수 없는 오류'), 'error');
                    return;
                }
                
                const { store, account } = result.data;
                openAccountModal(store, account);
                
            } catch (error) {
                console.error('계정 확인 중 오류:', error);
                showToast('계정 확인 중 오류가 발생했습니다', 'error');
            }
        }

        // 계정 관리 모달 열기
        function openAccountModal(store, account) {
            const modal = document.getElementById('account-modal');
            
            // 매장 정보 표시
            document.getElementById('account-store-name').textContent = store.name;
            document.getElementById('account-store-code').textContent = store.code;
            document.getElementById('account-branch-name').textContent = store.branch?.name || '-';
            document.getElementById('account-owner-name').textContent = store.owner_name || '-';
            
            const accountExists = document.getElementById('account-exists');
            const accountNotExists = document.getElementById('account-not-exists');
            const saveBtn = document.getElementById('save-account-btn');
            const resetBtn = document.getElementById('reset-password-btn');
            
            if (account) {
                // 계정이 있는 경우
                accountExists.style.display = 'block';
                accountNotExists.style.display = 'none';
                saveBtn.style.display = 'inline-block';
                resetBtn.style.display = 'inline-block';
                
                // 계정 정보 입력
                document.getElementById('edit-account-name').value = account.name || '';
                document.getElementById('edit-account-email').value = account.email || '';
                document.getElementById('edit-account-password').value = '';
                document.getElementById('account-created-date').textContent = 
                    account.created_at ? new Date(account.created_at).toLocaleDateString('ko-KR') : '-';
                
                // 전역 변수에 저장 (저장 시 사용)
                window.currentStoreAccount = { store, account };
            } else {
                // 계정이 없는 경우
                accountExists.style.display = 'none';
                accountNotExists.style.display = 'block';
                saveBtn.style.display = 'none';
                resetBtn.style.display = 'none';
                
                // 전역 변수에 저장 (생성 시 사용)
                window.currentStoreAccount = { store, account: null };
            }
            
            modal.classList.remove('hidden');
            modal.classList.add('flex', 'fade-in');
        }

        // 계정 관리 모달 닫기
        function closeAccountModal() {
            const modal = document.getElementById('account-modal');
            modal.classList.add('hidden');
            modal.classList.remove('flex', 'fade-in');
            window.currentStoreAccount = null;
        }

        // 계정 변경사항 저장
        async function saveAccountChanges() {
            if (!window.currentStoreAccount) return;
            
            const { store, account } = window.currentStoreAccount;
            const newName = document.getElementById('edit-account-name').value;
            const newEmail = document.getElementById('edit-account-email').value;
            const newPassword = document.getElementById('edit-account-password').value;
            
            if (!newName || !newEmail) {
                showToast('이름과 이메일을 입력해주세요', 'warning');
                return;
            }
            
            try {
                const updateData = {
                    name: newName,
                    email: newEmail
                };
                
                if (newPassword.trim()) {
                    updateData.password = newPassword;
                }
                
                // 사용자 정보 업데이트 API 호출 (구현 필요)
                const response = await fetch(`/api/users/${account.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(updateData)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('계정 정보가 업데이트되었습니다', 'success');
                    closeAccountModal();
                    loadStores(); // 목록 새로고침
                } else {
                    showToast('업데이트 실패: ' + (result.error || '알 수 없는 오류'), 'error');
                }
                
            } catch (error) {
                console.error('계정 업데이트 중 오류:', error);
                showToast('계정 업데이트 중 오류가 발생했습니다', 'error');
            }
        }

        // 비밀번호 리셋
        async function resetAccountPassword() {
            if (!window.currentStoreAccount) return;
            
            const { account } = window.currentStoreAccount;
            
            if (!confirm('이 계정의 비밀번호를 "123456"으로 리셋하시겠습니까?')) {
                return;
            }
            
            try {
                const response = await fetch(`/api/users/${account.id}/reset-password`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ password: '123456' })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('비밀번호가 리셋되었습니다 (새 비밀번호: 123456)', 'success');
                } else {
                    showToast('비밀번호 리셋 실패: ' + (result.error || '알 수 없는 오류'), 'error');
                }
                
            } catch (error) {
                console.error('비밀번호 리셋 중 오류:', error);
                showToast('비밀번호 리셋 중 오류가 발생했습니다', 'error');
            }
        }

        // 매장 계정 생성
        async function createAccountForStore() {
            if (!window.currentStoreAccount) return;
            
            const { store } = window.currentStoreAccount;
            
            try {
                const response = await fetch(`/api/stores/${store.id}/account`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        name: store.name + ' 관리자',
                        email: store.code.toLowerCase() + '@ykp.com',
                        password: '123456'
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showToast('계정이 생성되었습니다', 'success');
                    closeAccountModal();
                    loadStores(); // 목록 새로고침
                } else {
                    showToast('계정 생성 실패: ' + (result.error || '알 수 없는 오류'), 'error');
                }
                
            } catch (error) {
                console.error('계정 생성 중 오류:', error);
                showToast('계정 생성 중 오류가 발생했습니다', 'error');
            }
        }

        // 지사 관리 페이지로 이동
        function goToBranchManagement() {
            const userRole = '{{ auth()->user()->role ?? "guest" }}';
            
            if (userRole !== 'headquarters') {
                showToast('지사 관리는 본사 관리자만 접근 가능합니다', 'warning');
                return;
            }
            
            window.location.href = '/management/branches';
        }
    </script>
</body>
</html>