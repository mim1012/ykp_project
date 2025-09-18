<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>📊 {{ $user->store->name ?? '매장' }} 통계 - YKP ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css" rel="stylesheet">
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
                // KPI API + 기존 API들을 조합해서 사용
                const now = new Date();
                const ym = `${now.getFullYear()}-${String(now.getMonth()+1).padStart(2,'0')}`;
                const start = `${ym}-01`;
                const end = new Date(now.getFullYear(), now.getMonth()+1, 0).toISOString().slice(0,10);
                const [kpiRes, overviewRes, trendRes, statsRes, rankRes] = await Promise.all([
                    fetch('/api/statistics/kpi?days=30', { credentials: 'same-origin' }),
                    fetch('/api/dashboard/overview', { credentials: 'same-origin' }),
                    fetch('/api/dashboard/sales-trend?days=30', { credentials: 'same-origin' }),
                    fetch(`/api/sales/statistics?start_date=${start}&end_date=${end}`, { credentials: 'same-origin' }),
                    fetch('/api/statistics/store-rank', { credentials: 'same-origin' })
                ]);
                const [kpi, overview, trend, stats, rankData] = await Promise.all([
                    kpiRes.json(),
                    trendRes.json(),
                    overviewRes.json(),
                    statsRes.json(),
                    rankRes.json()
                ]);

                // KPI API 데이터를 우선 사용
                if (kpi.success && kpi.data) {
                    const todayData = kpi.data.today;
                    const monthlyData = kpi.data.monthly;

                    // 오늘 개통 건수
                    document.getElementById('today-activations').textContent = `${todayData.activations || 0}건`;

                    // 이번달 매출
                    document.getElementById('month-sales').textContent = `₩${Number(monthlyData.sales || 0).toLocaleString()}`;

                    // 목표 달성률
                    document.getElementById('store-goal').textContent = `${monthlyData.achievement_rate || 0}% 달성`;
                } else {
                    // KPI API 실패 시 기존 방식 사용
                    const todayActivations = overview.data?.today_activations ?? 0;
                    const monthSales = overview.data?.this_month_sales ?? 0;
                    document.getElementById('today-activations').textContent = `${todayActivations}건`;
                    document.getElementById('month-sales').textContent = `₩${Number(monthSales).toLocaleString()}`;

                    // 매장 목표 달성률 계산 (기본 목표: 50만원)
                    const monthlyGoal = 500000; // 50만원
                    const achievementRate = monthSales ? Math.round((monthSales / monthlyGoal) * 100) : 0;
                    document.getElementById('store-goal').textContent = `${achievementRate}% 달성`;
                }

                // 매장 순위 업데이트 (API 데이터 반영)
                if (rankData.success && rankData.data?.store) {
                    const storeRank = rankData.data.store.rank || '-';
                    const storeTotal = rankData.data.store.total || '-';
                    document.getElementById('store-rank').textContent = `${storeRank}위 / ${storeTotal}개`;
                }

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

                // 개인 성과 현황 업데이트
                updatePersonalGoals(kpi);

                console.log('✅ 매장 통계 로딩 완료');

            } catch (error) {
                console.error('❌ 매장 통계 로딩 실패:', error);
            }
        }

        // 개인 성과 현황 업데이트 함수
        function updatePersonalGoals(kpiData) {
            try {
                if (!kpiData.success || !kpiData.data) return;

                const todayData = kpiData.data.today;
                const monthlyData = kpiData.data.monthly;
                const overviewData = kpiData.data.overview;

                // 일일 목표 (5건 기준)
                const dailyGoal = 5;
                const dailyProgress = Math.min(100, Math.round((todayData.activations / dailyGoal) * 100));
                document.getElementById('daily-goal-progress').textContent = `${dailyProgress}%`;
                updateProgressBar('daily-goal-progress', dailyProgress);

                // 주간 목표 (25건 기준) - 주간 데이터가 없으므로 월간 데이터로 추정
                const weeklyGoal = 25;
                const daysInMonth = new Date().getDate();
                const daysInWeek = 7;
                const estimatedWeeklyActivations = Math.round((monthlyData.activations / daysInMonth) * daysInWeek);
                const weeklyProgress = Math.min(100, Math.round((estimatedWeeklyActivations / weeklyGoal) * 100));
                document.getElementById('weekly-goal-progress').textContent = `${weeklyProgress}%`;
                updateProgressBar('weekly-goal-progress', weeklyProgress);

                // 월간 목표 (100건 기준)
                const monthlyGoal = 100;
                const monthlyProgress = Math.min(100, Math.round((monthlyData.activations / monthlyGoal) * 100));
                document.getElementById('monthly-goal-progress').textContent = `${monthlyProgress}%`;
                updateProgressBar('monthly-goal-progress', monthlyProgress);

            } catch (error) {
                console.error('개인 성과 업데이트 실패:', error);
            }
        }

        // 프로그레스 바 업데이트 함수
        function updateProgressBar(elementId, percentage) {
            const element = document.getElementById(elementId);
            if (element) {
                const progressBar = element.parentElement.nextElementSibling?.querySelector('.bg-orange-600');
                if (progressBar) {
                    progressBar.style.width = `${percentage}%`;
                }
            }
        }

        // 페이지 로드 시 통계 로드
        document.addEventListener('DOMContentLoaded', loadStoreStatistics);
    </script>
</body>
</html>
