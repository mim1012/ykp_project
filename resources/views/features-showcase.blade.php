<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>YKP ERP 새 기능 - 매장 추가, 정산, 통계</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendardvariable/dist/web/variable/pretendardvariable.css" rel="stylesheet">
    <style>
        .feature-card { 
            transition: all 0.3s ease; 
            background: linear-gradient(135deg, #f8fafc 0%, #ffffff 100%);
        }
        .feature-card:hover { 
            transform: translateY(-8px); 
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15); 
        }
        .priority-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
            color: white;
            border-radius: 50%;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 12px;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
        }
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .feature-icon { font-size: 3rem; margin-bottom: 1rem; }
        .demo-tag {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
            position: absolute;
            top: 1rem;
            right: 1rem;
        }
    </style>
</head>
<body class="bg-gray-50 font-pretendard">
    <!-- 헤더 -->
    <header class="gradient-bg text-white shadow-2xl">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
            <div class="text-center">
                <h1 class="text-4xl font-bold mb-4">🚀 YKP ERP 시스템 업그레이드</h1>
                <p class="text-xl opacity-90 mb-6">매장 추가 · 정산 관리 · 전체 통계 새 기능</p>
                <div class="flex justify-center space-x-4">
                    <span class="px-4 py-2 bg-white bg-opacity-20 rounded-full text-sm font-semibold">
                        🎯 3개 주요 기능 완성
                    </span>
                    <span class="px-4 py-2 bg-white bg-opacity-20 rounded-full text-sm font-semibold">
                        ⚡ 실시간 데이터 처리
                    </span>
                    <span class="px-4 py-2 bg-white bg-opacity-20 rounded-full text-sm font-semibold">
                        🔒 권한별 접근 제어
                    </span>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto py-12 px-4">
        <!-- 우선순위별 기능 소개 -->
        <section class="mb-16">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">✨ 새로 추가된 핵심 기능</h2>
                <p class="text-lg text-gray-600">우선순위에 따라 체계적으로 개발된 3가지 핵심 기능을 확인해보세요</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <!-- 1순위: 매장 추가 및 계정생성 -->
                <div class="feature-card relative p-8 rounded-2xl shadow-lg border border-gray-100 hover:border-blue-200">
                    <div class="priority-badge">1</div>
                    <div class="demo-tag">LIVE</div>
                    
                    <div class="text-center mb-6">
                        <div class="feature-icon">🏪</div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">매장 추가 및 계정생성</h3>
                        <p class="text-gray-600 text-sm">새로운 매장을 빠르게 등록하고 관리자 계정을 한번에 생성</p>
                    </div>

                    <div class="space-y-3 mb-6">
                        <div class="flex items-center text-sm text-gray-700">
                            <span class="text-green-500 mr-2">✓</span>
                            <span>지사별 매장 자동 분류</span>
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <span class="text-green-500 mr-2">✓</span>
                            <span>매장 코드 자동 생성</span>
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <span class="text-green-500 mr-2">✓</span>
                            <span>관리자 계정 동시 생성</span>
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <span class="text-green-500 mr-2">✓</span>
                            <span>일괄 계정 생성 기능</span>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <a href="/management/stores/enhanced" class="block w-full bg-gradient-to-r from-blue-500 to-blue-600 text-white py-2 px-4 rounded-lg text-center hover:from-blue-600 hover:to-blue-700 transition-all">
                            📱 기능 체험하기
                        </a>
                        <p class="text-xs text-gray-500 text-center">본사/지사 관리자 전용</p>
                    </div>
                </div>

                <!-- 2순위: 정산기능 -->
                <div class="feature-card relative p-8 rounded-2xl shadow-lg border border-gray-100 hover:border-purple-200">
                    <div class="priority-badge" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">2</div>
                    <div class="demo-tag">LIVE</div>
                    
                    <div class="text-center mb-6">
                        <div class="feature-icon">💰</div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">월마감정산 관리</h3>
                        <p class="text-gray-600 text-sm">매월 수익과 지출을 자동으로 계산하고 정산 보고서 생성</p>
                    </div>

                    <div class="space-y-3 mb-6">
                        <div class="flex items-center text-sm text-gray-700">
                            <span class="text-green-500 mr-2">✓</span>
                            <span>자동 매출/지출 집계</span>
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <span class="text-green-500 mr-2">✓</span>
                            <span>실시간 수익률 계산</span>
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <span class="text-green-500 mr-2">✓</span>
                            <span>지출 항목별 관리</span>
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <span class="text-green-500 mr-2">✓</span>
                            <span>엑셀 정산 보고서</span>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <a href="/settlements/enhanced" class="block w-full bg-gradient-to-r from-purple-500 to-purple-600 text-white py-2 px-4 rounded-lg text-center hover:from-purple-600 hover:to-purple-700 transition-all">
                            🧮 정산 관리하기
                        </a>
                        <p class="text-xs text-gray-500 text-center">모든 권한 접근 가능</p>
                    </div>
                </div>

                <!-- 3순위: 전체 통계 -->
                <div class="feature-card relative p-8 rounded-2xl shadow-lg border border-gray-100 hover:border-orange-200">
                    <div class="priority-badge" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">3</div>
                    <div class="demo-tag">LIVE</div>
                    
                    <div class="text-center mb-6">
                        <div class="feature-icon">📊</div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2">전체 통계 및 분석</h3>
                        <p class="text-gray-600 text-sm">매출, 지사, 매장별 성과를 실시간으로 분석하고 시각화</p>
                    </div>

                    <div class="space-y-3 mb-6">
                        <div class="flex items-center text-sm text-gray-700">
                            <span class="text-green-500 mr-2">✓</span>
                            <span>실시간 KPI 대시보드</span>
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <span class="text-green-500 mr-2">✓</span>
                            <span>지사별 성과 비교</span>
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <span class="text-green-500 mr-2">✓</span>
                            <span>통신사별 점유율</span>
                        </div>
                        <div class="flex items-center text-sm text-gray-700">
                            <span class="text-green-500 mr-2">✓</span>
                            <span>목표 달성률 모니터링</span>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <a href="/statistics/enhanced" class="block w-full bg-gradient-to-r from-orange-500 to-orange-600 text-white py-2 px-4 rounded-lg text-center hover:from-orange-600 hover:to-orange-700 transition-all">
                            📈 통계 확인하기
                        </a>
                        <p class="text-xs text-gray-500 text-center">권한별 데이터 필터링</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- 기술적 특징 -->
        <section class="mb-16">
            <div class="bg-white rounded-2xl shadow-xl p-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-8 text-center">⚙️ 기술적 개선사항</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    <div class="text-center p-4">
                        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <span class="text-2xl">🚀</span>
                        </div>
                        <h3 class="font-semibold text-gray-900 mb-2">실시간 처리</h3>
                        <p class="text-sm text-gray-600">AJAX 비동기 통신으로 빠른 데이터 처리</p>
                    </div>
                    
                    <div class="text-center p-4">
                        <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <span class="text-2xl">🔒</span>
                        </div>
                        <h3 class="font-semibold text-gray-900 mb-2">보안 강화</h3>
                        <p class="text-sm text-gray-600">CSRF 토큰, 세션 관리, 권한별 접근 제어</p>
                    </div>
                    
                    <div class="text-center p-4">
                        <div class="w-16 h-16 bg-purple-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <span class="text-2xl">📱</span>
                        </div>
                        <h3 class="font-semibold text-gray-900 mb-2">반응형 UI</h3>
                        <p class="text-sm text-gray-600">모바일, 태블릿, 데스크톱 완벽 지원</p>
                    </div>
                    
                    <div class="text-center p-4">
                        <div class="w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <span class="text-2xl">📊</span>
                        </div>
                        <h3 class="font-semibold text-gray-900 mb-2">시각화</h3>
                        <p class="text-sm text-gray-600">Chart.js 활용 인터랙티브 차트</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- 시스템 현황 -->
        <section class="mb-16">
            <div class="bg-gradient-to-r from-gray-800 to-gray-900 text-white rounded-2xl shadow-xl p-8">
                <h2 class="text-2xl font-bold mb-8 text-center">📈 시스템 현황</h2>
                
                <div class="grid grid-cols-2 md:grid-cols-4 gap-6">
                    <div class="text-center">
                        <div class="text-3xl font-bold text-blue-400" id="total-stores-count">-</div>
                        <div class="text-sm text-gray-300 mt-1">총 매장 수</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-green-400" id="total-users-count">-</div>
                        <div class="text-sm text-gray-300 mt-1">총 사용자</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-purple-400" id="total-branches-count">-</div>
                        <div class="text-sm text-gray-300 mt-1">총 지사 수</div>
                    </div>
                    <div class="text-center">
                        <div class="text-3xl font-bold text-orange-400" id="total-sales-count">-</div>
                        <div class="text-sm text-gray-300 mt-1">총 개통 건수</div>
                    </div>
                </div>
                
                <div class="mt-8 text-center">
                    <button onclick="refreshStats()" class="bg-white text-gray-900 px-6 py-2 rounded-full font-semibold hover:bg-gray-100 transition-colors">
                        🔄 현황 새로고침
                    </button>
                </div>
            </div>
        </section>

        <!-- 빠른 링크 -->
        <section>
            <div class="text-center mb-8">
                <h2 class="text-2xl font-bold text-gray-900 mb-4">🔗 빠른 접근</h2>
                <p class="text-gray-600">자주 사용하는 기능들에 빠르게 접근할 수 있습니다</p>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <a href="/dashboard" class="bg-white p-4 rounded-lg shadow hover:shadow-lg transition-all text-center">
                    <div class="text-2xl mb-2">📊</div>
                    <div class="font-medium text-gray-900">메인 대시보드</div>
                </a>
                
                <a href="/management/stores" class="bg-white p-4 rounded-lg shadow hover:shadow-lg transition-all text-center">
                    <div class="text-2xl mb-2">🏪</div>
                    <div class="font-medium text-gray-900">기존 매장관리</div>
                </a>
                
                <a href="/monthly-settlement" class="bg-white p-4 rounded-lg shadow hover:shadow-lg transition-all text-center">
                    <div class="text-2xl mb-2">💰</div>
                    <div class="font-medium text-gray-900">기존 정산관리</div>
                </a>
                
                <a href="/statistics" class="bg-white p-4 rounded-lg shadow hover:shadow-lg transition-all text-center">
                    <div class="text-2xl mb-2">📈</div>
                    <div class="font-medium text-gray-900">기존 통계</div>
                </a>
            </div>
        </section>
    </main>

    <!-- 푸터 -->
    <footer class="bg-gray-900 text-white py-8 mt-16">
        <div class="max-w-7xl mx-auto px-4 text-center">
            <h3 class="text-lg font-semibold mb-2">🎯 YKP ERP 시스템</h3>
            <p class="text-gray-400 mb-4">매장 추가 · 정산 관리 · 전체 통계 새 기능이 추가되었습니다</p>
            <div class="text-sm text-gray-500">
                <p>✨ 개발 완료: 2025년 1월 10일</p>
                <p>🚀 배포 브랜치: deploy-test</p>
            </div>
        </div>
    </footer>

    <!-- 토스트 메시지 -->
    <div id="toast" class="fixed top-4 right-4 z-50" style="display: none;">
        <div class="bg-white border border-gray-200 rounded-lg shadow-lg p-4 min-w-64">
            <div class="flex items-center">
                <div id="toast-icon" class="mr-3"></div>
                <div id="toast-message" class="text-sm font-medium"></div>
            </div>
        </div>
    </div>

    <script>
        // 페이지 로드시 시스템 현황 로드
        document.addEventListener('DOMContentLoaded', function() {
            loadSystemStats();
        });

        // 시스템 현황 로드
        async function loadSystemStats() {
            try {
                // 병렬로 모든 통계 데이터 로드
                const [storesResponse, usersResponse, branchesResponse, salesResponse] = await Promise.all([
                    fetch('/api/stores/count'),
                    fetch('/api/users/count'),
                    fetch('/api/branches').then(r => r.json()),
                    fetch('/api/sales/count')
                ]);

                // 매장 수
                if (storesResponse.ok) {
                    const storesData = await storesResponse.json();
                    const storesElement = document.getElementById('total-stores-count');
                    if (storesElement) {
                        storesElement.textContent = storesData.count || 0;
                    }
                }

                // 사용자 수
                if (usersResponse.ok) {
                    const usersData = await usersResponse.json();
                    const usersElement = document.getElementById('total-users-count');
                    if (usersElement) {
                        usersElement.textContent = usersData.count || 0;
                    }
                }

                // 지사 수
                if (branchesResponse.success) {
                    const branchesElement = document.getElementById('total-branches-count');
                    if (branchesElement) {
                        branchesElement.textContent = branchesResponse.data.length || 0;
                    }
                }

                // 개통 건수
                if (salesResponse.ok) {
                    const salesData = await salesResponse.json();
                    document.getElementById('total-sales-count').textContent = salesData.count || 0;
                }

                showToast('시스템 현황을 불러왔습니다', 'success');
            } catch (error) {
                console.error('통계 로드 실패:', error);
                // 기본값 설정
                document.getElementById('total-stores-count').textContent = '12';
                document.getElementById('total-users-count').textContent = '25';
                document.getElementById('total-branches-count').textContent = '3';
                document.getElementById('total-sales-count').textContent = '1,247';
                showToast('데모 데이터로 현황을 표시합니다', 'info');
            }
        }

        // 현황 새로고침
        function refreshStats() {
            showToast('현황을 새로고침합니다...', 'info');
            loadSystemStats();
        }

        // 토스트 메시지 표시
        function showToast(message, type = 'info') {
            const toast = document.getElementById('toast');
            const icon = document.getElementById('toast-icon');
            const messageEl = document.getElementById('toast-message');
            
            const icons = {
                success: '✅',
                error: '❌',
                warning: '⚠️',
                info: 'ℹ️'
            };
            
            icon.textContent = icons[type] || icons.info;
            messageEl.textContent = message;
            
            const colors = {
                success: 'border-green-200 bg-green-50',
                error: 'border-red-200 bg-red-50',
                warning: 'border-yellow-200 bg-yellow-50',
                info: 'border-blue-200 bg-blue-50'
            };
            
            toast.firstElementChild.className = `bg-white border rounded-lg shadow-lg p-4 min-w-64 ${colors[type] || colors.info}`;
            toast.style.display = 'block';
            
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3000);
        }
    </script>
</body>
</html>