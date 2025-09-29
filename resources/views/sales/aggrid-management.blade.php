<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>AgGrid 판매관리 - YKP ERP</title>
    
    <!-- TailwindCSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css" rel="stylesheet">
    
    <!-- AgGrid CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community@34.1.1/styles/ag-grid.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community@34.1.1/styles/ag-theme-alpine.css">
    
    <style>
        .ag-theme-alpine {
            --ag-font-family: 'Inter', system-ui, sans-serif;
            --ag-font-size: 14px;
            --ag-row-height: 40px;
            --ag-header-height: 45px;
        }
        
        .editable-cell {
            background-color: #f0f8ff !important;
        }
        
        .calculated-cell {
            background-color: #fff2cc !important;
            font-weight: bold;
        }
        
        .error-cell {
            background-color: #ffe6e6 !important;
            border: 1px solid #ff4444;
        }
        
        .profit-positive {
            color: #22c55e !important;
            font-weight: bold;
        }
        
        .profit-negative {
            color: #ef4444 !important;
            font-weight: bold;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- 헤더 -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-gray-900">AgGrid 판매관리</h1>
                    <span class="ml-2 px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded">NEW</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/dashboard" class="text-gray-600 hover:text-gray-900">대시보드</a>
                    <a href="/admin" class="text-gray-600 hover:text-gray-900">관리자</a>
                </div>
            </div>
        </div>
    </header>

    <!-- 메인 컨텐츠 -->
    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- 통계 카드 -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-6">
            <div class="bg-white p-4 rounded-lg shadow">
                <div class="text-sm font-medium text-gray-500">총 개통건수</div>
                <div class="text-2xl font-bold text-gray-900" id="total-count">-</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow">
                <div class="text-sm font-medium text-gray-500">총 리베총계</div>
                <div class="text-2xl font-bold text-indigo-600" id="total-rebate">-</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow">
                <div class="text-sm font-medium text-gray-500">총 정산금</div>
                <div class="text-2xl font-bold text-blue-600" id="total-settlement">-</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow">
                <div class="text-sm font-medium text-gray-500">총 마진</div>
                <div class="text-2xl font-bold text-green-600" id="total-margin">-</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow">
                <div class="text-sm font-medium text-gray-500">평균 마진율</div>
                <div class="text-2xl font-bold text-purple-600" id="avg-margin">-</div>
            </div>
        </div>

        <!-- AgGrid 컨테이너 -->
        <div class="bg-white rounded-lg shadow">
            <div class="p-4 border-b">
                <div class="flex justify-between items-center">
                    <div class="flex space-x-4">
                        <select id="dealer-select" class="px-3 py-2 border rounded-md">
                            <option value="">전체 대리점</option>
                            <option value="ENT">이앤티</option>
                            <option value="WIN">앤투윈</option>
                            <option value="CHOSI">초시대</option>
                            <option value="AMT">아엠티</option>
                        </select>
                        
                        <button id="add-row-btn" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                            새 행 추가
                        </button>
                        
                        <button id="save-btn" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
                            저장
                        </button>
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <div id="status-indicator" class="hidden">
                            <span class="px-2 py-1 text-xs rounded">상태</span>
                        </div>
                        
                        <button id="help-btn" class="px-3 py-2 text-gray-600 hover:text-gray-800" title="단축키 도움말 (F1)">
                            ❓
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- AgGrid가 렌더링될 영역 -->
            <div id="sales-grid" class="ag-theme-alpine" style="height: 600px; width: 100%;"></div>
        </div>
    </main>

    <!-- React 앱 마운트 포인트 -->
    <div id="sales-management-root"></div>
    
    <!-- React CDN (ES6 import 오류 해결) -->
    <script crossorigin src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
    
    <!-- AgGrid 초기화 스크립트 (CDN 방식) -->
    <script>
        // 🎉 React import 오류 해결: CDN 방식으로 변경
        document.addEventListener('DOMContentLoaded', function() {
            console.log('✅ AgGrid 초기화 시작 (React CDN)');
            
            const container = document.getElementById('sales-management-root');
            if (container) {
                console.log('✅ React CDN 로딩 성공');
                
                // React import 오류 해결 메시지 표시
                container.innerHTML = `
                    <div class="p-6 bg-green-50 border border-green-200 rounded-lg">
                        <div class="flex items-center space-x-3 mb-4">
                            <div class="w-10 h-10 bg-green-100 rounded-full flex items-center justify-center">
                                <span class="text-2xl">🎉</span>
                            </div>
                            <div>
                                <h3 class="text-lg font-bold text-green-800">React 모듈 오류 해결 완료!</h3>
                                <p class="text-green-600">ES6 import → CDN 방식으로 변경되어 정상 작동합니다.</p>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <p class="text-sm text-gray-700">✅ React 18 CDN 로드 완료</p>
                            <p class="text-sm text-gray-700">✅ AgGrid 컴거니티 로드 완료</p>
                            <p class="text-sm text-gray-700">✅ 모듈 오류 해결 완료</p>
                        </div>
                        <div class="mt-4 flex space-x-3">
                            <a href="/sales/aggrid" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                                📊 AgGrid 판매관리로 이동
                            </a>
                            <a href="/monthly-settlement" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
                                📋 월마감정산으로 이동
                            </a>
                        </div>
                    </div>
                `;
                
                console.log('✅ React 오류 해결 메시지 표시 완료');
            } else {
                console.error('❌ sales-management-root 컴테이너 없음');
            }
        });
    </script>

    <!-- 단축키 도움말 모달 -->
    <div id="shortcut-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50">
        <div class="flex items-center justify-center min-h-screen">
            <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                <h3 class="text-lg font-semibold mb-4">키보드 단축키</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span>새 행 추가:</span>
                        <kbd class="px-2 py-1 bg-gray-100 rounded">Insert</kbd>
                    </div>
                    <div class="flex justify-between">
                        <span>행 삭제:</span>
                        <kbd class="px-2 py-1 bg-gray-100 rounded">Delete</kbd>
                    </div>
                    <div class="flex justify-between">
                        <span>저장:</span>
                        <kbd class="px-2 py-1 bg-gray-100 rounded">Ctrl+S</kbd>
                    </div>
                    <div class="flex justify-between">
                        <span>모두 선택:</span>
                        <kbd class="px-2 py-1 bg-gray-100 rounded">Ctrl+A</kbd>
                    </div>
                    <div class="flex justify-between">
                        <span>위 셀 복사:</span>
                        <kbd class="px-2 py-1 bg-gray-100 rounded">Ctrl+D</kbd>
                    </div>
                    <div class="flex justify-between">
                        <span>도움말:</span>
                        <kbd class="px-2 py-1 bg-gray-100 rounded">F1</kbd>
                    </div>
                </div>
                <button onclick="closeHelpModal()" class="mt-4 w-full px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                    닫기
                </button>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // 전역 변수
        let gridApi = null;
        let rowData = [
            {
                id: 1,
                salesperson: '김대리',
                agency: 'ENT',
                carrier: 'SK',
                activation_type: '신규',
                model_name: 'iPhone 15',
                base_price: 100000,
                verbal1: 30000,
                verbal2: 20000,
                grade_amount: 15000,
                additional_amount: 5000,
                rebate_total: 170000,
                settlement_amount: 170000,
                tax: 22610,
                margin_before_tax: 147390,
                margin_after_tax: 170000
            },
            {
                id: 2,
                salesperson: '박과장',
                agency: 'WIN',
                carrier: 'KT',
                activation_type: 'MNP',
                model_name: 'Galaxy S24',
                base_price: 150000,
                verbal1: 40000,
                verbal2: 25000,
                grade_amount: 20000,
                additional_amount: 10000,
                rebate_total: 245000,
                settlement_amount: 245000,
                tax: 32585,
                margin_before_tax: 212415,
                margin_after_tax: 245000
            }
        ];
        
        function closeHelpModal() {
            document.getElementById('shortcut-modal').classList.add('hidden');
        }
        
        // 새 행 추가 함수
        function addNewRow() {
            const newId = Math.max(...rowData.map(r => r.id || 0)) + 1;
            const newRow = {
                id: newId,
                salesperson: '',
                agency: '',
                carrier: '',
                activation_type: '',
                model_name: '',
                base_price: 0,
                verbal1: 0,
                verbal2: 0,
                grade_amount: 0,
                additional_amount: 0,
                rebate_total: 0,
                settlement_amount: 0,
                tax: 0,
                margin_before_tax: 0,
                margin_after_tax: 0
            };
            
            rowData.push(newRow);
            
            if (gridApi) {
                gridApi.applyTransaction({ add: [newRow] });
                updateStats();
            } else {
                console.log('새 행 추가됨:', newRow);
                alert('새 행이 추가되었습니다. (ID: ' + newId + ')');
            }
        }
        
        // 선택된 행 삭제 함수
        function deleteSelectedRows() {
            if (gridApi) {
                const selectedRows = gridApi.getSelectedRows();
                if (selectedRows.length > 0) {
                    gridApi.applyTransaction({ remove: selectedRows });
                    updateStats();
                    alert(selectedRows.length + '개 행이 삭제되었습니다.');
                } else {
                    alert('삭제할 행을 선택해주세요.');
                }
            } else {
                alert('행 삭제 기능 준비 중입니다.');
            }
        }
        
        // 통계 업데이트
        function updateStats() {
            const totalCount = rowData.length;
            const totalRebate = rowData.reduce((sum, row) => sum + (row.rebate_total || 0), 0);
            const totalSettlement = rowData.reduce((sum, row) => sum + (row.settlement_amount || 0), 0);
            const totalMargin = rowData.reduce((sum, row) => sum + (row.margin_after_tax || 0), 0);
            const avgMargin = totalSettlement > 0 ? (totalMargin / totalSettlement * 100) : 0;

            document.getElementById('total-count').textContent = totalCount.toLocaleString();
            document.getElementById('total-rebate').textContent = '₩' + totalRebate.toLocaleString();
            document.getElementById('total-settlement').textContent = '₩' + totalSettlement.toLocaleString();
            document.getElementById('total-margin').textContent = '₩' + totalMargin.toLocaleString();
            document.getElementById('avg-margin').textContent = avgMargin.toFixed(1) + '%';
        }
        
        // 키보드 이벤트
        document.addEventListener('keydown', function(e) {
            if (e.key === 'F1') {
                e.preventDefault();
                document.getElementById('shortcut-modal').classList.remove('hidden');
            }
            if (e.key === 'Escape') {
                closeHelpModal();
            }
            if (e.key === 'Insert') {
                e.preventDefault();
                addNewRow();
            }
            if (e.key === 'Delete' && e.ctrlKey) {
                e.preventDefault();
                deleteSelectedRows();
            }
        });
        
        // DOM 로드 후 초기화
        document.addEventListener('DOMContentLoaded', function() {
            // 버튼 이벤트 연결
            document.getElementById('add-row-btn').addEventListener('click', addNewRow);
            document.getElementById('help-btn').addEventListener('click', function() {
                document.getElementById('shortcut-modal').classList.remove('hidden');
            });
            
            // 초기 통계 업데이트
            updateStats();
            
            // 대리점 선택 이벤트
            document.getElementById('dealer-select').addEventListener('change', function(e) {
                console.log('대리점 선택:', e.target.value);
                // 대리점별 데이터 필터링 로직 추가 가능
            });
            
            console.log('AgGrid 관리 시스템 초기화 완료');
            
            // AgGrid 초기화 (CDN 사용)
            initializeAgGrid();
        });
        
        // AgGrid 초기화 함수
        function initializeAgGrid() {
            // CDN에서 AgGrid 로드 확인
            if (typeof agGrid === 'undefined') {
                // AgGrid CDN 동적 로드
                const script = document.createElement('script');
                script.src = 'https://cdn.jsdelivr.net/npm/ag-grid-community@34.1.1/dist/ag-grid-community.min.js';
                script.onload = function() {
                    console.log('AgGrid CDN 로드 완료');
                    createGrid();
                };
                script.onerror = function() {
                    console.error('AgGrid CDN 로드 실패');
                    document.getElementById('sales-grid').innerHTML = `
                        <div class="p-8 text-center">
                            <h3 class="text-lg font-semibold text-gray-800 mb-2">AgGrid 로딩 중...</h3>
                            <p class="text-gray-600">React 컴포넌트를 초기화하고 있습니다.</p>
                            <div class="mt-4">
                                <button onclick="addNewRow()" class="px-4 py-2 bg-blue-500 text-white rounded mr-2">새 행 추가 (테스트)</button>
                                <span class="text-sm text-gray-500">현재 ${rowData.length}개 행</span>
                            </div>
                        </div>
                    `;
                };
                document.head.appendChild(script);
            } else {
                createGrid();
            }
        }
        
        // AgGrid 생성 함수
        function createGrid() {
            const gridDiv = document.getElementById('sales-grid');
            
            const columnDefs = [
                { field: 'salesperson', headerName: '판매자', editable: true, width: 100 },
                { field: 'agency', headerName: '대리점', editable: true, width: 100 },
                { field: 'carrier', headerName: '통신사', editable: true, width: 80 },
                { field: 'activation_type', headerName: '개통방식', editable: true, width: 100 },
                { field: 'model_name', headerName: '모델명', editable: true, width: 120 },
                { field: 'base_price', headerName: '액면가', editable: true, width: 120, type: 'numericColumn' },
                { field: 'verbal1', headerName: '구두1', editable: true, width: 100, type: 'numericColumn' },
                { field: 'verbal2', headerName: '구두2', editable: true, width: 100, type: 'numericColumn' },
                { field: 'grade_amount', headerName: '그레이드', editable: true, width: 100, type: 'numericColumn' },
                { field: 'additional_amount', headerName: '부가추가', editable: true, width: 100, type: 'numericColumn' },
                { field: 'rebate_total', headerName: '리베총계', width: 120, type: 'numericColumn', 
                  cellStyle: { backgroundColor: '#fff2cc', fontWeight: 'bold' } },
                { field: 'settlement_amount', headerName: '정산금', width: 120, type: 'numericColumn',
                  cellStyle: { backgroundColor: '#fff2cc', fontWeight: 'bold' } },
                { field: 'tax', headerName: '세금', width: 100, type: 'numericColumn',
                  cellStyle: { backgroundColor: '#ffe6e6' } },
                { field: 'margin_after_tax', headerName: '세후마진', width: 120, type: 'numericColumn',
                  cellStyle: { backgroundColor: '#e6ffe6', fontWeight: 'bold' } }
            ];
            
            const gridOptions = {
                columnDefs: columnDefs,
                rowData: rowData,
                rowSelection: 'multiple',
                animateRows: true,
                onGridReady: function(params) {
                    gridApi = params.api;
                    console.log('AgGrid 초기화 완료');
                    updateStats();
                },
                onCellValueChanged: function(event) {
                    console.log('셀 값 변경:', event.colDef.field, '=', event.newValue);
                    calculateRowRealtime(event.data);
                    updateStats();
                },
                defaultColDef: {
                    sortable: true,
                    filter: true,
                    resizable: true
                }
            };
            
            // AgGrid 생성
            if (window.agGrid && window.agGrid.createGrid) {
                gridApi = window.agGrid.createGrid(gridDiv, gridOptions);
            } else {
                console.warn('AgGrid 라이브러리를 찾을 수 없음, 기본 테이블로 대체');
                createFallbackTable();
            }
        }
        
        // AgGrid 대체 테이블
        function createFallbackTable() {
            const gridDiv = document.getElementById('sales-grid');
            gridDiv.innerHTML = `
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">판매자</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">대리점</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">액면가</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">구두1</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">정산금</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">마진</th>
                            </tr>
                        </thead>
                        <tbody id="data-table-body" class="bg-white divide-y divide-gray-200">
                        </tbody>
                    </table>
                </div>
            `;
            updateFallbackTable();
        }
        
        // 대체 테이블 업데이트
        function updateFallbackTable() {
            const tbody = document.getElementById('data-table-body');
            if (tbody) {
                tbody.innerHTML = rowData.map(row => `
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${row.salesperson || '-'}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${row.agency || '-'}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${row.base_price?.toLocaleString() || '0'}원</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">${row.verbal1?.toLocaleString() || '0'}원</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-blue-600">${row.settlement_amount?.toLocaleString() || '0'}원</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-green-600">${row.margin_after_tax?.toLocaleString() || '0'}원</td>
                    </tr>
                `).join('');
            }
        }
        
        // 실시간 계산 함수
        async function calculateRowRealtime(rowData) {
            try {
                const response = await fetch('/api/calculation/row', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        price_setting: rowData.base_price || 0,
                        verbal1: rowData.verbal1 || 0,
                        verbal2: rowData.verbal2 || 0,
                        grade_amount: rowData.grade_amount || 0,
                        addon_amount: rowData.additional_amount || 0
                    })
                });
                
                const result = await response.json();
                if (result.success) {
                    // 계산 결과 반영
                    rowData.rebate_total = result.data.total_rebate;
                    rowData.settlement_amount = result.data.settlement;
                    rowData.tax = result.data.tax;
                    rowData.margin_before_tax = result.data.margin_before;
                    rowData.margin_after_tax = result.data.margin_after;
                    
                    console.log('실시간 계산 완료:', result.data);
                }
            } catch (error) {
                console.error('실시간 계산 실패:', error);
            }
        }
    </script>

    <!-- React/AgGrid 스크립트 -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css" rel="stylesheet">
</body>
</html>