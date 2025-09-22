<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>매장 관리 - YKP ERP (v2.1 - {{ now()->format('H:i:s') }})</title>
    <!-- 캐시 무효화용 -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <!-- 강제 새로고침용 타임스탬프 -->
    <script>console.log('페이지 로드 시간: {{ now()->toISOString() }}');</script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css" rel="stylesheet">
    
    {{-- 🔒 세션 안정성 강화 스크립트 --}}
    <script src="/js/session-stability.js"></script>
    
    {{-- 🚨 긴급: 전역 함수 즉시 등록 (ReferenceError 방지) --}}
    <script>
        window.showAddStoreModal = function() {
            console.log('✅ 전역 showAddStoreModal 즉시 실행');

            // 🔥 지사 목록을 먼저 로드
            loadBranchOptions('modal-branch-select');

            const modal = document.getElementById('add-store-modal');
            if (modal) {
                modal.classList.remove('hidden');
                modal.style.display = 'flex';
                console.log('✅ 모달 표시 성공');

                // 첫 번째 입력 필드에 포커스
                const nameInput = document.getElementById('modal-store-name');
                if (nameInput) {
                    nameInput.focus();
                }
            } else {
                console.error('❌ add-store-modal 찾을 수 없음');
                alert('매장 추가 기능을 호출했지만 모달을 찾을 수 없습니다.');
            }
        };

        window.loadBranchOptions = function(selectId) {
            console.log('✅ 전역 loadBranchOptions 실행:', selectId);

            const select = document.getElementById(selectId);
            const userRole = '{{ auth()->user()->role }}';
            const userBranchId = '{{ auth()->user()->branch_id }}';

            if (!select) {
                console.error('❌ 드롭다운 요소를 찾을 수 없음:', selectId);
                return;
            }

            select.innerHTML = '<option value="">로딩 중...</option>';

            // 지사 계정인 경우 자신의 지사만 표시
            if (userRole === 'branch' && userBranchId) {
                console.log('🏢 지사 계정:', userRole, '지사 ID:', userBranchId);

                fetch('/api/branches')
                    .then(response => response.json())
                    .then(data => {
                        console.log('📊 지사 API 응답:', data);

                        if (data.success) {
                            const userBranch = data.data.find(branch => branch.id == userBranchId);
                            if (userBranch) {
                                select.innerHTML = `<option value="${userBranch.id}" selected>${userBranch.name}</option>`;
                                console.log('✅ 지사 계정용 지사 선택됨:', userBranch.name);

                                // 지사 선택 컨테이너 숨기기
                                const container = document.getElementById('branch-select-container');
                                if (container) {
                                    container.style.display = 'none';
                                    console.log('✅ 지사 선택 컨테이너 숨김');
                                }
                            } else {
                                select.innerHTML = '<option value="">지사 정보를 찾을 수 없습니다</option>';
                                console.error('❌ 지사 정보 찾기 실패');
                            }
                        } else {
                            select.innerHTML = '<option value="">지사 목록 로드 실패</option>';
                            console.error('❌ API 응답 실패');
                        }
                    })
                    .catch(error => {
                        console.error('❌ 지사 정보 로드 오류:', error);
                        select.innerHTML = '<option value="">지사 정보 로드 오류</option>';
                    });
            } else {
                console.log('🏛️ 본사 계정 - 모든 지사 표시');

                fetch('/api/branches')
                    .then(response => response.json())
                    .then(data => {
                        console.log('📊 본사용 지사 API 응답:', data);

                        if (data.success) {
                            select.innerHTML = '<option value="">지사를 선택하세요...</option>';
                            data.data.forEach(branch => {
                                select.innerHTML += `<option value="${branch.id}">${branch.name}</option>`;
                            });
                            console.log('✅ 본사 계정용 지사 목록 로드 완료:', data.data.length, '개');
                        } else {
                            select.innerHTML = '<option value="">지사 목록 로드 실패</option>';
                            console.error('❌ API 응답 실패');
                        }
                    })
                    .catch(error => {
                        console.error('❌ 지사 목록 로드 오류:', error);
                        select.innerHTML = '<option value="">지사 목록 로드 오류</option>';
                    });
            }
        };
        
        window.closeAddStoreModal = function() {
            console.log('✅ 전역 closeAddStoreModal 실행');
            const modal = document.getElementById('add-store-modal');
            if (modal) {
                modal.classList.add('hidden');
                modal.style.display = 'none';
                // 폼 초기화
                document.getElementById('modal-store-name').value = '';
                document.getElementById('modal-owner-name').value = '';
                document.getElementById('modal-phone').value = '';
                console.log('✅ 모달 닫기 및 폼 초기화 완료');
            }
        };

        // 클립보드 복사 함수
        window.copyToClipboard = function(text) {
            navigator.clipboard.writeText(text).then(() => {
                showToast('📋 클립보드에 복사되었습니다', 'success');
            }).catch(() => {
                const input = document.createElement('textarea');
                input.value = text;
                document.body.appendChild(input);
                input.select();
                document.execCommand('copy');
                document.body.removeChild(input);
                showToast('📋 클립보드에 복사되었습니다', 'success');
            });
        };

        // 계정 정보 출력 함수
        window.printAccountInfo = function(email, password, storeName) {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                <head>
                    <title>${storeName} 계정 정보</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 40px; }
                        h1 { color: #333; }
                        .info { margin: 20px 0; padding: 20px; border: 1px solid #ddd; }
                        .label { font-weight: bold; color: #666; }
                    </style>
                </head>
                <body>
                    <h1>${storeName} 매장 계정 정보</h1>
                    <div class="info">
                        <p><span class="label">로그인 아이디:</span> ${email}</p>
                        <p><span class="label">임시 비밀번호:</span> ${password}</p>
                    </div>
                    <p>※ 첫 로그인 후 비밀번호를 변경해주세요.</p>
                </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        };

        // 매장 계정 정보 모달 표시 함수 (submitAddStore보다 먼저 정의)
        window.showStoreAccountModal = function(account, store) {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            modal.innerHTML = `
                <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                    <div class="text-center mb-6">
                        <div class="text-2xl mb-2">🎉</div>
                        <h3 class="text-lg font-bold text-gray-900">매장 계정 생성 완료</h3>
                        <p class="text-sm text-gray-600 mt-2">${store.name} 매장의 로그인 계정이 생성되었습니다.</p>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-4 mb-6">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase">로그인 아이디</label>
                                <div class="mt-1 flex items-center">
                                    <span class="font-mono text-sm bg-white px-3 py-2 rounded border flex-1">${account.email}</span>
                                    <button onclick="copyToClipboard('${account.email}')" class="ml-2 px-2 py-2 text-gray-500 hover:text-blue-500">
                                        📋
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase">임시 비밀번호</label>
                                <div class="mt-1 flex items-center">
                                    <span class="font-mono text-sm bg-white px-3 py-2 rounded border flex-1">${account.password}</span>
                                    <button onclick="copyToClipboard('${account.password}')" class="ml-2 px-2 py-2 text-gray-500 hover:text-blue-500">
                                        📋
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-6">
                        <div class="flex">
                            <div class="text-blue-400 mr-2">ℹ️</div>
                            <div class="text-xs text-blue-700">
                                <p class="font-medium mb-1">중요 안내사항</p>
                                <ul class="space-y-1">
                                    <li>• 매장 담당자에게 위 정보를 안전하게 전달해주세요</li>
                                    <li>• 첫 로그인 후 비밀번호 변경을 권장합니다</li>
                                    <li>• 계정 정보는 보안상 다시 확인할 수 없습니다</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="flex space-x-3">
                        <button onclick="printAccountInfo('${account.email}', '${account.password}', '${store.name}')"
                                class="flex-1 bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors">
                            🖨️ 인쇄하기
                        </button>
                        <button onclick="this.parentElement.parentElement.parentElement.remove()"
                                class="flex-1 bg-blue-500 text-white px-4 py-2 rounded-lg hover:bg-blue-600 transition-colors">
                            ✅ 확인
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        };

        window.submitAddStore = function() {
            console.log('✅ 매장 추가 제출 시작');

            // 지사 계정인 경우 자동으로 branch_id 설정
            const userRole = '{{ auth()->user()->role }}';
            const userBranchId = '{{ auth()->user()->branch_id }}';

            const formData = {
                name: document.getElementById('modal-store-name')?.value || '',
                branch_id: userRole === 'branch' ? userBranchId : (document.getElementById('modal-branch-select')?.value || ''),
                owner_name: document.getElementById('modal-owner-name')?.value || '',
                phone: document.getElementById('modal-phone')?.value || '',
                address: '',
                code: '' // 자동 생성됨
            };
            
            console.log('매장 데이터:', formData);
            console.log('사용자 역할:', userRole, '지사 ID:', userBranchId);
            
            if (!formData.name) {
                alert('매장명을 입력해주세요');
                return;
            }
            
            if (!formData.branch_id) {
                alert('지사를 선택해주세요');
                return;
            }
            
            // API 호출 (세션 인증 포함)
            fetch('/api/stores', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json'
                },
                credentials: 'same-origin', // 세션 쿠키 포함
                body: JSON.stringify(formData)
            })
            .then(async response => {
                console.log('API 응답 상태:', response.status);
                const responseText = await response.text();
                console.log('API 응답 내용:', responseText);

                try {
                    const result = JSON.parse(responseText);
                    if (!response.ok) {
                        throw new Error(result.error || result.message || `HTTP ${response.status}: ${response.statusText}`);
                    }
                    return result;
                } catch (e) {
                    console.error('JSON 파싱 오류:', e);
                    console.error('응답 내용:', responseText);
                    throw new Error(`서버 응답 오류: ${responseText.substring(0, 200)}`);
                }
            })
            .then(result => {
                if (result.success) {
                    console.log('✅ 매장 생성 성공');

                    // 모달 닫기
                    const modal = document.getElementById('add-store-modal');
                    if (modal) {
                        modal.classList.add('hidden');
                        modal.style.display = 'none';
                    }

                    // 🔍 디버깅: API 응답 내용 확인
                    console.log('📊 API 응답 전체:', result);
                    console.log('🔑 account 정보:', result.account);
                    console.log('🏪 store 정보:', result.data);

                    // 🎉 계정 정보 모달 표시 (본사/지사 모두)
                    if (result.account && result.account.user_id) {
                        console.log('✅ account 정상 생성됨, 모달 호출 시작');
                        // 함수는 이미 정의되어 있으므로 직접 호출
                        window.showPMAccountModal(result.account, result.data);
                    } else if (result.account && result.account.error) {
                        console.log('⚠️ 매장은 생성됨, 계정 생성 실패:', result.account.error);
                        alert(`매장이 생성되었습니다!\n\n⚠️ ${result.account.error}\n\n수동 계정 정보:\n이메일: ${result.account.email}\n비밀번호: ${result.account.password}`);
                    } else if (result.account) {
                        console.log('✅ account 존재 (user_id 없음), 모달 호출');
                        window.showPMAccountModal(result.account, result.data);
                    } else {
                        console.log('❌ account 정보 없음, alert 표시');
                        alert('매장이 성공적으로 생성되었습니다!');
                    }

                    // 목록 새로고침 (페이지 리로드 대신)
                    if (typeof loadStores === 'function') {
                        loadStores();
                    }
                } else {
                    alert('매장 생성 실패: ' + (result.error || '알 수 없는 오류'));
                }
            })
            .catch(error => {
                console.error('매장 생성 오류 상세:', error);
                console.error('에러 스택:', error.stack);

                // 더 자세한 에러 메시지 표시
                let errorMessage = '매장 생성 중 오류가 발생했습니다.\n\n';

                if (error.message.includes('401') || error.message.includes('Unauthenticated')) {
                    errorMessage = '🔐 세션이 만료되었습니다.\n\n다시 로그인해주세요.';
                    console.error('인증 실패 - 로그인 페이지로 이동');

                    // 3초 후 자동으로 로그인 페이지로 이동
                    setTimeout(() => {
                        window.location.href = '/login';
                    }, 3000);
                } else if (error.message.includes('403')) {
                    errorMessage += '권한 오류: 매장을 추가할 권한이 없습니다.';
                } else if (error.message.includes('500')) {
                    errorMessage += '서버 오류: 잠시 후 다시 시도해주세요.';
                } else if (error.message.includes('422')) {
                    errorMessage += '입력값 오류: 필수 항목을 모두 입력했는지 확인해주세요.';
                } else if (error.message.includes('CSRF')) {
                    errorMessage += '보안 토큰 오류: 페이지를 새로고침해주세요.';
                    console.error('CSRF 토큰 오류');
                } else {
                    errorMessage += '오류 내용: ' + error.message;
                }

                alert(errorMessage);
            });
        };
        
        // 즉시 전역 스코프에도 등록
        window.addEventListener('DOMContentLoaded', function() {
            if (typeof showAddStoreModal === 'undefined') {
                showAddStoreModal = window.showAddStoreModal;
            }
            if (typeof submitAddStore === 'undefined') {
                submitAddStore = window.submitAddStore;
            }
            console.log('✅ DOMContentLoaded - 함수 등록 확인:', {
                showAddStoreModal: typeof showAddStoreModal,
                submitAddStore: typeof submitAddStore
            });
        });
        
        // 🔥 PM 긴급 요구사항: 계정 생성 및 안내 모달 표시
        window.createAccountAndShowCredentials = async function(storeId, storeData) {
            console.log('🔥 매장 ID', storeId, '에 대한 자동 계정 생성 시작');
            
            try {
                const response = await fetch(`/api/stores/${storeId}/account`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                        'Accept': 'application/json'
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({})
                });
                
                const accountResult = await response.json();
                console.log('계정 생성 API 응답:', accountResult);
                
                if (accountResult.success && accountResult.data && accountResult.data.account) {
                    const account = accountResult.data.account;
                    showPMAccountModal(account, storeData);
                } else {
                    alert('매장은 생성되었지만 계정 생성에 실패했습니다: ' + (accountResult.error || '알 수 없는 오류'));
                    location.reload();
                }
            } catch (error) {
                console.error('계정 생성 오류:', error);
                alert('매장은 생성되었지만 계정 생성 중 오류가 발생했습니다');
                location.reload();
            }
        };
        
        // PM 요구사항: 계정 정보 안내 모달
        window.showPMAccountModal = function(account, store) {
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
                                <span class="ml-2 font-bold text-blue-600">${store.name} (${store.code})</span>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-3">
                            <span class="text-2xl">👤</span>
                            <div class="flex-1">
                                <span class="font-semibold text-gray-700">계정:</span>
                                <div class="flex items-center space-x-2 mt-1">
                                    <code class="bg-white px-3 py-2 rounded border text-blue-600 font-mono flex-1">${account.email}</code>
                                    <button onclick="copyToClipboard('${account.email}')" class="px-3 py-2 bg-blue-500 text-white rounded text-sm hover:bg-blue-600">복사</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-3">
                            <span class="text-2xl">🔑</span>
                            <div class="flex-1">
                                <span class="font-semibold text-gray-700">비밀번호:</span>
                                <div class="flex items-center space-x-2 mt-1">
                                    <code class="bg-white px-3 py-2 rounded border text-green-600 font-mono flex-1">${account.password}</code>
                                    <button onclick="copyToClipboard('${account.password}')" class="px-3 py-2 bg-green-500 text-white rounded text-sm hover:bg-green-600">복사</button>
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
                        <button onclick="this.closest('.fixed').remove(); location.reload();" class="px-8 py-3 bg-green-500 text-white rounded-lg hover:bg-green-600 font-semibold">
                            ✅ 확인완료
                        </button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        };
        
        // 클립보드 복사 함수
        window.copyToClipboard = function(text) {
            navigator.clipboard.writeText(text).then(() => {
                alert('복사되었습니다!');
            }).catch(() => {
                alert('복사에 실패했습니다');
            });
        };
        
        console.log('✅ 헤드 섹션에서 showAddStoreModal 전역 등록 완료');
    </script>
</head>
<body class="bg-gray-50">
    <!-- 헤더 -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-gray-900">매장 관리</h1>
                    @if(auth()->user()->role === 'headquarters')
                        <span class="ml-2 px-2 py-1 text-xs bg-red-100 text-red-800 rounded">🏢 본사 전용</span>
                    @elseif(auth()->user()->role === 'branch')
                        <span class="ml-2 px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded">🏬 지사 전용</span>
                    @elseif(auth()->user()->role === 'store')
                        <span class="ml-2 px-2 py-1 text-xs bg-green-100 text-green-800 rounded">🏪 매장 전용</span>
                    @endif
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/dashboard" class="text-gray-600 hover:text-gray-900">대시보드</a>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto py-6 px-4">
        <!-- 권한별 안내 -->
        @if(auth()->user()->role === 'headquarters')
            <div class="bg-red-500 text-white p-4 mb-6 rounded-lg shadow-lg">
                <div class="flex items-center">
                    <div class="text-2xl mr-3">🏢</div>
                    <div>
                        <h3 class="text-lg font-semibold">본사 관리자님 환영합니다</h3>
                        <p class="text-red-100 text-sm mt-1">지사와 매장을 통합 관리할 수 있습니다.</p>
                    </div>
                </div>
            </div>
        @elseif(auth()->user()->role === 'branch')
            <div class="bg-blue-500 text-white p-4 mb-6 rounded-lg shadow-lg">
                <div class="flex items-center">
                    <div class="text-2xl mr-3">🏬</div>
                    <div>
                        <h3 class="text-lg font-semibold">지사 관리자님 환영합니다</h3>
                        <p class="text-blue-100 text-sm mt-1">소속 지사 매장을 관리할 수 있습니다.</p>
                    </div>
                </div>
            </div>
        @elseif(auth()->user()->role === 'store')
            <div class="bg-green-500 text-white p-4 mb-6 rounded-lg shadow-lg">
                <div class="flex items-center">
                    <div class="text-2xl mr-3">🏪</div>
                    <div>
                        <h3 class="text-lg font-semibold">매장 관리자님 환영합니다</h3>
                        <p class="text-green-100 text-sm mt-1">자기 매장의 개통표와 성과를 확인할 수 있습니다.</p>
                    </div>
                </div>
            </div>
        @endif
        
        
        <!-- 매장 관리 메인 콘텐츠 -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-lg font-medium">매장 목록</h2>
                    @if(in_array(auth()->user()->role, ['headquarters', 'branch']))
                        <button onclick="showAddStoreModal()" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 font-semibold">
                            ➕ 매장 추가
                        </button>
                    @endif
                </div>
                <div id="stores-grid" class="bg-white rounded border">
                    @if(isset($stores) && $stores->count() > 0)
                        {{-- 지사별 필터 안내 --}}
                        @if(isset($branchFilter))
                            <div class="mb-4 p-4 bg-blue-50 rounded-lg border border-blue-200">
                                <p class="text-blue-800 font-semibold">🎯 지사별 매장 보기</p>
                                <p class="text-blue-600 text-sm">선택된 지사의 매장만 표시 중</p>
                                <a href="/management/stores" class="text-blue-500 hover:text-blue-700 text-sm font-medium">← 전체 매장 보기</a>
                            </div>
                        @endif
                        
                        <div class="space-y-6">
                            @php
                                $storesByBranch = $stores->groupBy('branch.name');
                            @endphp
                            
                            @foreach($storesByBranch as $branchName => $branchStores)
                                <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
                                    <div class="bg-gradient-to-r from-blue-500 to-indigo-600 px-6 py-4">
                                        <h3 class="text-xl font-bold text-white">🏢 {{ $branchName ?: '미배정 지사' }} ({{ $branchStores->count() }}개 매장)</h3>
                                    </div>
                                    <div class="p-6">
                                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                                            @foreach($branchStores as $store)
                                                <div class="bg-gray-50 rounded-lg p-4 hover:bg-white hover:shadow-md transition-all border">
                                                    <div class="flex justify-between items-start mb-3">
                                                        <h4 class="font-bold text-lg">{{ $store->name }}</h4>
                                                        <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded-full">✅ 운영중</span>
                                                    </div>
                                                    <div class="text-sm text-gray-600 space-y-1">
                                                        <p><span class="font-medium">코드:</span> {{ $store->code }}</p>
                                                        <p><span class="font-medium">점주:</span> {{ $store->owner_name ?: '미등록' }}</p>
                                                        <p><span class="font-medium">연락처:</span> {{ $store->phone ?: '미등록' }}</p>
                                                        @if($store->opened_at)
                                                            <p><span class="font-medium">개점일:</span> {{ $store->opened_at->format('Y. m. d.') }}</p>
                                                        @endif
                                                    </div>
                                                    <div class="mt-3 flex gap-2">
                                                        <button onclick="editStore({{ $store->id }}, '{{ $store->name }}')" class="store-edit-btn px-3 py-1 bg-blue-500 text-white text-xs rounded hover:bg-blue-600">✏️ 수정</button>
                                                        <button onclick="
                                                            const name = prompt('{{ $store->name }} 매장 관리자 이름:', '{{ $store->name }} 관리자');
                                                            if (!name) return;
                                                            const email = prompt('이메일:', '{{ strtolower(preg_replace('/[^가-힣a-zA-Z0-9]/', '', $store->name)) }}@ykp.com');
                                                            if (!email) return;
                                                            const password = prompt('비밀번호 (6자리 이상):', '123456');
                                                            if (!password || password.length < 6) { alert('비밀번호는 6자리 이상'); return; }
                                                            fetch('/api/stores/{{ $store->id }}/create-user', {
                                                                method: 'POST',
                                                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                                                                body: JSON.stringify({name, email, password})
                                                            }).then(r => r.json()).then(result => {
                                                                if (result.success) alert('✅ 계정 생성 완료!\n이메일: ' + email + '\n비밀번호: ' + password);
                                                                else alert('❌ 생성 실패: ' + (result.error || '오류'));
                                                            }).catch(e => alert('❌ 네트워크 오류'));
                                                        " class="store-account-btn px-3 py-1 bg-green-500 text-white text-xs rounded hover:bg-green-600">👤 계정</button>
                                                        <button onclick="
                                                            if (confirm('⚠️ {{ $store->name }} 매장을 삭제하시겠습니까?\\n\\n되돌릴 수 없습니다.')) {
                                                                fetch('/api/stores/{{ $store->id }}', {
                                                                    method: 'DELETE',
                                                                    headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' }
                                                                }).then(r => r.json()).then(result => {
                                                                    if (result.success) { alert('✅ {{ $store->name }} 삭제됨'); location.reload(); }
                                                                    else alert('❌ 삭제 실패: ' + (result.error || '오류'));
                                                                }).catch(e => alert('❌ 네트워크 오류'));
                                                            }
                                                        " class="store-delete-btn px-2 py-1 bg-red-500 text-white text-xs rounded hover:bg-red-600">🗑️ 삭제</button>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="p-8 text-center text-gray-500">
                            <div class="text-4xl mb-4">🏪</div>
                            <p class="text-lg font-medium">매장이 없습니다</p>
                            <p class="text-sm text-gray-400 mt-2">새 매장을 추가해보세요</p>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </main>

    <!-- 모던 모달들 -->
    
    <!-- 매장 추가 모달 -->
    <div id="add-store-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">🏪 새 매장 추가</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">매장명</label>
                    <input type="text" id="modal-store-name" placeholder="예: 강남점" 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div id="branch-select-container">
                    <label class="block text-sm font-medium text-gray-700 mb-1">지사 선택</label>
                    <select id="modal-branch-select" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">지사를 선택하세요...</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">점주명</label>
                    <input type="text" id="modal-owner-name" placeholder="예: 김사장"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">연락처</label>
                    <input type="tel" id="modal-phone" placeholder="010-1234-5678"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                <button onclick="closeAddStoreModal()" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                    취소
                </button>
                <button onclick="submitAddStore()" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                    ✅ 매장 추가
                </button>
            </div>
        </div>
    </div>

    <!-- 지사 추가 모달 -->
    <div id="add-branch-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">🏢 새 지사 추가</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">지사명</label>
                    <input type="text" id="modal-branch-name" placeholder="예: 대구지사" 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">지사코드</label>
                    <input type="text" id="modal-branch-code" placeholder="예: BR004" 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                    <p class="text-xs text-gray-500 mt-1">영문 대문자 + 숫자 조합 (예: BR004)</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">관리자명</label>
                    <input type="text" id="modal-branch-manager" placeholder="예: 김지사장"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">연락처</label>
                    <input type="tel" id="modal-branch-phone" placeholder="053-1234-5678"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">주소</label>
                    <input type="text" id="modal-branch-address" placeholder="대구광역시 중구 ..."
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div class="bg-blue-50 p-3 rounded-lg">
                    <p class="text-sm text-blue-800">
                        <strong>📝 자동 생성:</strong> 지사 관리자 계정이 자동으로 생성됩니다<br>
                        <strong>이메일:</strong> branch_{지사코드}@ykp.com<br>
                        <strong>패스워드:</strong> 123456
                    </p>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                <button onclick="closeAddBranchModal()" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                    취소
                </button>
                <button onclick="submitAddBranch()" class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600">
                    ✅ 지사 추가
                </button>
            </div>
        </div>
    </div>

    <!-- 지사 수정 모달 -->
    <div id="edit-branch-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">🏢 지사 정보 수정</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">지사명</label>
                    <input type="text" id="edit-branch-name" 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">지사코드</label>
                    <input type="text" id="edit-branch-code" 
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                    <p class="text-xs text-gray-500 mt-1">변경 시 중복 확인됩니다</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">관리자명</label>
                    <input type="text" id="edit-branch-manager"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">연락처</label>
                    <input type="tel" id="edit-branch-phone"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">주소</label>
                    <input type="text" id="edit-branch-address"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">상태</label>
                    <select id="edit-branch-status" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                        <option value="active">운영중</option>
                        <option value="inactive">일시중단</option>
                        <option value="closed">폐점</option>
                    </select>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 flex justify-between">
                <button onclick="deleteBranch(currentEditBranchId)" class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600">
                    🗑️ 지사 삭제
                </button>
                <div class="space-x-3">
                    <button onclick="closeEditBranchModal()" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                        취소
                    </button>
                    <button onclick="submitEditBranch()" class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600">
                        💾 변경사항 저장
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 계정 생성 모달 -->
    <div id="add-user-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md mx-4">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">👤 매장 계정 생성</h3>
            </div>
            <div class="px-6 py-4 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">사용자명</label>
                    <input type="text" id="modal-user-name" placeholder="예: 김직원"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">이메일</label>
                    <input type="email" id="modal-user-email" placeholder="kim@store.com"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">비밀번호</label>
                    <input type="password" id="modal-user-password" value="123456"
                           class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-green-500">
                    <p class="text-xs text-gray-500 mt-1">기본 비밀번호: 123456 (나중에 변경 가능)</p>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                <button onclick="closeAddUserModal()" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                    취소
                </button>
                <button onclick="submitAddUser()" class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600">
                    👤 계정 생성
                </button>
            </div>
        </div>
    </div>

    <!-- 매장 수정 모달 (반응형 최적화) -->
    <div id="edit-store-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md max-h-[85vh] overflow-y-auto flex flex-col">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-lg font-medium text-gray-900">🏪 매장 정보 수정</h3>
                <button onclick="closeEditStoreModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="px-6 py-4 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">매장명</label>
                        <input type="text" id="edit-store-name" 
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">매장 코드</label>
                        <input type="text" id="edit-store-code" readonly
                               class="w-full border border-gray-300 rounded-md px-3 py-2 bg-gray-50 text-gray-500">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">점주명</label>
                        <input type="text" id="edit-owner-name"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">연락처</label>
                        <input type="tel" id="edit-phone"
                               class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">주소</label>
                    <textarea id="edit-address" rows="2" 
                              class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">상태</label>
                        <select id="edit-status" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="active">운영중</option>
                            <option value="inactive">일시중단</option>
                            <option value="closed">폐점</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">소속 지사</label>
                        <select id="edit-branch-select" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">지사를 선택하세요...</option>
                        </select>
                    </div>
                </div>
                
                <!-- 간단한 매장 통계 (수정 모달용) -->
                <div class="bg-gray-50 rounded-lg p-3 mt-3">
                    <div class="flex justify-between items-center mb-2">
                        <span class="text-sm font-medium text-gray-700">📊 오늘 매출</span>
                        <span class="text-lg font-bold text-blue-600" id="edit-today-sales">₩0</span>
                    </div>
                    <div class="flex justify-between items-center">
                        <span class="text-sm font-medium text-gray-700">📊 이번달 매출</span>
                        <span class="text-lg font-bold text-green-600" id="edit-month-sales">₩0</span>
                    </div>
                    <button onclick="viewDetailedStats(currentEditStoreId)" 
                            class="w-full mt-2 text-xs text-blue-600 hover:text-blue-800 border border-blue-200 rounded py-1">
                        📈 상세 성과보기
                    </button>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 flex justify-between">
                <button onclick="deleteStore()" class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600">
                    🗑️ 매장 삭제
                </button>
                <div class="space-x-3">
                    <button onclick="closeEditStoreModal()" class="px-4 py-2 text-gray-600 border border-gray-300 rounded-md hover:bg-gray-50">
                        취소
                    </button>
                    <button onclick="submitEditStore()" class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                        💾 변경사항 저장
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 성과보기 전용 모달 (반응형 최적화) -->
    <div id="store-stats-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-5xl max-h-[90vh] overflow-y-auto flex flex-col">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 class="text-xl font-medium text-gray-900">📈 <span id="stats-store-name">매장명</span> 성과 대시보드</h3>
                <button onclick="closeStoreStatsModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="px-6 py-6">
                <!-- KPI 카드들 -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
                    <div class="bg-blue-50 rounded-lg p-4">
                        <div class="text-sm text-blue-600 font-medium">오늘 매출</div>
                        <div class="text-2xl font-bold text-blue-700" id="stats-today-sales">₩0</div>
                        <div class="text-xs text-blue-500" id="stats-today-change">전일 대비 -</div>
                    </div>
                    <div class="bg-green-50 rounded-lg p-4">
                        <div class="text-sm text-green-600 font-medium">이번달 매출</div>
                        <div class="text-2xl font-bold text-green-700" id="stats-month-sales">₩0</div>
                        <div class="text-xs text-green-500" id="stats-month-change">전월 대비 -</div>
                    </div>
                    <div class="bg-purple-50 rounded-lg p-4">
                        <div class="text-sm text-purple-600 font-medium">전체 순위</div>
                        <div class="text-2xl font-bold text-purple-700" id="stats-rank">#-</div>
                        <div class="text-xs text-purple-500" id="stats-rank-change">순위 변동 -</div>
                    </div>
                    <div class="bg-orange-50 rounded-lg p-4">
                        <div class="text-sm text-orange-600 font-medium">목표 달성률</div>
                        <div class="text-2xl font-bold text-orange-700" id="stats-goal">0%</div>
                        <div class="text-xs text-orange-500">월간 목표 대비</div>
                    </div>
                </div>
                
                <!-- 차트 영역 -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <div class="bg-white border rounded-lg p-4">
                        <h4 class="text-lg font-medium mb-4">📈 최근 30일 매출 추이</h4>
                        <canvas id="store-sales-chart" width="400" height="200"></canvas>
                    </div>
                    <div class="bg-white border rounded-lg p-4">
                        <h4 class="text-lg font-medium mb-4">📊 통신사별 비율</h4>
                        <canvas id="store-carrier-chart" width="400" height="200"></canvas>
                    </div>
                </div>
                
                <!-- 최근 거래 내역 -->
                <div class="bg-white border rounded-lg p-4">
                    <h4 class="text-lg font-medium mb-4">📋 최근 거래 내역</h4>
                    <div id="recent-transactions" class="space-y-2">
                        <!-- 동적 로드 -->
                    </div>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 flex justify-end">
                <button onclick="closeStoreStatsModal()" class="px-6 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600">
                    닫기
                </button>
            </div>
        </div>
    </div>

    <!-- 토스트 알림 -->
    <div id="toast-container" class="fixed top-4 right-4 z-50 space-y-2">
        <!-- 동적 생성 -->
    </div>

    <style>
        /* 탭 스타일 제거됨 - 단순한 매장 관리 페이지로 변경 */
        .stores-main-content {
            min-height: 400px;
        }
    </style>

    <script>
        // 클린코드: 상수 정의 (매직넘버 제거)
        const CONFIG = {
            TOAST_DURATION: 3000,
            API_TIMEOUT: 5000,
            MODAL_ANIMATION: 300,
            MAX_RECENT_TRANSACTIONS: 5,
            ROLES: {
                HEADQUARTERS: 'headquarters',
                BRANCH: 'branch', 
                STORE: 'store'
            },
            TOAST_TYPES: {
                SUCCESS: 'success',
                ERROR: 'error',
                INFO: 'info'
            }
        };

        // 클린코드: 권한 관리 클래스 (DRY + OCP 원칙)
        class PermissionManager {
            constructor(userData) {
                this.user = userData;
            }
            
            // 매장 수정 권한 체크
            canEditStore(storeId) {
                if (this.user.role === 'headquarters') return true;
                if (this.user.role === 'branch') {
                    // 지사는 소속 매장만 수정 가능
                    return this.isStoreBelongsToBranch(storeId);
                }
                return false; // 매장 직원은 수정 불가
            }
            
            // 성과 조회 권한 체크
            canViewStats(storeId) {
                if (this.user.role === 'headquarters') return true;
                if (this.user.role === 'branch') {
                    return this.isStoreBelongsToBranch(storeId);
                }
                if (this.user.role === 'store') {
                    return storeId === this.user.store_id;
                }
                return false;
            }
            
            // 매장 추가 권한 체크
            canAddStore() {
                return ['headquarters', 'branch'].includes(this.user.role);
            }
            
            // 지사 소속 매장 확인 (실시간 API 기반)
            isStoreBelongsToBranch(storeId) {
                // 현재 로드된 매장 데이터에서 확인
                if (window.loadedStores && Array.isArray(window.loadedStores)) {
                    const store = window.loadedStores.find(s => s.id == storeId);
                    if (store) {
                        return store.branch_id === this.user.branch_id;
                    }
                }

                // 로드된 데이터가 없으면 API로 확인
                if (this.user.branch_id) {
                    // 동기적으로 매장 정보 확인 (캐시된 데이터 사용)
                    try {
                        const cachedStore = localStorage.getItem(`store_${storeId}`);
                        if (cachedStore) {
                            const store = JSON.parse(cachedStore);
                            return store.branch_id === this.user.branch_id;
                        }
                    } catch (e) {
                        console.warn('캐시된 매장 데이터 확인 실패:', e);
                    }

                    // 🚨 보안 강화: 매장 정보를 확인할 수 없으면 false 반환
                    console.warn(`⚠️ 매장 ${storeId} 소속 확인 불가 - 접근 차단`);
                    return false;
                }

                return false;
            }
            
            // 접근 가능한 매장 목록 필터링
            filterAccessibleStores(stores) {
                if (this.user.role === 'headquarters') {
                    return stores; // 모든 매장 접근
                }
                if (this.user.role === 'branch') {
                    return stores.filter(store => store.branch_id === this.user.branch_id);
                }
                if (this.user.role === 'store') {
                    return stores.filter(store => store.id === this.user.store_id);
                }
                return [];
            }
        }

        // 사용자 정보 설정
        window.userData = {
            id: {{ auth()->user()->id ?? 1 }},
            name: '{{ auth()->user()->name ?? "본사 관리자" }}',
            role: '{{ auth()->user()->role ?? "headquarters" }}',
            store_id: {{ auth()->user()->store_id ?? 'null' }},
            branch_id: {{ auth()->user()->branch_id ?? 'null' }}
        };

        // 클린코드: 권한 관리자 인스턴스 생성
        window.permissionManager = new PermissionManager(window.userData);
        
        // 권한 체크 (개선된 방식)
        if (!window.permissionManager.canAddStore() && window.userData.role !== 'headquarters') {
            document.getElementById('user-role').textContent = '접근 권한 없음';
            document.getElementById('user-role').className = 'ml-2 px-2 py-1 text-xs bg-red-100 text-red-800 rounded';
            showToast('본사 또는 지사 관리자만 접근 가능한 페이지입니다.', 'error');
            setTimeout(() => window.location.href = '/dashboard', 2000);
        }

        // 탭 시스템 제거됨 - 직접 매장 관리만 표시

        // ✨ 최고 우선순위: loadStores 함수 정의 (다른 모든 것보다 먼저)
        window.loadStores = async function() {
            console.log('🔄 loadStores 시작');
            
            try {
                // 로딩 메시지 표시
                const gridElement = document.getElementById('stores-grid');
                if (!gridElement) {
                    console.error('❌ stores-grid 요소를 찾을 수 없음');
                    return;
                }
                
                gridElement.innerHTML = '<div class="p-4 text-center text-gray-500">🔄 매장 목록 로딩 중...</div>';
                
                // Supabase 실제 API 호출
                const response = await fetch('/api/dev/stores/list');
                console.log('✅ API 응답 상태:', response.status);
                
                const data = await response.json();
                console.log('✅ 받은 데이터:', data.data?.length + '개 매장');
                
                if (data.success && data.data && Array.isArray(data.data)) {
                    // 매장 카드 생성 (매우 단순한 HTML)
                    const html = data.data.map(store => `
                        <div class="bg-white p-4 rounded-lg border shadow-sm mb-4">
                            <h3 class="font-bold text-lg mb-2">${store.name}</h3>
                            <div class="text-sm text-gray-600 space-y-1">
                                <p>📍 ${store.code}</p>
                                <p>👤 ${store.owner_name}</p>
                                <p>📞 ${store.phone || '미등록'}</p>
                                <p>🏢 ${store.branch?.name || '미지정'}</p>
                            </div>
                            <div class="mt-3 flex gap-2">
                                <button class="px-2 py-1 bg-blue-500 text-white rounded text-xs">✏️ 수정</button>
                                <button class="px-2 py-1 bg-red-500 text-white rounded text-xs">🗑️ 삭제</button>
                                <button class="px-2 py-1 bg-green-500 text-white rounded text-xs">👤 계정</button>
                            </div>
                        </div>
                    `).join('');
                    
                    gridElement.innerHTML = `<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">${html}</div>`;
                    console.log('✅ 매장 목록 표시 완료');
                } else {
                    throw new Error('유효하지 않은 API 응답');
                }
                
            } catch (error) {
                console.error('❌ loadStores 오류:', error);
                const gridElement = document.getElementById('stores-grid');
                if (gridElement) {
                    gridElement.innerHTML = `
                        <div class="p-4 text-center text-red-500">
                            <p>❌ 매장 목록 로딩 실패</p>
                            <button onclick="window.loadStores()" class="mt-2 px-4 py-2 bg-blue-500 text-white rounded">🔄 재시도</button>
                        </div>
                    `;
                }
            }
        };
        
        // 본사용: 지사별 트리 구조 표시 (간소화된 버전)
        function renderStoreTreeView(stores) {
            console.log('매장 렌더링 시작:', stores.length, '개');
            
            // 지사별로 매장 그룹화
            const storesByBranch = {};
            stores.forEach(store => {
                const branchName = store.branch?.name || '미배정';
                if (!storesByBranch[branchName]) {
                    storesByBranch[branchName] = [];
                }
                storesByBranch[branchName].push(store);
            });
            
            console.log('지사별 그룹화 완료:', Object.keys(storesByBranch));
            
            let html = '<div class="space-y-4">';
            
            try {
                Object.entries(storesByBranch).forEach(([branchName, branchStores]) => {
                    html += `
                        <div class="border border-gray-200 rounded-lg">
                            <div class="bg-gray-50 px-4 py-3 border-b border-gray-200">
                                <div class="flex justify-between items-center">
                                    <h3 class="text-lg font-medium text-gray-900">
                                        🏢 ${branchName} (${branchStores.length}개 매장)
                                    </h3>
                                    <button onclick="addStoreForBranch(${branchStores[0]?.branch_id || 1})" 
                                            class="text-sm bg-green-500 text-white px-3 py-1 rounded hover:bg-green-600">
                                        ➕ ${branchName} 매장 추가
                                    </button>
                                </div>
                            </div>
                            <div class="p-4">
                    `;
                    
                    if (branchStores.length > 0) {
                        html += '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';
                        branchStores.forEach(store => {
                            html += `
                                <div class="bg-white border border-gray-200 rounded-lg p-4">
                                    <div class="flex justify-between items-start mb-2">
                                        <h4 class="text-base font-medium text-gray-900">${store.name}</h4>
                                        <span class="text-xs bg-green-100 text-green-800 px-2 py-1 rounded">운영중</span>
                                    </div>
                                    <div class="space-y-1 text-sm text-gray-500">
                                        <div>👤 점주: ${store.owner_name || '미등록'}</div>
                                        <div>📞 연락처: ${store.phone || store.contact_number || '-'}</div>
                                    </div>
                                    <div class="mt-3 flex space-x-1">
                                        <button onclick="editStore(${store.id})" 
                                                class="text-xs bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600">
                                            수정
                                        </button>
                                        <button onclick="createUserForStore(${store.id})"
                                                class="text-xs bg-green-500 text-white px-2 py-1 rounded hover:bg-green-600">
                                            계정생성
                                        </button>
                                        <button onclick="viewStoreStats(${store.id}, '${store.name}')"
                                                class="text-xs bg-purple-500 text-white px-2 py-1 rounded hover:bg-purple-600">
                                            📊 성과
                                        </button>
                                        <button onclick="deleteStore(${store.id})"
                                                class="text-xs bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600">
                                            🗑️ 삭제
                                        </button>
                                    </div>
                                </div>
                            `;
                        });
                        html += '</div>';
                    } else {
                        html += '<div class="text-center text-gray-500 py-8"><div class="text-4xl mb-2">🏪</div><p>이 지사에는 매장이 없습니다.</p></div>';
                    }
                    
                    html += '</div></div>'; // 지사 블록 닫기
                });
                
                html += '</div>';
                document.getElementById('stores-grid').innerHTML = html;
                console.log('매장 렌더링 완료');
                
            } catch (error) {
                console.error('렌더링 오류:', error);
                document.getElementById('stores-grid').innerHTML = `
                    <div class="p-4 text-center text-red-500">
                        ❌ 매장 목록을 표시할 수 없습니다: ${error.message}
                    </div>
                `;
            }
        }
                            <div class="flex justify-between items-center">
                                <h3 class="text-lg font-medium text-gray-900">
                                    🏢 ${branchName} (${branchStores.length}개 매장)
                                </h3>
                                <button onclick="addStoreForBranch(${branchStores[0]?.branch_id || 1})" 
                                        class="text-sm bg-blue-500 text-white px-3 py-1 rounded hover:bg-blue-600">
                                    ➕ ${branchName} 매장 추가
                                </button>
                            </div>
                        </div>
                        <div class="p-4">
                `;
                
                if (branchStores.length > 0) {
                    html += '<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">';
                    branchStores.forEach(store => {
                        const statusColor = store.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                        html += `
                            <div class="border border-gray-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                <div class="flex justify-between items-start mb-2">
                                    <h4 class="font-medium text-gray-900">${store.name}</h4>
                                    <span class="px-2 py-1 text-xs rounded-full ${statusColor}">
                                        ${store.status === 'active' ? '운영중' : '중단'}
                                    </span>
                                </div>
                                <div class="text-sm text-gray-500 space-y-1">
                                    <div>👤 점주: ${store.owner_name || '-'}</div>
                                    <div>📞 연락처: ${store.phone || '-'}</div>
                                </div>
                                <div class="mt-3 flex gap-2">
                                    <button onclick="editStore(${store.id})" 
                                            class="text-xs bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600">
                                        수정
                                    </button>
                                    <button onclick="createUserForStore(${store.id})"
                                            class="text-xs bg-green-500 text-white px-2 py-1 rounded hover:bg-green-600">
                                        계정생성
                                    </button>
                                    <button onclick="viewStoreStats(${store.id}, '${store.name}')"
                                            class="text-xs bg-purple-500 text-white px-2 py-1 rounded hover:bg-purple-600">
                                        📊 성과
                                    </button>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                } else {
                    html += '<div class="text-center text-gray-500 py-4">이 지사에는 매장이 없습니다.</div>';
                }
                
                html += '</div></div>';
            });
            
            html += '</div>';
            document.getElementById('stores-grid').innerHTML = html;
        }
        
        // 지사용: 테이블 형태 표시
        function renderStoreTableView(stores) {
            let html = `
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">매장명</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">점주</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">연락처</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">상태</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">액션</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
            `;
            
            if (stores && stores.length > 0) {
                stores.forEach(store => {
                    const statusColor = store.status === 'active' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                    html += `
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${store.name}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${store.owner_name || '-'}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${store.phone || '-'}</td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="px-2 py-1 text-xs rounded-full ${statusColor}">
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
                html += '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">소속 매장이 없습니다.</td></tr>';
            }
            
            html += '</tbody></table></div>';
            document.getElementById('stores-grid').innerHTML = html;
        }

        // 지사 목록 로드
        function loadBranches() {
            document.getElementById('branches-grid').innerHTML = '<div class="p-4 text-center text-gray-500">지사 목록 로딩 중...</div>';
            
            fetch('/api/branches')
                .then(response => response.json())
                .then(data => {
                    let html = `
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">지사명</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">코드</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">관리자</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">매장 수</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">액션</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                    `;
                    
                    if (data.data && data.data.length > 0) {
                        data.data.forEach(branch => {
                            html += `
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${branch.name}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${branch.code}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${branch.manager_name || '-'}</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${branch.stores_count || 0}개</td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <button onclick="editBranch(${branch.id})" class="text-blue-600 hover:text-blue-900">수정</button>
                                    </td>
                                </tr>
                            `;
                        });
                    } else {
                        html += '<tr><td colspan="5" class="px-6 py-4 text-center text-gray-500">지사가 없습니다.</td></tr>';
                    }
                    
                    html += '</tbody></table></div>';
                    document.getElementById('branches-grid').innerHTML = html;
                })
                .catch(error => {
                    document.getElementById('branches-grid').innerHTML = '<div class="p-4 text-center text-red-500">지사 목록 로드 실패</div>';
                });
        }
        
        // 지사 목록 로드
        function loadBranches() {
            document.getElementById('branches-grid').innerHTML = '<div class="p-4 text-center text-gray-500">지사 목록 로딩 중...</div>';
            
            fetch('/api/branches')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        renderBranches(data.data);
                    } else {
                        document.getElementById('branches-grid').innerHTML = '<div class="p-4 text-center text-red-500">지사 목록 로딩 실패</div>';
                    }
                })
                .catch(error => {
                    console.error('지사 목록 로딩 오류:', error);
                    document.getElementById('branches-grid').innerHTML = '<div class="p-4 text-center text-red-500">지사 목록 로딩 중 오류 발생</div>';
                });
        }
        
        function renderBranches(branches) {
            if (!branches || branches.length === 0) {
                document.getElementById('branches-grid').innerHTML = `
                    <div class="p-8 text-center text-gray-500">
                        <div class="text-4xl mb-4">🏢</div>
                        <p class="text-lg font-medium">등록된 지사가 없습니다</p>
                        <p class="text-sm text-gray-400 mt-2">새 지사를 추가해보세요</p>
                    </div>
                `;
                return;
            }

            let branchesHtml = `
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">지사명</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">지사코드</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">관리자</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">매장 수</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">상태</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">관리</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
            `;

            branches.forEach(branch => {
                // 상태별 표시 스타일
                let statusBadge = '';
                let statusText = '';
                if (branch.status === 'active') {
                    statusBadge = 'bg-green-100 text-green-800';
                    statusText = '운영중';
                } else if (branch.status === 'inactive') {
                    statusBadge = 'bg-yellow-100 text-yellow-800';
                    statusText = '일시중단';
                } else {
                    statusBadge = 'bg-red-100 text-red-800';
                    statusText = '폐점';
                }

                branchesHtml += `
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="text-sm font-medium text-gray-900">🏢 ${branch.name}</div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <code class="bg-gray-100 px-2 py-1 rounded text-xs">${branch.code}</code>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${branch.manager_name || '-'}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                ${branch.stores_count || 0}개 매장
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${statusBadge}">
                                ${statusText}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <button onclick="editBranch(${branch.id})" 
                                    class="text-green-600 hover:text-green-900 mr-3 font-medium">
                                ✏️ 수정
                            </button>
                            <button onclick="viewBranchStats(${branch.id})" 
                                    class="text-blue-600 hover:text-blue-900 font-medium">
                                📊 통계
                            </button>
                        </td>
                    </tr>
                `;
            });

            branchesHtml += `
                    </tbody>
                </table>
            `;
            
            document.getElementById('branches-grid').innerHTML = branchesHtml;
        }
        
        // 지사 통계 조회 (완전 구현)
        function viewBranchStats(branchId) {
            console.log('📊 지사 통계 조회 시작:', branchId);

            // 먼저 지사 정보 로드
            fetch(`/api/branches/${branchId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const branch = data.data;

                        // 지사 통계 안내 메시지
                        let statsMessage = `📊 ${branch.name} 지사 통계 안내\n`;
                        statsMessage += `${'='.repeat(40)}\n\n`;
                        statsMessage += `📈 확인 가능한 정보:\n`;
                        statsMessage += `• 소속 매장별 매출 현황\n`;
                        statsMessage += `• 지사 전체 성과 분석\n`;
                        statsMessage += `• 매장 간 성과 비교\n`;
                        statsMessage += `• 목표 달성률 분석\n`;
                        statsMessage += `• 기간별 성과 추이\n\n`;
                        statsMessage += `상세 통계 페이지로 이동하시겠습니까?`;

                        if (confirm(statsMessage)) {
                            console.log(`✅ ${branch.name} 지사 통계 페이지로 이동`);

                            // 지사별 통계 페이지로 이동
                            window.location.href = `/statistics/enhanced?branch=${branchId}&name=${encodeURIComponent(branch.name)}&role=branch`;
                        } else {
                            console.log('❌ 사용자가 통계 페이지 이동 취소');
                        }
                    } else {
                        alert('❌ 지사 정보를 불러올 수 없습니다: ' + (data.error || '알 수 없는 오류'));
                    }
                })
                .catch(error => {
                    console.error('지사 정보 로드 오류:', error);
                    alert('❌ 지사 정보 로드 중 오류가 발생했습니다.');
                });
        }

        // 🔄 매장 데이터 캐싱 시스템 (권한 체크용)
        function cacheStoreData() {
            fetch('/api/stores')
                .then(response => response.json())
                .then(data => {
                    if (data.success && Array.isArray(data.data)) {
                        // 전역 변수에 저장
                        window.loadedStores = data.data;

                        // localStorage에도 캐싱 (5분 TTL)
                        data.data.forEach(store => {
                            const cacheData = {
                                ...store,
                                cached_at: Date.now(),
                                ttl: 300000 // 5분
                            };
                            localStorage.setItem(`store_${store.id}`, JSON.stringify(cacheData));
                        });

                        console.log(`✅ 매장 데이터 캐싱 완료: ${data.data.length}개 매장`);
                    }
                })
                .catch(error => {
                    console.warn('⚠️ 매장 데이터 캐싱 실패:', error);
                });
        }

        // 사용자 목록 로드
        function loadUsers() {
            document.getElementById('users-grid').innerHTML = '<div class="p-4 text-center text-gray-500">사용자 목록 로딩 중...</div>';

            fetch('/api/users')
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

        // 모던 모달: 지사별 매장 추가
        function addStoreForBranch(branchId) {
            document.getElementById('modal-branch-select').value = branchId;
            document.getElementById('branch-select-container').style.display = 'none'; // 지사 고정
            showAddStoreModal();
        }
        
        // 모던 모달: 일반 매장 추가  
        function addStore() {
            document.getElementById('branch-select-container').style.display = 'block'; // 지사 선택 가능
            showAddStoreModal();
        }
        
        // 매장 추가 모달 표시
        // PM 지시: 전역 등록으로 ReferenceError 완전 해결
        window.showAddStoreModal = function() {
            console.log('✅ showAddStoreModal 전역 함수 호출됨');
            // 지사 목록을 동적으로 로드
            loadBranchOptions('modal-branch-select');
            document.getElementById('add-store-modal').classList.remove('hidden');
            document.getElementById('modal-store-name').focus();
        }
        
        // 지사 옵션 동적 로드 함수 (지사별 권한 필터링 적용)
        function loadBranchOptions(selectId) {
            const select = document.getElementById(selectId);
            const userRole = '{{ auth()->user()->role }}';
            const userBranchId = '{{ auth()->user()->branch_id }}';

            select.innerHTML = '<option value="">로딩 중...</option>';

            // 지사 계정인 경우 자신의 지사만 표시
            if (userRole === 'branch' && userBranchId) {
                // 지사 정보 로드
                fetch('/api/branches')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            const userBranch = data.data.find(branch => branch.id == userBranchId);
                            if (userBranch) {
                                select.innerHTML = `<option value="${userBranch.id}" selected>${userBranch.name}</option>`;
                                // 지사 선택 컨테이너 숨기기 (이미 선택됨)
                                const container = document.getElementById('branch-select-container');
                                if (container) {
                                    container.style.display = 'none';
                                }
                            } else {
                                select.innerHTML = '<option value="">지사 정보를 찾을 수 없습니다</option>';
                            }
                        }
                    })
                    .catch(error => {
                        console.error('지사 정보 로드 오류:', error);
                        select.innerHTML = '<option value="">지사 정보 로드 오류</option>';
                    });
            } else {
                // 본사 계정인 경우 모든 지사 표시
                fetch('/api/branches')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            select.innerHTML = '<option value="">지사를 선택하세요...</option>';
                            data.data.forEach(branch => {
                                select.innerHTML += `<option value="${branch.id}">${branch.name}</option>`;
                            });
                        } else {
                            select.innerHTML = '<option value="">지사 목록 로드 실패</option>';
                        }
                    })
                    .catch(error => {
                        console.error('지사 목록 로드 오류:', error);
                        select.innerHTML = '<option value="">지사 목록 로드 오류</option>';
                    });
            }
        }

        // 매장 추가 모달 닫기
        function closeAddStoreModal() {
            document.getElementById('add-store-modal').classList.add('hidden');
            // 폼 초기화
            document.getElementById('modal-store-name').value = '';
            document.getElementById('modal-owner-name').value = '';
            document.getElementById('modal-phone').value = '';
        }
        
        // 매장 추가 실행
        function submitAddStore() {
            const storeData = {
                name: document.getElementById('modal-store-name').value,
                branch_id: parseInt(document.getElementById('modal-branch-select').value),
                owner_name: document.getElementById('modal-owner-name').value,
                phone: document.getElementById('modal-phone').value
            };

            if (!storeData.name.trim()) {
                showToast('매장명을 입력해주세요.', 'error');
                return;
            }

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
                    showToast('✅ 매장이 성공적으로 추가되었습니다!', 'success');
                    closeAddStoreModal();

                    // 계정 정보 모달 표시
                    if (data.account) {
                        showStoreAccountModal(data.account, data.data);
                    }

                    loadStores(); // 목록 새로고침
                } else {
                    showToast('❌ ' + (data.message || data.error || '매장 추가 실패'), 'error');
                }
            })
            .catch(error => {
                console.error('매장 추가 오류:', error);
                alert('매장 추가 중 오류가 발생했습니다.');
            });
        }
        
        // 지사/본사용: 간단한 매장 추가
        async function addStore() {
            const name = prompt('매장명을 입력하세요 (예: 강남점):');
            if (!name) return;
            
            let branchId;
            if (window.userData.role === 'headquarters') {
                const branchName = prompt('지사명을 입력하세요:');
                if (!branchName) return;
                
                // API에서 지사 목록을 가져와서 동적으로 ID 찾기
                try {
                    const branchResponse = await fetch('/api/branches');
                    const branchData = await branchResponse.json();
                    
                    if (branchData.success) {
                        const branch = branchData.data.find(b => 
                            b.name.includes(branchName) || branchName.includes(b.name)
                        );
                        branchId = branch ? branch.id : null;
                        
                        if (!branchId) {
                            alert(`❌ "${branchName}" 지사를 찾을 수 없습니다.\n\n등록된 지사 목록:\n${branchData.data.map(b => `• ${b.name}`).join('\n')}`);
                            return;
                        }
                        
                        console.log(`✅ 지사 매핑 완료: ${branchName} → ID ${branchId}`);
                    } else {
                        alert('지사 목록을 불러올 수 없습니다.');
                        return;
                    }
                } catch (error) {
                    console.error('지사 검색 오류:', error);
                    alert('지사 검색 중 오류가 발생했습니다.');
                    return;
                }
                
            } else {
                // 지사는 자기 지사로 자동 설정
                branchId = window.userData.branch_id;
                alert(`${window.userData.branch_name || '현재 지사'}에 매장을 추가합니다.`);
            }
            
            const ownerName = prompt('점주명을 입력하세요:') || '';
            const phone = prompt('연락처를 입력하세요 (선택):') || '';
            
            const storeData = {
                name: name,
                branch_id: branchId,
                owner_name: ownerName,
                phone: phone
                // 코드, 주소는 자동 처리
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
                    showToast('✅ 매장이 성공적으로 추가되었습니다!', 'success');
                    closeAddStoreModal();
                    loadStores(); // 목록 새로고침
                } else {
                    showToast('❌ ' + (data.message || data.error || '매장 추가 실패'), 'error');
                }
            })
            .catch(error => {
                console.error('매장 추가 오류:', error);
                alert('매장 추가 중 오류가 발생했습니다.');
            });
        }

        // 지사 관리 함수들
        let currentEditBranchId = null;
        
        function addBranch() {
            document.getElementById('add-branch-modal').classList.remove('hidden');
        }
        
        function closeAddBranchModal() {
            document.getElementById('add-branch-modal').classList.add('hidden');
            // 폼 초기화
            document.getElementById('modal-branch-name').value = '';
            document.getElementById('modal-branch-code').value = '';
            document.getElementById('modal-branch-manager').value = '';
            document.getElementById('modal-branch-phone').value = '';
            document.getElementById('modal-branch-address').value = '';
        }
        
        function submitAddBranch() {
            const branchData = {
                name: document.getElementById('modal-branch-name').value,
                code: document.getElementById('modal-branch-code').value.toUpperCase(),
                manager_name: document.getElementById('modal-branch-manager').value,
                phone: document.getElementById('modal-branch-phone').value,
                address: document.getElementById('modal-branch-address').value
            };
            
            // 필수 필드 검증
            if (!branchData.name.trim() || !branchData.code.trim()) {
                showToast('지사명과 지사코드는 필수 입력 항목입니다.', 'error');
                return;
            }
            
            // 지사코드 형식 검증 (영문대문자 + 숫자)
            if (!/^[A-Z]{2,3}[0-9]{3,4}$/.test(branchData.code)) {
                showToast('지사코드는 영문대문자 + 숫자 형식이어야 합니다. (예: BR004)', 'error');
                return;
            }
            
            fetch('/api/branches/add', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(branchData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(`✅ 지사가 성공적으로 추가되었습니다!\n📧 관리자 계정: ${data.data.login_info.email}\n🔑 초기 비밀번호: ${data.data.login_info.password}`, 'success');
                    closeAddBranchModal();
                    loadBranches(); // 지사 목록 새로고침
                    loadStores(); // 매장 목록도 새로고침 (지사 구조 변경 반영)
                } else {
                    showToast('❌ ' + (data.message || data.error || '지사 추가 실패'), 'error');
                }
            })
            .catch(error => {
                console.error('지사 추가 오류:', error);
                showToast('지사 추가 중 오류가 발생했습니다.', 'error');
            });
        }
        
        function editBranch(branchId) {
            currentEditBranchId = branchId;
            
            // 지사 정보 불러오기
            fetch(`/api/branches/${branchId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const branch = data.data;
                    document.getElementById('edit-branch-name').value = branch.name;
                    document.getElementById('edit-branch-code').value = branch.code;
                    document.getElementById('edit-branch-manager').value = branch.manager_name || '';
                    document.getElementById('edit-branch-phone').value = branch.phone || '';
                    document.getElementById('edit-branch-address').value = branch.address || '';
                    document.getElementById('edit-branch-status').value = branch.status;
                    
                    document.getElementById('edit-branch-modal').classList.remove('hidden');
                } else {
                    showToast('지사 정보를 불러올 수 없습니다.', 'error');
                }
            })
            .catch(error => {
                console.error('지사 정보 로딩 오류:', error);
                showToast('지사 정보 로딩 중 오류가 발생했습니다.', 'error');
            });
        }
        
        function closeEditBranchModal() {
            document.getElementById('edit-branch-modal').classList.add('hidden');
            currentEditBranchId = null;
        }
        
        function submitEditBranch() {
            const branchData = {
                name: document.getElementById('edit-branch-name').value,
                code: document.getElementById('edit-branch-code').value.toUpperCase(),
                manager_name: document.getElementById('edit-branch-manager').value,
                phone: document.getElementById('edit-branch-phone').value,
                address: document.getElementById('edit-branch-address').value,
                status: document.getElementById('edit-branch-status').value
            };
            
            // 필수 필드 검증
            if (!branchData.name.trim() || !branchData.code.trim()) {
                showToast('지사명과 지사코드는 필수 입력 항목입니다.', 'error');
                return;
            }
            
            fetch(`/api/branches/${currentEditBranchId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(branchData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('✅ 지사 정보가 성공적으로 수정되었습니다!', 'success');
                    closeEditBranchModal();
                    loadBranches(); // 지사 목록 새로고침
                    loadStores(); // 매장 목록도 새로고침
                } else {
                    showToast('❌ ' + (data.message || data.error || '지사 수정 실패'), 'error');
                }
            })
            .catch(error => {
                console.error('지사 수정 오류:', error);
                showToast('지사 수정 중 오류가 발생했습니다.', 'error');
            });
        }
        
        function deleteBranch(branchId) {
            if (!confirm('정말로 이 지사를 삭제하시겠습니까?\n\n⚠️ 주의: 지사를 삭제하면 해당 지사 관리자 계정도 비활성화됩니다.\n하위 매장이 있는 경우 삭제할 수 없습니다.')) {
                return;
            }
            
            fetch(`/api/branches/${branchId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('✅ 지사가 성공적으로 삭제되었습니다!', 'success');
                    closeEditBranchModal();
                    loadBranches(); // 지사 목록 새로고침
                    loadStores(); // 매장 목록도 새로고침
                } else {
                    if (data.stores_count && data.stores_count > 0) {
                        showToast(`❌ 하위 매장이 ${data.stores_count}개 있어 삭제할 수 없습니다.\n매장: ${data.stores.join(', ')}\n먼저 매장을 다른 지사로 이관하거나 삭제해주세요.`, 'error');
                    } else {
                        showToast('❌ ' + (data.message || data.error || '지사 삭제 실패'), 'error');
                    }
                }
            })
            .catch(error => {
                console.error('지사 삭제 오류:', error);
                showToast('지사 삭제 중 오류가 발생했습니다.', 'error');
            });
        }

        // 사용자 추가 기능 (완전 구현)
        function addUser() {
            console.log('👤 새 사용자 추가 시작');

            // 사용자 정보 입력 받기
            const userName = prompt('새 사용자 이름을 입력하세요:', '');
            if (!userName || userName.trim() === '') {
                alert('❌ 사용자 이름은 필수입니다.');
                return;
            }

            const userEmail = prompt('이메일을 입력하세요:', `${userName.toLowerCase().replace(/\s+/g, '')}@ykp.com`);
            if (!userEmail || !userEmail.includes('@')) {
                alert('❌ 올바른 이메일을 입력해주세요.');
                return;
            }

            const userPassword = prompt('비밀번호를 입력하세요 (6자리 이상):', '123456');
            if (!userPassword || userPassword.length < 6) {
                alert('❌ 비밀번호는 6자리 이상이어야 합니다.');
                return;
            }

            // 역할 선택
            const roles = ['headquarters', 'branch', 'store'];
            const roleNames = ['본사 관리자', '지사 관리자', '매장 직원'];
            const roleChoice = prompt(`역할을 선택하세요:\n1. ${roleNames[0]}\n2. ${roleNames[1]}\n3. ${roleNames[2]}\n번호를 입력하세요:`);

            if (!roleChoice || roleChoice < 1 || roleChoice > 3) {
                alert('❌ 올바른 역할을 선택해주세요.');
                return;
            }

            const selectedRole = roles[parseInt(roleChoice) - 1];
            const selectedRoleName = roleNames[parseInt(roleChoice) - 1];

            // 소속 정보 입력 (역할에 따라)
            let branchId = null, storeId = null;

            if (selectedRole === 'branch' || selectedRole === 'store') {
                // 지사 선택 (현재 사용자가 지사 관리자면 자동 설정)
                if (window.userData.role === 'branch') {
                    branchId = window.userData.branch_id;
                } else {
                    const branchIdInput = prompt('지사 ID를 입력하세요:', '');
                    branchId = parseInt(branchIdInput);
                    if (isNaN(branchId)) {
                        alert('❌ 올바른 지사 ID를 입력해주세요.');
                        return;
                    }
                }
            }

            if (selectedRole === 'store') {
                const storeIdInput = prompt('매장 ID를 입력하세요:', '');
                storeId = parseInt(storeIdInput);
                if (isNaN(storeId)) {
                    alert('❌ 올바른 매장 ID를 입력해주세요.');
                    return;
                }
            }

            // 확인 메시지
            let confirmMessage = `새 사용자 생성 확인\n\n`;
            confirmMessage += `이름: ${userName}\n`;
            confirmMessage += `이메일: ${userEmail}\n`;
            confirmMessage += `비밀번호: ${userPassword}\n`;
            confirmMessage += `역할: ${selectedRoleName}\n`;
            if (branchId) confirmMessage += `지사 ID: ${branchId}\n`;
            if (storeId) confirmMessage += `매장 ID: ${storeId}\n`;
            confirmMessage += `\n생성하시겠습니까?`;

            if (!confirm(confirmMessage)) {
                console.log('❌ 사용자 생성 취소');
                return;
            }

            // API 호출
            fetch('/api/users', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    name: userName.trim(),
                    email: userEmail.trim(),
                    password: userPassword,
                    role: selectedRole,
                    branch_id: branchId,
                    store_id: storeId,
                    is_active: true
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`✅ "${userName}" 사용자가 성공적으로 생성되었습니다!\n\n📧 이메일: ${userEmail}\n🔑 비밀번호: ${userPassword}\n🏢 역할: ${selectedRoleName}\n\n이 정보를 해당 사용자에게 전달하세요.`);

                    // 활동 로그 기록
                    fetch('/api/activities/log', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            activity_type: 'account_create',
                            activity_title: `새 ${selectedRoleName} 계정 생성`,
                            activity_description: `${userName} (${userEmail}) 계정을 생성했습니다.`,
                            target_type: 'user',
                            target_id: data.data?.id
                        })
                    });

                    // 페이지 새로고침
                    loadUsers();
                } else {
                    alert('❌ 사용자 생성 실패: ' + (data.error || '알 수 없는 오류'));
                }
            })
            .catch(error => {
                console.error('사용자 생성 오류:', error);
                alert('❌ 사용자 생성 중 오류가 발생했습니다.');
            });
        }

        // 통합된 매장 수정 함수 (404 오류 해결 + 권한 체크 + DB 자동 매핑)
        let currentEditStoreId = null;

        // 전역 editStore 함수 - 모든 버튼에서 사용
        window.editStore = function editStore(storeId, storeName) {
            console.log('✏️ 매장 수정 시작:', storeId, storeName || 'Unknown');
            // 권한 체크 (선택사항 - permissionManager가 없어도 동작)
            if (window.permissionManager && !window.permissionManager.canEditStore(storeId)) {
                showToast('❌ 이 매장을 수정할 권한이 없습니다.', 'error');
                return;
            }

            currentEditStoreId = storeId;

            console.log('📡 매장 정보 로딩 중...');

            // 지사 목록을 먼저 로드
            loadBranchOptions('edit-branch-select');

            // DB에서 매장 정보 자동 로드
            fetch(`/api/stores/${storeId}`)
                .then(response => {
                    console.log('📡 매장 정보 API 응답:', response.status);

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }

                    // Content-Type 안전 검증
                    const contentType = response.headers.get('content-type');
                    if (!contentType || !contentType.includes('application/json')) {
                        throw new Error('API가 JSON이 아닌 응답을 반환했습니다.');
                    }

                    return response.json();
                })
                .then(data => {
                    console.log('📊 매장 데이터:', data);

                    if (data.success) {
                        const store = data.data;
                        console.log('✅ 매장 정보 로드 완료:', store.name);

                        // 모달이 존재하는지 확인
                        const editModal = document.getElementById('edit-store-modal');
                        if (!editModal) {
                            console.error('❌ edit-store-modal을 찾을 수 없음');
                            alert('매장 수정 모달을 찾을 수 없습니다.\n페이지를 새로고침해주세요.');
                            return;
                        }

                        // 안전한 값 설정 함수
                        const setFieldValue = (id, value) => {
                            const field = document.getElementById(id);
                            if (field) {
                                field.value = value || '';
                                console.log(`✅ ${id} 설정:`, value);
                            } else {
                                console.warn(`⚠️ 필드 찾기 실패: ${id}`);
                            }
                        };

                        // 폼에 자동 매핑
                        setFieldValue('edit-store-name', store.name);
                        setFieldValue('edit-store-code', store.code);
                        setFieldValue('edit-owner-name', store.owner_name);
                        setFieldValue('edit-phone', store.phone || store.contact_number);
                        setFieldValue('edit-address', store.address);
                        setFieldValue('edit-status', store.status || 'active');
                        setFieldValue('edit-branch-select', store.branch_id);

                        // 실시간 통계도 로드 (함수가 있다면)
                        if (typeof loadStoreStatsForEdit === 'function') {
                            loadStoreStatsForEdit(storeId);
                        }

                        // 모달 표시
                        editModal.classList.remove('hidden');
                        editModal.style.display = 'flex';

                        // 첫 번째 입력 필드에 포커스
                        const nameInput = document.getElementById('edit-store-name');
                        if (nameInput) {
                            setTimeout(() => nameInput.focus(), 100);
                        }

                        showToast(`📝 ${store.name} 매장 정보를 불러왔습니다.`, 'success');
                    } else {
                        throw new Error(data.error || '매장 정보를 가져올 수 없습니다.');
                    }
                })
                .catch(error => {
                    console.error('❌ 매장 정보 로드 오류:', error);

                    let errorMessage = '매장 정보 로드 중 오류가 발생했습니다.\n\n';

                    if (error.message.includes('HTTP 404')) {
                        errorMessage += '📍 해당 매장을 찾을 수 없습니다.';
                    } else if (error.message.includes('HTTP 403')) {
                        errorMessage += '🔒 매장 정보 조회 권한이 없습니다.';
                    } else if (error.message.includes('HTTP 500')) {
                        errorMessage += '🔧 서버 내부 오류가 발생했습니다.';
                    } else if (error.message.includes('NetworkError') || error.message.includes('fetch')) {
                        errorMessage += '🌐 네트워크 연결을 확인해주세요.';
                    } else {
                        errorMessage += `🔍 오류 세부사항: ${error.message}`;
                    }

                    alert(`❌ ${errorMessage}`);
                    showToast('❌ 매장 정보 로드 실패', 'error');
                });
        };
        
        // 수정 모달용 실시간 통계 로드
        function loadStoreStatsForEdit(storeId) {
            fetch(`/api/stores/${storeId}/stats`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const stats = data.data;
                        document.getElementById('edit-today-sales').textContent = '₩' + Number(stats.today_sales || 0).toLocaleString();
                        document.getElementById('edit-month-sales').textContent = '₩' + Number(stats.month_sales || 0).toLocaleString();
                        document.getElementById('edit-store-rank').textContent = '#' + (Math.floor(Math.random() * 50) + 1); // 임시 순위
                    }
                })
                .catch(error => console.log('통계 로드 실패:', error));
        }
        
        // 매장 수정 저장
        function submitEditStore() {
            if (!currentEditStoreId) return;
            
            const updatedData = {
                name: document.getElementById('edit-store-name').value,
                owner_name: document.getElementById('edit-owner-name').value,
                phone: document.getElementById('edit-phone').value,
                address: document.getElementById('edit-address').value,
                status: document.getElementById('edit-status').value,
                branch_id: parseInt(document.getElementById('edit-branch-select').value)
            };
            
            if (!updatedData.name.trim()) {
                showToast('매장명을 입력해주세요.', 'error');
                return;
            }
            
            fetch(`/api/stores/${currentEditStoreId}`, {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(updatedData)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('✅ 매장 정보가 수정되었습니다!', 'success');
                    closeEditStoreModal();
                    loadStores(); // 목록 새로고침
                } else {
                    showToast('❌ ' + (data.error || '수정 실패'), 'error');
                }
            })
            .catch(error => {
                console.error('매장 수정 오류:', error);
                showToast('❌ 수정 중 오류가 발생했습니다.', 'error');
            });
        }
        
        // 매장 수정 모달 닫기
        function closeEditStoreModal() {
            document.getElementById('edit-store-modal').classList.add('hidden');
            currentEditStoreId = null;
        }

        // 📊 통합된 매장 성과 보기 함수 (중복 제거)
        function viewStoreStatsModal(storeId) {
            // 권한 체크 먼저 (permissionManager가 있다면)
            if (window.permissionManager && !window.permissionManager.canViewStats(storeId)) {
                showToast('❌ 이 매장의 성과를 조회할 권한이 없습니다.', 'error');
                return;
            }

            // 매장 이름 설정
            fetch(`/api/stores/${storeId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('stats-store-name').textContent = data.data.name;

                        // 성과 대시보드 모달 표시
                        document.getElementById('store-stats-modal').classList.remove('hidden');

                        // 실시간 통계 로드
                        loadFullStoreStats(storeId);

                        showToast(`📈 ${data.data.name} 성과 데이터를 로딩합니다...`, 'info');
                    }
                });
        }
        
        // 풀스크린 성과 통계 로드
        function loadFullStoreStats(storeId) {
            fetch(`/api/stores/${storeId}/stats`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const stats = data.data;
                        
                        // KPI 카드 업데이트
                        document.getElementById('stats-today-sales').textContent = '₩' + Number(stats.today_sales || 0).toLocaleString();
                        document.getElementById('stats-month-sales').textContent = '₩' + Number(stats.month_sales || 0).toLocaleString();
                        document.getElementById('stats-rank').textContent = '#' + (Math.floor(Math.random() * 50) + 1);
                        document.getElementById('stats-goal').textContent = Math.floor(Math.random() * 100) + '%';
                        
                        // 변동률 계산 (임시)
                        document.getElementById('stats-today-change').textContent = '+12.5%';
                        document.getElementById('stats-month-change').textContent = '+8.3%';
                        document.getElementById('stats-rank-change').textContent = '↑2';
                        
                        // 최근 거래 내역 표시
                        displayRecentTransactions(stats.recent_sales || []);
                        
                        showToast('📊 성과 데이터 로드 완료!', 'success');
                    }
                })
                .catch(error => {
                    console.error('성과 데이터 로드 오류:', error);
                    showToast('❌ 성과 데이터 로드 실패', 'error');
                });
        }
        
        // 최근 거래 내역 표시
        function displayRecentTransactions(transactions) {
            const container = document.getElementById('recent-transactions');
            
            if (transactions.length === 0) {
                container.innerHTML = '<div class="text-center text-gray-500 py-4">최근 거래 내역이 없습니다.</div>';
                return;
            }
            
            let html = '';
            transactions.forEach(sale => {
                const amount = Number(sale.settlement_amount || 0).toLocaleString();
                html += `
                    <div class="flex justify-between items-center p-3 bg-gray-50 rounded-lg">
                        <div>
                            <div class="font-medium text-gray-900">${sale.model_name || '모델명 없음'}</div>
                            <div class="text-sm text-gray-500">${sale.sale_date} • ${sale.carrier || 'SK'}</div>
                        </div>
                        <div class="text-lg font-bold text-green-600">₩${amount}</div>
                    </div>
                `;
            });
            
            container.innerHTML = html;
        }
        
        // 성과보기 모달 닫기
        function closeStoreStatsModal() {
            document.getElementById('store-stats-modal').classList.add('hidden');
        }

        // 개선된 사용자 수정 함수
        function editUser(userId) {
            console.log('👤 사용자 수정 시작:', userId);

            // 사용자 정보 로딩
            fetch(`/api/users/${userId}`)
                .then(response => {
                    console.log('📡 사용자 정보 API 응답:', response.status);
                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('📊 사용자 데이터:', data);

                    if (data.success) {
                        const user = data.data;
                        console.log('✅ 사용자 정보 로드 완료:', user.name);

                        // 사용자 정보 수정 폼
                        let editForm = `👤 ${user.name} 계정 정보 수정\n`;
                        editForm += `${'='.repeat(40)}\n\n`;
                        editForm += `📧 이메일: ${user.email}\n`;
                        editForm += `🔑 역할: ${getRoleText(user.role)}\n`;
                        editForm += `🏢 소속: ${user.store?.name || user.branch?.name || '본사'}\n`;
                        editForm += `📊 상태: ${user.is_active ? '활성' : '비활성'}\n\n`;

                        // 수정 옵션 선택
                        const editOptions = [
                            '1. 비밀번호 리셋',
                            '2. 계정 활성화/비활성화',
                            '3. 역할 변경',
                            '4. 소속 변경',
                            '5. 계정 삭제',
                            '0. 취소'
                        ];

                        const option = prompt(editForm + '수정할 항목을 선택하세요:\n\n' + editOptions.join('\n'));

                        if (!option || option === '0') {
                            console.log('❌ 사용자가 수정 취소');
                            return;
                        }

                        // 선택된 옵션에 따른 처리
                        switch (option) {
                            case '1':
                                resetUserPassword(userId, user);
                                break;
                            case '2':
                                toggleUserStatus(userId, user);
                                break;
                            case '3':
                                changeUserRole(userId, user);
                                break;
                            case '4':
                                changeUserAffiliation(userId, user);
                                break;
                            case '5':
                                deleteUser(userId, user);
                                break;
                            default:
                                alert('❌ 잘못된 선택입니다.');
                        }

                    } else {
                        throw new Error(data.error || '사용자 정보를 가져올 수 없습니다.');
                    }
                })
                .catch(error => {
                    console.error('❌ 사용자 정보 로드 오류:', error);

                    let errorMessage = '사용자 정보를 불러오는 중 오류가 발생했습니다.\n\n';

                    if (error.message.includes('HTTP 404')) {
                        errorMessage += '📍 해당 사용자를 찾을 수 없습니다.';
                    } else if (error.message.includes('HTTP 403')) {
                        errorMessage += '🔒 사용자 정보 조회 권한이 없습니다.';
                    } else {
                        errorMessage += `🔍 오류: ${error.message}`;
                    }

                    alert(`❌ ${errorMessage}`);
                });
        }

        // 비밀번호 리셋
        function resetUserPassword(userId, user) {
            const newPassword = prompt(`🔑 ${user.name}의 새 비밀번호를 입력하세요:`, '123456');
            if (!newPassword || newPassword.length < 6) {
                alert('❌ 비밀번호는 6자리 이상이어야 합니다.');
                return;
            }

            if (!confirm(`🔑 ${user.name}의 비밀번호를 "${newPassword}"로 변경하시겠습니까?`)) {
                return;
            }

            fetch(`/api/users/${userId}/reset-password`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({ password: newPassword })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`✅ 비밀번호 리셋 완료!\n\n📧 ${user.email}\n🔑 새 비밀번호: ${newPassword}\n\n이 정보를 해당 사용자에게 전달하세요.`);
                    loadStores(); // 사용자 목록 새로고침
                } else {
                    alert('❌ 비밀번호 리셋 실패: ' + (data.error || '알 수 없는 오류'));
                }
            })
            .catch(error => {
                console.error('비밀번호 리셋 오류:', error);
                alert('❌ 비밀번호 리셋 중 오류가 발생했습니다.');
            });
        }

        // 계정 활성화/비활성화
        function toggleUserStatus(userId, user) {
            const newStatus = user.is_active ? 'inactive' : 'active';
            const actionText = newStatus === 'active' ? '활성화' : '비활성화';

            if (!confirm(`📊 ${user.name} 계정을 ${actionText}하시겠습니까?`)) {
                return;
            }

            fetch(`/api/users/${userId}/toggle-status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`✅ ${user.name} 계정이 ${actionText}되었습니다.`);
                    loadStores(); // 사용자 목록 새로고침
                } else {
                    alert('❌ 계정 상태 변경 실패: ' + (data.error || '알 수 없는 오류'));
                }
            })
            .catch(error => {
                console.error('계정 상태 변경 오류:', error);
                alert('❌ 계정 상태 변경 중 오류가 발생했습니다.');
            });
        }

        // 역할 텍스트 반환 함수
        function getRoleText(role) {
            const roleMap = {
                'headquarters': '🏛️ 본사',
                'branch': '🏬 지사',
                'store': '🏪 매장'
            };
            return roleMap[role] || role;
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

        // 클린코드: 토스트 알림 시스템 (상수화 + 개선)
        function showToast(message, type = CONFIG.TOAST_TYPES.SUCCESS, duration = CONFIG.TOAST_DURATION) {
            const toast = document.createElement('div');
            const bgColor = type === CONFIG.TOAST_TYPES.SUCCESS ? 'bg-green-500' : 
                           type === CONFIG.TOAST_TYPES.ERROR ? 'bg-red-500' : 'bg-blue-500';
            const icon = type === CONFIG.TOAST_TYPES.SUCCESS ? '✅' : 
                        type === CONFIG.TOAST_TYPES.ERROR ? '❌' : 'ℹ️';
            
            toast.className = `${bgColor} text-white px-4 py-3 rounded-lg shadow-lg flex items-center space-x-2 transform translate-x-full transition-transform duration-300`;
            toast.innerHTML = `
                <span class="text-lg">${icon}</span>
                <span class="font-medium">${message}</span>
                <button onclick="this.parentElement.remove()" class="ml-2 text-white hover:text-gray-200">×</button>
            `;
            
            document.getElementById('toast-container').appendChild(toast);
            
            // 애니메이션으로 나타내기
            setTimeout(() => {
                toast.classList.remove('translate-x-full');
            }, 100);
            
            // 자동 제거
            setTimeout(() => {
                toast.classList.add('translate-x-full');
                setTimeout(() => toast.remove(), 300);
            }, duration);
        }
        
        // 계정 생성 모달 관련 함수들
        let currentStoreForUser = null;
        
        function createUserForStore(storeId) {
            currentStoreForUser = storeId;
            document.getElementById('add-user-modal').classList.remove('hidden');
            document.getElementById('modal-user-name').focus();
        }
        
        function closeAddUserModal() {
            document.getElementById('add-user-modal').classList.add('hidden');
            document.getElementById('modal-user-name').value = '';
            document.getElementById('modal-user-email').value = '';
            document.getElementById('modal-user-password').value = '123456';
            currentStoreForUser = null;
        }
        
        function submitAddUser() {
            const userData = {
                name: document.getElementById('modal-user-name').value,
                email: document.getElementById('modal-user-email').value,
                password: document.getElementById('modal-user-password').value
            };
            
            if (!userData.name.trim() || !userData.email.trim()) {
                showToast('이름과 이메일을 입력해주세요.', 'error');
                return;
            }
            
            // 실제 계정 생성 API 호출
            console.log('🔄 사용자 계정 생성 API 호출 시작...', userData);

            fetch(`/api/stores/${currentStoreForUser}/create-user`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                },
                body: JSON.stringify({
                    name: userData.name.trim(),
                    email: userData.email.trim(),
                    password: userData.password || '123456',
                    role: 'store',
                    store_id: currentStoreForUser,
                    is_active: true
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast(`✅ ${userData.name} 계정이 성공적으로 생성되었습니다!\n📧 ${userData.email}\n🔑 비밀번호: ${userData.password}`, 'success');

                    // 활동 로그 기록
                    fetch('/api/activities/log', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify({
                            activity_type: 'account_create',
                            activity_title: `매장 계정 생성: ${userData.name}`,
                            activity_description: `${userData.email} 계정을 생성했습니다.`,
                            target_type: 'user',
                            target_id: data.data?.id
                        })
                    });

                    closeAddUserModal();
                    loadUsers(); // 목록 새로고침
                } else {
                    showToast(`❌ 계정 생성 실패: ${data.error || '알 수 없는 오류'}`, 'error');
                }
            })
            .catch(error => {
                console.error('계정 생성 API 오류:', error);
                showToast('❌ 계정 생성 중 네트워크 오류가 발생했습니다.', 'error');
            });
        };
        
        // 🔒 전역 상태 초기화
        window.storesPageInitialized = false;
        
        // 🛠️ 매장별 액션 버튼 함수들 정의 - editStore 함수 제거 (중복 해결)
        
        // 🗑️ 중복 정의 제거됨 (통합된 함수 사용)
        
        // 통합된 매장 삭제 함수 (올바른 API 엔드포인트 사용)
        window.deleteStore = function(storeId) {
            const store = allStores?.find(s => s.id === storeId);
            const storeName = store?.name || `매장 ID ${storeId}`;

            if (!confirm(`⚠️ 정말로 "${storeName}" 매장을 삭제하시겠습니까?\n\n이 작업은 되돌릴 수 없습니다.`)) {
                return;
            }

            fetch(`/api/stores/${storeId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    'Accept': 'application/json',
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin'
            })
            .then(response => {
                if (!response.ok && response.status === 401) {
                    throw new Error('인증이 필요합니다. 다시 로그인해주세요.');
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    alert(`✅ "${storeName}" ${data.message || '매장이 삭제되었습니다.'}`);
                    if (typeof loadStores === 'function') {
                        loadStores(); // 목록 새로고침
                    } else {
                        location.reload();
                    }
                } else {
                    alert('❌ 삭제 실패: ' + (data.error || '알 수 없는 오류'));
                }
            })
            .catch(error => {
                console.error('매장 삭제 오류:', error);
                if (error.message.includes('인증')) {
                    alert('❌ ' + error.message);
                    window.location.href = '/login';
                } else {
                    alert('❌ 매장 삭제 중 오류가 발생했습니다: ' + error.message);
                }
            });
        };
        
        // ✨ 안전한 초기화 함수 + 버튼 이벤트 등록
        function initializeStoresPage() {
            if (window.storesPageInitialized) {
                console.log('ℹ️ 이미 초기화됨 - 스킵');
                return false;
            }
            
            console.log('✅ 매장관리 페이지 초기화 시작');
            
            // 🛠️ 매장 액션 버튼 이벤트 리스너 등록
            setupStoreActionButtons();
            
            // loadStores 함수 실행
            if (typeof window.loadStores === 'function') {
                console.log('✅ loadStores 함수 실행');
                window.loadStores();
                window.storesPageInitialized = true;
                return true;
            } else {
                console.error('❌ loadStores 함수 미정의');
                return false;
            }
        }
        
        // 매장 관리 버튼 함수들 (전역 등록) - 404 오류 해결을 위해 모달 방식으로 변경
        
        // 강화된 매장 계정 생성/수정 함수
        window.createStoreAccount = function createStoreAccount(storeId, storeName) {
            console.log('👤 매장 계정 생성/수정 시작:', storeId, storeName);

            // 먼저 기존 계정 상태 확인
            fetch(`/debug/store-account/${storeId}`)
                .then(response => {
                    console.log('📡 계정 상태 API 응답:', response.status, response.headers.get('content-type'));

                    if (!response.ok) {
                        throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                    }

                    // Content-Type 안전 검증
                    const contentType = response.headers.get('content-type') || '';
                    console.log('🔍 Response Content-Type:', contentType);

                    if (!contentType.includes('application/json')) {
                        // HTML 오류 페이지인 경우 텍스트로 읽어서 에러 추출
                        return response.text().then(htmlText => {
                            console.log('⚠️ HTML 응답 받음:', htmlText.substring(0, 200));

                            // Laravel 오류 페이지에서 에러 메시지 추출
                            const errorMatch = htmlText.match(/<title>([^<]+)<\/title>/);
                            const errorTitle = errorMatch ? errorMatch[1] : '알 수 없는 오류';

                            throw new Error(`API 오류: ${errorTitle} (HTML 응답)`);
                        });
                    }

                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        const accountInfo = data.data;
                        console.log('📊 매장 계정 상태:', accountInfo);

                        if (accountInfo.account_exists) {
                            // 기존 계정이 있는 경우
                            const existingAccount = accountInfo.existing_account;
                            let accountMessage = `👤 "${storeName}" 매장 계정 정보\n`;
                            accountMessage += `${'='.repeat(40)}\n\n`;
                            accountMessage += `📧 기존 계정: ${existingAccount.email}\n`;
                            accountMessage += `👤 이름: ${existingAccount.name}\n`;
                            accountMessage += `📊 상태: ${existingAccount.is_active ? '✅ 활성' : '❌ 비활성'}\n\n`;

                            if (!existingAccount.is_active) {
                                accountMessage += `⚠️ 계정이 비활성화되어 있습니다.\n`;
                                accountMessage += `계정을 활성화하고 비밀번호를 리셋하시겠습니까?`;
                            } else {
                                accountMessage += `✅ 계정이 이미 활성화되어 있습니다.\n`;
                                accountMessage += `비밀번호를 리셋하시겠습니까?`;
                            }

                            if (confirm(accountMessage)) {
                                // 계정 활성화/리셋
                                activateStoreAccount(storeId, storeName);
                            }
                        } else {
                            // 새 계정 생성
                            let createMessage = `👤 "${storeName}" 매장 새 계정 생성\n`;
                            createMessage += `${'='.repeat(40)}\n\n`;
                            createMessage += `📧 생성될 이메일: ${accountInfo.suggested_email}\n`;
                            createMessage += `🔑 기본 비밀번호: 123456\n`;
                            createMessage += `🏪 역할: 매장 관리자\n\n`;
                            createMessage += `새 계정을 생성하시겠습니까?`;

                            if (confirm(createMessage)) {
                                // 새 계정 생성
                                createNewStoreAccount(storeId, storeName);
                            }
                        }
                    } else {
                        throw new Error(data.error || '계정 상태를 확인할 수 없습니다.');
                    }
                })
                .catch(error => {
                    console.error('❌ 계정 상태 확인 오류:', error);
                    alert('❌ 계정 상태 확인 중 오류가 발생했습니다.\n기본 계정 생성을 진행하시겠습니까?');

                    // 기본 계정 생성 프로세스로 폴백
                    createNewStoreAccount(storeId, storeName);
                });
        };

        // 매장 계정 활성화/리셋
        function activateStoreAccount(storeId, storeName) {
            console.log('🔄 매장 계정 활성화/리셋:', storeId);

            fetch(`/api/stores/${storeId}/ensure-account`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let successMessage = `✅ "${storeName}" 계정 ${data.action === 'activated' ? '활성화' : '생성'} 완료!\n\n`;
                    successMessage += `📧 이메일: ${data.user.email}\n`;
                    successMessage += `🔑 비밀번호: 123456\n`;
                    successMessage += `📊 상태: 활성\n\n`;
                    successMessage += `이 정보를 매장 관리자에게 전달하세요.`;

                    alert(successMessage);
                    console.log(`✅ ${storeName} 계정 처리 완료`);
                } else {
                    alert('❌ 계정 처리 실패: ' + (data.error || '알 수 없는 오류'));
                }
            })
            .catch(error => {
                console.error('❌ 계정 처리 오류:', error);
                alert('❌ 계정 처리 중 오류가 발생했습니다.');
            });
        }

        // 새 매장 계정 생성
        function createNewStoreAccount(storeId, storeName) {
            const name = prompt(`${storeName} 매장의 관리자 이름을 입력하세요:`, `${storeName} 관리자`);
            if (!name) return;
            
            const email = prompt('이메일을 입력하세요:', `${storeName.replace(/[^가-힣a-zA-Z0-9]/g, '').toLowerCase()}@ykp.com`);
            if (!email) return;
            
            const password = prompt('비밀번호를 입력하세요 (6자리 이상):', '123456');
            if (!password || password.length < 6) {
                alert('비밀번호는 6자리 이상이어야 합니다');
                return;
            }
            
            // API 호출
            fetch(`/api/stores/${storeId}/create-user`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({ name, email, password })
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert(`✅ ${storeName} 계정이 생성되었습니다!\n이메일: ${email}\n비밀번호: ${password}`);
                } else {
                    alert('❌ 계정 생성 실패: ' + (result.error || '알 수 없는 오류'));
                }
            })
            .catch(error => {
                alert('❌ 네트워크 오류: ' + error.message);
            });
        };
        
        // 📊 통합된 매장 성과 보기 함수 (중복 제거 완료)
        window.viewStoreStats = function(storeId, storeName) {
            console.log('📊 통합 매장 성과 보기:', storeId, storeName);

            // 권한 체크 (permissionManager가 있다면)
            if (window.permissionManager && !window.permissionManager.canViewStats(storeId)) {
                alert('❌ 이 매장의 성과를 조회할 권한이 없습니다.');
                return;
            }

            // 매장명 확정
            const finalStoreName = storeName || `매장 ${storeId}`;

            // 성과 조회 옵션 제공
            let statsOptions = `📊 ${finalStoreName} 성과 분석 옵션\n`;
            statsOptions += `${'='.repeat(45)}\n\n`;
            statsOptions += `어떤 방식으로 성과를 확인하시겠습니까?\n\n`;
            statsOptions += `1. 📈 상세 통계 페이지로 이동\n`;
            statsOptions += `   • 기간별 성과 추이\n`;
            statsOptions += `   • 매장 순위 및 비교\n`;
            statsOptions += `   • 목표 달성률 분석\n\n`;
            statsOptions += `2. 🔍 빠른 성과 요약 보기\n`;
            statsOptions += `   • 이번달 매출 및 개통건수\n`;
            statsOptions += `   • 순위 및 성장률\n\n`;
            statsOptions += `선택하세요 (1 또는 2):`;

            const choice = prompt(statsOptions);

            if (choice === '1') {
                // 상세 통계 페이지로 이동
                console.log(`✅ ${finalStoreName} 상세 통계 페이지로 이동`);
                window.location.href = `/statistics/enhanced?store=${storeId}&name=${encodeURIComponent(finalStoreName)}`;
            } else if (choice === '2') {
                // 빠른 성과 요약 (모달)
                console.log(`✅ ${finalStoreName} 빠른 성과 요약 표시`);
                if (typeof viewStoreStatsModal === 'function') {
                    viewStoreStatsModal(storeId);
                } else {
                    // 모달 함수가 없으면 통계 페이지로 이동
                    window.location.href = `/statistics/enhanced?store=${storeId}&name=${encodeURIComponent(finalStoreName)}`;
                }
            } else if (choice !== null) {
                alert('❌ 올바른 옵션을 선택해주세요 (1 또는 2)');
            }
        };
        

        // 삭제됨 - deleteStore 함수로 통합
        /* window.deleteStoreWithConfirmation = function(storeId, storeName, forceDelete = false) {
            console.log('🗑️ 매장 삭제 확인:', { storeId, storeName, forceDelete });

            // 첫 번째 확인
            if (!forceDelete && !confirm(`⚠️ 정말로 "${storeName}" 매장을 삭제하시겠습니까?\n\n이 작업은 되돌릴 수 없습니다.`)) {
                console.log('❌ 사용자가 삭제 취소');
                return;
            }
            console.log('📡 매장 삭제 API 호출...');

            // API 호출
            const url = forceDelete ?
                `/api/stores/${storeId}?force=true` :
                `/api/stores/${storeId}`;

            fetch(url, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => {
                console.log('📡 매장 삭제 API 응답:', response.status);
                return response.json();
            })
            .then(result => {
                console.log('📊 매장 삭제 결과:', result);

                if (result.success) {
                    // 성공 시
                    let successMessage = `✅ "${storeName}" 매장이 성공적으로 삭제되었습니다.`;

                    if (result.deleted_data) {
                        successMessage += `\n\n📊 삭제된 데이터:`;
                        if (result.deleted_data.sales_count > 0) {
                            successMessage += `\n• 개통표 기록: ${result.deleted_data.sales_count}건`;
                        }
                        if (result.deleted_data.users_count > 0) {
                            successMessage += `\n• 사용자 계정: ${result.deleted_data.users_count}개`;
                        }
                    }

                    alert(successMessage);
                    location.reload(); // 페이지 새로고침

                } else if (result.requires_confirmation) {
                    // 확인이 필요한 경우 (관련 데이터 존재) - 상세 가이드 표시
                    console.log('⚠️ 관련 데이터 확인 필요 - 사용자 가이드 표시');

                    showStoreDeleteGuide(storeId, storeName, result);

                } else {
                    // 일반 오류
                    alert('❌ 매장 삭제 실패: ' + (result.error || '알 수 없는 오류'));
                }
            })
            .catch(error => {
                console.error('❌ 매장 삭제 네트워크 오류:', error);
                alert('❌ 네트워크 오류가 발생했습니다: ' + error.message);
            });
        };

        // 🗑️ 매장 삭제 상세 가이드 함수
        function showStoreDeleteGuide(storeId, storeName, result) {
            console.log('📋 매장 삭제 가이드 표시:', result);

            // 사용자 친화적인 옵션 선택 UI 생성
            let optionMessage = `🗑️ "${storeName}" 매장 삭제 옵션\n`;
            optionMessage += `${'='.repeat(50)}\n\n`;

            optionMessage += `📊 연결된 데이터:\n`;
            Object.entries(result.details.data_types).forEach(([type, count]) => {
                optionMessage += `• ${type}: ${count}\n`;
            });

            optionMessage += `\n🔧 삭제 방법을 선택하세요:\n\n`;

            result.actions.forEach((action, index) => {
                optionMessage += `${index + 1}. ${action.label}\n`;
                optionMessage += `   ${action.description}\n`;
                if (action.warning) {
                    optionMessage += `   ⚠️ ${action.warning}\n`;
                }
                optionMessage += `\n`;
            });

            const choice = prompt(optionMessage + '선택하세요 (번호 입력):');

            if (!choice || choice === '3') {
                console.log('❌ 사용자가 삭제 취소');
                return;
            }

            const selectedAction = result.actions[parseInt(choice) - 1];
            if (!selectedAction) {
                alert('❌ 잘못된 선택입니다.');
                return;
            }

            console.log('✅ 사용자 선택:', selectedAction.label);

            switch (selectedAction.action) {
                case 'backup_first':
                    handleBackupFirstDeletion(storeId, storeName, result);
                    break;
                case 'deactivate_store':
                    handleStoreDeactivation(storeId, storeName);
                    break;
                case 'disable_accounts':
                    handleAccountDisabling(storeId, storeName);
                    break;
                case 'force_delete':
                    handleForceDelete(storeId, storeName);
                    break;
                default:
                    console.log('❌ 알 수 없는 액션');
            }
        }

        // 📊 데이터 백업 후 삭제 처리
        function handleBackupFirstDeletion(storeId, storeName, result) {
            let backupMessage = `📊 "${storeName}" 데이터 백업 안내\n`;
            backupMessage += `${'='.repeat(40)}\n\n`;

            backupMessage += `백업할 데이터:\n`;
            backupMessage += `• 개통표 기록: ${result.details.sales_count}건\n`;
            backupMessage += `• 사용자 계정: ${result.details.users_count}개\n\n`;

            backupMessage += `💾 백업 방법:\n`;
            backupMessage += `1. 통계 페이지에서 해당 매장 데이터 내보내기\n`;
            backupMessage += `2. 계정 관리에서 사용자 정보 내보내기\n`;
            backupMessage += `3. 백업 완료 후 다시 삭제 시도\n\n`;

            backupMessage += `지금 백업 페이지로 이동하시겠습니까?`;

            if (confirm(backupMessage)) {
                // 통계 페이지로 이동하여 데이터 백업
                window.open(`/statistics/enhanced?store=${storeId}&name=${encodeURIComponent(storeName)}&backup=true`, '_blank');
                alert('📊 백업 페이지가 열렸습니다.\n백업 완료 후 다시 삭제를 시도해주세요.');
            }
        }

        // 🗑️ 강제 삭제 처리
        function handleForceDelete(storeId, storeName) {
            let confirmMessage = `⚠️ "${storeName}" 강제 삭제 최종 확인\n`;
            confirmMessage += `${'='.repeat(40)}\n\n`;

            confirmMessage += `🚨 다음 데이터들이 영구적으로 삭제됩니다:\n`;
            confirmMessage += `• 모든 개통표 기록\n`;
            confirmMessage += `• 관련 사용자 계정\n`;
            confirmMessage += `• 매장 통계 데이터\n`;
            confirmMessage += `• 매장 기본 정보\n\n`;

            confirmMessage += `❗ 이 작업은 되돌릴 수 없습니다!\n\n`;

            confirmMessage += `정말로 "${storeName}" 매장을 강제 삭제하시겠습니까?\n\n`;
            confirmMessage += `확인하려면 매장명을 정확히 입력하세요:`;

            const confirmation = prompt(confirmMessage);

            if (confirmation === storeName) {
                console.log('✅ 사용자가 매장명 확인 완료 - 강제 삭제 진행');
                deleteStoreWithConfirmation(storeId, storeName, true);
            } else if (confirmation !== null) {
                alert('❌ 매장명이 일치하지 않습니다.\n삭제가 취소되었습니다.');
            }
        }

        // 🏪 매장 폐점 처리 (데이터 보존)
        function handleStoreDeactivation(storeId, storeName) {
            let deactivateMessage = `🏪 "${storeName}" 매장 폐점 처리\n`;
            deactivateMessage += `${'='.repeat(40)}\n\n`;
            deactivateMessage += `📋 폐점 처리 내용:\n`;
            deactivateMessage += `• 매장 상태: 운영중 → 폐점\n`;
            deactivateMessage += `• 사용자 계정: 자동 비활성화\n`;
            deactivateMessage += `• 개통표 데이터: 완전 보존 ✅\n`;
            deactivateMessage += `• 매장 정보: 완전 보존 ✅\n\n`;
            deactivateMessage += `💡 장점:\n`;
            deactivateMessage += `• 데이터 손실 없음\n`;
            deactivateMessage += `• 필요시 재개점 가능\n`;
            deactivateMessage += `• 법적/감사 요구사항 준수\n\n`;
            deactivateMessage += `폐점 처리를 진행하시겠습니까?`;

            if (confirm(deactivateMessage)) {
                fetch(`/api/stores/${storeId}/deactivate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let successMessage = `✅ "${storeName}" 매장 폐점 처리 완료!\n\n`;
                        successMessage += `📊 보존된 데이터:\n`;
                        successMessage += `• 개통표 기록: ${data.preserved_data.sales_count}건\n`;
                        successMessage += `• 사용자 정보: ${data.preserved_data.users_count}개\n\n`;
                        successMessage += `${data.note}`;

                        alert(successMessage);
                        location.reload();
                    } else {
                        alert('❌ 폐점 처리 실패: ' + (data.error || '알 수 없는 오류'));
                    }
                })
                .catch(error => {
                    alert('❌ 폐점 처리 중 오류가 발생했습니다.');
                });
            }
        }

        // 👥 계정만 비활성화 처리
        function handleAccountDisabling(storeId, storeName) {
            let disableMessage = `👥 "${storeName}" 매장 계정 비활성화\n`;
            disableMessage += `${'='.repeat(40)}\n\n`;
            disableMessage += `📋 비활성화 내용:\n`;
            disableMessage += `• 사용자 계정: 로그인 불가 처리\n`;
            disableMessage += `• 매장 정보: 완전 보존 ✅\n`;
            disableMessage += `• 개통표 데이터: 완전 보존 ✅\n`;
            disableMessage += `• 매장 상태: 운영중 유지\n\n`;
            disableMessage += `💡 용도: 일시적 운영 중단, 직원 교체 등\n\n`;
            disableMessage += `계정 비활성화를 진행하시겠습니까?`;

            if (confirm(disableMessage)) {
                fetch(`/api/stores/${storeId}/disable-accounts`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`✅ "${storeName}" 매장 계정 비활성화 완료!\n\n비활성화된 계정: ${data.affected_accounts}개\n\n필요시 계정 관리에서 재활성화 가능합니다.`);
                        location.reload();
                    } else {
                        alert('❌ 계정 비활성화 실패: ' + (data.error || '알 수 없는 오류'));
                    }
                })
                .catch(error => {
                    alert('❌ 계정 비활성화 중 오류가 발생했습니다.');
                });
            }
        }

        // 🛠️ 매장 버튼 이벤트 리스너 설정
        function setupStoreActionButtons() {
            console.log('🛠️ 매장 버튼 이벤트 등록 시작');
            
            // 이벤트 위임 사용 (동적 요소에도 작동)
            document.addEventListener('click', function(e) {
                const target = e.target;
                console.log('클릭 이벤트 감지:', target.className, target.tagName);
                
                const storeId = target.dataset.storeId;
                const storeName = target.dataset.storeName;
                
                console.log('Store 데이터:', { storeId, storeName });
                
                if (!storeId) {
                    console.log('storeId가 없어서 종료');
                    return;
                }
                
                if (target.classList.contains('store-edit-btn')) {
                    console.log('✏️ 매장 수정 클릭:', storeId, storeName);
                    alert('매장 수정: ' + storeName + ' (ID: ' + storeId + ')');
                } else if (target.classList.contains('store-account-btn')) {
                    console.log('👤 계정 생성 클릭:', storeId, storeName);

                    // 개선된 계정 생성 함수 호출
                    createStoreAccount(storeId, storeName);
                } else if (target.classList.contains('store-stats-btn')) {
                    console.log('📊 성과 보기 클릭:', storeId, storeName);

                    // 개선된 성과 보기 함수 호출
                    viewStoreStats(storeId, storeName);
                } else if (target.classList.contains('store-delete-btn')) {
                    console.log('🗑️ 매장 삭제 클릭:', storeId, storeName);
                    deleteStore(storeId); // 통합된 함수 호출
                }
            });
            
            console.log('✅ 매장 버튼 이벤트 등록 완료');
        }
        
        
        // 3가지 초기화 전략 (안전성 강화)
        document.addEventListener('DOMContentLoaded', initializeStoresPage);
        
        // 대안 1: 즉시 실행 (이미 DOM이 로드된 경우)
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            console.log('✅ DOM 이미 로드됨 - 즉시 초기화');
            setTimeout(initializeStoresPage, 100);
        }
        
        // 대안 2: 윈도우 로드 이벤트 (최후 수단)
        window.addEventListener('load', function() {
            if (!window.storesPageInitialized) {
                console.log('⚠️ 최후 수단: window.onload로 초기화');
                initializeStoresPage();
            }
        });
        
        // 대안 3: 지연 실행 (모든 것이 실패한 경우)
        setTimeout(function() {
            if (!window.storesPageInitialized) {
                console.log('🚑 긴급 지연 초기화 (3초 후)');
                initializeStoresPage();
            }
        }, 3000);
        

        // 🚀 4단계 초기화 전략 실행
        
        // 1단계: 즉시 실행
        if (document.readyState === 'complete' || document.readyState === 'interactive') {
            console.log('🚀 1단계: DOM 이미 준비됨 - 즉시 초기화');
            setTimeout(initializeStoresPage, 50);
        }
        
        // 2단계: DOMContentLoaded
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🚀 2단계: DOMContentLoaded 이벤트');
            initializeStoresPage();

            // 매장 데이터 캐싱 (권한 체크용)
            setTimeout(cacheStoreData, 1000);
        });
        
        // 3단계: window.load
        window.addEventListener('load', function() {
            console.log('🚑 3단계: window.load 이벤트');
            if (!window.storesPageInitialized) {
                initializeStoresPage();
            }
        });
        
        // 4단계: 최종 안전장치 (3초 뒤)
        setTimeout(function() {
            if (!window.storesPageInitialized) {
                console.log('🎆 4단계: 최종 안전장치 가동');
                initializeStoresPage();
            }
        }, 3000);
        
        // 전역 오류 처리
        window.addEventListener('error', function(e) {
            console.error('JavaScript 오류:', e.error);
            console.error('파일:', e.filename);
            console.error('라인:', e.lineno);
            
            // 오류 시 긴급 복구 시도
            if (!window.storesPageInitialized) {
                console.log('🚑 오류 감지 - 긴급 복구 시도');
                setTimeout(() => {
                    if (typeof initializeStoresPage === 'function') {
                        initializeStoresPage();
                    }
                }, 1000);
            }
        });


        // 본사용 지사 목록 로드
        function loadBranchesForModal() {
            fetch('/api/stores/branches')
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        const select = document.getElementById('quick-branch-id');
                        result.data.forEach(branch => {
                            const option = document.createElement('option');
                            option.value = branch.id;
                            option.textContent = branch.name;
                            select.appendChild(option);
                        });
                    }
                })
                .catch(error => {
                    console.error('지사 목록 로드 오류:', error);
                });
        }

        // 매장 추가 폼 제출 처리
        async function handleQuickStoreSubmit(e) {
            e.preventDefault();
            
            const formData = {
                name: document.getElementById('quick-store-name').value,
                branch_id: document.getElementById('quick-branch-id').value,
                owner_name: document.getElementById('quick-owner-name').value,
                phone: document.getElementById('quick-phone').value,
                address: document.getElementById('quick-address').value
            };
            
            try {
                // 매장 생성
                const storeResponse = await fetch('/api/stores/add', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(formData)
                });

                const storeResult = await storeResponse.json();

                if (storeResult.success) {
                    // 매장 생성 성공 - 모달 닫기
                    closeAddStoreModal();

                    // 매장 목록 실시간 업데이트
                    await refreshStoreList();

                    // 🔍 디버깅: API 응답 내용 확인
                    console.log('📊 API 응답 전체 (async):', storeResult);
                    console.log('🔑 account 정보 (async):', storeResult.account);

                    // 🎉 계정 정보 모달 표시 (이미 계정이 생성되어 응답에 포함됨)
                    if (storeResult.account) {
                        console.log('✅ account 존재 (async), 모달 호출 시작');
                        showStoreAccountModal(storeResult.account, storeResult.data);
                    } else {
                        console.log('❌ account 정보 없음 (async), 토스트 표시');
                        showToast('매장이 성공적으로 추가되었습니다!', 'success');
                    }
                } else {
                    alert('매장 추가 실패: ' + (storeResult.error || '알 수 없는 오류'));
                }
            } catch (error) {
                console.error('매장 추가 오류:', error);
                alert('매장 추가 중 오류가 발생했습니다.');
            }
        }

        // 신규 매장에 대한 계정 생성
        async function createAccountForNewStore(storeId) {
            try {
                const response = await fetch(`/api/stores/${storeId}/account`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({})
                });
                
                return await response.json();
            } catch (error) {
                console.error('계정 생성 오류:', error);
                return null;
            }
        }

        // PM 요구사항 계정 생성 모달
        function showPMAccountCreatedModal(account, store) {
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
                                <span class="ml-2 font-bold text-blue-600">${store.name} (${store.code})</span>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-3">
                            <span class="text-2xl">👤</span>
                            <div class="flex-1">
                                <span class="font-semibold text-gray-700">계정:</span>
                                <div class="flex items-center space-x-2 mt-1">
                                    <code class="bg-white px-3 py-2 rounded border text-blue-600 font-mono flex-1">${account.email}</code>
                                    <button onclick="copyToClipboard('${account.email}')" class="px-3 py-2 bg-blue-500 text-white rounded text-sm hover:bg-blue-600">복사</button>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-3">
                            <span class="text-2xl">🔑</span>
                            <div class="flex-1">
                                <span class="font-semibold text-gray-700">비밀번호:</span>
                                <div class="flex items-center space-x-2 mt-1">
                                    <code class="bg-white px-3 py-2 rounded border text-green-600 font-mono flex-1">${account.password}</code>
                                    <button onclick="copyToClipboard('${account.password}')" class="px-3 py-2 bg-green-500 text-white rounded text-sm hover:bg-green-600">복사</button>
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

        // 토스트 메시지 표시
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `fixed top-4 right-4 px-4 py-2 rounded-lg text-white z-50 ${
                type === 'success' ? 'bg-green-500' : 
                type === 'error' ? 'bg-red-500' : 'bg-blue-500'
            }`;
            toast.textContent = message;
            document.body.appendChild(toast);
            
            setTimeout(() => toast.remove(), 3000);
        }

        // 매장 목록 실시간 새로고침
        async function refreshStoreList() {
            if (typeof loadStores === 'function') {
                await loadStores();
                console.log('✅ 매장 목록 실시간 업데이트 완료');
            }
        }

        // 중복 함수 제거 - 이미 상단에 정의됨
        /*
        window.showStoreAccountModal = function(account, store) {
            const modal = document.createElement('div');
            modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
            modal.innerHTML = `
                <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                    <div class="text-center mb-6">
                        <div class="text-2xl mb-2">🎉</div>
                        <h3 class="text-lg font-bold text-gray-900">매장 계정 생성 완료</h3>
                        <p class="text-sm text-gray-600 mt-2">${store.name} 매장의 로그인 계정이 생성되었습니다.</p>
                    </div>

                    <div class="bg-gray-50 rounded-lg p-4 mb-6">
                        <div class="space-y-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase">로그인 아이디</label>
                                <div class="mt-1 flex items-center">
                                    <span class="font-mono text-sm bg-white px-3 py-2 rounded border flex-1">${account.email}</span>
                                    <button onclick="copyToClipboard('${account.email}')" class="ml-2 px-2 py-2 text-gray-500 hover:text-blue-500">
                                        📋
                                    </button>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 uppercase">임시 비밀번호</label>
                                <div class="mt-1 flex items-center">
                                    <span class="font-mono text-sm bg-white px-3 py-2 rounded border flex-1">${account.password}</span>
                                    <button onclick="copyToClipboard('${account.password}')" class="ml-2 px-2 py-2 text-gray-500 hover:text-blue-500">
                                        📋
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-6">
                        <div class="flex">
                            <div class="text-blue-400 mr-2">ℹ️</div>
                            <div class="text-xs text-blue-700">
                                <p class="font-medium mb-1">중요 안내사항</p>
                                <ul class="space-y-1">
                                    <li>• 매장 담당자에게 위 정보를 안전하게 전달해주세요</li>
                                    <li>• 첫 로그인 후 비밀번호 변경을 권장합니다</li>
                                    <li>• 계정 정보는 보안상 다시 확인할 수 없습니다</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="flex space-x-3">
                        <button onclick="printAccountInfo('${account.email}', '${account.password}', '${store.name}')"
                                class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200">
                            🖨️ 인쇄
                        </button>
                        <button onclick="closeAccountModal()"
                                class="flex-1 px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600">
                            확인
                        </button>
                    </div>
                </div>
            `;

            document.body.appendChild(modal);

            // 모달 클릭 시 닫기
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    closeAccountModal();
                }
            });
        }
        */

        // 계정 모달 닫기
        function closeAccountModal() {
            const modal = document.querySelector('.fixed.inset-0');
            if (modal) {
                modal.remove();
            }
        }

        // 클립보드 복사
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                showToast('📋 클립보드에 복사되었습니다', 'success');
            }).catch(() => {
                showToast('❌ 복사 실패', 'error');
            });
        }

        // 계정 정보 인쇄
        function printAccountInfo(email, password, storeName) {
            const printWindow = window.open('', '_blank');
            printWindow.document.write(`
                <html>
                    <head>
                        <title>매장 계정 정보 - ${storeName}</title>
                        <style>
                            body { font-family: Arial, sans-serif; padding: 20px; }
                            .header { text-align: center; margin-bottom: 30px; }
                            .info-box { border: 1px solid #ccc; padding: 15px; margin: 10px 0; }
                            .label { font-weight: bold; color: #555; }
                            .value { font-family: monospace; background: #f5f5f5; padding: 5px; }
                        </style>
                    </head>
                    <body>
                        <div class="header">
                            <h2>YKP ERP 시스템</h2>
                            <h3>${storeName} 매장 계정 정보</h3>
                            <p>발급일: ${new Date().toLocaleDateString('ko-KR')}</p>
                        </div>

                        <div class="info-box">
                            <div class="label">로그인 아이디:</div>
                            <div class="value">${email}</div>
                        </div>

                        <div class="info-box">
                            <div class="label">임시 비밀번호:</div>
                            <div class="value">${password}</div>
                        </div>

                        <div style="margin-top: 30px; border-top: 1px solid #ccc; padding-top: 15px;">
                            <p><strong>중요 안내사항:</strong></p>
                            <ul>
                                <li>첫 로그인 후 반드시 비밀번호를 변경해주세요</li>
                                <li>계정 정보를 안전하게 보관해주세요</li>
                                <li>타인에게 계정 정보가 노출되지 않도록 주의해주세요</li>
                            </ul>
                        </div>
                    </body>
                </html>
            `);
            printWindow.document.close();
            printWindow.print();
        }

    </script>
</body>
</html>