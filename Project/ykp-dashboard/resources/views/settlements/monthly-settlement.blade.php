<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>월마감정산 - YKP ERP</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    
    {{-- 🔒 세션 안정성 강화 --}}
    <script src="/js/session-stability.js"></script>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <!-- AgGrid -->
    <script src="https://cdn.jsdelivr.net/npm/ag-grid-community@31.0.0/dist/ag-grid-community.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community@31.0.0/styles/ag-grid.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community@31.0.0/styles/ag-theme-alpine.css">
    
    <style>
        .settlement-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        .excel-section {
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            margin-bottom: 20px;
            overflow: hidden;
        }
        .excel-header {
            background: #1f2937;
            color: white;
            padding: 12px 20px;
            font-weight: bold;
            text-align: center;
        }
        .excel-content {
            background: #f9fafb;
            padding: 0;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
            border-left: 4px solid;
        }
        .summary-card.revenue { border-left-color: #059669; }
        .summary-card.expense { border-left-color: #dc2626; }
        .summary-card.profit { border-left-color: #7c3aed; }
        .summary-card.growth { border-left-color: #f59e0b; }
        
        .summary-value {
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .summary-label {
            font-size: 14px;
            color: #6b7280;
            margin-bottom: 4px;
        }
        .summary-detail {
            font-size: 12px;
            color: #9ca3af;
        }
        
        .control-panel {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            align-items: end;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            text-decoration: none;
            text-align: center;
            display: inline-block;
        }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-warning { background: #f59e0b; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-secondary { background: #6b7280; color: white; }
        
        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-draft { background: #fef3c7; color: #92400e; }
        .status-confirmed { background: #dcfce7; color: #166534; }
        .status-closed { background: #e5e7eb; color: #374151; }
        
        .form-input {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }
        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
        }
        
        .chart-container {
            height: 300px;
            position: relative;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- 헤더 -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-gray-900">월마감정산</h1>
                    <span class="ml-2 px-2 py-1 text-xs bg-purple-100 text-purple-800 rounded">Excel Logic</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/dashboard" class="text-gray-600 hover:text-gray-900">메인 대시보드</a>
                    <a href="/payroll" class="text-gray-600 hover:text-gray-900">급여 관리</a>
                    <a href="/refunds" class="text-gray-600 hover:text-gray-900">환수 관리</a>
                </div>
            </div>
        </div>
    </header>

    <!-- 메인 컨텐츠 -->
    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- 컨트롤 패널 -->
        <div class="control-panel">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">정산 연도</label>
                <select id="yearFilter" class="form-input" onchange="loadSettlements()">
                    <option value="2025">2025년</option>
                    <option value="2024">2024년</option>
                    <option value="2023">2023년</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">정산 월</label>
                <input type="month" id="monthFilter" class="form-input" onchange="loadMonthlyDashboard()">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">대리점</label>
                <select id="dealerFilter" class="form-input" onchange="loadSettlements()">
                    <option value="">전체</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-2">
                <button onclick="generateCurrentMonth()" class="btn btn-primary">
                    🔄 이번달 정산생성
                </button>
                <button onclick="generateAllDealers()" class="btn btn-warning">
                    📊 전체 일괄생성
                </button>
            </div>
        </div>

        <!-- 월별 요약 (엑셀 상단 요약 부분) -->
        <div id="monthlyDashboard" class="summary-grid">
            <div class="summary-card revenue">
                <div class="summary-value text-green-600" id="totalRevenue">₩0</div>
                <div class="summary-label">총 수익</div>
                <div class="summary-detail" id="revenueDetail">개통 0건</div>
            </div>
            <div class="summary-card expense">
                <div class="summary-value text-red-600" id="totalExpenses">₩0</div>
                <div class="summary-label">총 지출</div>
                <div class="summary-detail" id="expenseDetail">4개 항목</div>
            </div>
            <div class="summary-card profit">
                <div class="summary-value text-purple-600" id="netProfit">₩0</div>
                <div class="summary-label">순이익</div>
                <div class="summary-detail" id="profitRate">0%</div>
            </div>
            <div class="summary-card growth">
                <div class="summary-value text-orange-600" id="growthRate">0%</div>
                <div class="summary-label">전월 대비</div>
                <div class="summary-detail" id="growthDetail">변동 없음</div>
            </div>
        </div>

        <!-- 차트 섹션 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
            <div class="settlement-container">
                <div style="padding: 20px; border-bottom: 1px solid #f3f4f6;">
                    <h3 class="text-lg font-semibold text-gray-900">연간 수익성 추이</h3>
                </div>
                <div style="padding: 20px;">
                    <div class="chart-container">
                        <canvas id="yearlyTrendChart"></canvas>
                    </div>
                </div>
            </div>
            
            <div class="settlement-container">
                <div style="padding: 20px; border-bottom: 1px solid #f3f4f6;">
                    <h3 class="text-lg font-semibold text-gray-900">지출 구성 분석</h3>
                </div>
                <div style="padding: 20px;">
                    <div class="chart-container">
                        <canvas id="expenseBreakdownChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- 월마감정산 목록 (엑셀 테이블 스타일) -->
        <div class="settlement-container">
            <div style="padding: 20px; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">월마감정산 목록</h3>
                    <p class="text-sm text-gray-600 mt-1">엑셀 월마감정산과 동일한 로직 • 자동 집계 • 상태 관리</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="exportSettlementsToExcel()" class="btn btn-success">📊 엑셀 다운로드</button>
                    <button onclick="showSettlementHelp()" class="btn btn-secondary">❓ 도움말</button>
                </div>
            </div>
            
            <!-- AgGrid 컨테이너 -->
            <div id="settlementGrid" class="ag-theme-alpine" style="height: 500px; width: 100%;"></div>
        </div>
    </main>

    <!-- 도움말 모달 -->
    <div id="helpModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg p-6 w-full max-w-2xl max-h-96 overflow-y-auto">
                <h3 class="text-lg font-semibold mb-4">📊 월마감정산 사용법</h3>
                <div class="space-y-4 text-sm">
                    <div>
                        <h4 class="font-semibold text-green-600">🔄 정산 생성</h4>
                        <p>"이번달 정산생성" 버튼으로 현재 월의 모든 데이터를 자동 집계</p>
                    </div>
                    <div>
                        <h4 class="font-semibold text-blue-600">📊 자동 계산</h4>
                        <p>• 총수익 = 개통정산금 합계 - 부가세</p>
                        <p>• 총지출 = 일일지출 + 고정지출 + 급여 + 환수</p>
                        <p>• 순이익 = 총수익 - 총지출</p>
                    </div>
                    <div>
                        <h4 class="font-semibold text-purple-600">📋 상태 관리</h4>
                        <p>• 임시저장 → 확정 → 마감 순서로 진행</p>
                        <p>• 마감 후에는 수정 불가</p>
                    </div>
                    <div>
                        <h4 class="font-semibold text-orange-600">📈 분석 기능</h4>
                        <p>• 전월 대비 성장률 자동 계산</p>
                        <p>• 대리점별 수익성 비교</p>
                        <p>• 연간 트렌드 차트</p>
                    </div>
                </div>
                <div class="flex justify-end mt-6">
                    <button onclick="hideSettlementHelp()" class="btn btn-primary">확인</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 전역 변수
        let settlementGridApi;
        let yearlyChart, expenseChart;
        let settlementData = [];
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        // AgGrid 컬럼 정의 (월마감정산용)
        const settlementColumnDefs = [
            {
                headerName: '기본 정보',
                children: [
                    {
                        field: 'year_month',
                        headerName: '정산월',
                        width: 80,
                        cellStyle: { backgroundColor: '#f8fafc', fontWeight: 'bold' }
                    },
                    {
                        field: 'dealer_code',
                        headerName: '대리점',
                        width: 100,
                        cellRenderer: function(params) {
                            const dealerName = params.data.dealer_profile?.dealer_name || params.value;
                            return dealerName;
                        }
                    },
                    {
                        field: 'settlement_status',
                        headerName: '상태',
                        width: 100,
                        cellRenderer: function(params) {
                            const statusMap = {
                                'draft': { class: 'status-draft', text: '임시저장' },
                                'confirmed': { class: 'status-confirmed', text: '확정' },
                                'closed': { class: 'status-closed', text: '마감' }
                            };
                            const status = statusMap[params.value] || statusMap['draft'];
                            return `<span class="status-badge ${status.class}">${status.text}</span>`;
                        }
                    }
                ]
            },
            {
                headerName: '수익 분석 (엑셀 수익 부분)',
                children: [
                    {
                        field: 'total_sales_amount',
                        headerName: '총정산금',
                        width: 120,
                        valueFormatter: params => '₩' + Number(params.value || 0).toLocaleString(),
                        cellStyle: { backgroundColor: '#f0fdf4', color: '#059669', fontWeight: 'bold' }
                    },
                    {
                        field: 'total_sales_count',
                        headerName: '개통건수',
                        width: 80,
                        valueFormatter: params => (params.value || 0) + '건'
                    },
                    {
                        field: 'total_vat_amount',
                        headerName: '부가세',
                        width: 100,
                        valueFormatter: params => '₩' + Number(params.value || 0).toLocaleString(),
                        cellStyle: { color: '#dc2626' }
                    },
                    {
                        field: 'gross_profit',
                        headerName: '총수익',
                        width: 120,
                        valueFormatter: params => '₩' + Number(params.value || 0).toLocaleString(),
                        cellStyle: { backgroundColor: '#ecfdf5', color: '#059669', fontWeight: 'bold' }
                    }
                ]
            },
            {
                headerName: '지출 분석 (엑셀 지출 부분)',
                children: [
                    {
                        field: 'total_daily_expenses',
                        headerName: '일일지출',
                        width: 100,
                        valueFormatter: params => '₩' + Number(params.value || 0).toLocaleString()
                    },
                    {
                        field: 'total_payroll_amount',
                        headerName: '급여',
                        width: 100,
                        valueFormatter: params => '₩' + Number(params.value || 0).toLocaleString()
                    },
                    {
                        field: 'total_refund_amount',
                        headerName: '환수',
                        width: 100,
                        valueFormatter: params => '₩' + Number(params.value || 0).toLocaleString(),
                        cellStyle: { color: '#dc2626' }
                    },
                    {
                        field: 'total_expense_amount',
                        headerName: '총지출',
                        width: 120,
                        valueFormatter: params => '₩' + Number(params.value || 0).toLocaleString(),
                        cellStyle: { backgroundColor: '#fef2f2', color: '#dc2626', fontWeight: 'bold' }
                    }
                ]
            },
            {
                headerName: '최종 손익 (엑셀 순이익 계산)',
                children: [
                    {
                        field: 'net_profit',
                        headerName: '순이익',
                        width: 120,
                        valueFormatter: params => '₩' + Number(params.value || 0).toLocaleString(),
                        cellStyle: function(params) {
                            const value = params.value || 0;
                            return {
                                backgroundColor: value >= 0 ? '#ecfdf5' : '#fef2f2',
                                color: value >= 0 ? '#059669' : '#dc2626',
                                fontWeight: 'bold'
                            };
                        }
                    },
                    {
                        field: 'profit_rate',
                        headerName: '수익률',
                        width: 80,
                        valueFormatter: params => (params.value || 0).toFixed(1) + '%',
                        cellStyle: function(params) {
                            const value = params.value || 0;
                            return {
                                color: value >= 0 ? '#059669' : '#dc2626',
                                fontWeight: 'bold'
                            };
                        }
                    },
                    {
                        field: 'growth_rate',
                        headerName: '전월대비',
                        width: 90,
                        valueFormatter: params => {
                            const value = params.value || 0;
                            return (value >= 0 ? '+' : '') + value.toFixed(1) + '%';
                        },
                        cellStyle: function(params) {
                            const value = params.value || 0;
                            return {
                                color: value >= 0 ? '#059669' : '#dc2626',
                                fontWeight: 'bold'
                            };
                        }
                    },
                    {
                        headerName: '액션',
                        width: 120,
                        cellRenderer: function(params) {
                            const status = params.data.settlement_status;
                            let buttons = '';
                            
                            if (status === 'draft') {
                                buttons += `<button onclick="confirmSettlement('${params.data.id}')" 
                                                  class="btn btn-success mr-1" style="padding: 4px 8px; font-size: 12px;">확정</button>`;
                            } else if (status === 'confirmed') {
                                buttons += `<button onclick="closeSettlement('${params.data.id}')" 
                                                  class="btn btn-warning mr-1" style="padding: 4px 8px; font-size: 12px;">마감</button>`;
                            }
                            
                            buttons += `<button onclick="viewSettlementDetail('${params.data.id}')" 
                                              class="btn btn-secondary" style="padding: 4px 8px; font-size: 12px;">상세</button>`;
                            
                            return buttons;
                        }
                    }
                ]
            }
        ];

        // AgGrid 옵션
        const settlementGridOptions = {
            columnDefs: settlementColumnDefs,
            rowData: [],
            defaultColDef: {
                sortable: true,
                filter: true,
                resizable: true,
            },
            enableRangeSelection: true,
            rowSelection: 'multiple',
            animateRows: true,
            onGridReady: function(params) {
                settlementGridApi = params.api;
                loadSettlements();
            },
            getRowStyle: function(params) {
                const status = params.data.settlement_status;
                if (status === 'confirmed') {
                    return { background: '#f0f9ff' }; // 확정: 파란색
                } else if (status === 'closed') {
                    return { background: '#f9fafb' }; // 마감: 회색
                }
                return {}; // 임시저장: 흰색
            }
        };

        // 페이지 로드 시 초기화
        document.addEventListener('DOMContentLoaded', function() {
            // AgGrid 초기화
            const gridDiv = document.querySelector('#settlementGrid');
            new agGrid.Grid(gridDiv, settlementGridOptions);
            
            // 차트 초기화
            initializeCharts();
            
            // 초기 데이터 로드
            loadDealers();
            
            // 현재 월 기본값 설정
            document.getElementById('monthFilter').value = new Date().toISOString().slice(0, 7);
            loadMonthlyDashboard();
        });

        // 대리점 목록 로드
        async function loadDealers() {
            try {
                const response = await fetch('/api/calculation/profiles');
                const data = await response.json();
                
                const dealerFilter = document.getElementById('dealerFilter');
                data.data.forEach(dealer => {
                    dealerFilter.innerHTML += `<option value="${dealer.dealer_code}">${dealer.dealer_name}</option>`;
                });
            } catch (error) {
                console.error('대리점 목록 로드 오류:', error);
            }
        }

        // 월마감정산 목록 로드
        async function loadSettlements() {
            const year = document.getElementById('yearFilter').value;
            const dealerCode = document.getElementById('dealerFilter').value;
            
            let url = `/api/monthly-settlements?year=${year}&limit=100`;
            if (dealerCode) url += `&dealer_code=${dealerCode}`;
            
            try {
                if (settlementGridApi) {
                    settlementGridApi.showLoadingOverlay();
                }
                
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success) {
                    settlementData = data.data;
                    if (settlementGridApi) {
                        settlementGridApi.setRowData(settlementData);
                        settlementGridApi.sizeColumnsToFit();
                        
                        if (data.data.length === 0) {
                            settlementGridApi.showNoRowsOverlay();
                        }
                    }
                } else {
                    console.error('정산 목록 로드 실패:', data.message);
                }
            } catch (error) {
                console.error('정산 목록 로드 오류:', error);
            }
        }

        // 월별 대시보드 데이터 로드
        async function loadMonthlyDashboard() {
            const yearMonth = document.getElementById('monthFilter').value;
            if (!yearMonth) return;
            
            try {
                const response = await fetch(`/api/monthly-settlements/dashboard/${yearMonth}`);
                const data = await response.json();
                
                if (data.success) {
                    const summary = data.data.summary;
                    
                    document.getElementById('totalRevenue').textContent = '₩' + Number(summary.total_revenue).toLocaleString();
                    document.getElementById('totalExpenses').textContent = '₩' + Number(summary.total_expenses).toLocaleString();
                    document.getElementById('netProfit').textContent = '₩' + Number(summary.total_net_profit).toLocaleString();
                    document.getElementById('growthRate').textContent = summary.average_profit_rate.toFixed(1) + '%';
                    
                    document.getElementById('revenueDetail').textContent = `개통 ${summary.total_sales_count}건`;
                    document.getElementById('expenseDetail').textContent = `${summary.dealer_count}개 대리점`;
                    document.getElementById('profitRate').textContent = summary.average_profit_rate.toFixed(1) + '%';
                    
                    // 차트 업데이트
                    updateExpenseChart(data.data.expense_breakdown);
                }
            } catch (error) {
                console.error('월별 대시보드 로드 오류:', error);
            }
        }

        // 이번달 정산 생성
        async function generateCurrentMonth() {
            const currentMonth = new Date().toISOString().slice(0, 7);
            const dealerCode = document.getElementById('dealerFilter').value;
            
            if (!dealerCode) {
                alert('대리점을 선택해주세요.');
                return;
            }
            
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '🔄 생성 중...';
            button.disabled = true;
            
            try {
                const response = await fetch('/api/monthly-settlements/generate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({
                        year_month: currentMonth,
                        dealer_code: dealerCode
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('✅ 이번달 정산이 생성되었습니다!', 'success');
                    loadSettlements();
                    loadMonthlyDashboard();
                } else {
                    alert('❌ 생성 실패: ' + (result.message || '알 수 없는 오류'));
                }
            } catch (error) {
                alert('❌ 네트워크 오류: ' + error.message);
            } finally {
                button.innerHTML = originalText;
                button.disabled = false;
            }
        }

        // 전체 대리점 일괄 생성
        async function generateAllDealers() {
            if (!confirm('모든 대리점의 이번달 정산을 생성하시겠습니까? 시간이 소요될 수 있습니다.')) return;
            
            const currentMonth = new Date().toISOString().slice(0, 7);
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '📊 생성 중...';
            button.disabled = true;
            
            try {
                const response = await fetch('/api/monthly-settlements/generate-all', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ year_month: currentMonth })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification(result.message, 'success');
                    loadSettlements();
                    loadMonthlyDashboard();
                } else {
                    alert('❌ 일괄 생성 실패: ' + (result.message || '알 수 없는 오류'));
                }
            } catch (error) {
                alert('❌ 네트워크 오류: ' + error.message);
            } finally {
                button.innerHTML = originalText;
                button.disabled = false;
            }
        }

        // 정산 확정
        async function confirmSettlement(settlementId) {
            if (!confirm('정산을 확정하시겠습니까? 확정 후에는 일부 수정이 제한됩니다.')) return;
            
            try {
                const response = await fetch(`/api/monthly-settlements/${settlementId}/confirm`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('✅ 정산이 확정되었습니다', 'success');
                    loadSettlements();
                } else {
                    alert('❌ 확정 실패: ' + (result.message || '알 수 없는 오류'));
                }
            } catch (error) {
                alert('❌ 네트워크 오류: ' + error.message);
            }
        }

        // 정산 마감
        async function closeSettlement(settlementId) {
            if (!confirm('정산을 마감하시겠습니까? 마감 후에는 수정할 수 없습니다.')) return;
            
            try {
                const response = await fetch(`/api/monthly-settlements/${settlementId}/close`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('✅ 정산이 마감되었습니다', 'warning');
                    loadSettlements();
                } else {
                    alert('❌ 마감 실패: ' + (result.message || '알 수 없는 오류'));
                }
            } catch (error) {
                alert('❌ 네트워크 오류: ' + error.message);
            }
        }

        // 정산 상세 보기
        function viewSettlementDetail(settlementId) {
            // 상세 모달 또는 새 페이지로 이동
            window.open(`/settlement-detail/${settlementId}`, '_blank');
        }

        // 차트 초기화
        function initializeCharts() {
            // 연간 트렌드 차트
            const yearlyCtx = document.getElementById('yearlyTrendChart').getContext('2d');
            yearlyChart = new Chart(yearlyCtx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: '순이익',
                        data: [],
                        borderColor: '#7c3aed',
                        backgroundColor: 'rgba(124, 58, 237, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₩' + Number(value).toLocaleString();
                                }
                            }
                        }
                    }
                }
            });

            // 지출 구성 차트
            const expenseCtx = document.getElementById('expenseBreakdownChart').getContext('2d');
            expenseChart = new Chart(expenseCtx, {
                type: 'doughnut',
                data: {
                    labels: ['일일지출', '고정지출', '급여', '환수'],
                    datasets: [{
                        data: [0, 0, 0, 0],
                        backgroundColor: ['#3b82f6', '#10b981', '#f59e0b', '#ef4444']
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { position: 'bottom' }
                    }
                }
            });
            
            loadYearlyTrend();
        }

        // 연간 트렌드 로드
        async function loadYearlyTrend() {
            try {
                const year = document.getElementById('yearFilter').value;
                const response = await fetch(`/api/monthly-settlements/trend/${year}`);
                const data = await response.json();
                
                if (data.success && yearlyChart) {
                    const trendData = data.data;
                    yearlyChart.data.labels = trendData.map(item => item.month);
                    yearlyChart.data.datasets[0].data = trendData.map(item => item.profit);
                    yearlyChart.update();
                }
            } catch (error) {
                console.error('연간 트렌드 로드 오류:', error);
            }
        }

        // 지출 구성 차트 업데이트
        function updateExpenseChart(expenseBreakdown) {
            if (expenseChart) {
                expenseChart.data.datasets[0].data = [
                    expenseBreakdown.daily_expenses,
                    expenseBreakdown.fixed_expenses, 
                    expenseBreakdown.payroll_expenses,
                    expenseBreakdown.refund_amount
                ];
                expenseChart.update();
            }
        }

        // 엑셀 내보내기
        function exportSettlementsToExcel() {
            const year = document.getElementById('yearFilter').value;
            const dealerCode = document.getElementById('dealerFilter').value;
            
            let url = `/api/monthly-settlements/export/excel?year=${year}`;
            if (dealerCode) url += `&dealer_code=${dealerCode}`;
            
            window.open(url, '_blank');
            showNotification('📊 월마감정산 보고서를 다운로드합니다', 'info');
        }

        // 도움말 모달
        function showSettlementHelp() {
            document.getElementById('helpModal').classList.remove('hidden');
        }

        function hideSettlementHelp() {
            document.getElementById('helpModal').classList.add('hidden');
        }

        // 알림 표시 헬퍼
        function showNotification(message, type = 'info') {
            const colors = {
                success: 'bg-green-500',
                error: 'bg-red-500', 
                warning: 'bg-yellow-500',
                info: 'bg-blue-500'
            };
            
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 ${colors[type]} text-white px-6 py-3 rounded-lg shadow-lg`;
            notification.innerHTML = message;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 4000);
        }
    </script>
</body>
</html>