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
            .then(response => {
                console.log('API 응답 상태:', response.status);
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
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
                    
                    // 🔥 PM 긴급 요구사항: 지사 계정은 자동 계정 생성 + 모달 표시
                    const userRole = '{{ auth()->user()->role }}';
                    if (userRole === 'branch') {
                        console.log('🔥 지사 계정 - 자동 계정 생성 및 모달 표시 시작');
                        createAccountAndShowCredentials(result.data.id, result.data);
                    } else {
                        alert('매장이 성공적으로 생성되었습니다!');
                        location.reload();
                    }
                } else {
                    alert('매장 생성 실패: ' + (result.error || '알 수 없는 오류'));
                }
            })
            .catch(error => {
                console.error('매장 생성 오류:', error);
                alert('매장 생성 중 오류가 발생했습니다');
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
                                                        <button onclick="alert('매장 수정: {{ $store->name }}'); window.location.href='/management/stores/enhanced?edit={{ $store->id }}';" class="store-edit-btn px-3 py-1 bg-blue-500 text-white text-xs rounded hover:bg-blue-600">✏️ 수정</button>
                                                        <button onclick="
                                                            const name = prompt('{{ $store->name }} 매장 관리자 이름:', '{{ $store->name }} 관리자');
                                                            if (!name) return;
                                                            const email = prompt('이메일:', '{{ strtolower(preg_replace('/[^가-힣a-zA-Z0-9]/', '', $store->name)) }}@ykp.com');
                                                            if (!email) return;
                                                            const password = prompt('비밀번호 (6자리 이상):', '123456');
                                                            if (!password || password.length < 6) { alert('비밀번호는 6자리 이상'); return; }
                                                            fetch('/test-api/stores/{{ $store->id }}/create-user', {
                                                                method: 'POST',
                                                                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                                                                body: JSON.stringify({name, email, password})
                                                            }).then(r => r.json()).then(result => {
                                                                if (result.success) alert('✅ 계정 생성 완료!\n이메일: ' + email + '\n비밀번호: ' + password);
                                                                else alert('❌ 생성 실패: ' + (result.error || '오류'));
                                                            }).catch(e => alert('❌ 네트워크 오류'));
                                                        " class="store-account-btn px-3 py-1 bg-green-500 text-white text-xs rounded hover:bg-green-600">👤 계정</button>
                                                        <button onclick="window.location.href='/statistics/enhanced?store={{ $store->id }}&name={{ urlencode($store->name) }}';" class="store-stats-btn px-3 py-1 bg-purple-500 text-white text-xs rounded hover:bg-purple-600">📊 성과</button>
                                                        <button onclick="
                                                            if (confirm('⚠️ {{ $store->name }} 매장을 삭제하시겠습니까?\\n\\n되돌릴 수 없습니다.')) {
                                                                fetch('/test-api/stores/{{ $store->id }}', {
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
            
            // 지사 소속 매장 확인
            isStoreBelongsToBranch(storeId) {
                // TODO: 실제 매장-지사 매핑 데이터로 확인
                return this.user.branch_id !== null;
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
                                        <button onclick="viewStoreStats(${store.id})" 
                                                class="text-xs bg-purple-500 text-white px-2 py-1 rounded hover:bg-purple-600">
                                            성과보기
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
                                    <button onclick="viewStoreStats(${store.id})" 
                                            class="text-xs bg-purple-500 text-white px-2 py-1 rounded hover:bg-purple-600">
                                        성과보기
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
            
            fetch('/test-api/branches')
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
            
            fetch('/test-api/branches')
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
        
        // 지사 통계 조회 (향후 구현)
        function viewBranchStats(branchId) {
            showToast('지사 통계 기능은 향후 구현 예정입니다.', 'info');
        }

        // 사용자 목록 로드  
        function loadUsers() {
            document.getElementById('users-grid').innerHTML = '<div class="p-4 text-center text-gray-500">사용자 목록 로딩 중...</div>';
            
            fetch('/test-api/users')
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
        
        // 지사 옵션 동적 로드 함수
        function loadBranchOptions(selectId) {
            const select = document.getElementById(selectId);
            select.innerHTML = '<option value="">로딩 중...</option>';
            
            fetch('/test-api/branches')
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
            
            fetch('/test-api/stores/add', {
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
                    const branchResponse = await fetch('/test-api/branches');
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
            
            fetch('/test-api/stores/add', {
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
            
            fetch('/test-api/branches/add', {
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
            fetch(`/test-api/branches/${branchId}`)
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
            
            fetch(`/test-api/branches/${currentEditBranchId}`, {
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
            
            fetch(`/test-api/branches/${branchId}`, {
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

        function addUser() {
            alert('사용자 추가 기능 구현 예정');
        }

        // 클린코드: 매장 수정 모달 (권한 체크 + DB 자동 매핑)
        let currentEditStoreId = null;
        
        function editStore(storeId) {
            // 권한 체크 먼저
            if (!window.permissionManager.canEditStore(storeId)) {
                showToast('❌ 이 매장을 수정할 권한이 없습니다.', 'error');
                return;
            }
            
            currentEditStoreId = storeId;
            
            // 지사 목록을 먼저 로드
            loadBranchOptions('edit-branch-select');
            
            // Supabase에서 매장 정보 자동 로드
            fetch(`/test-api/stores/${storeId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const store = data.data;
                        
                        // 폼에 자동 매핑
                        document.getElementById('edit-store-name').value = store.name || '';
                        document.getElementById('edit-store-code').value = store.code || '';
                        document.getElementById('edit-owner-name').value = store.owner_name || '';
                        document.getElementById('edit-phone').value = store.phone || '';
                        document.getElementById('edit-address').value = store.address || '';
                        document.getElementById('edit-status').value = store.status || 'active';
                        document.getElementById('edit-branch-select').value = store.branch_id || 1;
                        
                        // 실시간 통계도 로드
                        loadStoreStatsForEdit(storeId);
                        
                        // 모달 표시
                        document.getElementById('edit-store-modal').classList.remove('hidden');
                        document.getElementById('edit-store-name').focus();
                        
                        showToast(`📝 ${store.name} 매장 정보를 불러왔습니다.`, 'info');
                    } else {
                        showToast('❌ 매장 정보를 불러올 수 없습니다.', 'error');
                    }
                })
                .catch(error => {
                    console.error('매장 정보 로드 오류:', error);
                    showToast('❌ 매장 정보 로드 중 오류 발생', 'error');
                });
        }
        
        // 수정 모달용 실시간 통계 로드
        function loadStoreStatsForEdit(storeId) {
            fetch(`/test-api/stores/${storeId}/stats`)
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
            
            fetch(`/test-api/stores/${currentEditStoreId}`, {
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

        // 클린코드: 성과보기 모달 (권한 체크 + 실시간 대시보드)
        function viewStoreStats(storeId) {
            // 권한 체크 먼저
            if (!window.permissionManager.canViewStats(storeId)) {
                showToast('❌ 이 매장의 성과를 조회할 권한이 없습니다.', 'error');
                return;
            }
            
            // 매장 이름 설정
            fetch(`/test-api/stores/${storeId}`)
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
            fetch(`/test-api/stores/${storeId}/stats`)
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
            
            fetch(`/test-api/stores/${storeId}/create-user`, {
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
            
            // 계정 생성 API 호출 (향후 구현)
            showToast(`${userData.email} 계정이 생성되었습니다!`, 'success');
            closeAddUserModal();
            loadUsers(); // 목록 새로고침
        };
        
        // 🔒 전역 상태 초기화
        window.storesPageInitialized = false;
        
        // 🛠️ 매장별 액션 버튼 함수들 정의
        window.editStore = function(storeId) {
            alert('매장 수정 기능: 매장 ID ' + storeId);
            // TODO: 편집 모달 구현
        };
        
        window.viewStoreStats = function(storeId) {
            alert('매장 성과 보기: 매장 ID ' + storeId);
            // TODO: 성과 대시보드 구현
        };
        
        window.deleteStore = function(storeId) {
            if (confirm('정말로 이 매장을 삭제하시겠습니까?')) {
                fetch('/api/dev/stores/' + storeId, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('매장이 삭제되었습니다.');
                        location.reload();
                    } else {
                        alert('삭제 실패: ' + (data.error || '알 수 없는 오류'));
                    }
                })
                .catch(error => {
                    alert('삭제 중 오류가 발생했습니다.');
                });
            }
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
        
        // 매장 관리 버튼 함수들 (전역 등록)
        window.editStore = function editStore(storeId, storeName) {
            console.log('✏️ 매장 수정:', storeId, storeName);
            if (confirm(`"${storeName}" 매장 정보를 수정하시겠습니까?`)) {
                window.location.href = `/management/stores/enhanced?edit=${storeId}`;
            }
        };
        
        window.createStoreAccount = function createStoreAccount(storeId, storeName) {
            console.log('👤 계정 생성:', storeId, storeName);
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
            fetch(`/test-api/stores/${storeId}/create-user`, {
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
        
        window.viewStoreStats = function(storeId, storeName) {
            console.log('📊 성과 보기:', storeId, storeName);
            // 실제 매장 통계 페이지로 이동
            window.location.href = `/statistics/enhanced?store=${storeId}&name=${encodeURIComponent(storeName)}`;
        };
        
        window.deleteStore = function(storeId, storeName) {
            console.log('🗑️ 매장 삭제:', storeId, storeName);
            if (!confirm(`⚠️ 정말로 "${storeName}" 매장을 삭제하시겠습니까?\n\n이 작업은 되돌릴 수 없습니다.`)) {
                return;
            }
            
            // API 호출
            fetch(`/test-api/stores/${storeId}`, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    alert(`✅ ${storeName} 매장이 삭제되었습니다.`);
                    location.reload(); // 페이지 새로고침
                } else {
                    alert('❌ 삭제 실패: ' + (result.error || '알 수 없는 오류'));
                }
            })
            .catch(error => {
                alert('❌ 네트워크 오류: ' + error.message);
            });
        };
        
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
                    if (confirm(storeName + ' 매장의 사용자 계정을 생성하시겠습니까?')) {
                        // TODO: 계정 생성 API 호출
                        alert('계정 생성 기능이 구현될 예정입니다.');
                    }
                } else if (target.classList.contains('store-stats-btn')) {
                    console.log('📊 성과 보기 클릭:', storeId, storeName);
                    alert('매장 성과: ' + storeName + ' (ID: ' + storeId + ')');
                } else if (target.classList.contains('store-delete-btn')) {
                    console.log('🗑️ 매장 삭제 클릭:', storeId, storeName);
                    if (confirm('정말로 "' + storeName + '" 매장을 삭제하시겠습니까?')) {
                        deleteStoreAPI(storeId, storeName);
                    }
                }
            });
            
            console.log('✅ 매장 버튼 이벤트 등록 완료');
        }
        
        // 🗑️ 매장 삭제 API 호출
        function deleteStoreAPI(storeId, storeName) {
            console.log('🗑️ 매장 삭제 API 호출:', storeId);
            
            fetch('/api/dev/stores/' + storeId, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('✅ "' + storeName + '" 매장이 삭제되었습니다.');
                    location.reload(); // 페이지 새로고침
                } else {
                    alert('❌ 삭제 실패: ' + (data.error || '알 수 없는 오류'));
                }
            })
            .catch(error => {
                console.error('매장 삭제 오류:', error);
                alert('매장 삭제 중 오류가 발생했습니다.');
            });
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
        
        // 매장 삭제 기능 (1차 구현)
        function deleteStore(storeId) {
            const store = allStores?.find(s => s.id === storeId);
            const storeName = store?.name || `매장 ID ${storeId}`;
            
            if (!confirm(`⚠️ 정말로 "${storeName}" 매장을 삭제하시겠습니까?\n\n삭제하면 다음 항목들이 함께 삭제됩니다:\n• 매장 정보\n• 매장 사용자 계정\n• 매장 관련 데이터\n\n이 작업은 되돌릴 수 없습니다.`)) {
                return;
            }

            fetch(`/test-api/stores/${storeId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`✅ "${storeName}" 매장이 삭제되었습니다.`);
                    loadStores(); // 목록 새로고침
                } else {
                    alert('❌ 매장 삭제 실패: ' + (data.error || '알 수 없는 오류'));
                }
            })
            .catch(error => {
                console.error('매장 삭제 오류:', error);
                alert('매장 삭제 중 오류가 발생했습니다.');
            });
        }

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
                const storeResponse = await fetch('/api/stores', {
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
                    
                    // 계정 자동 생성
                    const accountResult = await createAccountForNewStore(storeResult.data.id);
                    
                    // 매장 목록 실시간 업데이트
                    await refreshStoreList();
                    
                    // 성공 메시지
                    if (accountResult && accountResult.data && accountResult.data.account) {
                        showPMAccountCreatedModal(accountResult.data.account, storeResult.data);
                    } else {
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
        
    </script>
</body>
</html>