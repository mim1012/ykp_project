<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>📊 {{ $user->store->name ?? '매장' }} 통계 - YKP ERP</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50">
    <!-- 헤더 -->
    <header class="bg-white/95 border-b border-slate-200 backdrop-blur">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-slate-900">{{ $user->store->name ?? '매장' }} 통계</h1>
                    <span class="ml-2 px-2 py-1 text-xs bg-amber-50 text-amber-700 border border-amber-200 rounded">매장 전용</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/dashboard" class="text-slate-600 hover:text-slate-900">대시보드</a>
                    <a href="/test/complete-aggrid" class="text-amber-600 hover:text-amber-800">개통표 입력</a>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto py-6 px-4">
        <!-- 매장 실적 KPI -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-orange-500">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                            <span class="text-orange-600 font-semibold">📝</span>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">오늘 개통</dt>
                            <dd class="text-lg font-medium text-gray-900" id="today-activations">0건</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-orange-500">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                            <span class="text-orange-600 font-semibold">💰</span>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">이번달 매출</dt>
                            <dd class="text-lg font-medium text-gray-900" id="month-sales">₩0</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-orange-500">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                            <span class="text-orange-600 font-semibold">📊</span>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">매장 순위</dt>
                            <dd class="text-lg font-medium text-gray-900" id="store-rank">25위 / 25개</dd>
                        </dl>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow p-6 border-l-4 border-orange-500">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-orange-100 rounded-full flex items-center justify-center">
                            <span class="text-orange-600 font-semibold">🎯</span>
                        </div>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">목표 달성률</dt>
                            <dd class="text-lg font-medium text-gray-900" id="store-goal">0% 달성</dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <!-- 개통 실적 분석 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">📈 일별 개통 실적 (30일)</h3>
                </div>
                <div class="p-6">
                    <canvas id="dailyActivationsChart" width="400" height="200"></canvas>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">📱 통신사별 실적</h3>
                </div>
                <div class="p-6">
                    <canvas id="carrierPerformanceChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>

        <!-- 개인 성과 현황 -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">💼 개인 성과 현황</h3>
            </div>
            <div class="p-6">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <!-- 일일 목표 -->
                    <div class="text-center p-4 bg-orange-50 rounded-lg">
                        <div class="text-2xl font-bold text-orange-600" id="daily-goal-progress">0%</div>
                        <div class="text-sm text-gray-600">일일 목표 (5건)</div>
                        <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                            <div class="bg-orange-600 h-2 rounded-full" style="width: 0%"></div>
                        </div>
                    </div>

                    <!-- 주간 목표 -->
                    <div class="text-center p-4 bg-orange-50 rounded-lg">
                        <div class="text-2xl font-bold text-orange-600" id="weekly-goal-progress">0%</div>
                        <div class="text-sm text-gray-600">주간 목표 (25건)</div>
                        <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                            <div class="bg-orange-600 h-2 rounded-full" style="width: 0%"></div>
                        </div>
                    </div>

                    <!-- 월간 목표 -->
                    <div class="text-center p-4 bg-orange-50 rounded-lg">
                        <div class="text-2xl font-bold text-orange-600" id="monthly-goal-progress">0%</div>
                        <div class="text-sm text-gray-600">월간 목표 (100건)</div>
                        <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                            <div class="bg-orange-600 h-2 rounded-full" style="width: 0%"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // 매장 통계 데이터 로드
        async function loadStoreStatistics() {
            try {
                console.log('🏪 매장 통계 데이터 로딩...');
                
                // 매장 전용 데이터 로드
                const storeId = {{ $user->store_id ?? 1 }};
                // 보호된 대시보드 API + 판매 통계(통신사 분포)
                const now = new Date();
                const ym = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}`;
                const start = `${ym}-01`;
                const end = new Date(now.getFullYear(), now.getMonth()+1, 0).toISOString().slice(0,10);
                const [overviewRes, trendRes, statsRes] = await Promise.all([
                    fetch('/api/dashboard/overview', { credentials: 'same-origin' }),
                    fetch('/api/dashboard/sales-trend?days=30', { credentials: 'same-origin' }),
                    fetch(`/api/sales/statistics?start_date=${start}&end_date=${end}`, { credentials: 'same-origin' })
                ]);
                const [overview, trend, stats] = await Promise.all([
                    overviewRes.json(),
                    trendRes.json(),
                    statsRes.json()
                ]);
                
                const today = overview.data?.today ?? { activations: 0 };
                const month = overview.data?.month ?? { sales: 0 };
                document.getElementById('today-activations').textContent = `${today.activations}건`;
                document.getElementById('month-sales').textContent = `₩${Number(month.sales || 0).toLocaleString()}`;

                // 일별 개통 실적 차트 (30일)
                const dailyChart = new Chart(document.getElementById('dailyActivationsChart'), {
                    type: 'line',
                    data: {
                        labels: (trend.data?.trend_data || []).map(d => d.day_label),
                        datasets: [{
                            label: '일별 개통 건수',
                            data: (trend.data?.trend_data || []).map(d => d.activations),
                            fill: true,
                            borderColor: 'rgba(249, 115, 22, 1)',
                            backgroundColor: 'rgba(249, 115, 22, 0.1)',
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: { display: true, text: '최근 30일 개통 실적' }
                        }
                    }
                });

                // 통신사별 실적 차트
                const carriers = (stats.by_carrier || []).map(c => c.carrier || c.Carrier || '기타');
                const carrierCounts = (stats.by_carrier || []).map(c => c.count || 0);
                const carrierChart = new Chart(document.getElementById('carrierPerformanceChart'), {
                    type: 'doughnut',
                    data: {
                        labels: carriers.length ? carriers : ['SK','KT','LG','MVNO'],
                        datasets: [{
                            data: carrierCounts.length ? carrierCounts : [0,0,0,0],
                            backgroundColor: [
                                'rgba(255, 99, 132, 0.8)',
                                'rgba(54, 162, 235, 0.8)', 
                                'rgba(255, 205, 86, 0.8)',
                                'rgba(16, 185, 129, 0.8)'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: { display: true, text: '통신사별 개통 실적' }
                        }
                    }
                });

                console.log('✅ 매장 통계 로딩 완료');

            } catch (error) {
                console.error('❌ 매장 통계 로딩 실패:', error);
            }
        }

        // 페이지 로드 시 통계 로드
        document.addEventListener('DOMContentLoaded', loadStoreStatistics);
    </script>
</body>
</html>
