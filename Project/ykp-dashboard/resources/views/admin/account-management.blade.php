<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>👥 계정 관리 - YKP ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <!-- 헤더 -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-gray-900">👥 계정 관리</h1>
                    <span class="ml-2 px-2 py-1 text-xs bg-red-100 text-red-800 rounded">본사 전용</span>
                </div>
                <div class="flex items-center space-x-4">
                    <button onclick="exportAccounts()" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600">
                        📤 계정 정보 내보내기
                    </button>
                    <a href="/dashboard" class="text-gray-600 hover:text-gray-900">대시보드</a>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- 통계 -->
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-lg font-medium mb-4">📊 계정 현황</h2>
            <div class="grid grid-cols-4 gap-4">
                <div class="bg-blue-50 p-4 rounded text-center">
                    <div class="text-2xl font-bold text-blue-600" id="total-accounts">-</div>
                    <div class="text-sm text-gray-600">전체 계정</div>
                </div>
                <div class="bg-green-50 p-4 rounded text-center">
                    <div class="text-2xl font-bold text-green-600" id="branch-accounts">-</div>
                    <div class="text-sm text-gray-600">🏬 지사 관리자</div>
                </div>
                <div class="bg-orange-50 p-4 rounded text-center">
                    <div class="text-2xl font-bold text-orange-600" id="store-accounts">-</div>
                    <div class="text-sm text-gray-600">🏪 매장 직원</div>
                </div>
                <div class="bg-red-50 p-4 rounded text-center">
                    <div class="text-2xl font-bold text-red-600" id="inactive-accounts">-</div>
                    <div class="text-sm text-gray-600">❌ 비활성</div>
                </div>
            </div>
        </div>

        <!-- 계정 목록 -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-medium">🔑 모든 계정 정보</h3>
                    <div class="flex space-x-2">
                        <button onclick="filterAccounts('all')" class="filter-btn active px-3 py-1 text-sm bg-blue-500 text-white rounded">전체</button>
                        <button onclick="filterAccounts('branch')" class="filter-btn px-3 py-1 text-sm border border-gray-300 rounded hover:bg-gray-50">지사</button>
                        <button onclick="filterAccounts('store')" class="filter-btn px-3 py-1 text-sm border border-gray-300 rounded hover:bg-gray-50">매장</button>
                    </div>
                </div>
                <p class="text-sm text-gray-500 mt-1">모든 로그인 정보를 확인하고 관리할 수 있습니다.</p>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">이름</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">🔑 로그인 정보</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">역할</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">소속</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">상태</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">관리</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="accounts-tbody">
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                                계정 목록 로딩 중...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- 비밀번호 리셋 모달 -->
    <div id="reset-password-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">🔑 비밀번호 리셋</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">사용자</label>
                    <input type="text" id="reset-user-info" readonly
                           class="w-full border border-gray-300 rounded-md px-3 py-2 bg-gray-50">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">새 비밀번호</label>
                    <input type="text" id="reset-new-password" value="123456"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">해당 사용자에게 전달할 비밀번호입니다.</p>
                </div>
                <div class="bg-yellow-50 p-3 rounded">
                    <p class="text-sm text-yellow-800">
                        <strong>⚠️ 주의:</strong> 새 비밀번호를 해당 사용자에게 안전하게 전달하세요.
                    </p>
                </div>
            </div>
            <div class="px-6 py-4 bg-gray-50 flex justify-end space-x-3">
                <button onclick="closeResetPasswordModal()" 
                        class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                    취소
                </button>
                <button onclick="confirmResetPassword()" 
                        class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600">
                    🔑 비밀번호 리셋
                </button>
            </div>
        </div>
    </div>

    <script>
        let allAccounts = [];
        let currentResetUserId = null;

        // 페이지 로드 시 초기화
        document.addEventListener('DOMContentLoaded', function() {
            loadAccounts();

            // ✨ 실시간 업데이트 강화 - 1분마다 자동 새로고침
            setInterval(loadAccounts, 60000);

            // 🔄 실시간 업데이트 리스너 초기화
            initRealtimeAccountSync();
        });

        // 🔄 실시간 계정 동기화 리스너
        function initRealtimeAccountSync() {
            console.log('📡 실시간 계정 동기화 리스너 초기화...');

            // 1. localStorage 이벤트로 다른 탭의 변경사항 감지
            window.addEventListener('storage', function(event) {
                if (event.key === 'account_update_trigger') {
                    try {
                        const updateData = JSON.parse(event.newValue);
                        console.log('📨 계정 업데이트 신호 수신:', updateData);

                        if (updateData.type === 'account_change') {
                            console.log('🔄 다른 탭에서 계정 변경 감지 - 데이터 새로고침');
                            setTimeout(loadAccounts, 1000); // 1초 후 새로고침
                        }
                    } catch (e) {
                        console.error('❌ 계정 업데이트 처리 오류:', e);
                    }
                }
            });

            // 2. 주기적 데이터 동기화 (30초마다)
            setInterval(() => {
                console.log('⏰ 주기적 계정 데이터 동기화...');
                loadAccountsQuietly();
            }, 30000);

            console.log('✅ 실시간 계정 동기화 리스너 초기화 완료');
        }

        // 🔇 조용한 계정 로드 (UI 갱신 없이 백그라운드 동기화)
        async function loadAccountsQuietly() {
            try {
                const response = await fetch('/test-api/accounts/all');
                if (!response.ok) return;

                const data = await response.json();
                if (!data.success) return;

                const newAccounts = data.data;

                // 기존 데이터와 비교하여 변경사항이 있을 때만 업데이트
                if (JSON.stringify(allAccounts) !== JSON.stringify(newAccounts)) {
                    console.log('🔄 계정 데이터 변경 감지 - UI 업데이트');
                    allAccounts = newAccounts;
                    renderAccounts(allAccounts);
                    updateStatistics(allAccounts);
                }
            } catch (error) {
                console.warn('⚠️ 조용한 계정 동기화 실패:', error.message);
            }
        }

        // 모든 계정 로드
        async function loadAccounts() {
            try {
                const response = await fetch('/test-api/accounts/all');
                const data = await response.json();
                
                if (data.success) {
                    allAccounts = data.data;
                    renderAccounts(allAccounts);
                    updateStatistics(allAccounts);
                } else {
                    showError('계정 목록을 불러올 수 없습니다.');
                }
            } catch (error) {
                console.error('계정 로드 오류:', error);
                showError('계정 목록 로드 중 오류가 발생했습니다.');
            }
        }

        // 계정 목록 렌더링
        function renderAccounts(accounts) {
            const tbody = document.getElementById('accounts-tbody');
            
            if (accounts.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-gray-500">
                            표시할 계정이 없습니다.
                        </td>
                    </tr>
                `;
                return;
            }

            tbody.innerHTML = accounts.map(account => `
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900">${account.name}</div>
                        <div class="text-xs text-gray-500">ID: ${account.id}</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm text-gray-900 font-mono bg-gray-100 px-2 py-1 rounded">${account.email}</div>
                        <div class="text-xs text-blue-600 font-mono mt-1">비밀번호: 123456</div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getRoleBadgeClass(account.role)}">
                            ${getRoleText(account.role)}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        ${getAffiliationText(account)}
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full ${getStatusBadgeClass(account.status)}">
                            ${account.status === 'active' ? '✅ 활성' : '❌ 비활성'}
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm space-x-2">
                        ${account.role !== 'headquarters' ? `
                            <button onclick="resetPassword(${account.id})" 
                                    class="text-orange-600 hover:text-orange-900 font-medium">🔑 비밀번호 리셋</button>
                            <button onclick="toggleAccountStatus(${account.id})" 
                                    class="text-${account.status === 'active' ? 'red' : 'green'}-600 hover:text-${account.status === 'active' ? 'red' : 'green'}-900 font-medium">
                                ${account.status === 'active' ? '❌ 비활성화' : '✅ 활성화'}
                            </button>
                        ` : '<span class="text-gray-400">본사 계정</span>'}
                    </td>
                </tr>
            `).join('');
        }

        // 통계 업데이트
        function updateStatistics(accounts) {
            const total = accounts.length;
            const branches = accounts.filter(a => a.role === 'branch').length;
            const stores = accounts.filter(a => a.role === 'store').length;
            const inactive = accounts.filter(a => a.status !== 'active').length;

            document.getElementById('total-accounts').textContent = total;
            document.getElementById('branch-accounts').textContent = branches;
            document.getElementById('store-accounts').textContent = stores;
            document.getElementById('inactive-accounts').textContent = inactive;
        }

        // 계정 필터링
        function filterAccounts(role) {
            // 필터 버튼 스타일 업데이트
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active', 'bg-blue-500', 'text-white');
                btn.classList.add('border', 'border-gray-300', 'hover:bg-gray-50');
            });
            
            const activeBtn = document.querySelector(`[onclick="filterAccounts('${role}')"]`);
            activeBtn.classList.add('active', 'bg-blue-500', 'text-white');
            activeBtn.classList.remove('border', 'border-gray-300', 'hover:bg-gray-50');

            // 계정 필터링
            const filteredAccounts = role === 'all' 
                ? allAccounts 
                : allAccounts.filter(account => account.role === role);
                
            renderAccounts(filteredAccounts);
        }

        // 비밀번호 리셋
        function resetPassword(userId) {
            const user = allAccounts.find(u => u.id === userId);
            if (!user) return;

            currentResetUserId = userId;
            document.getElementById('reset-user-info').value = `${user.name} (${user.email})`;
            document.getElementById('reset-password-modal').classList.remove('hidden');
        }

        function closeResetPasswordModal() {
            document.getElementById('reset-password-modal').classList.add('hidden');
            currentResetUserId = null;
        }

        async function confirmResetPassword() {
            if (!currentResetUserId) return;

            const newPassword = document.getElementById('reset-new-password').value;
            if (!newPassword.trim()) {
                alert('새 비밀번호를 입력하세요.');
                return;
            }

            try {
                const response = await fetch(`/test-api/users/${currentResetUserId}/reset-password`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ password: newPassword })
                });

                const data = await response.json();
                
                if (data.success) {
                    alert(`✅ 비밀번호가 성공적으로 리셋되었습니다!\n\n📧 ${data.user.email}\n🔑 새 비밀번호: ${newPassword}\n\n⚠️ 이 정보를 해당 사용자에게 전달하세요!`);
                    closeResetPasswordModal();
                } else {
                    alert('❌ 비밀번호 리셋 실패: ' + data.error);
                }
            } catch (error) {
                alert('❌ 비밀번호 리셋 중 오류가 발생했습니다.');
            }
        }

        // 계정 활성/비활성화
        async function toggleAccountStatus(userId) {
            const user = allAccounts.find(u => u.id === userId);
            if (!user) return;

            const newStatus = user.status === 'active' ? 'inactive' : 'active';
            const actionText = newStatus === 'active' ? '활성화' : '비활성화';

            if (!confirm(`${user.name} (${user.email}) 계정을 ${actionText} 하시겠습니까?`)) return;

            try {
                const response = await fetch(`/test-api/users/${userId}/toggle-status`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ status: newStatus })
                });

                const data = await response.json();
                
                if (data.success) {
                    alert(`✅ ${user.name} 계정이 ${actionText}되었습니다.`);
                    loadAccounts(); // 목록 새로고침
                } else {
                    alert('❌ 계정 상태 변경 실패: ' + data.error);
                }
            } catch (error) {
                alert('❌ 계정 상태 변경 중 오류가 발생했습니다.');
            }
        }

        // 계정 정보 내보내기
        function exportAccounts() {
            if (allAccounts.length === 0) {
                alert('내보낼 계정 정보가 없습니다.');
                return;
            }

            const csvContent = [
                ['이름', '이메일(로그인ID)', '기본비밀번호', '역할', '소속', '상태', '생성일'],
                ...allAccounts.map(account => [
                    account.name,
                    account.email,
                    '123456',
                    getRoleText(account.role),
                    getAffiliationText(account),
                    account.status === 'active' ? '활성' : '비활성',
                    account.created_at || '알수없음'
                ])
            ].map(row => row.map(cell => `"${cell}"`).join(',')).join('\n');

            const blob = new Blob(['\ufeff' + csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `YKP_ERP_모든계정정보_${new Date().toISOString().split('T')[0]}.csv`;
            link.click();
            
            alert('📤 모든 계정 정보가 다운로드되었습니다!\n\n파일에는 로그인 정보가 포함되어 있으니 안전하게 관리하세요.');
        }

        // 유틸리티 함수들
        function getRoleText(role) {
            switch (role) {
                case 'headquarters': return '🏢 본사';
                case 'branch': return '🏬 지사';
                case 'store': return '🏪 매장';
                default: return '기타';
            }
        }

        function getRoleBadgeClass(role) {
            switch (role) {
                case 'headquarters': return 'bg-red-100 text-red-800';
                case 'branch': return 'bg-blue-100 text-blue-800';
                case 'store': return 'bg-green-100 text-green-800';
                default: return 'bg-gray-100 text-gray-800';
            }
        }

        function getStatusBadgeClass(status) {
            return status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
        }

        function getAffiliationText(account) {
            if (account.role === 'headquarters') return '본사';
            if (account.role === 'branch') return account.branch_name || `지사 (ID: ${account.branch_id})`;
            if (account.role === 'store') return account.store_name || `매장 (ID: ${account.store_id})`;
            return '없음';
        }

        function showError(message) {
            document.getElementById('accounts-tbody').innerHTML = `
                <tr>
                    <td colspan="6" class="px-6 py-8 text-center text-red-500">
                        ❌ ${message}
                    </td>
                </tr>
            `;
        }
    </script>
</body>
</html>