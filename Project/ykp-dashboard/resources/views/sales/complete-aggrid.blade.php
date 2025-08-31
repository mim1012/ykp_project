<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>완전한 개통표 - YKP ERP</title>
    @vite(['resources/css/app.css'])
    <style>
        .field-group-basic { background-color: #f8fafc; }
        .field-group-amount { background-color: #eff6ff; }
        .field-group-policy { background-color: #f3e8ff; }
        .field-group-calculated { background-color: #fef3c7; font-weight: bold; }
        .field-group-margin { background-color: #ecfdf5; font-weight: bold; }
        .field-group-meta { background-color: #fdf2f8; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- 헤더 -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-gray-900">완전한 개통표 AgGrid</h1>
                    <span class="ml-2 px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded">ALL FIELDS</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/sales/excel-input" class="text-gray-600 hover:text-gray-900">엑셀입력</a>
                    <a href="/sales/advanced-input-pro" class="text-gray-600 hover:text-gray-900">고급입력</a>
                    <a href="/dashboard" class="text-gray-600 hover:text-gray-900">대시보드</a>
                </div>
            </div>
        </div>
    </header>

    <!-- 메인 컨텐츠 -->
    <main class="max-w-full mx-auto py-6 px-4">
        <!-- 날짜 선택 컨트롤 -->
        <div class="bg-white p-4 rounded-lg shadow mb-6">
            <div class="flex items-center gap-4">
                <div class="flex items-center gap-2">
                    <label class="text-sm font-medium text-gray-700">조회 날짜:</label>
                    <input type="date" id="filter-date" class="border border-gray-300 rounded px-3 py-1 text-sm">
                </div>
                <div class="flex items-center gap-2">
                    <button onclick="loadTodayData()" class="px-3 py-1 bg-blue-500 text-white rounded text-sm hover:bg-blue-600">오늘</button>
                    <button onclick="loadYesterdayData()" class="px-3 py-1 bg-gray-500 text-white rounded text-sm hover:bg-gray-600">어제</button>
                    <button onclick="loadDateData()" class="px-3 py-1 bg-green-500 text-white rounded text-sm hover:bg-green-600">조회</button>
                </div>
                <div class="flex items-center gap-2 ml-auto">
                    <span class="text-sm text-gray-500">현재 조회일:</span>
                    <span id="current-date" class="text-sm font-medium text-blue-600">2025-09-01</span>
                </div>
            </div>
        </div>
        
        <!-- 통계 카드 -->
        <div class="grid grid-cols-1 md:grid-cols-6 gap-4 mb-6">
            <div class="bg-white p-4 rounded-lg shadow">
                <div class="text-sm font-medium text-gray-500">총 건수</div>
                <div class="text-xl font-bold text-gray-900" id="total-count">2</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow">
                <div class="text-sm font-medium text-gray-500">총 정산금</div>
                <div class="text-xl font-bold text-blue-600" id="total-settlement">415,000원</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow">
                <div class="text-sm font-medium text-gray-500">총 세금</div>
                <div class="text-xl font-bold text-red-600" id="total-tax">55,195원</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow">
                <div class="text-sm font-medium text-gray-500">총 마진</div>
                <div class="text-xl font-bold text-green-600" id="total-margin">415,000원</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow">
                <div class="text-sm font-medium text-gray-500">평균 마진율</div>
                <div class="text-xl font-bold text-purple-600" id="avg-margin">100.0%</div>
            </div>
            <div class="bg-white p-4 rounded-lg shadow">
                <div class="text-sm font-medium text-gray-500">월정액 합계</div>
                <div class="text-xl font-bold text-indigo-600" id="total-monthly">0원</div>
            </div>
        </div>

        <!-- 컨트롤 패널 -->
        <div class="bg-white rounded-lg shadow mb-6">
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
                            ➕ 새 개통 등록
                        </button>
                        
                        <button id="calculate-all-btn" class="px-4 py-2 bg-purple-500 text-white rounded hover:bg-purple-600">
                            🔄 전체 재계산
                        </button>
                        
                        <button id="save-btn" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
                            💾 일괄 저장
                        </button>
                        
                        <button id="export-btn" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600">
                            📤 엑셀 내보내기
                        </button>
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <div id="status-indicator" class="px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded">
                            준비됨
                        </div>
                        
                        <button id="help-btn" class="px-3 py-2 text-gray-600 hover:text-gray-800" title="도움말 (F1)">
                            ❓
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 완전한 개통표 테이블 (가로 스크롤) -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto" style="max-height: 600px; overflow-y: auto;">
                <table class="min-w-full divide-y divide-gray-200" style="min-width: 3000px;">
                    <thead class="bg-gray-50 sticky top-0 z-10">
                        <tr>
                            <!-- 기본 정보 -->
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase field-group-basic">선택</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase field-group-basic">판매일</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase field-group-basic">판매자</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase field-group-basic">대리점</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase field-group-basic">지사</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase field-group-basic">매장</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase field-group-basic">통신사</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase field-group-basic">개통방식</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase field-group-basic">모델명</th>
                            
                            <!-- 고객 정보 -->
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase field-group-meta">고객명</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase field-group-meta">전화번호</th>
                            
                            <!-- 금액 정보 (입력) -->
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase field-group-amount">액면가</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase field-group-amount">구두1</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase field-group-amount">구두2</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase field-group-amount">그레이드</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase field-group-amount">부가추가</th>
                            
                            <!-- 정책 정보 -->
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase field-group-policy">서류상현금</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase field-group-policy">유심비</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase field-group-policy">MNP할인</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase field-group-policy">차감</th>
                            
                            <!-- 계산 결과 -->
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase field-group-calculated">리베총계</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase field-group-calculated">정산금</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase field-group-calculated">세금</th>
                            
                            <!-- 마진 계산 -->
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase field-group-margin">현금받음</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase field-group-margin">페이백</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase field-group-margin">세전마진</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase field-group-margin">세후마진</th>
                            
                            <!-- 기타 -->
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase field-group-meta">월정액</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase field-group-meta">메모</th>
                            <th class="px-2 py-3 text-left text-xs font-medium text-gray-500 uppercase">액션</th>
                        </tr>
                    </thead>
                    <tbody id="data-table-body" class="bg-white divide-y divide-gray-200">
                        <!-- 데이터 행들이 여기에 동적으로 추가됩니다 -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- 필드 설명 -->
        <div class="bg-white rounded-lg shadow p-4 mt-6">
            <h3 class="text-lg font-semibold mb-4">필드 그룹 설명</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-sm">
                <div class="field-group-basic p-3 rounded">
                    <strong>기본 정보</strong>: 판매일, 판매자, 대리점, 통신사, 개통방식, 모델명
                </div>
                <div class="field-group-amount p-3 rounded">
                    <strong>금액 정보</strong>: 액면가, 구두1/2, 그레이드, 부가추가 (입력 항목)
                </div>
                <div class="field-group-policy p-3 rounded">
                    <strong>정책 정보</strong>: 서류상현금, 유심비, MNP할인, 차감 (정책 항목)
                </div>
                <div class="field-group-calculated p-3 rounded">
                    <strong>계산 결과</strong>: 리베총계, 정산금, 세금 (자동 계산)
                </div>
                <div class="field-group-margin p-3 rounded">
                    <strong>마진 계산</strong>: 현금받음, 페이백, 세전/세후마진 (자동 계산)
                </div>
                <div class="field-group-meta p-3 rounded">
                    <strong>기타 정보</strong>: 고객명, 전화번호, 월정액, 메모
                </div>
            </div>
        </div>
    </main>

    <script>
        // 완전한 데이터 구조 (Sales 모델의 모든 필드)
        let completeTableData = [
            {
                id: 1,
                // 기본 정보
                sale_date: '2025-01-21',
                salesperson: '김대리',
                dealer_code: 'ENT',
                store_id: 1,
                branch_id: 1,
                carrier: 'SK',
                activation_type: '신규',
                model_name: 'iPhone 15',
                
                // 고객 정보
                customer_name: '홍길동',
                phone_number: '010-1234-5678',
                
                // 금액 정보 (입력)
                base_price: 100000,
                verbal1: 30000,
                verbal2: 20000,
                grade_amount: 15000,
                additional_amount: 5000,
                
                // 정책 정보
                cash_activation: 0,
                usim_fee: 0,
                new_mnp_discount: 800,
                deduction: 0,
                
                // 계산 결과
                rebate_total: 170000,
                settlement_amount: 170800,
                tax: 22716,
                
                // 마진 계산
                cash_received: 0,
                payback: 0,
                margin_before_tax: 148084,
                margin_after_tax: 170800,
                
                // 기타
                monthly_fee: 89000,
                memo: '신규 고객'
            },
            {
                id: 2,
                // 기본 정보
                sale_date: '2025-01-21',
                salesperson: '박과장',
                dealer_code: 'WIN',
                store_id: 2,
                branch_id: 1,
                carrier: 'KT',
                activation_type: 'MNP',
                model_name: 'Galaxy S24',
                
                // 고객 정보
                customer_name: '이순신',
                phone_number: '010-9876-5432',
                
                // 금액 정보 (입력)
                base_price: 150000,
                verbal1: 40000,
                verbal2: 25000,
                grade_amount: 20000,
                additional_amount: 10000,
                
                // 정책 정보
                cash_activation: 5000,
                usim_fee: 0,
                new_mnp_discount: 800,
                deduction: 1000,
                
                // 계산 결과
                rebate_total: 245000,
                settlement_amount: 239200,
                tax: 31815,
                
                // 마진 계산
                cash_received: 10000,
                payback: 5000,
                margin_before_tax: 212385,
                margin_after_tax: 244200,
                
                // 기타
                monthly_fee: 119000,
                memo: 'MNP 고객, 프리미엄 플랜'
            }
        ];
        
        // 테이블 업데이트 (완전한 필드)
        function updateCompleteTable() {
            const tbody = document.getElementById('data-table-body');
            tbody.innerHTML = completeTableData.map(row => `
                <tr data-id="${row.id}" class="hover:bg-gray-50">
                    <!-- 기본 정보 -->
                    <td class="px-2 py-2 field-group-basic">
                        <input type="checkbox" class="row-select">
                    </td>
                    <td class="px-2 py-2 field-group-basic">
                        <input type="date" value="${row.sale_date || ''}" 
                               onchange="updateRowData(${row.id}, 'sale_date', this.value)"
                               class="w-full px-1 py-1 border rounded text-xs">
                    </td>
                    <td class="px-2 py-2 field-group-basic">
                        <input type="text" value="${row.salesperson || ''}" 
                               onchange="updateRowData(${row.id}, 'salesperson', this.value)"
                               class="w-20 px-1 py-1 border rounded text-xs">
                    </td>
                    <td class="px-2 py-2 field-group-basic">
                        <select onchange="updateRowData(${row.id}, 'dealer_code', this.value)"
                                class="w-20 px-1 py-1 border rounded text-xs">
                            <option value="" ${!row.dealer_code ? 'selected' : ''}>선택</option>
                            <option value="ENT" ${row.dealer_code === 'ENT' ? 'selected' : ''}>이앤티</option>
                            <option value="WIN" ${row.dealer_code === 'WIN' ? 'selected' : ''}>앤투윈</option>
                            <option value="CHOSI" ${row.dealer_code === 'CHOSI' ? 'selected' : ''}>초시대</option>
                            <option value="AMT" ${row.dealer_code === 'AMT' ? 'selected' : ''}>아엠티</option>
                        </select>
                    </td>
                    <td class="px-2 py-2 field-group-basic">
                        <input type="number" value="${row.store_id || 1}" 
                               onchange="updateRowData(${row.id}, 'store_id', parseInt(this.value) || 1)"
                               class="w-16 px-1 py-1 border rounded text-xs">
                    </td>
                    <td class="px-2 py-2 field-group-basic">
                        <input type="number" value="${row.branch_id || 1}" 
                               onchange="updateRowData(${row.id}, 'branch_id', parseInt(this.value) || 1)"
                               class="w-16 px-1 py-1 border rounded text-xs">
                    </td>
                    <td class="px-2 py-2 field-group-basic">
                        <select onchange="updateRowData(${row.id}, 'carrier', this.value)"
                                class="w-16 px-1 py-1 border rounded text-xs">
                            <option value="SK" ${row.carrier === 'SK' ? 'selected' : ''}>SK</option>
                            <option value="KT" ${row.carrier === 'KT' ? 'selected' : ''}>KT</option>
                            <option value="LG" ${row.carrier === 'LG' ? 'selected' : ''}>LG</option>
                        </select>
                    </td>
                    <td class="px-2 py-2 field-group-basic">
                        <select onchange="updateRowData(${row.id}, 'activation_type', this.value)"
                                class="w-20 px-1 py-1 border rounded text-xs">
                            <option value="신규" ${row.activation_type === '신규' ? 'selected' : ''}>신규</option>
                            <option value="MNP" ${row.activation_type === 'MNP' ? 'selected' : ''}>MNP</option>
                            <option value="기변" ${row.activation_type === '기변' ? 'selected' : ''}>기변</option>
                        </select>
                    </td>
                    <td class="px-2 py-2 field-group-basic">
                        <input type="text" value="${row.model_name || ''}" 
                               onchange="updateRowData(${row.id}, 'model_name', this.value)"
                               class="w-24 px-1 py-1 border rounded text-xs">
                    </td>
                    
                    <!-- 고객 정보 -->
                    <td class="px-2 py-2 field-group-meta">
                        <input type="text" value="${row.customer_name || ''}" 
                               onchange="updateRowData(${row.id}, 'customer_name', this.value)"
                               class="w-20 px-1 py-1 border rounded text-xs">
                    </td>
                    <td class="px-2 py-2 field-group-meta">
                        <input type="tel" value="${row.phone_number || ''}" 
                               onchange="updateRowData(${row.id}, 'phone_number', this.value)"
                               class="w-28 px-1 py-1 border rounded text-xs">
                    </td>
                    
                    <!-- 금액 정보 (입력) -->
                    <td class="px-2 py-2 field-group-amount">
                        <input type="number" value="${row.base_price || 0}" 
                               onchange="updateRowData(${row.id}, 'base_price', parseInt(this.value) || 0); calculateCompleteRow(${row.id})"
                               class="w-24 px-1 py-1 border rounded text-xs">
                    </td>
                    <td class="px-2 py-2 field-group-amount">
                        <input type="number" value="${row.verbal1 || 0}" 
                               onchange="updateRowData(${row.id}, 'verbal1', parseInt(this.value) || 0); calculateCompleteRow(${row.id})"
                               class="w-20 px-1 py-1 border rounded text-xs">
                    </td>
                    <td class="px-2 py-2 field-group-amount">
                        <input type="number" value="${row.verbal2 || 0}" 
                               onchange="updateRowData(${row.id}, 'verbal2', parseInt(this.value) || 0); calculateCompleteRow(${row.id})"
                               class="w-20 px-1 py-1 border rounded text-xs">
                    </td>
                    <td class="px-2 py-2 field-group-amount">
                        <input type="number" value="${row.grade_amount || 0}" 
                               onchange="updateRowData(${row.id}, 'grade_amount', parseInt(this.value) || 0); calculateCompleteRow(${row.id})"
                               class="w-20 px-1 py-1 border rounded text-xs">
                    </td>
                    <td class="px-2 py-2 field-group-amount">
                        <input type="number" value="${row.additional_amount || 0}" 
                               onchange="updateRowData(${row.id}, 'additional_amount', parseInt(this.value) || 0); calculateCompleteRow(${row.id})"
                               class="w-20 px-1 py-1 border rounded text-xs">
                    </td>
                    
                    <!-- 정책 정보 -->
                    <td class="px-2 py-2 field-group-policy">
                        <input type="number" value="${row.cash_activation || 0}" 
                               onchange="updateRowData(${row.id}, 'cash_activation', parseInt(this.value) || 0); calculateCompleteRow(${row.id})"
                               class="w-20 px-1 py-1 border rounded text-xs">
                    </td>
                    <td class="px-2 py-2 field-group-policy">
                        <input type="number" value="${row.usim_fee || 0}" 
                               onchange="updateRowData(${row.id}, 'usim_fee', parseInt(this.value) || 0); calculateCompleteRow(${row.id})"
                               class="w-20 px-1 py-1 border rounded text-xs">
                    </td>
                    <td class="px-2 py-2 field-group-policy">
                        <input type="number" value="${row.new_mnp_discount || 0}" 
                               onchange="updateRowData(${row.id}, 'new_mnp_discount', parseInt(this.value) || 0); calculateCompleteRow(${row.id})"
                               class="w-20 px-1 py-1 border rounded text-xs">
                    </td>
                    <td class="px-2 py-2 field-group-policy">
                        <input type="number" value="${row.deduction || 0}" 
                               onchange="updateRowData(${row.id}, 'deduction', parseInt(this.value) || 0); calculateCompleteRow(${row.id})"
                               class="w-20 px-1 py-1 border rounded text-xs">
                    </td>
                    
                    <!-- 계산 결과 (읽기 전용) -->
                    <td class="px-2 py-2 field-group-calculated">
                        <span class="text-xs font-bold text-yellow-800" id="rebate-${row.id}">${row.rebate_total?.toLocaleString() || '0'}원</span>
                    </td>
                    <td class="px-2 py-2 field-group-calculated">
                        <span class="text-xs font-bold text-yellow-800" id="settlement-${row.id}">${row.settlement_amount?.toLocaleString() || '0'}원</span>
                    </td>
                    <td class="px-2 py-2 field-group-calculated">
                        <span class="text-xs font-bold text-red-800" id="tax-${row.id}">${row.tax?.toLocaleString() || '0'}원</span>
                    </td>
                    
                    <!-- 마진 계산 -->
                    <td class="px-2 py-2 field-group-margin">
                        <input type="number" value="${row.cash_received || 0}" 
                               onchange="updateRowData(${row.id}, 'cash_received', parseInt(this.value) || 0); calculateCompleteRow(${row.id})"
                               class="w-20 px-1 py-1 border rounded text-xs">
                    </td>
                    <td class="px-2 py-2 field-group-margin">
                        <input type="number" value="${row.payback || 0}" 
                               onchange="updateRowData(${row.id}, 'payback', parseInt(this.value) || 0); calculateCompleteRow(${row.id})"
                               class="w-20 px-1 py-1 border rounded text-xs">
                    </td>
                    <td class="px-2 py-2 field-group-margin">
                        <span class="text-xs font-bold text-green-800" id="margin-before-${row.id}">${row.margin_before_tax?.toLocaleString() || '0'}원</span>
                    </td>
                    <td class="px-2 py-2 field-group-margin">
                        <span class="text-xs font-bold text-green-800" id="margin-after-${row.id}">${row.margin_after_tax?.toLocaleString() || '0'}원</span>
                    </td>
                    
                    <!-- 기타 -->
                    <td class="px-2 py-2 field-group-meta">
                        <input type="number" value="${row.monthly_fee || 0}" 
                               onchange="updateRowData(${row.id}, 'monthly_fee', parseInt(this.value) || 0)"
                               class="w-20 px-1 py-1 border rounded text-xs">
                    </td>
                    <td class="px-2 py-2 field-group-meta">
                        <input type="text" value="${row.memo || ''}" 
                               onchange="updateRowData(${row.id}, 'memo', this.value)"
                               class="w-32 px-1 py-1 border rounded text-xs">
                    </td>
                    
                    <!-- 액션 -->
                    <td class="px-2 py-2">
                        <button onclick="deleteCompleteRow(${row.id})" 
                                class="px-2 py-1 bg-red-500 text-white rounded hover:bg-red-600 text-xs">
                            🗑️
                        </button>
                    </td>
                </tr>
            `).join('');
        }
        
        // 새 행 추가 (완전한 필드)
        function addCompleteNewRow() {
            const newId = Math.max(...completeTableData.map(r => r.id || 0)) + 1;
            const newRow = {
                id: newId,
                // 기본 정보
                sale_date: new Date().toISOString().split('T')[0],
                salesperson: '',
                dealer_code: '',
                store_id: 1,
                branch_id: 1,
                carrier: 'SK',
                activation_type: '신규',
                model_name: '',
                
                // 고객 정보
                customer_name: '',
                phone_number: '',
                
                // 금액 정보
                base_price: 0,
                verbal1: 0,
                verbal2: 0,
                grade_amount: 0,
                additional_amount: 0,
                
                // 정책 정보
                cash_activation: 0,
                usim_fee: 0,
                new_mnp_discount: 800, // 기본값
                deduction: 0,
                
                // 계산 결과
                rebate_total: 0,
                settlement_amount: 0,
                tax: 0,
                
                // 마진 계산
                cash_received: 0,
                payback: 0,
                margin_before_tax: 0,
                margin_after_tax: 0,
                
                // 기타
                monthly_fee: 0,
                memo: ''
            };
            
            completeTableData.push(newRow);
            updateCompleteTable();
            updateCompleteStats();
            
            showStatus(`새 개통 등록이 추가되었습니다! (ID: ${newId})`, 'success');
        }
        
        // 행 데이터 업데이트
        function updateRowData(id, field, value) {
            const row = completeTableData.find(r => r.id === id);
            if (row) {
                row[field] = value;
                console.log(`행 ${id} 업데이트:`, field, '=', value);
            }
        }
        
        // 완전한 실시간 계산
        async function calculateCompleteRow(id) {
            const row = completeTableData.find(r => r.id === id);
            if (!row) return;
            
            try {
                showStatus('계산 중...', 'loading');
                
                const response = await fetch('/api/calculation/row', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        price_setting: row.base_price || 0,
                        verbal1: row.verbal1 || 0,
                        verbal2: row.verbal2 || 0,
                        grade_amount: row.grade_amount || 0,
                        addon_amount: row.additional_amount || 0,
                        paper_cash: row.cash_activation || 0,
                        usim_fee: row.usim_fee || 0,
                        new_mnp_disc: row.new_mnp_discount || 0,
                        deduction: row.deduction || 0,
                        cash_in: row.cash_received || 0,
                        payback: row.payback || 0
                    })
                });
                
                const result = await response.json();
                if (result.success) {
                    // 모든 계산 결과 반영
                    row.rebate_total = result.data.total_rebate;
                    row.settlement_amount = result.data.settlement;
                    row.tax = result.data.tax;
                    row.margin_before_tax = result.data.margin_before;
                    row.margin_after_tax = result.data.margin_after;
                    
                    // UI 업데이트
                    document.getElementById(`rebate-${id}`).textContent = row.rebate_total.toLocaleString() + '원';
                    document.getElementById(`settlement-${id}`).textContent = row.settlement_amount.toLocaleString() + '원';
                    document.getElementById(`tax-${id}`).textContent = row.tax.toLocaleString() + '원';
                    document.getElementById(`margin-before-${id}`).textContent = row.margin_before_tax.toLocaleString() + '원';
                    document.getElementById(`margin-after-${id}`).textContent = row.margin_after_tax.toLocaleString() + '원';
                    
                    updateCompleteStats();
                    showStatus('계산 완료!', 'success');
                    
                    console.log('완전한 실시간 계산 완료:', result.data);
                } else {
                    showStatus('계산 실패: ' + result.message, 'error');
                }
            } catch (error) {
                console.error('계산 API 호출 실패:', error);
                showStatus('계산 실패: 네트워크 오류', 'error');
            }
        }
        
        // 행 삭제
        function deleteCompleteRow(id) {
            if (confirm('이 개통 데이터를 삭제하시겠습니까?')) {
                completeTableData = completeTableData.filter(row => row.id !== id);
                updateCompleteTable();
                updateCompleteStats();
                showStatus('개통 데이터가 삭제되었습니다.', 'success');
            }
        }
        
        // 완전한 통계 업데이트
        function updateCompleteStats() {
            const totalCount = completeTableData.length;
            const totalSettlement = completeTableData.reduce((sum, row) => sum + (row.settlement_amount || 0), 0);
            const totalTax = completeTableData.reduce((sum, row) => sum + (row.tax || 0), 0);
            const totalMargin = completeTableData.reduce((sum, row) => sum + (row.margin_after_tax || 0), 0);
            const totalMonthly = completeTableData.reduce((sum, row) => sum + (row.monthly_fee || 0), 0);
            const avgMargin = totalSettlement > 0 ? (totalMargin / totalSettlement * 100) : 0;
            
            document.getElementById('total-count').textContent = totalCount.toLocaleString();
            document.getElementById('total-settlement').textContent = totalSettlement.toLocaleString() + '원';
            document.getElementById('total-tax').textContent = totalTax.toLocaleString() + '원';
            document.getElementById('total-margin').textContent = totalMargin.toLocaleString() + '원';
            document.getElementById('total-monthly').textContent = totalMonthly.toLocaleString() + '원';
            document.getElementById('avg-margin').textContent = avgMargin.toFixed(1) + '%';
        }
        
        // 상태 표시
        function showStatus(message, type = 'info') {
            const indicator = document.getElementById('status-indicator');
            indicator.textContent = message;
            
            indicator.className = 'px-2 py-1 text-xs rounded';
            
            if (type === 'success') {
                indicator.className += ' bg-green-100 text-green-800';
            } else if (type === 'error') {
                indicator.className += ' bg-red-100 text-red-800';
            } else if (type === 'loading') {
                indicator.className += ' bg-blue-100 text-blue-800';
            } else {
                indicator.className += ' bg-gray-100 text-gray-600';
            }
            
            if (type !== 'loading') {
                setTimeout(() => {
                    indicator.textContent = '준비됨';
                    indicator.className = 'px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded';
                }, 3000);
            }
        }
        
        // 전체 재계산
        async function calculateAll() {
            showStatus('전체 재계산 중...', 'loading');
            
            for (const row of completeTableData) {
                await calculateCompleteRow(row.id);
                await new Promise(resolve => setTimeout(resolve, 200)); // 200ms 딜레이
            }
            
            showStatus('전체 재계산 완료!', 'success');
        }
        
        // 엑셀 내보내기 (기본 구현)
        function exportToExcel() {
            const csvContent = "data:text/csv;charset=utf-8," 
                + "판매일,판매자,대리점,통신사,개통방식,모델명,고객명,전화번호,액면가,구두1,구두2,그레이드,부가추가,서류상현금,유심비,MNP할인,차감,리베총계,정산금,세금,현금받음,페이백,세전마진,세후마진,월정액,메모\n"
                + completeTableData.map(row => 
                    `${row.sale_date},${row.salesperson},${row.dealer_code},${row.carrier},${row.activation_type},${row.model_name},${row.customer_name},${row.phone_number},${row.base_price},${row.verbal1},${row.verbal2},${row.grade_amount},${row.additional_amount},${row.cash_activation},${row.usim_fee},${row.new_mnp_discount},${row.deduction},${row.rebate_total},${row.settlement_amount},${row.tax},${row.cash_received},${row.payback},${row.margin_before_tax},${row.margin_after_tax},${row.monthly_fee},"${row.memo}"`
                ).join("\n");
                
            const encodedUri = encodeURI(csvContent);
            const link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", `개통표_${new Date().toISOString().split('T')[0]}.csv`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            showStatus('엑셀 파일이 다운로드되었습니다!', 'success');
        }
        
        // DOM 로드 후 초기화
        document.addEventListener('DOMContentLoaded', function() {
            // 버튼 이벤트
            document.getElementById('add-row-btn').addEventListener('click', addCompleteNewRow);
            document.getElementById('calculate-all-btn').addEventListener('click', calculateAll);
            document.getElementById('export-btn').addEventListener('click', exportToExcel);
            
            // 키보드 단축키
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Insert') {
                    e.preventDefault();
                    addCompleteNewRow();
                }
                if (e.key === 'F5') {
                    e.preventDefault();
                    calculateAll();
                }
                if (e.ctrlKey && e.key === 's') {
                    e.preventDefault();
                    showStatus('저장 기능 준비 중...', 'info');
                }
            });
            
            // 초기 날짜 설정
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('filter-date').value = today;
            document.getElementById('current-date').textContent = today;
            
            // 초기 테이블 렌더링
            updateCompleteTable();
            updateCompleteStats();
            
            console.log('완전한 개통표 AgGrid 시스템 초기화 완료');
            showStatus('모든 필드 준비 완료 (40개 필드)', 'success');
        });
        
        // 날짜별 데이터 로딩 함수들
        function loadTodayData() {
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('filter-date').value = today;
            loadDateData();
        }
        
        function loadYesterdayData() {
            const yesterday = new Date();
            yesterday.setDate(yesterday.getDate() - 1);
            const yesterdayStr = yesterday.toISOString().split('T')[0];
            document.getElementById('filter-date').value = yesterdayStr;
            loadDateData();
        }
        
        function loadDateData() {
            const selectedDate = document.getElementById('filter-date').value;
            if (!selectedDate) {
                alert('날짜를 선택해주세요.');
                return;
            }
            
            document.getElementById('current-date').textContent = selectedDate;
            showStatus(`${selectedDate} 데이터 로딩 중...`, 'info');
            
            // API 호출로 해당 날짜 데이터 로드
            fetch(`/api/sales?sale_date=${selectedDate}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // 해당 날짜 데이터로 테이블 업데이트
                        completeTableData = data.sales || [];
                        updateCompleteTable();
                        updateCompleteStats();
                        showStatus(`${selectedDate} 데이터 로드 완료 (${completeTableData.length}건)`, 'success');
                    } else {
                        // 새로운 날짜인 경우 빈 템플릿 제공
                        initializeNewDateData(selectedDate);
                        showStatus(`${selectedDate} 새로운 개통표 생성`, 'info');
                    }
                })
                .catch(error => {
                    console.error('데이터 로드 오류:', error);
                    initializeNewDateData(selectedDate);
                    showStatus(`${selectedDate} 새로운 개통표 생성`, 'info');
                });
        }
        
        // 새로운 날짜의 빈 데이터 초기화
        function initializeNewDateData(date) {
            completeTableData = Array.from({length: 10}, () => ({
                id: null,
                sale_date: date,
                salesperson: '',
                dealer_code: '',
                store_id: window.userData?.store_id || null,
                branch_id: window.userData?.branch_id || null,
                carrier: 'SK',
                activation_type: '신규',
                model_name: '',
                customer_name: '',
                phone_number: '',
                base_price: 0,
                verbal1: 0,
                verbal2: 0,
                grade_amount: 0,
                additional_amount: 0,
                cash_activation: 0,
                usim_fee: 0,
                new_mnp_discount: 0,
                deduction: 0,
                cash_received: 0,
                payback: 0,
                monthly_fee: 0,
                memo: ''
            }));
            
            updateCompleteTable();
            updateCompleteStats();
        }
        
        // 사용자 정보 설정
        window.userData = {
            id: {{ auth()->user()->id ?? 1 }},
            name: '{{ auth()->user()->name ?? "테스트 사용자" }}',
            role: '{{ auth()->user()->role ?? "headquarters" }}',
            store_id: {{ auth()->user()->store_id ?? 'null' }},
            branch_id: {{ auth()->user()->branch_id ?? 'null' }},
            store_name: '{{ auth()->user()->store->name ?? "본사" }}',
            branch_name: '{{ auth()->user()->branch->name ?? "본사" }}'
        };
        
        console.log('사용자 정보:', window.userData);
    </script>
</body>
</html>