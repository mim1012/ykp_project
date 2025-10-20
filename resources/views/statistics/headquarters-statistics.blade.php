<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>📊 전체 시스템 통계 - YKP ERP</title>
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
            <button id="export-store-stats-btn" class="px-4 py-1 bg-green-600 text-white rounded hover:bg-green-700 transition-colors flex items-center gap-2">
                <span>📥</span>
                <span>매장별 통계 엑셀 다운로드</span>
            </button>
        </div>
        <!-- 전체 현황 KPI -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            @include('components.kpi-card', ['label' => '전체 지사', 'valueId' => 'total-branches', 'value' => '-'])
            @include('components.kpi-card', ['label' => '전체 매장', 'valueId' => 'total-stores', 'value' => '-'])
            @include('components.kpi-card', ['label' => '이번달 매출', 'valueId' => 'total-sales', 'value' => '₩0'])
            @include('components.kpi-card', ['label' => '목표 달성률', 'valueId' => 'system-goal', 'value' => '-'])
        </div>

        <!-- 재무 요약 -->
        <div class="bg-white rounded-lg shadow p-6 mb-8">
            <h3 class="text-lg font-medium text-gray-900 mb-4">💵 재무 요약 (선택 기간)</h3>
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <div class="p-4 bg-gray-50 rounded">
                    <div class="text-sm text-gray-500">총 매출</div>
                    <div class="text-lg font-semibold loading-pulse" id="hq-fin-total-revenue">로딩 중...</div>
                </div>
                <div class="p-4 bg-gray-50 rounded">
                    <div class="text-sm text-gray-500">총 마진</div>
                    <div class="text-lg font-semibold loading-pulse" id="hq-fin-total-margin">로딩 중...</div>
                </div>
                <div class="p-4 bg-gray-50 rounded">
                    <div class="text-sm text-gray-500">총 지출</div>
                    <div class="text-lg font-semibold loading-pulse" id="hq-fin-total-expenses">로딩 중...</div>
                </div>
                <div class="p-4 bg-gray-50 rounded">
                    <div class="text-sm text-gray-500">순이익</div>
                    <div class="text-lg font-semibold loading-pulse" id="hq-fin-net-profit">로딩 중...</div>
                </div>
            </div>
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
                        <div id="dynamic-ranking-container" class="text-center text-gray-500 py-4">
                            <div class="text-sm">📊 매장 순위 로딩 중...</div>
                            <div class="text-xs mt-1">API에서 실제 데이터를 불러오고 있습니다</div>
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
    </main>

    <script>
        // 본사 통계 데이터 로드
        document.addEventListener('DOMContentLoaded', async function() {
            await loadHeadquartersStatistics();
            document.getElementById('hq-apply-filters').addEventListener('click', async () => {
                await loadHeadquartersStatistics();
            });

            // 매장별 통계 엑셀 다운로드 버튼 이벤트
            document.getElementById('export-store-stats-btn').addEventListener('click', function() {
                console.log('📥 매장별 통계 엑셀 다운로드 시작...');
                window.location.href = '/api/reports/store-statistics';
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

        // 개선된 JSON 파싱 함수 (잘못된 오류 분류 수정)
        async function safeJsonParse(response, apiName) {
            try {
                // HTTP 상태 코드 정확히 체크
                console.log(`🔍 ${apiName} API 상태:`, response.status, response.statusText, response.url);
                
                if (!response.ok) {
                    const text = await response.text();
                    console.warn(`⚠️ ${apiName} API 실제 오류 (${response.status}):`, text.substring(0, 200));
                    return { success: false, data: {} };
                }
                
                const text = await response.text();
                console.log(`📄 ${apiName} API 응답 내용:`, text.substring(0, 200) + '...');
                
                // HTML 응답 감지 (실제 오류인 경우)
                if (text.trim().startsWith('<!DOCTYPE') || text.trim().startsWith('<html')) {
                    console.warn(`⚠️ ${apiName} API가 HTML을 반환했습니다 (라우트 문제):`, text.substring(0, 100));
                    return { success: false, data: {} };
                }
                
                const json = JSON.parse(text);
                console.log(`✅ ${apiName} API 성공 (실제 200 OK):`, json);
                return json;
                
            } catch (error) {
                console.error(`❌ ${apiName} API 진짜 파싱 오류:`, error);
                return { success: false, data: {} };
            }
        }

        // Railway PostgreSQL 동시성 충돌 해결을 위한 순차 API 호출 함수
        async function callApiSequentially(apiConfig, retryCount = 2) {
            for (let attempt = 1; attempt <= retryCount + 1; attempt++) {
                try {
                    console.log(`🔄 ${apiConfig.name} API 호출 시도 ${attempt}/${retryCount + 1}: ${apiConfig.url}`);
                    
                    const response = await fetch(apiConfig.url, { 
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json'
                        }
                    });
                    
                    const result = await safeJsonParse(response, apiConfig.name);
                    
                    if (result.success) {
                        console.log(`✅ ${apiConfig.name} API 성공 (${attempt}번째 시도)`);
                        return result;
                    } else if (attempt < retryCount + 1) {
                        console.warn(`⚠️ ${apiConfig.name} API 실패, 재시도 중... (${attempt}/${retryCount + 1})`);
                        await new Promise(resolve => setTimeout(resolve, 200 * attempt)); // 점진적 대기
                    }
                } catch (error) {
                    console.error(`❌ ${apiConfig.name} API 오류 (${attempt}번째 시도):`, error);
                    if (attempt < retryCount + 1) {
                        await new Promise(resolve => setTimeout(resolve, 200 * attempt));
                    }
                }
            }
            
            console.error(`💥 ${apiConfig.name} API 최종 실패 (${retryCount + 1}번 시도 모두 실패)`);
            return { success: false, data: {} };
        }

        // 🎨 섹션별 즉시 UI 업데이트 함수 (사용자 경험 개선)
        function updateUISection(apiName, result, config) {
            try {
                console.log(`🎨 ${apiName} 섹션 UI 업데이트 중...`);
                
                switch(apiName) {
                    case 'profile':
                        if (result.success && result.data) {
                            // 전체 매장 수는 별도 API에서 가져오기 (profile이 아닌 stores API 사용)
                            loadTotalStoresCount();
                        }
                        break;
                        
                    case 'overview':
                        if (result.success && result.data) {
                            // DashboardController의 실제 API 응답 구조 사용
                            const monthSales = result.data.this_month_sales || 0;
                            const todayActivations = result.data.today_activations || 0;

                            // KPI 카드 즉시 업데이트
                            document.getElementById('total-sales').textContent = `₩${Number(monthSales).toLocaleString()}`;
                            document.getElementById('total-sales').className = 'text-2xl font-bold text-green-600';

                            // 시스템 목표는 Goals API에서 가져오기
                            updateSystemGoalAchievement(monthSales);

                            console.log(`💰 매출 정보 업데이트 완료: ₩${Number(monthSales).toLocaleString()}`);
                        }
                        break;
                        
                    case 'branches':
                        if (result.success && Array.isArray(result.data)) {
                            const totalBranchesElement = document.getElementById('total-branches');
                            if (totalBranchesElement) {
                                totalBranchesElement.textContent = `${result.data.length}개 지사`;
                                totalBranchesElement.className = 'text-2xl font-bold text-gray-900';
                            }
                            console.log(`🏢 지사 정보 업데이트 완료: ${result.data.length}개`);
                        }
                        break;
                        
                    case 'ranking':
                        if (result.success && Array.isArray(result.data)) {
                            updateStoreRanking(result.data);
                            console.log(`🏆 매장 랭킹 업데이트 완료: ${result.data.length}개 매장`);
                        }
                        break;
                        
                    case 'financial':
                        if (result.success && result.data) {
                            updateFinancialSummary(result.data);
                            console.log(`💵 재무 요약 업데이트 완료`);
                        }
                        break;
                        
                    case 'branchPerformance':
                        if (result.success && Array.isArray(result.data)) {
                            // 지사별 성과 차트에 즉시 반영
                            updateBranchChart(result.data, config.period || 'monthly');
                            console.log(`🏢 지사별 성과 업데이트 완료: ${result.data.length}개 지사`);
                        }
                        break;

                    case 'carrier':
                        if (result.success && result.data?.carrier_breakdown) {
                            updateCarrierTable(result.data.carrier_breakdown);
                            console.log(`📊 통신사 점유율 업데이트 완료: ${result.data.carrier_breakdown.length}개 통신사`);
                        }
                        break;

                    case 'monthlyTrend':
                        if (result.success && result.data) {
                            updateMonthlyTrendChart(result.data);
                            console.log(`📈 월별 성장 추이 업데이트 완료: ${result.data.labels?.length || 0}개월 데이터`);
                        }
                        break;
                }

            } catch (error) {
                console.error(`❌ ${apiName} UI 업데이트 오류:`, error);
            }
        }

        // 📊 매장 랭킹 업데이트 함수
        function updateStoreRanking(rankings) {
            try {
                const rankingContainer = document.getElementById('dynamic-ranking-container');
                if (!rankingContainer) return;
                
                if (rankings && rankings.length > 0) {
                    rankingContainer.innerHTML = '';
                    
                    rankings.slice(0, 5).forEach((store, index) => {
                        const storeDiv = document.createElement('div');
                        storeDiv.className = 'flex justify-between items-center p-4 bg-gray-50 rounded mb-2 animate-fade-in';
                        storeDiv.innerHTML = `
                            <div class="flex items-center">
                                <span class="text-lg font-bold text-yellow-600">${index === 0 ? '🥇' : index === 1 ? '🥈' : index === 2 ? '🥉' : '🏆'}</span>
                                <div class="ml-3">
                                    <div class="text-sm font-medium text-gray-900">${store.store_name}</div>
                                    <div class="text-sm text-gray-500">${store.branch_name}</div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-sm font-medium text-gray-900">₩${Number(store.total_sales).toLocaleString()}</div>
                                <div class="text-sm text-gray-500">${store.activation_count}건 개통</div>
                            </div>
                        `;
                        rankingContainer.appendChild(storeDiv);
                    });
                } else {
                    rankingContainer.innerHTML = '<div class="text-center text-gray-500 py-4">📊 매장 데이터를 불러오는 중...</div>';
                }
            } catch (error) {
                console.error('매장 랭킹 업데이트 오류:', error);
            }
        }

        // 💵 재무 요약 업데이트 함수
        function updateFinancialSummary(finData) {
            try {
                // API 응답에서 실제 데이터 사용
                const revenue = finData.total_sales || 0;
                const margin = finData.total_margin || 0;
                const activations = finData.total_activations || 0;
                const marginRate = finData.average_margin_rate || 0;

                // 지출과 순이익 계산 (세금을 지출로 간주)
                const expenses = revenue - margin; // 매출 - 마진 = 지출
                const netProfit = margin; // 순이익 = 마진

                // 재무 요약 업데이트
                document.getElementById('hq-fin-total-revenue').textContent = `₩${Number(revenue).toLocaleString()}`;
                document.getElementById('hq-fin-total-margin').textContent = `₩${Number(margin).toLocaleString()}`;
                document.getElementById('hq-fin-total-expenses').textContent = `₩${Number(expenses).toLocaleString()}`;
                document.getElementById('hq-fin-net-profit').textContent = `₩${Number(margin).toLocaleString()}`;

                // 긍정적/부정적 수치에 따른 색상 적용
                document.getElementById('hq-fin-net-profit').className = margin >= 0 ? 'text-lg font-semibold text-green-600' : 'text-lg font-semibold text-red-600';

                // 추가: 이번달 매출 업데이트 (KPI 카드)
                const totalSalesEl = document.getElementById('total-sales');
                if (totalSalesEl) {
                    totalSalesEl.textContent = `₩${Number(revenue).toLocaleString()}`;
                }

                // 추가: 목표 달성률 업데이트 (월 목표 10억 기준)
                const monthlyGoal = 1000000000; // 10억원
                const achievementRate = revenue > 0 ? Math.round((revenue / monthlyGoal) * 100) : 0;
                const systemGoalEl = document.getElementById('system-goal');
                if (systemGoalEl) {
                    systemGoalEl.textContent = `${achievementRate}% 달성`;
                }

                console.log('재무 요약 업데이트:', { revenue, margin, activations, marginRate, achievementRate });
            } catch (error) {
                console.error('재무 요약 업데이트 오류:', error);
            }
        }

        // 📊 통신사 테이블 업데이트 함수
        function updateCarrierTable(carrierBreakdown) {
            try {
                const tbody = document.getElementById('hq-carrier-table-body');
                if (!tbody) return;
                
                tbody.innerHTML = '';
                carrierBreakdown.forEach((carrier, index) => {
                    const tr = document.createElement('tr');
                    tr.className = index % 2 === 0 ? 'bg-white' : 'bg-gray-50';
                    tr.innerHTML = `
                        <td class="px-4 py-2 font-medium">${carrier.carrier}</td>
                        <td class="px-4 py-2">${carrier.count}건</td>
                        <td class="px-4 py-2 font-semibold text-indigo-600">${carrier.percentage}%</td>
                    `;
                    tbody.appendChild(tr);
                });
            } catch (error) {
                console.error('통신사 테이블 업데이트 오류:', error);
            }
        }

        // 📈 지사별 성과 차트 업데이트 함수
        function updateBranchChart(branchPerformanceData, period) {
            try {
                const ctx = document.getElementById('branchComparisonChart');
                if (!ctx) return;

                // 기존 차트 인스턴스 제거 (중복 방지)
                if (window.branchChart) {
                    window.branchChart.destroy();
                }

                // 매출 순으로 정렬하고 TOP 8 선택
                const sortedData = (branchPerformanceData || [])
                    .sort((a, b) => (b.revenue || 0) - (a.revenue || 0))
                    .slice(0, 8);

                window.branchChart = new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: sortedData.map(branch => branch.name || '지사'),
                        datasets: [{
                            label: '매출 합계 (₩)',
                            data: sortedData.map(branch => branch.revenue || 0),
                            backgroundColor: 'rgba(59, 130, 246, 0.6)',
                            borderColor: 'rgba(59, 130, 246, 1)',
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
                                text: `🏢 지사별 매출 성과 (${period === 'monthly' ? '월간' : period === 'weekly' ? '주간' : '일간'})`,
                                font: { size: 14, weight: 'bold' }
                            },
                            legend: {
                                display: false // 단일 데이터셋이므로 범례 숨김
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
                        },
                        elements: {
                            bar: {
                                borderRadius: 4
                            }
                        }
                    }
                });
                
                console.log(`📊 지사별 성과 차트 업데이트 완료: ${(branchPerformanceData || []).length}개 데이터`);
            } catch (error) {
                console.error('지사별 성과 차트 업데이트 오류:', error);
            }
        }

        // 📈 월별 성장 추이 꺾은선 그래프 업데이트 함수
        function updateMonthlyTrendChart(trendData) {
            try {
                const ctx = document.getElementById('monthlyTrendChart');
                if (!ctx) return;

                // 기존 차트 인스턴스 제거 (중복 방지)
                if (window.monthlyTrendChart) {
                    window.monthlyTrendChart.destroy();
                }

                // 데이터 검증
                if (!trendData.labels || !trendData.sales || trendData.labels.length === 0) {
                    console.warn('⚠️ 월별 추이 데이터가 비어있습니다.');
                    return;
                }

                // 월 라벨 포맷팅 (2024-10 -> 10월)
                const formattedLabels = trendData.labels.map(label => {
                    const [year, month] = label.split('-');
                    return `${month}월`;
                });

                // 꺾은선 그래프 생성
                window.monthlyTrendChart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: formattedLabels,
                        datasets: [{
                            label: '총 매출액 (₩)',
                            data: trendData.sales,
                            borderColor: 'rgba(99, 102, 241, 1)', // 인디고 색상
                            backgroundColor: 'rgba(99, 102, 241, 0.1)',
                            borderWidth: 3,
                            fill: true,
                            tension: 0.4, // 부드러운 곡선
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
                                text: '📈 월별 총 매출액 추이 (최근 12개월)',
                                font: { size: 14, weight: 'bold' }
                            },
                            legend: {
                                display: true,
                                position: 'top',
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        const value = context.parsed.y;
                                        const activations = trendData.activations?.[context.dataIndex] || 0;
                                        return [
                                            `매출액: ₩${Number(value).toLocaleString()}`,
                                            `개통 건수: ${activations}건`
                                        ];
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        // 백만원 단위로 표시
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

                console.log(`📈 월별 추이 그래프 생성 완료: ${trendData.labels.length}개월 데이터`);
            } catch (error) {
                console.error('월별 추이 그래프 업데이트 오류:', error);
            }
        }

        async function loadHeadquartersStatistics() {
            try {
                console.log('🚀 Railway PostgreSQL 최적화 순차 로딩 시작...');
                const period = document.getElementById('hq-ranking-period').value;
                const limit = parseInt(document.getElementById('hq-ranking-limit').value || '10', 10);
                
                // 기간 설정 기본값: 이번달
                const now = new Date();
                const yyyy = now.getFullYear();
                const mm = String(now.getMonth()+1).padStart(2,'0');
                const startDefault = `${yyyy}-${mm}-01`;
                const endDefault = new Date(yyyy, now.getMonth()+1, 0).toISOString().slice(0,10);
                const startDate = document.getElementById('hq-start-date').value || startDefault;
                const endDate = document.getElementById('hq-end-date').value || endDefault;
                const ym = endDate.slice(0,7);

                // 🎯 API 호출 순서 정의 (Railway PostgreSQL 충돌 방지)
                const apiSequence = [
                    { name: 'profile', url: '/api/profile' },
                    { name: 'overview', url: '/api/dashboard/overview' },
                    { name: 'ranking', url: `/api/dashboard/store-ranking?period=${period}&limit=${Math.min(Math.max(limit,3),50)}` },
                    { name: 'branches', url: '/api/users/branches' },
                    { name: 'branchPerformance', url: `/api/statistics/branch-performance?days=${period === 'daily' ? 1 : period === 'weekly' ? 7 : 30}` },
                    { name: 'financial', url: `/api/dashboard/financial-summary?start_date=${startDate}&end_date=${endDate}` },
                    { name: 'carrier', url: `/api/dashboard/dealer-performance?year_month=${ym}` },
                    { name: 'monthlyTrend', url: '/api/statistics/monthly-trend' }
                ];

                // 🔄 순차 호출 및 즉시 UI 업데이트
                const apiResults = {};
                
                for (const apiConfig of apiSequence) {
                    // Railway PostgreSQL 안정화를 위한 100ms 간격
                    if (apiSequence.indexOf(apiConfig) > 0) {
                        await new Promise(resolve => setTimeout(resolve, 100));
                    }
                    
                    // API 호출 및 결과 저장
                    apiResults[apiConfig.name] = await callApiSequentially(apiConfig);
                    
                    // 🎨 각 API 완료 시 즉시 UI 업데이트
                    updateUISection(apiConfig.name, apiResults[apiConfig.name], {
                        period, limit, startDate, endDate
                    });
                }

                // 별칭으로 기존 코드 호환성 유지
                const profile = apiResults.profile;
                const overview = apiResults.overview;
                const ranking = apiResults.ranking;
                const branches = apiResults.branches;
                const branchPerformance = apiResults.branchPerformance;
                const fin = apiResults.financial;
                const carrierPerf = apiResults.carrier;

                // 🎉 최종 로딩 완료 (지사별 성과 차트는 updateUISection에서 이미 업데이트됨)
                console.log('🚀 Railway PostgreSQL 최적화 로딩 완료! 모든 섹션이 순차적으로 업데이트되었습니다.');

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

        // 전체 매장 수 로딩 함수
        async function loadTotalStoresCount() {
            try {
                const response = await fetch('/api/stores/count');
                const result = await response.json();

                if (result.success) {
                    document.getElementById('total-stores').textContent = `${result.count}개 매장`;
                    document.getElementById('total-stores').className = 'text-2xl font-bold text-gray-900';
                    console.log(`🏪 전체 매장 수 업데이트: ${result.count}개`);
                } else {
                    document.getElementById('total-stores').textContent = '0개 매장';
                }
            } catch (error) {
                console.error('매장 수 로딩 실패:', error);
                document.getElementById('total-stores').textContent = '0개 매장';
            }
        }

        // 시스템 목표 달성률 업데이트 함수
        function updateSystemGoalAchievement(monthSales) {
            try {
                // 시스템 기본 목표: 월 5천만원
                const systemTarget = 50000000;

                const achievementRate = monthSales > 0 ? ((monthSales / systemTarget) * 100).toFixed(1) : 0;
                const systemGoalEl = document.getElementById('system-goal');

                if (systemGoalEl) {
                    systemGoalEl.textContent = `${achievementRate}% 달성`;
                    systemGoalEl.className = 'text-2xl font-bold text-indigo-600';
                }
            } catch (error) {
                console.error('목표 달성률 계산 실패:', error);
                const systemGoalEl = document.getElementById('system-goal');
                if (systemGoalEl) {
                    systemGoalEl.textContent = '계산 오류';
                }
            }
        }
    </script>
</body>
</html>
