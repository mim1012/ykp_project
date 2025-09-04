<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>직원급여 관리 - YKP ERP</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css" rel="stylesheet">
    
    <!-- AgGrid Enterprise -->
    <script src="https://cdn.jsdelivr.net/npm/ag-grid-community@31.0.0/dist/ag-grid-community.min.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community@31.0.0/styles/ag-grid.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/ag-grid-community@31.0.0/styles/ag-theme-alpine.css">
    <style>
        .payroll-container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .excel-grid {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }
        .excel-grid th {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            padding: 12px 8px;
            font-weight: 600;
            text-align: center;
            color: #374151;
        }
        .excel-grid td {
            border: 1px solid #e2e8f0;
            padding: 8px;
            text-align: center;
            position: relative;
        }
        .excel-grid td.editable {
            background: #fefce8;
            cursor: pointer;
        }
        .excel-grid td.calculated {
            background: #f0fdf4;
            color: #059669;
            font-weight: 600;
        }
        .excel-grid td.status-cell {
            background: #fef3c7;
        }
        .inline-input {
            width: 100%;
            border: none;
            background: transparent;
            text-align: center;
            padding: 4px;
            font-size: 14px;
        }
        .inline-input:focus {
            outline: 2px solid #3b82f6;
            background: white;
        }
        .status-toggle {
            cursor: pointer;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
            border: none;
            transition: all 0.2s;
        }
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .status-paid {
            background: #d1fae5;
            color: #065f46;
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
            color: #1f2937;
            margin-bottom: 4px;
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
        .btn-primary {
            background: #3b82f6;
            color: white;
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- 헤더 -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-gray-900">직원급여 관리</h1>
                    <span class="ml-2 px-2 py-1 text-xs bg-green-100 text-green-800 rounded">Excel Style</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/" class="text-gray-600 hover:text-gray-900">메인 대시보드</a>
                    <a href="/daily-expenses" class="text-gray-600 hover:text-gray-900">일일지출</a>
                    <a href="/fixed-expenses" class="text-gray-600 hover:text-gray-900">고정지출</a>
                </div>
            </div>
        </div>
    </header>

    <!-- 메인 컨텐츠 -->
    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- 요약 카드 (엑셀 월마감정산 스타일) -->
        <div class="summary-cards">
            <div class="summary-card">
                <div class="summary-value" id="totalPayroll">0원</div>
                <div class="summary-label">총 급여액</div>
            </div>
            <div class="summary-card">
                <div class="summary-value" id="paidAmount">0원</div>
                <div class="summary-label">지급 완료</div>
            </div>
            <div class="summary-card">
                <div class="summary-value" id="pendingAmount">0원</div>
                <div class="summary-label">미지급 금액</div>
            </div>
            <div class="summary-card">
                <div class="summary-value" id="employeeCount">0명</div>
                <div class="summary-label">직원 수</div>
            </div>
        </div>

        <!-- 필터 영역 (엑셀 필터 방식) -->
        <div class="filter-bar">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">정산 월</label>
                <input type="month" id="monthFilter" class="form-input w-full" onchange="loadPayrollData()">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">대리점</label>
                <select id="dealerFilter" class="form-input w-full" onchange="loadPayrollData()">
                    <option value="">전체</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">지급 상태</label>
                <select id="statusFilter" class="form-input w-full" onchange="loadPayrollData()">
                    <option value="">전체</option>
                    <option value="pending">미지급</option>
                    <option value="paid">지급완료</option>
                </select>
            </div>
            <div>
                <button onclick="showAddEmployeeModal()" class="btn btn-primary w-full">
                    + 직원 추가
                </button>
            </div>
        </div>

        <!-- 도구 모음 -->
        <div class="payroll-container">
            <div style="padding: 20px; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: between; align-items: center;">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">급여 내역 (AgGrid)</h3>
                    <p class="text-sm text-gray-600 mt-1">엑셀과 동일한 편집 방식 • 인센티브 자동 계산 • 실시간 저장</p>
                </div>
                <div class="flex gap-2">
                    <button onclick="addNewPayroll()" class="btn btn-primary">+ 직원 추가</button>
                    <button onclick="saveAllChanges()" class="btn btn-success">💾 모든 변경사항 저장</button>
                    <button onclick="exportToExcel()" class="btn" style="background: #059669; color: white;">📊 엑셀 다운로드</button>
                </div>
            </div>
            
            <!-- AgGrid 컨테이너 -->
            <div id="payrollGrid" class="ag-theme-alpine" style="height: 600px; width: 100%;"></div>
        </div>
    </main>

    <!-- 직원 추가 모달 -->
    <div id="addEmployeeModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
        <div class="flex items-center justify-center min-h-screen p-4">
            <div class="bg-white rounded-lg p-6 w-full max-w-md">
                <h3 class="text-lg font-semibold mb-4">새 직원 추가</h3>
                <form id="addEmployeeForm">
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">직원 ID</label>
                            <input type="text" name="employee_id" class="form-input w-full" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">직원명</label>
                            <input type="text" name="employee_name" class="form-input w-full" required>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">대리점</label>
                            <select name="dealer_code" class="form-input w-full" required>
                                <option value="">선택해주세요</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">직급</label>
                            <select name="position" class="form-input w-full">
                                <option value="점장">점장</option>
                                <option value="직원">직원</option>
                                <option value="상담원">상담원</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">기본급</label>
                            <input type="number" name="base_salary" class="form-input w-full" required>
                        </div>
                    </div>
                    <div class="flex gap-3 mt-6">
                        <button type="submit" class="btn btn-primary flex-1">등록</button>
                        <button type="button" onclick="hideAddEmployeeModal()" class="btn" style="background: #f3f4f6; color: #374151;">취소</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // 전역 변수
        let gridApi;
        let payrollData = [];
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        
        // AgGrid 컬럼 정의 (엑셀 스타일)
        const columnDefs = [
            {
                headerName: '직원 정보',
                children: [
                    {
                        field: 'employee_id',
                        headerName: '직원 ID',
                        width: 100,
                        editable: false,
                        cellStyle: { backgroundColor: '#f8fafc' }
                    },
                    {
                        field: 'employee_name', 
                        headerName: '직원명',
                        width: 100,
                        editable: true,
                        cellStyle: { backgroundColor: '#fefce8' }
                    },
                    {
                        field: 'position',
                        headerName: '직급',
                        width: 80,
                        editable: true,
                        cellEditor: 'agSelectCellEditor',
                        cellEditorParams: {
                            values: ['점장', '부점장', '직원', '상담원', '인턴']
                        },
                        cellStyle: { backgroundColor: '#fefce8' }
                    },
                    {
                        field: 'dealer_code',
                        headerName: '대리점',
                        width: 100,
                        editable: true,
                        cellEditor: 'agSelectCellEditor',
                        cellEditorParams: { values: [] }, // 동적으로 로드
                        cellStyle: { backgroundColor: '#fefce8' }
                    }
                ]
            },
            {
                headerName: '급여 계산',
                children: [
                    {
                        field: 'base_salary',
                        headerName: '기본급',
                        width: 120,
                        editable: true,
                        cellEditor: 'agNumberCellEditor',
                        valueFormatter: params => params.value ? Number(params.value).toLocaleString() + '원' : '0원',
                        cellStyle: { backgroundColor: '#fefce8' },
                        onCellValueChanged: recalculatePayroll
                    },
                    {
                        field: 'incentive_amount',
                        headerName: '인센티브',
                        width: 120,
                        editable: false,
                        valueFormatter: params => params.value ? Number(params.value).toLocaleString() + '원' : '0원',
                        cellStyle: { backgroundColor: '#f0fdf4', color: '#059669', fontWeight: 'bold' }
                    },
                    {
                        field: 'bonus_amount',
                        headerName: '보너스',
                        width: 100,
                        editable: true,
                        cellEditor: 'agNumberCellEditor',
                        valueFormatter: params => params.value ? Number(params.value).toLocaleString() + '원' : '0원',
                        cellStyle: { backgroundColor: '#fefce8' },
                        onCellValueChanged: recalculatePayroll
                    },
                    {
                        field: 'deduction_amount',
                        headerName: '공제액',
                        width: 100,
                        editable: true,
                        cellEditor: 'agNumberCellEditor',
                        valueFormatter: params => params.value ? Number(params.value).toLocaleString() + '원' : '0원',
                        cellStyle: { backgroundColor: '#fefce8' },
                        onCellValueChanged: recalculatePayroll
                    },
                    {
                        field: 'total_salary',
                        headerName: '실지급액',
                        width: 130,
                        editable: false,
                        valueFormatter: params => params.value ? Number(params.value).toLocaleString() + '원' : '0원',
                        cellStyle: { backgroundColor: '#f0fdf4', color: '#059669', fontWeight: 'bold' }
                    }
                ]
            },
            {
                headerName: '지급 관리',
                children: [
                    {
                        field: 'payment_status',
                        headerName: '지급상태',
                        width: 100,
                        cellRenderer: function(params) {
                            const isPaid = params.value === 'paid';
                            const statusClass = isPaid ? 'status-paid' : 'status-pending';
                            const statusText = isPaid ? '지급완료' : '미지급';
                            return `<button class="status-toggle ${statusClass}" 
                                           onclick="togglePaymentStatus('${params.data.id}')">
                                        ${statusText}
                                    </button>`;
                        }
                    },
                    {
                        field: 'payment_date',
                        headerName: '지급일',
                        width: 100,
                        valueFormatter: params => params.value || '-'
                    },
                    {
                        headerName: '액션',
                        width: 80,
                        cellRenderer: function(params) {
                            return `<button onclick="deletePayroll('${params.data.id}')" 
                                           style="color: #ef4444; background: none; border: none; cursor: pointer; font-size: 16px;">
                                        🗑️
                                    </button>`;
                        }
                    }
                ]
            }
        ];

        // AgGrid 옵션
        const gridOptions = {
            columnDefs: columnDefs,
            rowData: [],
            defaultColDef: {
                sortable: true,
                filter: true,
                resizable: true,
            },
            enableRangeSelection: true,
            enableClipboard: true,
            suppressRowClickSelection: true,
            rowSelection: 'multiple',
            animateRows: true,
            onGridReady: function(params) {
                gridApi = params.api;
                loadPayrollData();
            },
            onCellValueChanged: function(event) {
                // 셀 값 변경 시 서버에 자동 저장
                updateField(event.data.id, event.colDef.field, event.newValue);
            },
            getRowStyle: function(params) {
                if (params.data.payment_status === 'paid') {
                    return { background: '#f0f9ff' }; // 지급완료된 행은 파란색 배경
                }
                return {};
            }
        };

        // 페이지 로드 시 초기화
        document.addEventListener('DOMContentLoaded', function() {
            // AgGrid 초기화
            const gridDiv = document.querySelector('#payrollGrid');
            new agGrid.Grid(gridDiv, gridOptions);
            
            loadDealers();
            loadSummary();
            
            // 현재 월 기본값 설정
            document.getElementById('monthFilter').value = new Date().toISOString().slice(0, 7);
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
                    dealerFilter.innerHTML += `<option value="${dealer.dealer_code}">${dealer.dealer_name}</option>`;
                    if (dealerSelect) {
                        dealerSelect.innerHTML += `<option value="${dealer.dealer_code}">${dealer.dealer_name}</option>`;
                    }
                    dealerCodes.push(dealer.dealer_code);
                });
                
                // AgGrid 드롭다운 업데이트
                const dealerColumn = columnDefs.find(col => col.children?.some(child => child.field === 'dealer_code'));
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

        // 급여 데이터 로드 (AgGrid 방식)
        async function loadPayrollData() {
            const yearMonth = document.getElementById('monthFilter').value;
            const dealerCode = document.getElementById('dealerFilter').value;
            const paymentStatus = document.getElementById('statusFilter').value;
            
            let url = `/api/payroll?year_month=${yearMonth}&limit=100`;
            if (dealerCode) url += `&dealer_code=${dealerCode}`;
            if (paymentStatus) url += `&payment_status=${paymentStatus}`;
            
            try {
                const response = await fetch(url);
                const data = await response.json();
                
                if (data.success) {
                    payrollData = data.data;
                    if (gridApi) {
                        gridApi.setRowData(payrollData);
                        gridApi.sizeColumnsToFit();
                    }
                    
                    // 빈 데이터 시 안내 메시지
                    if (data.data.length === 0) {
                        gridApi?.showNoRowsOverlay();
                    }
                } else {
                    console.error('급여 데이터 로드 실패:', data.message);
                }
            } catch (error) {
                console.error('급여 데이터 로드 오류:', error);
                if (gridApi) {
                    gridApi.showLoadingOverlay();
                }
            }
        }

        // 급여 재계산 (엑셀 방식 자동 계산)
        function recalculatePayroll(event) {
            const data = event.data;
            const baseSalary = Number(data.base_salary || 0);
            const incentive = Number(data.incentive_amount || 0);
            const bonus = Number(data.bonus_amount || 0);
            const deduction = Number(data.deduction_amount || 0);
            
            // 실지급액 = 기본급 + 인센티브 + 보너스 - 공제액
            const totalSalary = baseSalary + incentive + bonus - deduction;
            
            // 데이터 업데이트
            data.total_salary = totalSalary;
            
            // 그리드 새로고침
            if (gridApi) {
                gridApi.applyTransaction({ update: [data] });
            }
            
            // 요약 정보 갱신
            loadSummary();
        }

        // 필드 업데이트 (AgGrid에서 호출)
        async function updateField(payrollId, field, value) {
            try {
                const response = await fetch(`/api/payroll/${payrollId}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ [field]: value })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // 성공 시 그리드 데이터 업데이트
                    const rowNode = gridApi.getRowNode(payrollId);
                    if (rowNode) {
                        rowNode.setData(result.data);
                    }
                    loadSummary(); // 요약 정보 갱신
                    
                    // 성공 피드백
                    showNotification('✅ 저장완료', 'success');
                } else {
                    alert('❌ 수정 실패: ' + (result.message || '알 수 없는 오류'));
                }
            } catch (error) {
                alert('❌ 네트워크 오류: ' + error.message);
            }
        }

        // 지급 상태 토글 (AgGrid에서 호출)
        async function togglePaymentStatus(payrollId) {
            try {
                const response = await fetch(`/api/payroll/${payrollId}/payment-status`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    }
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // 그리드 데이터 새로고침
                    loadPayrollData();
                    loadSummary();
                    showNotification('✅ 지급상태 변경완료', 'success');
                } else {
                    alert('❌ 상태 변경 실패: ' + (result.message || '알 수 없는 오류'));
                }
            } catch (error) {
                alert('❌ 네트워크 오류: ' + error.message);
            }
        }

        // 새 직원 급여 추가
        function addNewPayroll() {
            const newPayroll = {
                id: `new_${Date.now()}`,
                employee_id: `EMP${Date.now()}`,
                employee_name: '새 직원',
                position: '직원',
                dealer_code: 'DEFAULT',
                base_salary: 2000000,
                incentive_amount: 0,
                bonus_amount: 0,
                deduction_amount: 0,
                total_salary: 2000000,
                payment_status: 'pending',
                payment_date: null,
                year_month: document.getElementById('monthFilter').value
            };
            
            if (gridApi) {
                gridApi.applyTransaction({ add: [newPayroll] });
                
                // 새 행에 포커스
                setTimeout(() => {
                    const newRowIndex = gridApi.getDisplayedRowCount() - 1;
                    gridApi.setFocusedCell(newRowIndex, 'employee_name');
                    gridApi.startEditingCell({
                        rowIndex: newRowIndex,
                        colKey: 'employee_name'
                    });
                }, 100);
            }
        }

        // 모든 변경사항 저장
        async function saveAllChanges() {
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '💾 저장 중...';
            button.disabled = true;
            
            try {
                // 변경된 모든 행 데이터 가져오기
                const allRowData = [];
                gridApi.forEachNode(node => allRowData.push(node.data));
                
                // 서버에 일괄 저장
                const response = await fetch('/api/payroll/bulk-update', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken
                    },
                    body: JSON.stringify({ payrolls: allRowData })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('✅ 모든 변경사항이 저장되었습니다!', 'success');
                    loadSummary();
                } else {
                    alert('❌ 저장 실패: ' + (result.message || '알 수 없는 오류'));
                }
            } catch (error) {
                alert('❌ 네트워크 오류: ' + error.message);
            } finally {
                button.innerHTML = originalText;
                button.disabled = false;
            }
        }

        // 엑셀 내보내기
        function exportToExcel() {
            const yearMonth = document.getElementById('monthFilter').value;
            const dealerCode = document.getElementById('dealerFilter').value;
            
            let url = `/api/payroll/export/excel?year_month=${yearMonth}`;
            if (dealerCode) url += `&dealer_code=${dealerCode}`;
            
            // 파일 다운로드
            window.open(url, '_blank');
            showNotification('📊 엑셀 파일을 다운로드합니다', 'info');
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

        // 급여 삭제
        async function deletePayroll(payrollId) {
            if (!confirm('정말 삭제하시겠습니까?')) return;
            
            try {
                const response = await fetch(`/api/payroll/${payrollId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    }
                });
                
                const result = await response.json();
                
                if (result.success) {
                    loadPayrollData();
                    loadSummary();
                    showNotification('✅ 삭제 완료', 'success');
                } else {
                    alert('❌ 삭제 실패: ' + (result.message || '알 수 없는 오류'));
                }
            } catch (error) {
                alert('❌ 네트워크 오류: ' + error.message);
            }
        }
    </script>
</body>
</html>