<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YKP ERP - Modern Dashboard</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- Preload critical fonts -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="preload" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css" rel="stylesheet">
    </noscript>
    
    <!-- Vite CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css" rel="stylesheet">
    
    <!-- Preload critical scripts -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/chart.js" as="script">
    <link rel="preload" href="https://unpkg.com/lucide@latest" as="script">
    
    <script>
        // Global CSRF Token and User Data - inline for immediate availability
        window.csrfToken = '{{ csrf_token() }}';
        window.userData = {
            id: {{ auth()->id() }},
            name: '{{ auth()->user()->name }}',
            email: '{{ auth()->user()->email }}',
            role: '{{ auth()->user()->role }}',
            branch: '{{ auth()->user()->branch?->name ?? '' }}',
            store: '{{ auth()->user()->store?->name ?? '' }}',
            permissions: {
                canViewAllStores: {{ auth()->user()->isHeadquarters() ? 'true' : 'false' }},
                canViewBranchStores: {{ auth()->user()->isBranch() ? 'true' : 'false' }},
                accessibleStoreIds: {!! json_encode(auth()->user()->getAccessibleStoreIds()) !!}
            }
        };
    </script>
</head>
<body class="bg-gray-50">
    <!-- Flash Messages -->
    @if(session('message'))
        <div class="fixed top-4 right-4 z-50 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                {{ session('message') }}
            </div>
        </div>
        <script>
            setTimeout(() => {
                const flashMessage = document.querySelector('.fixed.top-4.right-4');
                if (flashMessage) flashMessage.remove();
            }, 5000);
        </script>
    @endif

    @if(session('error'))
        <div class="fixed top-4 right-4 z-50 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                {{ session('error') }}
            </div>
        </div>
        <script>
            setTimeout(() => {
                const flashMessage = document.querySelector('.fixed.top-4.right-4');
                if (flashMessage) flashMessage.remove();
            }, 5000);
        </script>
    @endif

    <!-- Dashboard Root - React will mount here -->
    <div id="dashboard-root">
        <!-- Fallback content while React loads -->
        <div class="min-h-screen bg-gray-50 p-6">
            <div class="max-w-7xl mx-auto">
                <div class="mb-8">
                    @php
                        $user = auth()->user();
                        $roleInfo = match($user->role) {
                            'headquarters' => [
                                'title' => '📊 본사 통합 대시보드',
                                'badge' => '🏢 본사',
                                'badgeColor' => 'bg-blue-100 text-blue-800',
                                'description' => '전체 매장 데이터를 관리할 수 있습니다'
                            ],
                            'branch' => [
                                'title' => '🏢 ' . ($user->branch?->name ?? 'BR001') . ' 지사 대시보드',
                                'badge' => '🏪 지사',
                                'badgeColor' => 'bg-green-100 text-green-800',
                                'description' => ($user->branch?->name ?? 'BR001지사') . ' 산하 매장 데이터를 관리할 수 있습니다'
                            ],
                            'store' => [
                                'title' => '🏪 ' . ($user->store?->name ?? 'BR001-001') . ' 매장 대시보드',
                                'badge' => '🏬 매장',
                                'badgeColor' => 'bg-orange-100 text-orange-800',
                                'description' => '해당 매장 데이터만 조회 및 입력할 수 있습니다'
                            ],
                            default => [
                                'title' => '📊 YKP ERP 대시보드',
                                'badge' => '👤 사용자',
                                'badgeColor' => 'bg-gray-100 text-gray-800',
                                'description' => '실시간 개통 관리 시스템'
                            ]
                        };
                    @endphp
                    
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="flex items-center gap-3 mb-2">
                                <h1 class="text-3xl font-bold text-gray-900">{{ $roleInfo['title'] }}</h1>
                                <span class="px-3 py-1 rounded-full text-sm font-medium {{ $roleInfo['badgeColor'] }}">
                                    {{ $roleInfo['badge'] }}
                                </span>
                            </div>
                            <p class="text-gray-600">{{ $roleInfo['description'] }}</p>
                            <p class="text-sm text-gray-500 mt-1">
                                👋 안녕하세요, <strong>{{ $user->name }}</strong>님! 
                                ({{ $user->email }})
                            </p>
                        </div>
                        <div class="text-right">
                            <form action="{{ route('logout') }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600 transition-colors">
                                    로그아웃
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- 빠른 액세스 카드들 -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                                <span class="text-2xl">📊</span>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">완전한 AgGrid</h3>
                                <p class="text-sm text-gray-500">40개 필드 개통표</p>
                            </div>
                        </div>
                        <a href="/test/complete-aggrid" 
                           class="mt-4 w-full bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 inline-block text-center">
                            개통표 입력하기
                        </a>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                                <span class="text-2xl">⚡</span>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">간단 AgGrid</h3>
                                <p class="text-sm text-gray-500">빠른 입력</p>
                            </div>
                        </div>
                        <a href="/test/simple-aggrid" 
                           class="mt-4 w-full bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 inline-block text-center">
                            빠른 입력하기
                        </a>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                <span class="text-2xl">⚙️</span>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">관리자 패널</h3>
                                <p class="text-sm text-gray-500">시스템 관리</p>
                            </div>
                        </div>
                        <a href="/admin" 
                           class="mt-4 w-full bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600 inline-block text-center">
                            관리하기
                        </a>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                                <span class="text-2xl">👥</span>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">급여 관리</h3>
                                <p class="text-sm text-gray-500">AgGrid 완성!</p>
                            </div>
                        </div>
                        <a href="/payroll" 
                           class="mt-4 w-full bg-indigo-500 text-white px-4 py-2 rounded hover:bg-indigo-600 inline-block text-center">
                            급여 관리하기
                        </a>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                                <span class="text-2xl">🔄</span>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">환수 관리</h3>
                                <p class="text-sm text-gray-500">분석 차트 포함!</p>
                            </div>
                        </div>
                        <a href="/refunds" 
                           class="mt-4 w-full bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 inline-block text-center">
                            환수 관리하기
                        </a>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow p-6 hover:shadow-lg transition-shadow">
                        <div class="flex items-center">
                            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                                <span class="text-2xl">📊</span>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">월마감정산</h3>
                                <p class="text-sm text-gray-500">엑셀 로직 완전구현!</p>
                            </div>
                        </div>
                        <a href="/monthly-settlement" 
                           class="mt-4 w-full bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600 inline-block text-center">
                            월마감정산
                        </a>
                    </div>
                </div>
                
                <!-- 실시간 시스템 상태 -->
                <div class="bg-white rounded-lg shadow p-6">
                    <h2 class="text-xl font-semibold text-gray-900 mb-4">📊 실시간 시스템 현황</h2>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <div class="text-center">
                            <div class="text-2xl font-bold text-blue-600" id="user-count">
                                <div class="animate-pulse bg-blue-100 rounded h-8 w-16 mx-auto"></div>
                            </div>
                            <div class="text-sm text-gray-500">등록 사용자</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-green-600" id="store-count">
                                <div class="animate-pulse bg-green-100 rounded h-8 w-16 mx-auto"></div>
                            </div>
                            <div class="text-sm text-gray-500">등록 매장</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-purple-600" id="dealer-count">
                                <div class="animate-pulse bg-purple-100 rounded h-8 w-16 mx-auto"></div>
                            </div>
                            <div class="text-sm text-gray-500">대리점 프로파일</div>
                        </div>
                        <div class="text-center">
                            <div class="text-2xl font-bold text-indigo-600" id="sales-count">
                                <div class="animate-pulse bg-indigo-100 rounded h-8 w-16 mx-auto"></div>
                            </div>
                            <div class="text-sm text-gray-500">개통 건수</div>
                        </div>
                    </div>
                    
                    <!-- 실시간 업데이트 상태 -->
                    <div class="mt-4 flex items-center justify-between text-sm">
                        <span class="text-gray-500" id="last-update">마지막 업데이트: 로딩 중...</span>
                        <button 
                            onclick="refreshSystemStats()" 
                            class="px-3 py-1 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors"
                        >
                            🔄 새로고침
                        </button>
                    </div>
                </div>
                
                <!-- React 로딩 상태 -->
                <div class="mt-6 p-4 bg-blue-50 rounded-lg">
                    <p class="text-blue-800">📱 React 대시보드를 로딩하고 있습니다...</p>
                    <p class="text-blue-600 text-sm mt-1">위 카드들로 바로 작업을 시작하실 수 있습니다!</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading fallback for non-JS users -->
    <noscript>
        <div class="flex items-center justify-center min-h-screen">
            <div class="text-center">
                <h1 class="text-2xl font-bold text-gray-900 mb-4">JavaScript가 필요합니다</h1>
                <p class="text-gray-600">이 애플리케이션을 사용하려면 JavaScript를 활성화해주세요.</p>
            </div>
        </div>
    </noscript>

    <!-- Load external dependencies asynchronously -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    <script src="https://unpkg.com/lucide@latest" defer></script>
    
    <!-- React App - Safe Version with Auth Check -->
    @auth
        <!-- Only load React for authenticated users -->
        <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css" rel="stylesheet">
    @else
        <!-- Guest users get static content only -->
        <script>
            console.log('Guest user - showing static welcome');
            document.addEventListener('DOMContentLoaded', () => {
                const root = document.getElementById('dashboard-root');
                if (root) {
                    root.innerHTML = `
                        <div style="padding: 2rem; text-align: center; background: #f9fafb; border-radius: 8px; margin: 2rem;">
                            <h2 style="color: #111827; margin-bottom: 1rem;">YKP Dashboard</h2>
                            <p style="color: #6b7280; margin-bottom: 1rem;">안전한 배포 모드로 실행 중입니다.</p>
                            <a href="/login" style="display: inline-block; padding: 0.75rem 1.5rem; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px; font-weight: 500;">로그인하기</a>
                        </div>
                    `;
                }
            });
        </script>
    @endauth
    
    <!-- 실시간 데이터 로딩 스크립트 -->
    <script>
        // CSRF 토큰 설정
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        // 실시간 시스템 통계 로드
        async function loadSystemStats() {
            try {
                // 로딩 상태 표시 (안전한 DOM 접근)
                const lastUpdateElement = document.getElementById('last-update');
                if (lastUpdateElement) {
                    lastUpdateElement.textContent = '마지막 업데이트: 로딩 중...';
                }
                
                // 각 모델의 개수를 API로 가져오기
                const [usersResponse, storesResponse, dealersResponse, salesResponse] = await Promise.all([
                    fetch('/api/users/count', { 
                        headers: { 'X-CSRF-TOKEN': csrfToken || '' }
                    }),
                    fetch('/api/stores/count', {
                        headers: { 'X-CSRF-TOKEN': csrfToken || '' }
                    }),
                    fetch('/api/dealer-profiles/count', {
                        headers: { 'X-CSRF-TOKEN': csrfToken || '' }
                    }),
                    fetch('/api/sales/count', {
                        headers: { 'X-CSRF-TOKEN': csrfToken || '' }
                    })
                ]);

                // 응답 데이터 파싱
                const userData = usersResponse.ok ? await usersResponse.json() : { count: '?' };
                const storeData = storesResponse.ok ? await storesResponse.json() : { count: '?' };
                const dealerData = dealersResponse.ok ? await dealersResponse.json() : { count: '?' };
                const salesData = salesResponse.ok ? await salesResponse.json() : { count: '?' };

                // UI 업데이트
                updateCountElement('user-count', userData.count, 'text-blue-600');
                updateCountElement('store-count', storeData.count, 'text-green-600');
                updateCountElement('dealer-count', dealerData.count, 'text-purple-600');
                updateCountElement('sales-count', salesData.count, 'text-indigo-600');

                // 업데이트 시간 표시
                const now = new Date();
                const lastUpdateElement = document.getElementById('last-update');
                if (lastUpdateElement) {
                    lastUpdateElement.textContent = `마지막 업데이트: ${now.toLocaleTimeString('ko-KR')}`;
                }

            } catch (error) {
                console.error('시스템 통계 로드 오류:', error);
                
                // 오류 시 기본값 표시
                updateCountElement('user-count', '56', 'text-blue-600');
                updateCountElement('store-count', '50', 'text-green-600');  
                updateCountElement('dealer-count', '5', 'text-purple-600');
                updateCountElement('sales-count', '12', 'text-indigo-600');
                
                const lastUpdateElement = document.getElementById('last-update');
                if (lastUpdateElement) {
                    lastUpdateElement.textContent = '마지막 업데이트: 연결 오류 (기본값 표시)';
                }
            }
        }
        
        // 카운트 요소 업데이트 헬퍼 함수
        function updateCountElement(elementId, count, colorClass) {
            const element = document.getElementById(elementId);
            if (element) {
                element.innerHTML = `<span class="${colorClass}">${count}</span>`;
            }
        }
        
        // 수동 새로고침 함수
        function refreshSystemStats() {
            const button = event.target;
            const originalText = button.innerHTML;
            
            // 버튼 로딩 상태
            button.innerHTML = '로딩...';
            button.disabled = true;
            
            loadSystemStats().finally(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            });
        }
        
        // 페이지 로드 시 데이터 로드
        document.addEventListener('DOMContentLoaded', function() {
            // 인증된 사용자만 데이터 로드
            @auth
                loadSystemStats();
                
                // 5분마다 자동 새로고침
                setInterval(loadSystemStats, 5 * 60 * 1000);
            @endauth
        });
        
        // 개발용: 즉시 실행 (인증 없이도 테스트 가능)
        if (window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1') {
            // 개발 환경에서는 바로 로드
            setTimeout(() => {
                if (document.readyState === 'complete') {
                    loadSystemStats();
                }
            }, 1000);
        }
    </script>

    <!-- Vite React Dashboard -->
    @vite('resources/js/dashboard.jsx')
</body>
</html>