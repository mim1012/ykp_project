<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>📊 {{ $user->branch->name ?? '지사' }} 통계 - YKP ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.3s ease-out;
        }
        .loading-pulse {
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- 헤더 -->
    <header class="bg-white/95 border-b border-slate-200 backdrop-blur">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-slate-900">{{ $user->branch->name ?? '지사' }} 통계</h1>
                    <span class="ml-2 px-2 py-1 text-xs bg-emerald-50 text-emerald-700 border border-emerald-200 rounded">지사 전용</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/dashboard" class="text-slate-600 hover:text-slate-900">대시보드</a>
                    <a href="/management/stores" class="text-emerald-600 hover:text-emerald-800">매장 관리</a>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto py-6 px-4">
        <!-- 지사 현황 KPI -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            @include('components.kpi-card', ['label' => '소속 매장 수', 'valueId' => 'branch-stores', 'value' => '-'])
            @include('components.kpi-card', ['label' => '지사 총 매출', 'valueId' => 'branch-total-sales', 'value' => '₩0'])
            @include('components.kpi-card', ['label' => '전체 지사 중 순위', 'valueId' => 'branch-rank', 'value' => '-'])
            @include('components.kpi-card', ['label' => '목표 달성률', 'valueId' => 'branch-goal', 'value' => '-'])
        </div>

        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-4 mb-6 flex items-center gap-4">
            <div>
                <label class="text-sm text-gray-600 mr-2">랭킹 기간</label>
                <select id="branch-ranking-period" class="border rounded px-2 py-1">
                    <option value="daily">일간</option>
                    <option value="weekly">주간</option>
                    <option value="monthly" selected>월간</option>
                </select>
            </div>
            <div>
                <label class="text-sm text-gray-600 mr-2">표시 개수</label>
                <input id="branch-ranking-limit" type="number" min="3" max="50" value="10" class="border rounded px-2 py-1 w-24" />
            </div>
            <div>
                <label class="text-sm text-gray-600 mr-2">시작일</label>
                <input id="branch-start-date" type="date" class="border rounded px-2 py-1" />
            </div>
            <div>
                <label class="text-sm text-gray-600 mr-2">종료일</label>
                <input id="branch-end-date" type="date" class="border rounded px-2 py-1" />
            </div>
            <div class="flex items-center gap-2">
                <button id="branch-preset-this-month" class="px-2 py-1 border rounded">이번달</button>
                <button id="branch-preset-this-week" class="px-2 py-1 border rounded">이번주</button>
                <button id="branch-preset-last-month" class="px-2 py-1 border rounded">지난달</button>
            </div>
            <button id="branch-apply-filters" data-testid="apply-filters" class="px-3 py-1 bg-emerald-600 text-white rounded">적용</button>
        </div>

        <!-- 재무 요약 -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h3 class="text-lg font-medium text-gray-900 mb-4">💵 재무 요약 (선택 기간)</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="p-4 bg-gray-50 rounded">
                    <div class="text-sm text-gray-500">총 매출</div>
                    <div class="text-lg font-semibold loading-pulse" id="branch-fin-total-revenue">로딩 중...</div>
                </div>
                <div class="p-4 bg-gray-50 rounded">
                    <div class="text-sm text-gray-500">총 마진</div>
                    <div class="text-lg font-semibold loading-pulse" id="branch-fin-total-margin">로딩 중...</div>
                </div>
                <div class="p-4 bg-gray-50 rounded">
                    <div class="text-sm text-gray-500">총 지출</div>
                    <div class="text-lg font-semibold loading-pulse" id="branch-fin-total-expenses">로딩 중...</div>
                </div>
            </div>
        </div>

        <!-- 소속 매장별 성과 비교 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">🏪 소속 매장별 성과 비교</h3>
                </div>
                <div class="p-6" style="height: 400px;">
                    <canvas id="storeComparisonChart"></canvas>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">📈 월별 성장 추이</h3>
                </div>
                <div class="p-6" style="height: 400px;">
                    <canvas id="branchTrendChart"></canvas>
                </div>
            </div>
        </div>

        <!-- 소속 매장 상세 현황 -->
        <div class="bg-white rounded-lg shadow">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium text-gray-900">📋 소속 매장 상세 현황</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">매장명</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">이번달 매출</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">개통 건수</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">목표 달성률</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">순위</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200" id="store-details-table">
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">서울 1호점</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">₩0</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">0건</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-red-600">0%</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">25위</td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">서울 2호점</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">₩800,000</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">8건</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-green-600">160%</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">2위</td>
                        </tr>
                        <!-- JavaScript로 실제 데이터 로드 -->
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        async function loadBranchStatistics() {
            try {
                console.log('🏬 지사 통계 데이터 로딩...');

                const period = document.getElementById('branch-ranking-period')?.value || 'monthly';
                const limit = parseInt(document.getElementById('branch-ranking-limit')?.value || '10', 10);
                const days = 30; // 기본값: 30일

                const [profileRes, kpiRes, overviewRes, rankingRes, trendRes, finRes] = await Promise.all([
                    fetch('/api/profile', { credentials: 'same-origin' }),
                    fetch(`/api/statistics/kpi?days=${days}`, { credentials: 'same-origin' }),
                    fetch('/api/dashboard/overview', { credentials: 'same-origin' }),
                    fetch(`/api/dashboard/store-ranking?period=${period}&limit=${Math.min(Math.max(limit,3),50)}`, { credentials: 'same-origin' }),
                    fetch(`/api/dashboard/sales-trend?days=${Math.min(Math.max(days,7),90)}`, { credentials: 'same-origin' }),
                    (() => {
                        const now = new Date();
                        const yyyy = now.getFullYear();
                        const mm = String(now.getMonth()+1).padStart(2,'0');
                        const startDefault = `${yyyy}-${mm}-01`;
                        const endDefault = new Date(yyyy, now.getMonth()+1, 0).toISOString().slice(0,10);
                        const startDate = document.getElementById('branch-start-date').value || startDefault;
                        const endDate = document.getElementById('branch-end-date').value || endDefault;
                        return fetch(`/api/dashboard/financial-summary?start_date=${startDate}&end_date=${endDate}`, { credentials: 'same-origin' })
                    })()
                ]);

                const [profile, kpi, overview, ranking, trend, fin] = await Promise.all([
                    profileRes.json(),
                    kpiRes.json(),
                    overviewRes.json(),
                    rankingRes.json(),
                    trendRes.json(),
                    finRes.json()
                ]);

                // KPI 업데이트 - KPI API 데이터 사용
                if (kpi.success && kpi.data) {
                    const monthlyData = kpi.data.monthly;
                    const overviewData = kpi.data.overview;

                    // 지사 총 매출
                    document.getElementById('branch-total-sales').textContent = `₩${Number(monthlyData.sales || 0).toLocaleString()}`;

                    // 목표 달성률
                    document.getElementById('branch-goal').textContent = `${monthlyData.achievement_rate || 0}% 달성`;

                    // 지사 소속 매장 수 로드
                    loadBranchStoresCount();

                    // 지사 순위 계산 (전체 지사 중 순위 - 별도 API에서 가져와야 함)
                    updateBranchRank(monthlyData.sales);
                } else {
                    console.error('KPI API 데이터 로드 실패:', kpi);
                }

                // 소속 매장별 성과 차트
                const stores = (ranking.data?.rankings || []);
                const labels = stores.map(s => s.store_name);
                const values = stores.map(s => s.total_sales);

                new Chart(document.getElementById('storeComparisonChart'), {
                    type: 'bar',
                    data: {
                        labels,
                        datasets: [{
                            label: `매출 (${period === 'monthly' ? '월간' : period === 'weekly' ? '주간' : '일간'})`,
                            data: values,
                            backgroundColor: 'rgba(34, 197, 94, 0.6)',
                            borderColor: 'rgba(34, 197, 94, 1)',
                            borderWidth: 2,
                            borderRadius: 4,
                            borderSkipped: false,
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            title: {
                                display: true,
                                text: `🏪 소속 매장별 매출 성과 (${period === 'monthly' ? '월간' : period === 'weekly' ? '주간' : '일간'})`,
                                font: { size: 14, weight: 'bold' }
                            },
                            legend: {
                                display: false
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '₩' + Number(value).toLocaleString();
                                    }
                                }
                            },
                            x: {
                                ticks: {
                                    maxRotation: 45,
                                    minRotation: 0
                                }
                            }
                        }
                    }
                });

                // 지사 월별(최근 30일) 추이
                const trendLabels = (trend.data?.trend_data || []).map(d => d.day_label);
                const trendData = (trend.data?.trend_data || []).map(d => d.sales);

                new Chart(document.getElementById('branchTrendChart'), {
                    type: 'line',
                    data: {
                        labels: trendLabels,
                        datasets: [{
                            label: '총 매출액 (₩)',
                            data: trendData,
                            borderColor: 'rgba(99, 102, 241, 1)',
                            backgroundColor: 'rgba(99, 102, 241, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4,
                            pointRadius: 5,
                            pointHoverRadius: 7,
                            pointBackgroundColor: 'rgba(99, 102, 241, 1)',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointHoverBackgroundColor: '#fff',
                            pointHoverBorderColor: 'rgba(99, 102, 241, 1)',
                            pointHoverBorderWidth: 3
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        interaction: {
                            mode: 'index',
                            intersect: false,
                        },
                        plugins: {
                            title: {
                                display: true,
                                text: `📈 일별 매출액 추이 (최근 ${days || 30}일)`,
                                font: { size: 14, weight: 'bold' }
                            },
                            legend: {
                                display: true,
                                position: 'top',
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        if (value >= 1000000) {
                                            return '₩' + (value / 1000000).toFixed(0) + 'M';
                                        }
                                        return '₩' + Number(value).toLocaleString();
                                    }
                                },
                                grid: {
                                    color: 'rgba(0, 0, 0, 0.05)'
                                }
                            },
                            x: {
                                grid: {
                                    display: false
                                },
                                ticks: {
                                    maxRotation: 0,
                                    minRotation: 0
                                }
                            }
                        }
                    }
                });

                // 상세 테이블 채우기
                const tbody = document.getElementById('store-details-table');
                tbody.innerHTML = '';
                stores.forEach((s) => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td class=\"px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900\">${s.store_name}</td>
                        <td class=\"px-6 py-4 whitespace-nowrap text-sm text-gray-500\">₩${Number(s.total_sales).toLocaleString()}</td>
                        <td class=\"px-6 py-4 whitespace-nowrap text-sm text-gray-500\">${s.activation_count}건</td>
                        <td class=\"px-6 py-4 whitespace-nowrap text-sm ${s.total_sales > 0 ? 'text-green-600' : 'text-red-600'}\">${Math.min(100, Math.round((s.total_sales/500000)*100))}%</td>
                        <td class=\"px-6 py-4 whitespace-nowrap text-sm text-gray-500\">${s.rank}위</td>
                    `;
                    tbody.appendChild(tr);
                });

                console.log('✅ 지사 통계 로딩 완료');

                // 재무 요약 업데이트
                updateFinancialSummary(fin);

            } catch (error) {
                console.error('❌ 지사 통계 로딩 실패:', error);
            }
        }

        // 페이지 로드 시 통계 로드
        document.addEventListener('DOMContentLoaded', () => {
            loadBranchStatistics();
            const btn = document.getElementById('branch-apply-filters');
            btn?.addEventListener('click', loadBranchStatistics);

            const preset = (type) => {
                const now = new Date();
                let start, end;
                if (type === 'thisMonth') {
                    start = new Date(now.getFullYear(), now.getMonth(), 1);
                    end = new Date(now.getFullYear(), now.getMonth()+1, 0);
                } else if (type === 'lastMonth') {
                    start = new Date(now.getFullYear(), now.getMonth()-1, 1);
                    end = new Date(now.getFullYear(), now.getMonth(), 0);
                } else if (type === 'thisWeek') {
                    const day = now.getDay();
                    const diffToMon = (day+6)%7;
                    start = new Date(now); start.setDate(now.getDate() - diffToMon);
                    end = new Date(start); end.setDate(start.getDate()+6);
                }
                const fmt = d => d.toISOString().slice(0,10);
                document.getElementById('branch-start-date').value = fmt(start);
                document.getElementById('branch-end-date').value = fmt(end);
                loadBranchStatistics();
            };
            document.getElementById('branch-preset-this-month').addEventListener('click', () => preset('thisMonth'));
            document.getElementById('branch-preset-this-week').addEventListener('click', () => preset('thisWeek'));
            document.getElementById('branch-preset-last-month').addEventListener('click', () => preset('lastMonth'));
        });

        // 지사 소속 매장 수 로딩 함수
        async function loadBranchStoresCount() {
            try {
                const branchId = {{ auth()->user()->branch_id ?? 'null' }};
                if (!branchId) {
                    document.getElementById('branch-stores').textContent = '0개 매장';
                    return;
                }

                const response = await fetch(`/api/branches/${branchId}/stores`);
                const result = await response.json();

                if (result.success && result.data) {
                    const storeCount = result.data.length;
                    document.getElementById('branch-stores').textContent = `${storeCount}개 매장`;
                    console.log(`🏪 ${branchId}번 지사 매장 수: ${storeCount}개`);
                } else {
                    document.getElementById('branch-stores').textContent = '0개 매장';
                }
            } catch (error) {
                console.error('지사 매장 수 로딩 실패:', error);
                document.getElementById('branch-stores').textContent = '0개 매장';
            }
        }

        // 지사 순위 업데이트 함수
        async function updateBranchRank(branchSales) {
            try {
                const response = await fetch('/api/dashboard/rankings');
                const result = await response.json();

                if (result.success && result.data?.branches) {
                    const branchId = {{ auth()->user()->branch_id ?? 'null' }};
                    const branches = result.data.branches;

                    // 현재 지사의 순위 찾기
                    const currentBranch = branches.find(b => b.id === branchId);
                    if (currentBranch) {
                        const rank = currentBranch.rank;
                        const total = branches.length;
                        document.getElementById('branch-rank').textContent = `${rank}위 / ${total}개`;
                    } else {
                        document.getElementById('branch-rank').textContent = '-위 / -개';
                    }
                } else {
                    document.getElementById('branch-rank').textContent = '-위 / -개';
                }
            } catch (error) {
                console.error('지사 순위 로딩 실패:', error);
                document.getElementById('branch-rank').textContent = '-위 / -개';
            }
        }

        // 💵 재무 요약 업데이트 함수
        function updateFinancialSummary(finData) {
            try {
                // API 응답에서 실제 데이터 사용
                const revenue = finData?.data?.total_sales || 0;
                const margin = finData?.data?.total_margin || 0;
                const activations = finData?.data?.total_activations || 0;
                const marginRate = finData?.data?.average_margin_rate || 0;

                const expenses = revenue - margin; // 매출 - 마진 = 지출

                // 재무 요약 업데이트
                const revenueEl = document.getElementById('branch-fin-total-revenue');
                const marginEl = document.getElementById('branch-fin-total-margin');
                const expensesEl = document.getElementById('branch-fin-total-expenses');

                if (revenueEl) {
                    revenueEl.textContent = `₩${Number(revenue).toLocaleString()}`;
                    revenueEl.className = 'text-lg font-semibold text-gray-900';
                }
                if (marginEl) {
                    marginEl.textContent = `₩${Number(margin).toLocaleString()}`;
                    marginEl.className = 'text-lg font-semibold text-gray-900';
                }
                if (expensesEl) {
                    expensesEl.textContent = `₩${Number(expenses).toLocaleString()}`;
                    expensesEl.className = 'text-lg font-semibold text-gray-900';
                }
                if (profitEl) {
                    profitEl.textContent = `₩${Number(margin).toLocaleString()}`;
                    profitEl.className = margin >= 0 ? 'text-lg font-semibold text-green-600' : 'text-lg font-semibold text-red-600';
                }

                console.log('재무 요약 업데이트:', { revenue, margin, activations, marginRate });
            } catch (error) {
                console.error('재무 요약 업데이트 오류:', error);
            }
        }
    </script>
</body>
</html>
