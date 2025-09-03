<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>📊 전체 시스템 통계 - YKP ERP</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50">
    <!-- 헤더 -->
    <header class="bg-white/95 border-b border-slate-200 backdrop-blur">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-slate-900">전체 시스템 통계</h1>
                    <span class="ml-2 px-2 py-1 text-xs bg-indigo-50 text-indigo-700 border border-indigo-200 rounded">본사 전용</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/dashboard" class="text-slate-600 hover:text-slate-900">대시보드</a>
                    <a href="/management/stores" class="text-indigo-600 hover:text-indigo-800">매장 관리</a>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto py-6 px-4">
        <!-- Filters -->
        <div class="bg-white rounded-lg shadow p-4 mb-6 flex items-center gap-4">
            <div>
                <label class="text-sm text-gray-600 mr-2">랭킹 기간</label>
                <select id="hq-ranking-period" class="border rounded px-2 py-1">
                    <option value="daily">일간</option>
                    <option value="weekly">주간</option>
                    <option value="monthly" selected>월간</option>
                </select>
            </div>
            <div>
                <label class="text-sm text-gray-600 mr-2">표시 개수</label>
                <input id="hq-ranking-limit" type="number" min="3" max="50" value="10" class="border rounded px-2 py-1 w-24" />
            </div>
            <div>
                <label class="text-sm text-gray-600 mr-2">시작일</label>
                <input id="hq-start-date" type="date" class="border rounded px-2 py-1" />
            </div>
            <div>
                <label class="text-sm text-gray-600 mr-2">종료일</label>
                <input id="hq-end-date" type="date" class="border rounded px-2 py-1" />
            </div>
            <div class="flex items-center gap-2">
                <button id="hq-preset-this-month" class="px-2 py-1 border rounded">이번달</button>
                <button id="hq-preset-this-week" class="px-2 py-1 border rounded">이번주</button>
                <button id="hq-preset-last-month" class="px-2 py-1 border rounded">지난달</button>
            </div>
            <button id="hq-apply-filters" data-testid="apply-filters" class="px-3 py-1 bg-indigo-600 text-white rounded">적용</button>
        </div>
        <!-- 전체 현황 KPI -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            @include('components.kpi-card', ['label' => '전체 지사', 'valueId' => 'total-branches', 'value' => '-'])
            @include('components.kpi-card', ['label' => '전체 매장', 'valueId' => 'total-stores', 'value' => '-'])
            @include('components.kpi-card', ['label' => '이번달 매출', 'valueId' => 'total-sales', 'value' => '₩0'])
            @include('components.kpi-card', ['label' => '목표 달성률', 'valueId' => 'system-goal', 'value' => '-'])
        </div>

        <!-- 지사별 성과 비교 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">🏢 지사별 성과 비교</h3>
                </div>
                <div class="p-6">
                    <canvas id="branchComparisonChart" width="400" height="200"></canvas>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">🏪 매장별 TOP 10 랭킹</h3>
                </div>
                <div class="p-6">
                    <div class="space-y-4" id="store-ranking-list">
                        <div class="flex justify-between items-center p-4 bg-gray-50 rounded">
                            <div class="flex items-center">
                                <span class="text-lg font-bold text-yellow-600">🥇</span>
                                <div class="ml-3">
                                    <div class="text-sm font-medium text-gray-900">경기 1호점</div>
                                    <div class="text-sm text-gray-500">경기지사</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-medium text-gray-900">₩1,400,000</div>
                                <div class="text-sm text-gray-500">14건 개통</div>
                            </div>
                        </div>
                        <!-- 추가 순위 데이터는 JavaScript로 로드 -->
                    </div>
                </div>
            </div>
        </div>

        <!-- 월별 추이 및 지역별 분포 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">📈 월별 성장 추이</h3>
                </div>
                <div class="p-6">
                    <canvas id="monthlyTrendChart" width="400" height="200"></canvas>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900">📊 통신사별 점유율</h3>
                </div>
                <div class="p-6">
                    <canvas id="carrierShareChart" width="400" height="200"></canvas>
                    <div class="mt-4 overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-gray-600">통신사</th>
                                    <th class="px-4 py-2 text-left text-gray-600">건수</th>
                                    <th class="px-4 py-2 text-left text-gray-600">점유율(%)</th>
                                </tr>
                            </thead>
                            <tbody id="hq-carrier-table-body"></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 재무 요약 -->
        <div class="mt-6 bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-medium text-gray-900 mb-4">💵 재무 요약 (선택 기간)</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="p-4 bg-gray-50 rounded">
                    <div class="text-sm text-gray-500">총 매출</div>
                    <div class="text-lg font-semibold" id="hq-fin-total-revenue">₩0</div>
                </div>
                <div class="p-4 bg-gray-50 rounded">
                    <div class="text-sm text-gray-500">총 마진</div>
                    <div class="text-lg font-semibold" id="hq-fin-total-margin">₩0</div>
                </div>
                <div class="p-4 bg-gray-50 rounded">
                    <div class="text-sm text-gray-500">총 지출</div>
                    <div class="text-lg font-semibold" id="hq-fin-total-expenses">₩0</div>
                </div>
                <div class="p-4 bg-gray-50 rounded">
                    <div class="text-sm text-gray-500">순이익</div>
                    <div class="text-lg font-semibold" id="hq-fin-net-profit">₩0</div>
                </div>
            </div>
        </div>
    </main>

    <script>
        // 본사 통계 데이터 로드
        document.addEventListener('DOMContentLoaded', async function() {
            await loadHeadquartersStatistics();
            document.getElementById('hq-apply-filters').addEventListener('click', async () => {
                await loadHeadquartersStatistics();
            });
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
                    const day = now.getDay(); // 0=Sun
                    const diffToMon = (day+6)%7; // Mon=0
                    start = new Date(now); start.setDate(now.getDate() - diffToMon);
                    end = new Date(start); end.setDate(start.getDate()+6);
                }
                const fmt = d => d.toISOString().slice(0,10);
                document.getElementById('hq-start-date').value = fmt(start);
                document.getElementById('hq-end-date').value = fmt(end);
                loadHeadquartersStatistics();
            };
            document.getElementById('hq-preset-this-month').addEventListener('click', () => preset('thisMonth'));
            document.getElementById('hq-preset-this-week').addEventListener('click', () => preset('thisWeek'));
            document.getElementById('hq-preset-last-month').addEventListener('click', () => preset('lastMonth'));
        });

        async function loadHeadquartersStatistics() {
            try {
                console.log('📊 본사 통계 데이터 로딩 중...');
                const period = document.getElementById('hq-ranking-period').value;
                const limit = parseInt(document.getElementById('hq-ranking-limit').value || '10', 10);
                // 실제 시스템 현황 데이터 로드
                // 기간 설정 기본값: 이번달
                const now = new Date();
                const yyyy = now.getFullYear();
                const mm = String(now.getMonth()+1).padStart(2,'0');
                const startDefault = `${yyyy}-${mm}-01`;
                const endDefault = new Date(yyyy, now.getMonth()+1, 0).toISOString().slice(0,10);
                const startDate = document.getElementById('hq-start-date').value || startDefault;
                const endDate = document.getElementById('hq-end-date').value || endDefault;

                const [profileRes, overviewRes, rankingRes, branchesRes, finRes, carrierRes] = await Promise.all([
                    fetch('/api/profile', { credentials: 'same-origin' }),
                    fetch('/api/dashboard/overview', { credentials: 'same-origin' }),
                    fetch(`/api/dashboard/store-ranking?period=${period}&limit=${Math.min(Math.max(limit,3),50)}`, { credentials: 'same-origin' }),
                    fetch('/api/users/branches', { credentials: 'same-origin' }),
                    fetch(`/api/dashboard/financial-summary?start_date=${startDate}&end_date=${endDate}`, { credentials: 'same-origin' }),
                    // 캐리어 분포: 선택 월 기준(종료일의 연-월)
                    (() => { const ym = endDate.slice(0,7); return fetch(`/api/dashboard/dealer-performance?year_month=${ym}`, { credentials: 'same-origin' }); })()
                ]);

                const [profile, overview, ranking, branches, fin, carrierPerf] = await Promise.all([
                    profileRes.json(),
                    overviewRes.json(),
                    rankingRes.json(),
                    branchesRes.json(),
                    finRes.json(),
                    carrierRes.json()
                ]);

                // KPI 업데이트
                // 대시보드 개요 데이터 적용
                const today = overview.data?.today ?? { sales: 0, activations: 0 };
                const month = overview.data?.month ?? { sales: 0, activations: 0 };

                const storeCount = (profile.permissions?.accessible_store_ids || []).length;
                const branchCount = Array.isArray(branches?.data) ? branches.data.length : '-';
                document.getElementById('total-branches').textContent = `${branchCount}개 지사`;
                document.getElementById('total-stores').textContent = `${storeCount}개 매장`;
                document.getElementById('total-sales').textContent = `₩${Number(month.sales || 0).toLocaleString()}`;
                
                const achievementRate = ((month.sales / 50000000) * 100).toFixed(1);
                document.getElementById('system-goal').textContent = `${achievementRate}% 달성`;

                // 지사별 성과 차트
                const branchChart = new Chart(document.getElementById('branchComparisonChart'), {
                    type: 'bar',
                    data: {
                        labels: (ranking.data?.rankings || []).map(r => r.branch_name || '지사'),
                        datasets: [{
                            label: '매출 합계 (상위 매장)',
                            data: (ranking.data?.rankings || []).map(r => r.total_sales || 0),
                            backgroundColor: 'rgba(59, 130, 246, 0.5)',
                            borderColor: 'rgba(59, 130, 246, 1)',
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        plugins: {
                            title: { 
                                display: true, 
                                text: `상위 매장 매출 (${period})` 
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    stepSize: 1
                                }
                            }
                        }
                    }
                });

                console.log('✅ 본사 통계 로딩 완료');

                // 재무 요약 업데이트
                try {
                    const rev = fin?.data?.revenue || { total_revenue: 0, total_margin: 0 };
                    const exp = fin?.data?.expenses || { total_expenses: 0 };
                    const prof = fin?.data?.profit || { net_profit: 0 };
                    document.getElementById('hq-fin-total-revenue').textContent = `₩${Number(rev.total_revenue||0).toLocaleString()}`;
                    document.getElementById('hq-fin-total-margin').textContent = `₩${Number(rev.total_margin||0).toLocaleString()}`;
                    document.getElementById('hq-fin-total-expenses').textContent = `₩${Number(exp.total_expenses||0).toLocaleString()}`;
                    document.getElementById('hq-fin-net-profit').textContent = `₩${Number(prof.net_profit||0).toLocaleString()}`;
                } catch {}

                // 캐리어 표 갱신
                try {
                    const tbody = document.getElementById('hq-carrier-table-body');
                    tbody.innerHTML = '';
                    const rows = carrierPerf?.data?.carrier_breakdown || [];
                    rows.forEach(r => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td class=\"px-4 py-2\">${r.carrier}</td>
                            <td class=\"px-4 py-2\">${r.count}</td>
                            <td class=\"px-4 py-2\">${r.percentage ?? 0}</td>
                        `;
                        tbody.appendChild(tr);
                    });
                } catch {}

            } catch (error) {
                console.error('❌ 본사 통계 로딩 실패:', error);
            }
        }

        // 사이드바 네비게이션 함수들
        function openStoreManagement() {
            window.location.href = '/management/stores';
        }

        function downloadSystemReport() {
            alert('📄 전체 시스템 리포트 다운로드 기능 구현 예정');
        }
    </script>
</body>
</html>
