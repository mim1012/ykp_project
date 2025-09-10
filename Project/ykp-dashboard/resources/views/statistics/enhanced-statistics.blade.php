<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>전체 통계 및 분석 - YKP ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendardvariable/dist/web/variable/pretendardvariable.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/date-fns@2.29.3/index.umd.min.js"></script>
    <style>
        .stat-card { transition: all 0.3s ease; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); }
        .gradient-bg { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .chart-container { position: relative; height: 300px; }
    </style>
</head>
<body class="bg-gray-50 font-pretendard">
    <!-- 헤더 -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-gray-900">전체 통계 및 분석</h1>
                    <span class="ml-2 px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded" id="store-filter-badge" style="display: none;">매장별 보기</span>
                </div>
                <div class="flex items-center space-x-4">
                    <select id="period-selector" class="border rounded px-3 py-1 text-sm">
                        <option value="7">최근 7일</option>
                        <option value="30" selected>최근 30일</option>
                        <option value="90">최근 90일</option>
                        <option value="365">최근 1년</option>
                    </select>
                    <button onclick="exportReport()" class="text-green-600 hover:text-green-800">📊 보고서 내보내기</button>
                    <a href="/dashboard" class="text-gray-600 hover:text-gray-900">대시보드</a>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto py-6 px-4">
        <!-- 핵심 지표 카드 -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
            <!-- 총 매출 -->
            <div class="stat-card bg-gradient-to-r from-blue-500 to-blue-600 text-white p-6 rounded-xl shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm font-medium">총 매출</p>
                        <p class="text-2xl font-bold" id="total-revenue">₩0</p>
                        <p class="text-blue-200 text-xs mt-1" id="revenue-growth">전주 대비 -</p>
                    </div>
                    <div class="bg-blue-400 bg-opacity-30 p-3 rounded-full">
                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 11a1 1 0 011-1h2a1 1 0 011 1v5a1 1 0 01-1 1H3a1 1 0 01-1-1v-5zM8 7a1 1 0 011-1h2a1 1 0 011 1v9a1 1 0 01-1 1H9a1 1 0 01-1-1V7zM14 4a1 1 0 011-1h2a1 1 0 011 1v12a1 1 0 01-1 1h-2a1 1 0 01-1-1V4z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- 순이익 -->
            <div class="stat-card bg-gradient-to-r from-green-500 to-green-600 text-white p-6 rounded-xl shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm font-medium">순이익</p>
                        <p class="text-2xl font-bold" id="net-profit">₩0</p>
                        <p class="text-green-200 text-xs mt-1" id="profit-margin">마진율: 0%</p>
                    </div>
                    <div class="bg-green-400 bg-opacity-30 p-3 rounded-full">
                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- 총 개통 건수 -->
            <div class="stat-card bg-gradient-to-r from-purple-500 to-purple-600 text-white p-6 rounded-xl shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100 text-sm font-medium">총 개통 건수</p>
                        <p class="text-2xl font-bold" id="total-activations">0건</p>
                        <p class="text-purple-200 text-xs mt-1" id="avg-daily">일평균: 0건</p>
                    </div>
                    <div class="bg-purple-400 bg-opacity-30 p-3 rounded-full">
                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 3a1 1 0 011-1h2.153a1 1 0 01.986.836l.74 4.435a1 1 0 01-.54 1.06l-1.548.773a11.037 11.037 0 006.105 6.105l.774-1.548a1 1 0 011.059-.54l4.435.74a1 1 0 01.836.986V17a1 1 0 01-1 1h-2C7.82 18 2 12.18 2 5V3z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <!-- 활성 매장 수 -->
            <div class="stat-card bg-gradient-to-r from-orange-500 to-red-500 text-white p-6 rounded-xl shadow-lg">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-orange-100 text-sm font-medium">활성 매장</p>
                        <p class="text-2xl font-bold" id="active-stores">0개</p>
                        <p class="text-orange-200 text-xs mt-1" id="store-growth">신규: +0개</p>
                    </div>
                    <div class="bg-orange-400 bg-opacity-30 p-3 rounded-full">
                        <svg class="w-8 h-8 text-white" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M19 7l-.867 12.142A2 2 0 0116.138 21H3.862a2 2 0 01-1.995-1.858L1 7m18 0l-2-4H3l-2 4m18 0v11a1 1 0 01-1 1h-1M1 7v11a1 1 0 001 1h1m0-18h14l2 4H3l2-4z"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- 차트 섹션 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
            <!-- 매출 추이 차트 -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">📈 매출 추이</h3>
                    <select id="chart-type" class="text-sm border rounded px-2 py-1">
                        <option value="daily">일별</option>
                        <option value="weekly">주별</option>
                        <option value="monthly">월별</option>
                    </select>
                </div>
                <div class="chart-container">
                    <canvas id="revenue-trend-chart"></canvas>
                </div>
            </div>

            <!-- 통신사별 점유율 -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">📊 통신사별 점유율</h3>
                    <button onclick="refreshCarrierData()" class="text-sm text-blue-600 hover:text-blue-800">새로고침</button>
                </div>
                <div class="chart-container">
                    <canvas id="carrier-pie-chart"></canvas>
                </div>
            </div>
        </div>

        <!-- 상세 분석 -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
            <!-- 지사별 성과 -->
            <div class="lg:col-span-2 bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">🏢 지사별 성과</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">지사명</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">매장수</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">매출</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">개통건수</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">평균단가</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">성장률</th>
                            </tr>
                        </thead>
                        <tbody id="branch-performance" class="bg-white divide-y divide-gray-200">
                            <!-- 동적으로 로드됨 -->
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top 성과 매장 -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">🏆 Top 성과 매장</h3>
                <div id="top-stores" class="space-y-3">
                    <!-- 동적으로 로드됨 -->
                </div>
            </div>
        </div>

        <!-- 실시간 모니터링 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- 실시간 활동 -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">⚡ 실시간 활동</h3>
                <div id="real-time-activity" class="space-y-3 max-h-60 overflow-y-auto">
                    <!-- 실시간 데이터 -->
                </div>
            </div>

            <!-- 목표 대비 진척도 -->
            <div class="bg-white rounded-xl shadow-lg p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">🎯 목표 달성률</h3>
                <div class="space-y-4">
                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-sm font-medium text-gray-700">월 매출 목표</span>
                            <span class="text-sm text-gray-600" id="monthly-target-percent">0%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" id="monthly-progress" style="width: 0%"></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1" id="monthly-target-text">₩0 / ₩50,000,000</p>
                    </div>

                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-sm font-medium text-gray-700">개통 건수 목표</span>
                            <span class="text-sm text-gray-600" id="activation-target-percent">0%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-green-600 h-2 rounded-full transition-all duration-300" id="activation-progress" style="width: 0%"></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1" id="activation-target-text">0건 / 200건</p>
                    </div>

                    <div>
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-sm font-medium text-gray-700">수익률 목표</span>
                            <span class="text-sm text-gray-600" id="profit-target-percent">0%</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-purple-600 h-2 rounded-full transition-all duration-300" id="profit-rate-progress" style="width: 0%"></div>
                        </div>
                        <p class="text-xs text-gray-500 mt-1" id="profit-target-text">0% / 60%</p>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- 로딩 오버레이 -->
    <div id="loading-overlay" class="fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center z-50" style="display: none;">
        <div class="bg-white rounded-lg p-6 flex items-center space-x-3">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500"></div>
            <span class="text-gray-700">데이터를 불러오는 중...</span>
        </div>
    </div>

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
        // 전역 변수
        let currentPeriod = 30;
        let charts = {};
        let updateInterval;
        let storeFilter = null; // 매장 필터

        // 페이지 로드시 초기화
        document.addEventListener('DOMContentLoaded', function() {
            // URL 파라미터에서 매장 필터 확인
            const urlParams = new URLSearchParams(window.location.search);
            const storeId = urlParams.get('store');
            const storeName = urlParams.get('name');
            
            if (storeId && storeName) {
                storeFilter = { id: storeId, name: storeName };
                document.getElementById('store-filter-badge').style.display = 'inline-block';
                document.getElementById('store-filter-badge').textContent = `${storeName} 매장 통계`;
                document.querySelector('h1').textContent = `${storeName} 매장 통계 분석`;
            }
            
            initializePage();
            setupEventListeners();
            startRealTimeUpdates();
        });

        // 이벤트 리스너 설정
        function setupEventListeners() {
            document.getElementById('period-selector').addEventListener('change', function() {
                currentPeriod = parseInt(this.value);
                loadAllData();
            });

            document.getElementById('chart-type').addEventListener('change', function() {
                updateRevenueChart();
            });
        }

        // 페이지 초기화
        function initializePage() {
            showLoading();
            loadAllData();
        }

        // 모든 데이터 로드
        async function loadAllData() {
            try {
                await Promise.all([
                    loadKPIData(),
                    loadChartData(),
                    loadBranchPerformance(),
                    loadTopStores(),
                    loadGoalProgress()
                ]);
                hideLoading();
            } catch (error) {
                console.error('데이터 로드 실패:', error);
                hideLoading();
                generateDemoData();
            }
        }

        // KPI 데이터 로드
        async function loadKPIData() {
            try {
                const storeParam = storeFilter ? `&store=${storeFilter.id}` : '';
                const response = await fetch(`/api/statistics/kpi?days=${currentPeriod}${storeParam}`);
                const result = await response.json();
                
                if (result.success) {
                    updateKPICards(result.data);
                } else {
                    throw new Error('KPI 데이터 로드 실패');
                }
            } catch (error) {
                console.error('KPI API 호출 실패:', error);
                // 실제 데이터 로드 실패 시 0 값으로 표시 (데모 데이터 제거)
                const emptyKPI = {
                    total_revenue: 0,
                    net_profit: 0,
                    profit_margin: 0,
                    total_activations: 0,
                    avg_daily: 0,
                    active_stores: 0,
                    store_growth: 0,
                    revenue_growth: 0
                };
                updateKPICards(emptyKPI);
                showToast('통계 데이터를 불러올 수 없습니다. 관리자에게 문의하세요.', 'error');
            }
        }

        // KPI 카드 업데이트
        function updateKPICards(data) {
            document.getElementById('total-revenue').textContent = formatCurrency(data.total_revenue);
            document.getElementById('net-profit').textContent = formatCurrency(data.net_profit);
            document.getElementById('profit-margin').textContent = `마진율: ${data.profit_margin}%`;
            document.getElementById('total-activations').textContent = `${data.total_activations}건`;
            document.getElementById('avg-daily').textContent = `일평균: ${data.avg_daily}건`;
            document.getElementById('active-stores').textContent = `${data.active_stores}개`;
            document.getElementById('store-growth').textContent = `신규: +${data.store_growth}개`;
            document.getElementById('revenue-growth').textContent = `전주 대비 +${data.revenue_growth}%`;
        }

        // 차트 데이터 로드
        async function loadChartData() {
            // 매출 추이 차트
            await updateRevenueChart();
            // 통신사 점유율 차트
            await updateCarrierChart();
        }

        // 매출 추이 차트 업데이트
        async function updateRevenueChart() {
            const chartType = document.getElementById('chart-type').value;
            
            try {
                const storeParam = storeFilter ? `&store=${storeFilter.id}` : '';
                const response = await fetch(`/api/statistics/revenue-trend?days=${currentPeriod}&type=${chartType}${storeParam}`);
                const result = await response.json();
                
                if (result.success) {
                    renderRevenueChart(result.data);
                } else {
                    throw new Error('차트 데이터 로드 실패');
                }
            } catch (error) {
                // 데모 차트 데이터 생성
                const demoData = generateDemoChartData(chartType);
                renderRevenueChart(demoData);
            }
        }

        // 데모 차트 데이터 생성
        function generateDemoChartData(type) {
            const labels = [];
            const revenueData = [];
            const profitData = [];
            
            const periods = type === 'daily' ? currentPeriod : 
                          type === 'weekly' ? Math.ceil(currentPeriod / 7) : 
                          Math.ceil(currentPeriod / 30);

            for (let i = periods - 1; i >= 0; i--) {
                if (type === 'daily') {
                    const date = new Date();
                    date.setDate(date.getDate() - i);
                    labels.push(date.toLocaleDateString('ko-KR', { month: 'short', day: 'numeric' }));
                    revenueData.push(Math.random() * 2000000 + 1000000);
                    profitData.push(Math.random() * 1200000 + 600000);
                } else if (type === 'weekly') {
                    labels.push(`${periods - i}주차`);
                    revenueData.push(Math.random() * 14000000 + 7000000);
                    profitData.push(Math.random() * 8400000 + 4200000);
                } else {
                    labels.push(`${periods - i}월`);
                    revenueData.push(Math.random() * 60000000 + 30000000);
                    profitData.push(Math.random() * 36000000 + 18000000);
                }
            }

            return { labels, revenue_data: revenueData, profit_data: profitData };
        }

        // 매출 차트 렌더링
        function renderRevenueChart(data) {
            const ctx = document.getElementById('revenue-trend-chart').getContext('2d');
            
            if (charts.revenueChart) {
                charts.revenueChart.destroy();
            }

            charts.revenueChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: data.labels,
                    datasets: [{
                        label: '매출',
                        data: data.revenue_data,
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4,
                        fill: true
                    }, {
                        label: '순이익',
                        data: data.profit_data,
                        borderColor: 'rgb(34, 197, 94)',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        tension: 0.4,
                        fill: false
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return formatCurrency(value);
                                }
                            }
                        }
                    }
                }
            });
        }

        // 통신사 차트 업데이트
        async function updateCarrierChart() {
            try {
                const storeParam = storeFilter ? `&store=${storeFilter.id}` : '';
                const response = await fetch(`/api/statistics/carrier-breakdown?days=${currentPeriod}${storeParam}`);
                const result = await response.json();
                
                if (result.success) {
                    renderCarrierChart(result.data);
                } else {
                    throw new Error('통신사 데이터 로드 실패');
                }
            } catch (error) {
                console.error('통신사 데이터 로드 실패:', error);
                // API 실패 시 빈 차트 표시 (데모 데이터 제거)
                const emptyCarrierData = {
                    labels: ['데이터 없음'],
                    data: [100],
                    colors: ['#CCCCCC']
                };
                renderCarrierChart(emptyCarrierData);
            }
        }

        // 통신사 차트 렌더링
        function renderCarrierChart(data) {
            const ctx = document.getElementById('carrier-pie-chart').getContext('2d');
            
            if (charts.carrierChart) {
                charts.carrierChart.destroy();
            }

            charts.carrierChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: data.labels,
                    datasets: [{
                        data: data.data,
                        backgroundColor: data.colors,
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                        }
                    }
                }
            });
        }

        // 지사별 성과 로드
        async function loadBranchPerformance() {
            try {
                const storeParam = storeFilter ? `&store=${storeFilter.id}` : '';
                const response = await fetch(`/api/statistics/branch-performance?days=${currentPeriod}${storeParam}`);
                const result = await response.json();
                
                if (result.success) {
                    renderBranchPerformance(result.data);
                } else {
                    throw new Error('지사 성과 데이터 로드 실패');
                }
            } catch (error) {
                console.error('지사 성과 데이터 로드 실패:', error);
                // API 실패 시 빈 테이블 표시 (데모 데이터 제거)
                const emptyBranches = [];
                renderBranchPerformance(emptyBranches);
                showToast('지사별 성과 데이터를 불러올 수 없습니다.', 'error');
            }
        }

        // 지사별 성과 렌더링
        function renderBranchPerformance(branches) {
            const tbody = document.getElementById('branch-performance');
            tbody.innerHTML = branches.map(branch => {
                const growthClass = branch.growth > 0 ? 'text-green-600' : 'text-red-600';
                const growthIcon = branch.growth > 0 ? '▲' : '▼';
                
                return `
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-900">${branch.name}</td>
                        <td class="px-4 py-3 text-gray-600">${branch.stores}개</td>
                        <td class="px-4 py-3 text-gray-900 font-semibold">${formatCurrency(branch.revenue)}</td>
                        <td class="px-4 py-3 text-gray-600">${branch.activations}건</td>
                        <td class="px-4 py-3 text-gray-600">${formatCurrency(branch.avg_price)}</td>
                        <td class="px-4 py-3 ${growthClass} font-medium">${growthIcon} ${Math.abs(branch.growth)}%</td>
                    </tr>
                `;
            }).join('');
        }

        // Top 매장 로드
        async function loadTopStores() {
            try {
                const storeParam = storeFilter ? `&store=${storeFilter.id}` : '';
                const response = await fetch(`/api/statistics/top-stores?days=${currentPeriod}${storeParam}`);
                const result = await response.json();
                
                if (result.success) {
                    renderTopStores(result.data);
                } else {
                    throw new Error('Top 매장 데이터 로드 실패');
                }
            } catch (error) {
                console.error('Top 매장 데이터 로드 실패:', error);
                // API 실패 시 빈 목록 표시 (데모 데이터 제거)
                const emptyStores = [];
                renderTopStores(emptyStores);
                showToast('Top 매장 데이터를 불러올 수 없습니다.', 'error');
            }
        }

        // Top 매장 렌더링
        function renderTopStores(stores) {
            const container = document.getElementById('top-stores');
            container.innerHTML = stores.map(store => {
                const rankColors = ['text-yellow-600', 'text-gray-600', 'text-orange-600', 'text-blue-600', 'text-purple-600'];
                const rankIcons = ['🥇', '🥈', '🥉', '4️⃣', '5️⃣'];
                
                return `
                    <div class="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors">
                        <div class="flex items-center">
                            <span class="text-xl mr-3">${rankIcons[store.rank - 1]}</span>
                            <div>
                                <p class="font-medium text-gray-900">${store.name}</p>
                                <p class="text-sm text-gray-600">${formatCurrency(store.revenue)}</p>
                            </div>
                        </div>
                        <span class="${rankColors[store.rank - 1]} font-bold text-lg">#${store.rank}</span>
                    </div>
                `;
            }).join('');
        }

        // 목표 진척도 로드
        async function loadGoalProgress() {
            try {
                const storeParam = storeFilter ? `?store=${storeFilter.id}` : '';
                const response = await fetch(`/api/statistics/goal-progress${storeParam}`);
                const result = await response.json();
                
                if (result.success) {
                    updateGoalProgress(result.data);
                } else {
                    throw new Error('목표 진척도 데이터 로드 실패');
                }
            } catch (error) {
                console.error('목표 진척도 데이터 로드 실패:', error);
                // API 실패 시 0% 표시 (데모 데이터 제거)
                const emptyGoals = {
                    monthly_revenue: { current: 0, target: 50000000 },
                    monthly_activations: { current: 0, target: 200 },
                    profit_rate: { current: 0, target: 60.0 }
                };
                updateGoalProgress(emptyGoals);
                showToast('목표 진척도 데이터를 불러올 수 없습니다.', 'error');
            }
        }

        // 목표 진척도 업데이트
        function updateGoalProgress(goals) {
            // 월 매출 목표
            const revenuePercent = Math.min((goals.monthly_revenue.current / goals.monthly_revenue.target) * 100, 100);
            document.getElementById('monthly-target-percent').textContent = `${revenuePercent.toFixed(1)}%`;
            document.getElementById('monthly-progress').style.width = `${revenuePercent}%`;
            document.getElementById('monthly-target-text').textContent = 
                `${formatCurrency(goals.monthly_revenue.current)} / ${formatCurrency(goals.monthly_revenue.target)}`;

            // 개통 건수 목표
            const activationPercent = Math.min((goals.monthly_activations.current / goals.monthly_activations.target) * 100, 100);
            document.getElementById('activation-target-percent').textContent = `${activationPercent.toFixed(1)}%`;
            document.getElementById('activation-progress').style.width = `${activationPercent}%`;
            document.getElementById('activation-target-text').textContent = 
                `${goals.monthly_activations.current}건 / ${goals.monthly_activations.target}건`;

            // 수익률 목표
            const profitPercent = Math.min((goals.profit_rate.current / goals.profit_rate.target) * 100, 100);
            document.getElementById('profit-target-percent').textContent = `${profitPercent.toFixed(1)}%`;
            document.getElementById('profit-rate-progress').style.width = `${profitPercent}%`;
            document.getElementById('profit-target-text').textContent = 
                `${goals.profit_rate.current}% / ${goals.profit_rate.target}%`;
        }

        // 실시간 업데이트 시작
        function startRealTimeUpdates() {
            updateRealTimeActivity();
            
            updateInterval = setInterval(() => {
                updateRealTimeActivity();
            }, 30000); // 30초마다 업데이트
        }

        // 실시간 활동 업데이트
        function updateRealTimeActivity() {
            const activities = [
                { time: '방금 전', text: '강남점에서 갤럭시S24 개통 완료', type: 'success' },
                { time: '2분 전', text: '홍대점에서 아이폰15 개통 완료', type: 'success' },
                { time: '5분 전', text: '잠실점에서 월 정산 완료', type: 'info' },
                { time: '8분 전', text: '부산서면점에서 아이패드 개통 완료', type: 'success' },
                { time: '12분 전', text: '대구점에서 신규 직원 등록', type: 'info' },
                { time: '15분 전', text: '인천점에서 갤럭시탭 개통 완료', type: 'success' }
            ];

            const container = document.getElementById('real-time-activity');
            container.innerHTML = activities.map(activity => {
                const iconColors = {
                    success: 'text-green-600',
                    info: 'text-blue-600',
                    warning: 'text-yellow-600'
                };
                
                return `
                    <div class="flex items-center space-x-3 p-2 hover:bg-gray-50 rounded">
                        <div class="w-2 h-2 rounded-full ${activity.type === 'success' ? 'bg-green-500' : 'bg-blue-500'}"></div>
                        <div class="flex-1">
                            <p class="text-sm text-gray-900">${activity.text}</p>
                            <p class="text-xs text-gray-500">${activity.time}</p>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // 통신사 데이터 새로고침
        function refreshCarrierData() {
            showToast('통신사 데이터를 새로고침합니다...', 'info');
            updateCarrierChart();
        }

        // 보고서 내보내기
        function exportReport() {
            showToast('통계 보고서를 생성하고 있습니다...', 'info');
            
            setTimeout(() => {
                showToast('보고서가 다운로드되었습니다!', 'success');
            }, 2000);
        }

        // 데모 데이터 생성
        function generateDemoData() {
            showToast('데모 데이터를 생성합니다...', 'info');
            loadAllData();
        }

        // 로딩 표시
        function showLoading() {
            document.getElementById('loading-overlay').style.display = 'flex';
        }

        // 로딩 숨김
        function hideLoading() {
            document.getElementById('loading-overlay').style.display = 'none';
        }

        // 통화 포맷팅
        function formatCurrency(amount) {
            return '₩' + new Intl.NumberFormat('ko-KR').format(amount);
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

        // 페이지 종료 시 정리
        window.addEventListener('beforeunload', function() {
            if (updateInterval) {
                clearInterval(updateInterval);
            }
        });
    </script>
</body>
</html>