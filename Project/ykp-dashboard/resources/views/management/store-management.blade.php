<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>매장 관리 - YKP ERP</title>
    @vite(['resources/css/app.css'])
</head>
<body class="bg-gray-50">
    <!-- 헤더 -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-gray-900">매장 관리</h1>
                    <span class="ml-2 px-2 py-1 text-xs bg-red-100 text-red-800 rounded" id="user-role">본사 전용</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/dashboard" class="text-gray-600 hover:text-gray-900">대시보드</a>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto py-6 px-4">
        <!-- 탭 메뉴 -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="border-b border-gray-200">
                <nav class="flex space-x-8 px-6">
                    <button onclick="showTab('stores')" class="tab-btn active" id="stores-tab">
                        🏪 매장 관리
                    </button>
                    <button onclick="showTab('branches')" class="tab-btn" id="branches-tab">
                        🏢 지사 관리  
                    </button>
                    <button onclick="showTab('users')" class="tab-btn" id="users-tab">
                        👥 사용자 관리
                    </button>
                </nav>
            </div>
            
            <!-- 매장 관리 탭 -->
            <div id="stores-content" class="tab-content p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-medium">매장 목록</h2>
                    <button onclick="addStore()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">
                        ➕ 매장 추가
                    </button>
                </div>
                <div id="stores-grid" class="bg-white rounded border">
                    <div class="p-4 text-center text-gray-500">로딩 중...</div>
                </div>
            </div>
            
            <!-- 지사 관리 탭 -->
            <div id="branches-content" class="tab-content p-6 hidden">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-medium">지사 목록</h2>
                    <button onclick="addBranch()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                        ➕ 지사 추가
                    </button>
                </div>
                <div id="branches-grid" class="bg-white rounded border">
                    <div class="p-4 text-center text-gray-500">로딩 중...</div>
                </div>
            </div>
            
            <!-- 사용자 관리 탭 -->
            <div id="users-content" class="tab-content p-6 hidden">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-medium">사용자 목록</h2>
                    <button onclick="addUser()" class="bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600">
                        ➕ 사용자 추가
                    </button>
                </div>
                <div id="users-grid" class="bg-white rounded border">
                    <div class="p-4 text-center text-gray-500">로딩 중...</div>
                </div>
            </div>
        </div>
    </main>

    <style>
        .tab-btn {
            padding: 12px 16px;
            border-bottom: 2px solid transparent;
            font-medium: 500;
            color: #6b7280;
            transition: all 0.2s;
        }
        .tab-btn:hover {
            color: #374151;
        }
        .tab-btn.active {
            color: #2563eb;
            border-bottom-color: #2563eb;
        }
        .tab-content {
            min-height: 400px;
        }
    </style>

    <script>
        // 사용자 정보 설정
        window.userData = {
            id: {{ auth()->user()->id ?? 1 }},
            name: '{{ auth()->user()->name ?? "본사 관리자" }}',
            role: '{{ auth()->user()->role ?? "headquarters" }}',
            store_id: {{ auth()->user()->store_id ?? 'null' }},
            branch_id: {{ auth()->user()->branch_id ?? 'null' }}
        };

        // 권한 체크
        if (window.userData.role !== 'headquarters') {
            document.getElementById('user-role').textContent = '접근 권한 없음';
            document.getElementById('user-role').className = 'ml-2 px-2 py-1 text-xs bg-red-100 text-red-800 rounded';
            alert('본사 관리자만 접근 가능한 페이지입니다.');
            window.location.href = '/dashboard';
        }

        // 탭 전환
        function showTab(tabName) {
            // 모든 탭 비활성화
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(content => content.classList.add('hidden'));
            
            // 선택된 탭 활성화
            document.getElementById(tabName + '-tab').classList.add('active');
            document.getElementById(tabName + '-content').classList.remove('hidden');
            
            // 해당 데이터 로드
            loadTabData(tabName);
        }

        // 탭별 데이터 로드
        function loadTabData(tabName) {
            switch(tabName) {
                case 'stores':
                    loadStores();
                    break;
                case 'branches':
                    loadBranches();
                    break;
                case 'users':
                    loadUsers();
                    break;
            }
        }

        // 매장 목록 로드
        function loadStores() {
            document.getElementById('stores-grid').innerHTML = '<div class="p-4 text-center text-gray-500">매장 목록 로딩 중...</div>';
            
            fetch('/api/stores')
                .then(response => response.json())
                .then(data => {
                    let html = `
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">매장명</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">코드</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">지사</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">점주</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">상태</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">액션</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                    `;
                    
                    if (data.data && data.data.length > 0) {
                        data.data.forEach(store => {
                            html += `
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${store.name}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${store.code}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${store.branch?.name || 'N/A'}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${store.owner_name || '-'}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs rounded-full ${store.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'}">
                                            ${store.status === 'active' ? '운영중' : '중단'}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <button onclick="editStore(${store.id})" class="text-blue-600 hover:text-blue-900 mr-2">수정</button>
                                        <button onclick="createUserForStore(${store.id})" class="text-green-600 hover:text-green-900">계정생성</button>
                                    </td>
                                </tr>
                            `;
                        });
                    } else {
                        html += '<tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">매장이 없습니다.</td></tr>';
                    }
                    
                    html += '</tbody></table></div>';
                    document.getElementById('stores-grid').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('stores-grid').innerHTML = '<div class="p-4 text-center text-red-500">매장 목록 로드 실패</div>';
                });
        }

        // 지사 목록 로드
        function loadBranches() {
            document.getElementById('branches-grid').innerHTML = '<div class="p-4 text-center text-gray-500">지사 목록 로딩 중...</div>';
            // TODO: 지사 API 구현
            document.getElementById('branches-grid').innerHTML = '<div class="p-4 text-center text-gray-500">지사 관리 기능 구현 예정</div>';
        }

        // 사용자 목록 로드  
        function loadUsers() {
            document.getElementById('users-grid').innerHTML = '<div class="p-4 text-center text-gray-500">사용자 목록 로딩 중...</div>';
            
            fetch('/api/api/users')
                .then(response => response.json())
                .then(data => {
                    let html = `
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">이름</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">이메일</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">권한</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">소속</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">액션</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                    `;
                    
                    if (data.data && data.data.length > 0) {
                        data.data.forEach(user => {
                            html += `
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${user.name}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${user.email}</td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="px-2 py-1 text-xs rounded-full ${getRoleColor(user.role)}">
                                            ${getRoleText(user.role)}
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${user.store?.name || user.branch?.name || '본사'}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <button onclick="editUser(${user.id})" class="text-blue-600 hover:text-blue-900">수정</button>
                                    </td>
                                </tr>
                            `;
                        });
                    } else {
                        html += '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">사용자가 없습니다.</td></tr>';
                    }
                    
                    html += '</tbody></table></div>';
                    document.getElementById('users-grid').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('users-grid').innerHTML = '<div class="p-4 text-center text-red-500">사용자 목록 로드 실패</div>';
                });
        }

        // 권한별 색상
        function getRoleColor(role) {
            switch(role) {
                case 'headquarters': return 'bg-purple-100 text-purple-800';
                case 'branch': return 'bg-blue-100 text-blue-800';
                case 'store': return 'bg-green-100 text-green-800';
                default: return 'bg-gray-100 text-gray-800';
            }
        }

        function getRoleText(role) {
            switch(role) {
                case 'headquarters': return '본사';
                case 'branch': return '지사';
                case 'store': return '매장';
                default: return '기타';
            }
        }

        // 매장 추가 함수
        function addStore() {
            const name = prompt('매장명을 입력하세요:');
            if (!name) return;
            
            const code = prompt('매장 코드를 입력하세요 (예: ST001):');
            if (!code) return;
            
            const branchId = prompt('지사 ID를 입력하세요 (1-5):');
            if (!branchId) return;
            
            const ownerName = prompt('점주명을 입력하세요:') || '';
            
            const storeData = {
                name: name,
                code: code,
                branch_id: parseInt(branchId),
                owner_name: ownerName,
                phone: '',
                address: ''
            };
            
            fetch('/api/stores', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(storeData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('매장이 성공적으로 추가되었습니다!');
                    loadStores(); // 목록 새로고침
                } else {
                    alert('오류: ' + (data.message || data.error || '매장 추가 실패'));
                }
            })
            .catch(error => {
                console.error('매장 추가 오류:', error);
                alert('매장 추가 중 오류가 발생했습니다.');
            });
        }

        function addBranch() {
            alert('지사 추가 기능 구현 예정');
        }

        function addUser() {
            alert('사용자 추가 기능 구현 예정');
        }

        function editStore(storeId) {
            alert(`매장 ${storeId} 수정 기능 구현 예정`);
        }

        function editUser(userId) {
            alert(`사용자 ${userId} 수정 기능 구현 예정`);
        }

        function createUserForStore(storeId) {
            const name = prompt('사용자명을 입력하세요:');
            if (!name) return;
            
            const email = prompt('이메일을 입력하세요:');
            if (!email) return;
            
            const password = prompt('비밀번호를 입력하세요 (최소 6자):') || '123456';
            
            const userData = {
                name: name,
                email: email,
                password: password
            };
            
            fetch(`/api/stores/${storeId}/create-user`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify(userData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`매장 계정이 생성되었습니다!\n이메일: ${email}\n비밀번호: ${password}`);
                    loadUsers(); // 사용자 목록 새로고침
                } else {
                    alert('오류: ' + (data.message || data.error || '계정 생성 실패'));
                }
            })
            .catch(error => {
                console.error('계정 생성 오류:', error);
                alert('계정 생성 중 오류가 발생했습니다.');
            });
        }

        // 초기 로드
        document.addEventListener('DOMContentLoaded', function() {
            showTab('stores');
        });
    </script>
</body>
</html>