<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>환수 관리 - YKP ERP</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @vite(['resources/css/app.css'])
    
    <!-- AgGrid -->
    <script src="https://cdn.jsdelivr.net/npm/ag-grid-community@31.0.0/dist/ag-grid-community.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community@31.0.0/styles/ag-grid.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community@31.0.0/styles/ag-theme-alpine.css">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .refund-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            text-align: center;
        }
        .summary-value {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 4px;
        }
        .summary-label {
            font-size: 14px;
            color: #6b7280;
        }
        .filter-bar {
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
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-success { background: #10b981; color: white; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-warning { background: #f59e0b; color: white; }
        
        .chart-container {
            height: 300px;
            width: 100%;
            position: relative;
        }
        .form-input {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
        }
        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            ring: 2px;
            ring-color: rgba(59, 130, 246, 0.5);
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- 헤더 -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-gray-900">환수 관리</h1>
                    <span class="ml-2 px-2 py-1 text-xs bg-red-100 text-red-800 rounded">Refund Analysis</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/dashboard" class="text-gray-600 hover:text-gray-900">메인 대시보드</a>
                    <a href="/payroll" class="text-gray-600 hover:text-gray-900">급여 관리</a>
                    <a href="/daily-expenses" class="text-gray-600 hover:text-gray-900">일일지출</a>
                </div>
            </div>
        </div>
    </header>

    <!-- 메인 컨텐츠 -->
    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- 요약 카드 (환수 분석) -->
        <div class="summary-cards">
            <div class="summary-card">
                <div class="summary-value text-red-600" id="totalRefundAmount">0원</div>
                <div class="summary-label">총 환수금액</div>
            </div>
            <div class="summary-card">
                <div class="summary-value text-orange-600" id="refundCount">0건</div>
                <div class="summary-label">환수 건수</div>
            </div>
            <div class="summary-card">
                <div class="summary-value text-purple-600" id="refundRate">0.0%</div>
                <div class="summary-label">환수율</div>
            </div>
            <div class="summary-card">
                <div class="summary-value text-blue-600" id="marginImpact">0원</div>
                <div class="summary-label">마진 영향</div>
            </div>
        </div>

        <!-- 필터 및 분석 영역 -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
            <!-- 필터 영역 -->
            <div class="lg:col-span-2">
                <div class="filter-bar">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">기간</label>
                        <div class="flex gap-2">
                            <input type="date" id="startDate" class="form-input" onchange="loadRefundData()">
                            <input type="date" id="endDate" class="form-input" onchange="loadRefundData()">
                        </div>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">대리점</label>
                        <select id="dealerFilter" class="form-input" onchange="loadRefundData()">
                            <option value="">전체</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">환수 사유</label>
                        <select id="reasonFilter" class="form-input" onchange="loadRefundData()">
                            <option value="">전체</option>
                            <option value="고객변심">고객변심</option>
                            <option value="기기불량">기기불량</option>
                            <option value="서비스불만">서비스불만</option>
                            <option value="요금불만">요금불만</option>
                            <option value="기타">기타</option>
                        </select>
                    </div>
                    <div>
                        <button onclick="showAddRefundModal()" class="btn btn-primary w-full">
                            + 환수 등록
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- 환수율 차트 -->
            <div class="refund-container">
                <div style="padding: 20px;">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">월별 환수율</h3>
                    <div class="chart-container">
                        <canvas id="refundRateChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- 환수 데이터 그리드 -->
        <div class="refund-container">
            <div style="padding: 20px; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">환수 내역</h3>
                    <p class="text-sm text-gray-600 mt-1">클릭하여 수정 • 원 개통 건과 연결 • 마진 영향 자동 계산</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="exportRefundsToExcel()" class="btn btn-success">📊 엑셀 다운로드</button>
                    <button onclick="refreshAnalysis()" class="btn btn-warning">🔄 분석 갱신</button>
                </div>
            </div>
            
            <!-- AgGrid 컨테이너 -->
            <div id="refundGrid" class="ag-theme-alpine" style="height: 500px; width: 100%;"></div>
        </div>
    </main>

    <!-- 환수 등록 모달 -->
    <div id="addRefundModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg p-6 w-full max-w-lg">
                <h3 class="text-lg font-semibold mb-4">새 환수 등록</h3>
                <form id="addRefundForm">
                    <div class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">환수일</label>
                                <input type="date" name="refund_date" class="form-input" required>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">대리점</label>
                                <select name="dealer_code" class="form-input" required>
                                    <option value="">선택해주세요</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">환수 사유</label>
                            <select name="refund_reason" class="form-input" required>
                                <option value="">선택해주세요</option>
                                <option value="고객변심">고객변심</option>
                                <option value="기기불량">기기불량</option>
                                <option value="서비스불만">서비스불만</option>
                                <option value="요금불만">요금불만</option>
                                <option value="기타">기타</option>
                            </select>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">환수금액</label>
                                <input type="number" name="refund_amount" class="form-input" required min="1000" step="1000">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">고객명</label>
                                <input type="text" name="customer_name" class="form-input" placeholder="선택사항">
                            </div>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">환수 내용</label>
                            <textarea name="refund_description" class="form-input" rows="3" placeholder="환수 사유 상세 설명"></textarea>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">원 개통 건 연결 (선택)</label>
                            <input type="text" name="original_sale_reference" class="form-input" placeholder="개통 ID 또는 고객명 검색">
                        </div>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button type="submit" class="btn btn-primary flex-1">등록</button>
                        <button type="button" onclick="hideAddRefundModal()" class="btn" style="background: #f3f4f6; color: #374151;">취소</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // 전역 변수
        let refundGridApi;
        let refundChart;
        let refundData = [];
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        // AgGrid 컬럼 정의 (환수 관리용)
        const refundColumnDefs = [
            {
                headerName: '환수 정보',
                children: [
                    {
                        field: 'refund_date',
                        headerName: '환수일',
                        width: 100,
                        editable: true,
                        cellEditor: 'agDateCellEditor'
                    },
                    {
                        field: 'dealer_code',
                        headerName: '대리점',
                        width: 100,
                        editable: true,
                        cellEditor: 'agSelectCellEditor',
                        cellEditorParams: { values: [] }
                    },
                    {
                        field: 'customer_name',
                        headerName: '고객명',
                        width: 100,
                        editable: true
                    },
                    {
                        field: 'refund_reason',
                        headerName: '환수 사유',
                        width: 120,
                        editable: true,
                        cellEditor: 'agSelectCellEditor',
                        cellEditorParams: {
                            values: ['고객변심', '기기불량', '서비스불만', '요금불만', '기타']
                        }
                    }
                ]
            },
            {
                headerName: '금액 및 영향',
                children: [
                    {
                        field: 'refund_amount',
                        headerName: '환수금액',
                        width: 120,
                        editable: true,
                        cellEditor: 'agNumberCellEditor',
                        valueFormatter: params => params.value ? Number(params.value).toLocaleString() + '원' : '0원',
                        cellStyle: { backgroundColor: '#fef2f2', color: '#dc2626', fontWeight: 'bold' }
                    },
                    {
                        field: 'margin_impact',
                        headerName: '마진 영향',
                        width: 120,
                        editable: false,
                        valueFormatter: params => params.value ? Number(params.value).toLocaleString() + '원' : '0원',
                        cellStyle: { backgroundColor: '#f0fdf4', color: '#059669' }
                    },
                    {
                        field: 'impact_percentage',
                        headerName: '영향률',
                        width: 80,
                        editable: false,
                        valueFormatter: params => params.value ? params.value.toFixed(1) + '%' : '0%',
                        cellStyle: { backgroundColor: '#fef3c7' }
                    }
                ]
            },
            {
                headerName: '관리',
                children: [
                    {
                        field: 'original_sale_reference',
                        headerName: '원 개통 건',
                        width: 120,
                        editable: true,
                        cellStyle: { backgroundColor: '#f8fafc' }
                    },
                    {
                        field: 'refund_description',
                        headerName: '상세 내용',
                        width: 200,
                        editable: true,
                        cellEditor: 'agLargeTextCellEditor',
                        cellEditorParams: { maxLength: 500, rows: 3 }
                    },
                    {
                        headerName: '액션',
                        width: 80,
                        cellRenderer: function(params) {
                            return `<button onclick="deleteRefund('${params.data.id}')" 
                                           class="text-red-500 hover:text-red-700" title="삭제">
                                        🗑️
                                    </button>`;
                        }
                    }
                ]
            }
        ];

        // AgGrid 옵션
        const refundGridOptions = {
            columnDefs: refundColumnDefs,
            rowData: [],
            defaultColDef: {
                sortable: true,
                filter: true,
                resizable: true,
            },
            enableRangeSelection: true,
            enableClipboard: true,
            rowSelection: 'multiple',
            animateRows: true,
            onGridReady: function(params) {
                refundGridApi = params.api;
                loadRefundData();
            },
            onCellValueChanged: function(event) {
                // 셀 값 변경 시 서버에 자동 저장 및 영향 재계산
                updateRefundField(event.data.id, event.colDef.field, event.newValue);
            }
        };

        // 페이지 로드 시 초기화
        document.addEventListener('DOMContentLoaded', function() {
            // AgGrid 초기화
            const gridDiv = document.querySelector('#refundGrid');
            new agGrid.Grid(gridDiv, refundGridOptions);
            
            // 초기 데이터 로드
            loadDealers();
            loadRefundData();
            loadRefundAnalysis();
            initializeRefundChart();
            
            // 기본 날짜 설정 (최근 30일)
            const today = new Date();
            const thirtyDaysAgo = new Date(today.getTime() - 30 * 24 * 60 * 60 * 1000);
            
            document.getElementById('endDate').value = today.toISOString().split('T')[0];
            document.getElementById('startDate').value = thirtyDaysAgo.toISOString().split('T')[0];
        });

        // 대리점 목록 로드
        async function loadDealers() {
            try {
                const response = await fetch('/api/calculation/profiles');
                const data = await response.json();
                
                const dealerFilter = document.getElementById('dealerFilter');
                const dealerSelect = document.querySelector('select[name="dealer_code"]');
                const dealerCodes = [];
                
                data.data.forEach(dealer => {
                    const option = `<option value="${dealer.dealer_code}">${dealer.dealer_name}</option>`;
                    dealerFilter.innerHTML += option;
                    if (dealerSelect) {
                        dealerSelect.innerHTML += option;
                    }
                    dealerCodes.push(dealer.dealer_code);
                });
                
                // AgGrid 드롭다운 업데이트
                const dealerColumn = refundColumnDefs.find(col => 
                    col.children?.some(child => child.field === 'dealer_code')
                );
                if (dealerColumn) {
                    const dealerField = dealerColumn.children.find(child => child.field === 'dealer_code');
                    if (dealerField) {
                        dealerField.cellEditorParams.values = dealerCodes;
                    }
                }
            } catch (error) {
                console.error('대리점 목록 로드 오류:', error);
            }
        }

        // 환수 데이터 로드
        async function loadRefundData() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const dealerCode = document.getElementById('dealerFilter').value;
            const refundReason = document.getElementById('reasonFilter').value;
            
            let url = `/api/refunds?limit=100`;
            if (startDate && endDate) url += `&start_date=${startDate}&end_date=${endDate}`;
            if (dealerCode) url += `&dealer_code=${dealerCode}`;
            if (refundReason) url += `&refund_reason=${refundReason}`;
            
            try {
                if (refundGridApi) {
                    refundGridApi.showLoadingOverlay();
                }
                
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success) {
                    refundData = data.data;
                    if (refundGridApi) {
                        refundGridApi.setRowData(refundData);
                        refundGridApi.sizeColumnsToFit();
                        
                        if (data.data.length === 0) {
                            refundGridApi.showNoRowsOverlay();
                        }
                    }
                } else {
                    console.error('환수 데이터 로드 실패:', data.message);
                }
            } catch (error) {
                console.error('환수 데이터 로드 오류:', error);
            }
        }

        // 환수 분석 요약 로드
        async function loadRefundAnalysis() {
            try {
                const startDate = document.getElementById('startDate').value;
                const endDate = document.getElementById('endDate').value;
                const dealerCode = document.getElementById('dealerFilter').value;
                
                let url = `/api/refunds/analysis/summary?`;
                if (startDate && endDate) url += `start_date=${startDate}&end_date=${endDate}&`;
                if (dealerCode) url += `dealer_code=${dealerCode}&`;
                
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success) {
                    const analysis = data.data;
                    
                    document.getElementById('totalRefundAmount').textContent = 
                        Number(analysis.total_refund_amount).toLocaleString() + '원';
                    document.getElementById('refundCount').textContent = 
                        analysis.total_refund_count + '건';
                    document.getElementById('refundRate').textContent = 
                        analysis.refund_rate.toFixed(1) + '%';
                    document.getElementById('marginImpact').textContent = 
                        Number(analysis.total_margin_impact).toLocaleString() + '원';
                }
            } catch (error) {
                console.error('환수 분석 로드 오류:', error);
            }
        }

        // 환수율 차트 초기화
        function initializeRefundChart() {
            const ctx = document.getElementById('refundRateChart').getContext('2d');
            refundChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: [],
                    datasets: [{
                        label: '환수율 (%)',
                        data: [],
                        borderColor: '#ef4444',
                        backgroundColor: 'rgba(239, 68, 68, 0.1)',
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 10,
                            ticks: {
                                callback: function(value) {
                                    return value + '%';
                                }
                            }
                        }
                    }
                }
            });
            
            loadRefundChart();
        }

        // 환수율 차트 데이터 로드
        async function loadRefundChart() {
            try {
                const response = await fetch('/api/refunds/analysis/monthly-trend');
                const data = await response.json();
                
                if (data.success && refundChart) {
                    const chartData = data.data;
                    refundChart.data.labels = chartData.map(item => item.month);
                    refundChart.data.datasets[0].data = chartData.map(item => item.refund_rate);
                    refundChart.update();
                }
            } catch (error) {
                console.error('환수율 차트 로드 오류:', error);
            }
        }

        // 환수 필드 업데이트
        async function updateRefundField(refundId, field, value) {
            try {
                const response = await fetch(`/api/refunds/${refundId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ [field]: value })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // 그리드 데이터 업데이트
                    const rowNode = refundGridApi.getRowNode(refundId);
                    if (rowNode) {
                        rowNode.setData(result.data);
                    }
                    
                    // 분석 데이터 갱신
                    loadRefundAnalysis();
                    showNotification('✅ 수정 완료', 'success');
                } else {
                    alert('❌ 수정 실패: ' + (result.message || '알 수 없는 오류'));
                }
            } catch (error) {
                alert('❌ 네트워크 오류: ' + error.message);
            }
        }

        // 환수 등록 모달 표시
        function showAddRefundModal() {
            document.querySelector('input[name="refund_date"]').value = new Date().toISOString().split('T')[0];
            document.getElementById('addRefundModal').classList.remove('hidden');
        }

        function hideAddRefundModal() {
            document.getElementById('addRefundModal').classList.add('hidden');
            document.getElementById('addRefundForm').reset();
        }

        // 환수 등록 폼 제출
        document.getElementById('addRefundForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            
            try {
                const response = await fetch('/api/refunds', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('✅ 환수가 등록되었습니다!', 'success');
                    hideAddRefundModal();
                    loadRefundData();
                    loadRefundAnalysis();
                    loadRefundChart();
                } else {
                    alert('❌ 등록 실패: ' + (result.message || '알 수 없는 오류'));
                }
            } catch (error) {
                alert('❌ 네트워크 오류: ' + error.message);
            }
        });

        // 환수 삭제
        async function deleteRefund(refundId) {
            if (!confirm('정말 삭제하시겠습니까? 삭제 후 복구할 수 없습니다.')) return;
            
            try {
                const response = await fetch(`/api/refunds/${refundId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    }
                });
                
                const result = await response.json();
                
                if (result.success) {
                    loadRefundData();
                    loadRefundAnalysis();
                    loadRefundChart();
                    showNotification('✅ 삭제 완료', 'success');
                } else {
                    alert('❌ 삭제 실패: ' + (result.message || '알 수 없는 오류'));
                }
            } catch (error) {
                alert('❌ 네트워크 오류: ' + error.message);
            }
        }

        // 엑셀 내보내기
        function exportRefundsToExcel() {
            const startDate = document.getElementById('startDate').value;
            const endDate = document.getElementById('endDate').value;
            const dealerCode = document.getElementById('dealerFilter').value;
            
            let url = `/api/refunds/export/excel?`;
            if (startDate && endDate) url += `start_date=${startDate}&end_date=${endDate}&`;
            if (dealerCode) url += `dealer_code=${dealerCode}&`;
            
            window.open(url, '_blank');
            showNotification('📊 환수 분석 보고서를 다운로드합니다', 'info');
        }

        // 분석 갱신
        function refreshAnalysis() {
            loadRefundAnalysis();
            loadRefundChart();
            showNotification('🔄 분석 데이터를 갱신했습니다', 'info');
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
            }, 3000);
        }
    </script>
</body>
</html>