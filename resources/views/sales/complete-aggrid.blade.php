<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>개통표 입력 - YKP ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css" rel="stylesheet">
    <style>
        /* PM 요구사항: 각 필드별 최적 width 설정 */
        .field-name { @apply w-28 px-1 py-1 border rounded text-xs; } /* 판매자, 고객명 */
        .field-dealer { @apply w-24 px-1 py-1 border rounded text-xs; } /* 대리점 */
        .field-carrier { @apply w-16 px-1 py-1 border rounded text-xs; } /* 통신사 */
        .field-activation { @apply w-20 px-1 py-1 border rounded text-xs; } /* 개통방식 */
        .field-model { @apply w-32 px-1 py-1 border rounded text-xs; } /* 모델명 */
        .field-date { @apply w-36 px-1 py-1 border rounded text-xs; } /* 개통일, 생년월일 */
        .field-serial { @apply w-28 px-1 py-1 border rounded text-xs; } /* 일련번호 */
        .field-phone { @apply w-36 px-1 py-1 border rounded text-xs; } /* 휴대폰번호 */
        .field-money { @apply w-28 px-1 py-1 border rounded text-xs; } /* 액면가, 구두1/2 */
        .field-amount { @apply w-24 px-1 py-1 border rounded text-xs; } /* 그레이드, 부가추가 */
        .field-policy { @apply w-20 px-1 py-1 border rounded text-xs; } /* 유심비, 차감 */
        .field-calculated { @apply text-xs font-bold min-w-32; } /* 계산 결과 */
        .plus-field { @apply text-green-600; }
        .minus-field { @apply text-red-600; }
        .total-field { @apply bg-yellow-50; }
        .margin-field { @apply bg-green-50; }
    </style>
</head>
<body class="bg-gray-50">
    <!-- 헤더 -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-gray-900">개통표 입력</h1>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/dashboard" class="text-gray-600 hover:text-gray-900">대시보드</a>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-full mx-auto py-6 px-4">
        <!-- 통계 카드 -->
        <div class="grid grid-cols-4 gap-4 mb-6">
            <div class="bg-blue-500 text-white p-4 rounded-lg">
                <div class="text-sm">총 개통건수</div>
                <div class="text-2xl font-bold" id="total-count">0</div>
            </div>
            <div class="bg-green-500 text-white p-4 rounded-lg">
                <div class="text-sm">총 정산금</div>
                <div class="text-2xl font-bold" id="total-settlement">₩0</div>
            </div>
            <div class="bg-purple-500 text-white p-4 rounded-lg">
                <div class="text-sm">총 마진</div>
                <div class="text-2xl font-bold" id="total-margin">₩0</div>
            </div>
            <div class="bg-yellow-500 text-white p-4 rounded-lg">
                <div class="text-sm">평균 마진율</div>
                <div class="text-2xl font-bold" id="average-margin">0%</div>
            </div>
        </div>

        <!-- 컨트롤 패널 -->
        <div class="bg-white rounded-lg shadow mb-6 p-4">
            <div class="flex space-x-4 mb-3">
                <button id="add-row-btn" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                    ➕ 새 개통 등록
                </button>
                <button id="calculate-all-btn" class="px-4 py-2 bg-purple-500 text-white rounded hover:bg-purple-600">
                    🔄 전체 재계산
                </button>
                <button id="save-btn" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
                    💾 전체 저장
                </button>
                <button id="bulk-delete-btn" class="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600">
                    🗑️ 일괄 삭제
                </button>
                <div id="status-indicator" class="px-3 py-2 bg-gray-100 text-gray-600 rounded">
                    시스템 준비 완료
                </div>
            </div>
            <!-- 날짜 필터 UI 추가 -->
            <div class="flex items-center space-x-3 border-t pt-3">
                <span class="text-sm font-medium text-gray-700">날짜 필터:</span>
                <input type="date" id="sale-date-filter" class="px-3 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                <button onclick="selectToday()" class="px-3 py-1.5 bg-indigo-500 text-white text-sm rounded hover:bg-indigo-600">오늘</button>
                <button onclick="selectYesterday()" class="px-3 py-1.5 bg-purple-500 text-white text-sm rounded hover:bg-purple-600">어제</button>
                <button onclick="selectWeek()" class="px-3 py-1.5 bg-green-500 text-white text-sm rounded hover:bg-green-600">이번 주</button>
                <button onclick="selectMonth()" class="px-3 py-1.5 bg-orange-500 text-white text-sm rounded hover:bg-orange-600">이번 달</button>
                <button onclick="clearDateFilter()" class="px-3 py-1.5 bg-gray-500 text-white text-sm rounded hover:bg-gray-600">전체 보기</button>
                <span id="dateStatus" class="ml-4 px-3 py-1.5 bg-blue-50 text-blue-700 text-sm rounded font-medium"></span>
            </div>
        </div>

        <!-- PM 요구사항: 27개 컬럼 완전한 개통표 테이블 -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto" style="max-height: 600px; overflow-y: auto;">
                <table class="min-w-full divide-y divide-gray-200" style="min-width: 4000px;">
                    <thead class="bg-gray-50 sticky top-0 z-10">
                        <tr>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">선택</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">판매자</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">대리점</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">통신사</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">개통방식</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">모델명</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">개통일</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">일련번호</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">휴대폰번호</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">고객명</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">생년월일</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">액면/셋팅가</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">구두1</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">구두2</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">그레이드</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">부가추가</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">서류상현금개통</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase plus-field">유심비(+)</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase minus-field">신규,번이(-800)</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase minus-field">차감(-)</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase total-field">리베총계</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase total-field">정산금</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase bg-red-50">부/소세</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase plus-field">현금받음(+)</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase minus-field">페이백(-)</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase margin-field">세전마진</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase margin-field">세후마진</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">액션</th>
                        </tr>
                    </thead>
                    <tbody id="data-table-body" class="bg-white divide-y divide-gray-200">
                        <!-- 27컬럼 데이터가 여기에 동적으로 추가됩니다 -->
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script>
        // 전역 사용자 데이터 설정
        window.userData = {
            id: {{ auth()->user()->id ?? 'null' }},
            name: '{{ auth()->user()->name ?? "" }}',
            email: '{{ auth()->user()->email ?? "" }}',
            role: '{{ auth()->user()->role ?? "store" }}',
            store_id: {{ auth()->user()->store_id ?? 'null' }},
            branch_id: {{ auth()->user()->branch_id ?? 'null' }}
        };

        // 디버깅: 실제 userData 값 확인
        // User data loaded

        // CSRF 토큰 설정
        window.csrfToken = '{{ csrf_token() }}';

        // PM 요구사항: 27개 필드 완전 매핑된 데이터 구조
        let salesData = [];
        // 임시 ID는 큰 값부터 시작해서 실제 DB ID와 충돌 방지
        let nextId = Date.now();
        
        // 새로운 행 데이터 구조 (DB 스키마와 1:1 매핑)
        function createNewRow() {
            return {
                id: nextId++,
                isPersisted: false, // 아직 DB에 저장되지 않은 임시 행
                salesperson: '',
                dealer_name: '',
                carrier: 'SK',
                activation_type: '신규',
                model_name: '',
                sale_date: (() => {
                    const today = new Date();
                    const year = today.getFullYear();
                    const month = String(today.getMonth() + 1).padStart(2, '0');
                    const day = String(today.getDate()).padStart(2, '0');
                    return `${year}-${month}-${day}`;
                })(),
                serial_number: '',
                phone_number: '',
                customer_name: '',
                customer_birth_date: '',
                base_price: 0,
                verbal1: 0,
                verbal2: 0,
                grade_amount: 0,
                additional_amount: 0,
                cash_activation: 0,
                usim_fee: 0,
                new_mnp_discount: -800,
                deduction: 0,
                rebate_total: 0,
                settlement_amount: 0,
                tax: 0,
                cash_received: 0,
                payback: 0,
                margin_before_tax: 0,
                margin_after_tax: 0,
                memo: ''
            };
        }
        
        // 27개 컬럼 순서대로 테이블 행 생성
        function renderTableRows() {
            const tbody = document.getElementById('data-table-body');
            tbody.innerHTML = salesData.map(row => `
                <tr data-id="${row.id}" class="${row.isPersisted ? 'bg-green-50 hover:bg-green-100' : 'hover:bg-gray-50'}"
                    title="${row.isPersisted ? '저장됨' : '미저장'}">
                    <!-- 1. 선택 -->
                    <td class="px-2 py-2">
                        <input type="checkbox" class="row-select" data-id="${row.id}">
                    </td>
                    <!-- 2. 판매자 -->
                    <td class="px-2 py-2">
                        <input type="text" value="${row.salesperson}" 
                               onchange="updateRowData(${row.id}, 'salesperson', this.value)"
                               class="field-name" placeholder="판매자명">
                    </td>
                    <!-- 3. 대리점 -->
                    <td class="px-2 py-2">
                        <select onchange="updateRowData(${row.id}, 'dealer_name', this.value)" class="field-dealer" id="dealer-select-${row.id}">
                            ${generateDealerOptions(row.dealer_name)}
                        </select>
                    </td>
                    <!-- 4. 통신사 -->
                    <td class="px-2 py-2">
                        <select onchange="updateRowData(${row.id}, 'carrier', this.value)" class="field-carrier">
                            <option value="SK" ${row.carrier === 'SK' ? 'selected' : ''}>SK</option>
                            <option value="KT" ${row.carrier === 'KT' ? 'selected' : ''}>KT</option>
                            <option value="LG" ${row.carrier === 'LG' ? 'selected' : ''}>LG</option>
                        </select>
                    </td>
                    <!-- 5. 개통방식 -->
                    <td class="px-2 py-2">
                        <select onchange="updateRowData(${row.id}, 'activation_type', this.value)" class="field-activation">
                            <option value="신규" ${row.activation_type === '신규' ? 'selected' : ''}>신규</option>
                            <option value="MNP" ${row.activation_type === 'MNP' ? 'selected' : ''}>MNP</option>
                            <option value="기변" ${row.activation_type === '기변' ? 'selected' : ''}>기변</option>
                        </select>
                    </td>
                    <!-- 6. 모델명 -->
                    <td class="px-2 py-2">
                        <input type="text" value="${row.model_name}" 
                               onchange="updateRowData(${row.id}, 'model_name', this.value)"
                               class="field-model" placeholder="iPhone15">
                    </td>
                    <!-- 7. 개통일 -->
                    <td class="px-2 py-2">
                        <input type="date" value="${row.sale_date}" 
                               onchange="updateRowData(${row.id}, 'sale_date', this.value)"
                               class="field-date">
                    </td>
                    <!-- 8. 일련번호 -->
                    <td class="px-2 py-2">
                        <input type="text" value="${row.serial_number}" 
                               onchange="updateRowData(${row.id}, 'serial_number', this.value)"
                               class="field-serial" placeholder="SN123456">
                    </td>
                    <!-- 9. 휴대폰번호 -->
                    <td class="px-2 py-2">
                        <input type="tel" value="${row.phone_number}" 
                               onchange="updateRowData(${row.id}, 'phone_number', this.value)"
                               class="field-phone" placeholder="010-1234-5678">
                    </td>
                    <!-- 10. 고객명 -->
                    <td class="px-2 py-2">
                        <input type="text" value="${row.customer_name}" 
                               onchange="updateRowData(${row.id}, 'customer_name', this.value)"
                               class="field-name" placeholder="김고객">
                    </td>
                    <!-- 11. 생년월일 -->
                    <td class="px-2 py-2">
                        <input type="date" value="${row.customer_birth_date}" 
                               onchange="updateRowData(${row.id}, 'customer_birth_date', this.value)"
                               class="field-date">
                    </td>
                    <!-- 12. 액면/셋팅가 -->
                    <td class="px-2 py-2">
                        <input type="number" value="${row.base_price}"
                               onchange="updateRowData(${row.id}, 'base_price', isNaN(parseFloat(this.value)) ? 0 : parseFloat(this.value)); calculateRow(${row.id})"
                               class="field-money" placeholder="300000">
                    </td>
                    <!-- 13. 구두1 -->
                    <td class="px-2 py-2">
                        <input type="number" value="${row.verbal1}" 
                               onchange="updateRowData(${row.id}, 'verbal1', parseInt(this.value) || 0); calculateRow(${row.id})"
                               class="field-money" placeholder="50000">
                    </td>
                    <!-- 14. 구두2 -->
                    <td class="px-2 py-2">
                        <input type="number" value="${row.verbal2}" 
                               onchange="updateRowData(${row.id}, 'verbal2', parseInt(this.value) || 0); calculateRow(${row.id})"
                               class="field-money" placeholder="30000">
                    </td>
                    <!-- 15. 그레이드 -->
                    <td class="px-2 py-2">
                        <input type="number" value="${row.grade_amount}" 
                               onchange="updateRowData(${row.id}, 'grade_amount', parseInt(this.value) || 0); calculateRow(${row.id})"
                               class="field-amount" placeholder="10000">
                    </td>
                    <!-- 16. 부가추가 -->
                    <td class="px-2 py-2">
                        <input type="number" value="${row.additional_amount}" 
                               onchange="updateRowData(${row.id}, 'additional_amount', parseInt(this.value) || 0); calculateRow(${row.id})"
                               class="field-amount" placeholder="5000">
                    </td>
                    <!-- 17. 서류상현금개통 -->
                    <td class="px-2 py-2">
                        <input type="number" value="${row.cash_activation}" 
                               onchange="updateRowData(${row.id}, 'cash_activation', parseInt(this.value) || 0); calculateRow(${row.id})"
                               class="field-amount" placeholder="0">
                    </td>
                    <!-- 18. 유심비(+) -->
                    <td class="px-2 py-2">
                        <input type="number" value="${row.usim_fee}" 
                               onchange="updateRowData(${row.id}, 'usim_fee', parseInt(this.value) || 0); calculateRow(${row.id})"
                               class="field-policy plus-field" placeholder="0">
                    </td>
                    <!-- 19. 신규,번이(-800) -->
                    <td class="px-2 py-2">
                        <input type="number" value="${row.new_mnp_discount}" 
                               onchange="updateRowData(${row.id}, 'new_mnp_discount', parseInt(this.value) || 0); calculateRow(${row.id})"
                               class="field-policy minus-field" placeholder="-800">
                    </td>
                    <!-- 20. 차감(-) -->
                    <td class="px-2 py-2">
                        <input type="number" value="${row.deduction}" 
                               onchange="updateRowData(${row.id}, 'deduction', parseInt(this.value) || 0); calculateRow(${row.id})"
                               class="field-policy minus-field" placeholder="0">
                    </td>
                    <!-- 21. 리베총계 (계산) -->
                    <td class="px-2 py-2 total-field">
                        <span class="field-calculated text-yellow-800" id="rebate-${row.id}">${(row.rebate_total || 0).toLocaleString()}원</span>
                    </td>
                    <!-- 22. 정산금 (계산) -->
                    <td class="px-2 py-2 total-field">
                        <span class="field-calculated text-yellow-800" id="settlement-${row.id}">${(row.settlement_amount || 0).toLocaleString()}원</span>
                    </td>
                    <!-- 23. 부/소세 (계산) -->
                    <td class="px-2 py-2 bg-red-50">
                        <span class="field-calculated text-red-800" id="tax-${row.id}">${(row.tax || 0).toLocaleString()}원</span>
                    </td>
                    <!-- 24. 현금받음(+) -->
                    <td class="px-2 py-2">
                        <input type="number" value="${row.cash_received}" 
                               onchange="updateRowData(${row.id}, 'cash_received', parseInt(this.value) || 0); calculateRow(${row.id})"
                               class="field-money plus-field" placeholder="0">
                    </td>
                    <!-- 25. 페이백(-) -->
                    <td class="px-2 py-2">
                        <input type="number" value="${row.payback}" 
                               onchange="updateRowData(${row.id}, 'payback', parseInt(this.value) || 0); calculateRow(${row.id})"
                               class="field-money minus-field" placeholder="0">
                    </td>
                    <!-- 26. 세전마진 (계산) -->
                    <td class="px-2 py-2 margin-field">
                        <span class="field-calculated text-green-800" id="margin-before-${row.id}">${(row.margin_before_tax || 0).toLocaleString()}원</span>
                    </td>
                    <!-- 27. 세후마진 (계산) -->
                    <td class="px-2 py-2 margin-field">
                        <span class="field-calculated text-green-800" id="margin-after-${row.id}">${(row.margin_after_tax || 0).toLocaleString()}원</span>
                    </td>
                    <!-- 28. 액션 -->
                    <td class="px-2 py-2">
                        <button onclick="deleteRow(${row.id})" class="px-2 py-1 bg-red-500 text-white rounded text-xs hover:bg-red-600">
                            🗑️ 삭제
                        </button>
                    </td>
                </tr>
            `).join('');
            
            updateStatistics();
        }
        
        // DB 필드명과 1:1 매핑된 행 데이터 업데이트
        function updateRowData(id, field, value) {
            const row = salesData.find(r => r.id === id);
            if (row) {
                row[field] = value;
                // Row data updated
            }
        }
        
        // 실시간 계산 로직 (PM 요구사항 반영)
        function calculateRow(id) {
            const row = salesData.find(r => r.id === id);
            if (!row) return;
            
            // SalesCalculator.php와 동일한 공식 사용
            // T = K + L + M + N + O (리베총계)
            const rebateTotal = (row.base_price || 0) + (row.verbal1 || 0) + (row.verbal2 || 0) +
                               (row.grade_amount || 0) + (row.additional_amount || 0);

            // U = T - P + Q + R + S (정산금)
            const settlementAmount = rebateTotal - (row.cash_activation || 0) + (row.usim_fee || 0) +
                                   (row.new_mnp_discount || 0) + (row.deduction || 0);

            row.rebate_total = rebateTotal;
            row.settlement_amount = settlementAmount;
            
            // V = U × 0.10 (세금)
            row.tax = Math.round(settlementAmount * 0.1);

            // Y = U - V + W + X (세전마진)
            row.margin_before_tax = settlementAmount - row.tax + (row.cash_received || 0) + (row.payback || 0);

            // Z = V + Y (세후마진)
            row.margin_after_tax = row.tax + row.margin_before_tax;
            
            // UI 업데이트
            document.getElementById(`rebate-${id}`).textContent = rebateTotal.toLocaleString() + '원';
            document.getElementById(`settlement-${id}`).textContent = settlementAmount.toLocaleString() + '원';
            document.getElementById(`tax-${id}`).textContent = row.tax.toLocaleString() + '원';
            document.getElementById(`margin-before-${id}`).textContent = row.margin_before_tax.toLocaleString() + '원';
            document.getElementById(`margin-after-${id}`).textContent = row.margin_after_tax.toLocaleString() + '원';
            
            updateStatistics();
        }
        
        // 통계 업데이트
        function updateStatistics() {
            const totalCount = salesData.length;
            const totalSettlement = salesData.reduce((sum, row) => sum + (row.settlement_amount || 0), 0);
            const totalMargin = salesData.reduce((sum, row) => sum + (row.margin_after_tax || 0), 0);
            const avgMarginRate = totalSettlement > 0 ? ((totalMargin / totalSettlement) * 100).toFixed(1) : 0;
            
            document.getElementById('total-count').textContent = totalCount;
            document.getElementById('total-settlement').textContent = '₩' + totalSettlement.toLocaleString();
            document.getElementById('total-margin').textContent = '₩' + totalMargin.toLocaleString();
            document.getElementById('average-margin').textContent = avgMarginRate + '%';
        }
        
        // 새 행 추가
        function addNewRow() {
            const newRow = createNewRow();
            salesData.push(newRow);
            renderTableRows();
            showStatus('새 행이 추가되었습니다!', 'success');
        }
        
        // 개별 행 삭제
        async function deleteRow(id) {
            if (confirm('이 행을 삭제하시겠습니까?')) {
                const row = salesData.find(r => r.id === id);

                if (!row) {
                    showStatus('삭제할 행을 찾을 수 없습니다.', 'error');
                    return;
                }

                // DB에 저장된 데이터인지 확인
                if (row.isPersisted) {
                    try {
                        // DB에서 삭제
                        const response = await fetch('/api/sales/bulk-delete', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': window.csrfToken,
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ sale_ids: [id] })
                        });

                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}`);
                        }

                        const result = await response.json();
                        if (result.success) {
                            salesData = salesData.filter(row => row.id !== id);
                            renderTableRows();
                            showStatus('행이 삭제되었습니다.', 'success');
                        } else {
                            throw new Error(result.message || '삭제 실패');
                        }
                    } catch (error) {
                        // 삭제 오류 발생
                        showStatus('삭제 중 오류가 발생했습니다.', 'error');
                    }
                } else {
                    // 아직 저장되지 않은 행은 클라이언트에서만 제거
                    salesData = salesData.filter(row => row.id !== id);
                    renderTableRows();
                    showStatus('임시 행이 삭제되었습니다.', 'success');
                }
            }
        }

        // PM 요구사항: 일괄 삭제 기능
        async function bulkDelete() {
            const checkedBoxes = document.querySelectorAll('.row-select:checked');

            if (checkedBoxes.length === 0) {
                showStatus('삭제할 행을 선택해주세요.', 'warning');
                return;
            }

            if (confirm(`선택한 ${checkedBoxes.length}개 행을 삭제하시겠습니까?`)) {
                const idsToDelete = Array.from(checkedBoxes).map(cb => parseInt(cb.dataset.id));

                // isPersisted 플래그를 사용해 DB에 저장된 ID와 미저장 ID 분리
                const savedIds = [];
                const unsavedIds = [];

                idsToDelete.forEach(id => {
                    const row = salesData.find(r => r.id === id);
                    if (row && row.isPersisted) {
                        savedIds.push(id);
                    } else {
                        unsavedIds.push(id);
                    }
                });

                try {
                    // DB에 저장된 데이터가 있으면 백엔드 호출
                    if (savedIds.length > 0) {
                        const response = await fetch('/api/sales/bulk-delete', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': window.csrfToken,
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ sale_ids: savedIds })
                        });

                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}`);
                        }

                        const result = await response.json();
                        if (!result.success) {
                            throw new Error(result.message || '삭제 실패');
                        }
                    }

                    // 모든 선택된 행 제거
                    salesData = salesData.filter(row => !idsToDelete.includes(row.id));
                    renderTableRows();
                    showStatus(`${checkedBoxes.length}개 행이 삭제되었습니다.`, 'success');
                } catch (error) {
                    // 일괄 삭제 오류 발생
                    showStatus('삭제 중 오류가 발생했습니다.', 'error');
                }
            }
        }
        
        // 데이터 무결성 검증 함수
        function validateSaleData(row) {
            const errors = [];

            // 필수 필드 검증
            if (!row.sale_date) {
                errors.push("판매일자 필수");
            }

            if (!row.carrier || !['SK', 'KT', 'LG', 'MVNO'].includes(row.carrier)) {
                errors.push("유효한 통신사 필수 (SK/KT/LG/MVNO)");
            }

            if (!row.activation_type || !['신규', '기변', 'MNP'].includes(row.activation_type)) {
                errors.push("유효한 개통유형 필수 (신규/기변/MNP)");
            }

            if (!row.model_name || !row.model_name.trim()) {
                errors.push("모델명 필수");
            }

            // 숫자 필드 유효성 검증
            const numericFields = [
                'base_price', 'verbal1', 'verbal2', 'grade_amount',
                'additional_amount', 'cash_activation', 'usim_fee',
                'new_mnp_discount', 'deduction', 'cash_received', 'payback'
            ];

            numericFields.forEach(field => {
                const value = row[field];
                if (value !== undefined && value !== null && value !== '' && isNaN(parseFloat(value))) {
                    errors.push(`${field}는 숫자여야 합니다`);
                }
            });

            // 음수 값 방지 (특정 필드)
            if (row.base_price < 0) {
                errors.push("액면가는 0 이상이어야 합니다");
            }

            if (row.usim_fee < 0) {
                errors.push("유심비는 0 이상이어야 합니다");
            }

            // 날짜 형식 검증
            if (row.sale_date) {
                const datePattern = /^\d{4}-\d{2}-\d{2}$/;
                if (!datePattern.test(row.sale_date)) {
                    errors.push("날짜 형식이 올바르지 않습니다 (YYYY-MM-DD)");
                }
            }

            if (row.customer_birth_date && row.customer_birth_date.trim()) {
                const datePattern = /^\d{4}-\d{2}-\d{2}$/;
                if (!datePattern.test(row.customer_birth_date)) {
                    errors.push("생년월일 형식이 올바르지 않습니다 (YYYY-MM-DD)");
                }
            }

            // 전화번호 형식 검증 (선택사항)
            if (row.phone_number && row.phone_number.trim()) {
                const phonePattern = /^[\d-]+$/;
                if (!phonePattern.test(row.phone_number)) {
                    errors.push("전화번호는 숫자와 하이픈만 포함해야 합니다");
                }
            }

            return errors.length > 0 ? errors : null;
        }

        // PM 요구사항: 완전한 27개 필드 DB 저장
        function saveAllData() {
            if (salesData.length === 0) {
                showStatus('저장할 데이터가 없습니다.', 'warning');
                return;
            }

            // 이미 저장된 데이터는 제외하고, 유효한 신규 데이터만 필터링
            const unsavedData = salesData.filter(row => !row.isPersisted);

            if (unsavedData.length === 0) {
                // 이미 모든 데이터가 저장되었는지 확인
                const persistedCount = salesData.filter(row => row.isPersisted).length;
                if (persistedCount > 0) {
                    showStatus(`이미 ${persistedCount}개의 데이터가 저장되어 있습니다. 새로운 데이터를 추가해주세요.`, 'info');
                } else {
                    showStatus('저장할 데이터가 없습니다.', 'warning');
                }
                return;
            }

            // 데이터 유효성 검증
            const validData = [];
            const invalidRows = [];

            unsavedData.forEach((row, index) => {
                const validationErrors = validateSaleData(row);
                if (validationErrors) {
                    invalidRows.push({
                        rowIndex: index + 1,
                        errors: validationErrors
                    });
                } else {
                    validData.push(row);
                }
            });

            // 유효성 검증 실패한 행이 있는 경우
            if (invalidRows.length > 0) {
                let errorMessage = '다음 행에 오류가 있습니다:\n';
                invalidRows.forEach(item => {
                    errorMessage += `행 ${item.rowIndex}: ${item.errors.join(', ')}\n`;
                });
                showStatus('데이터 검증 실패. 오류를 수정해주세요.', 'error');
                alert(errorMessage);
                return;
            }

            if (validData.length === 0) {
                showStatus('저장할 유효한 데이터가 없습니다.', 'warning');
                return;
            }
            
            showStatus('저장 중...', 'info');
            
            // PM 요구사항: 27개 필드 완전 매핑으로 DB 저장
            // 요청 데이터 준비 - 계산된 필드는 제외 (백엔드에서 재계산)
            const requestBody = {
                sales: validData.map(row => ({
                    // PM 요구사항: DB 스키마와 1:1 매핑 (계산 필드 제외)
                    sale_date: row.sale_date,
                    salesperson: row.salesperson,
                    dealer_name: row.dealer_name || null,
                    carrier: row.carrier,
                    activation_type: row.activation_type,
                    model_name: row.model_name,
                    serial_number: row.serial_number,
                    phone_number: row.phone_number,
                    customer_name: row.customer_name,
                    customer_birth_date: row.customer_birth_date,
                    base_price: row.base_price,
                    verbal1: row.verbal1,
                    verbal2: row.verbal2,
                    grade_amount: row.grade_amount,
                    additional_amount: row.additional_amount,
                    cash_activation: row.cash_activation,
                    usim_fee: row.usim_fee,
                    new_mnp_discount: row.new_mnp_discount,
                    deduction: row.deduction,
                    cash_received: row.cash_received,
                    payback: row.payback,
                    memo: row.memo || ''
                    // 계산된 필드 제거: rebate_total, settlement_amount, tax, margin_before_tax, margin_after_tax
                }))
            };

            // 디버깅: 요청 데이터 확인
            // Sending bulk save request

            fetch('/api/sales/bulk-save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.csrfToken,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify(requestBody)
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showStatus('✅ ' + data.message, 'success');
                    // Data saved successfully

                    // 저장 후 화면 초기화하고 저장된 데이터만 다시 로드
                    salesData = []; // 기존 데이터 모두 제거
                    renderTableRows(); // 빈 테이블 렌더링

                    // 저장된 데이터 다시 불러오기
                    setTimeout(() => {
                        loadExistingSalesData(selectedDateFilter);
                    }, 500);
                } else {
                    showStatus('❌ 저장 실패: ' + (data.message || '알 수 없는 오류'), 'error');
                }
            })
            .catch(async error => {
                // 저장 오류 발생

                // 에러 응답이 JSON인 경우 파싱
                if (error instanceof Response) {
                    try {
                        const errorData = await error.json();
                        // 에러 상세 정보
                        if (errorData.error) {
                            // 에러 메시지 및 파일/라인 정보
                            showStatus('❌ 저장 실패: ' + errorData.error, 'error');
                        } else {
                            showStatus('❌ 저장 중 오류 발생: ' + error.message, 'error');
                        }
                    } catch (e) {
                        showStatus('❌ 저장 중 오류 발생: ' + error.message, 'error');
                    }
                } else {
                    showStatus('❌ 저장 중 오류 발생: ' + error.message, 'error');
                }
            });
        }
        
        // 상태 메시지 표시
        function showStatus(message, type = 'info') {
            const indicator = document.getElementById('status-indicator');
            indicator.textContent = message;
            indicator.className = `px-3 py-2 rounded text-xs ${
                type === 'success' ? 'bg-green-100 text-green-800' :
                type === 'error' ? 'bg-red-100 text-red-800' :
                type === 'warning' ? 'bg-yellow-100 text-yellow-800' :
                'bg-gray-100 text-gray-600'
            }`;
            
            // 3초 후 기본 상태로 복원
            setTimeout(() => {
                indicator.textContent = '시스템 준비 완료';
                indicator.className = 'px-3 py-2 bg-gray-100 text-gray-600 rounded text-xs';
            }, 3000);
        }
        
        // 이벤트 리스너 등록
        // 전역 대리점 목록 저장
        let dealersList = [];

        // 대리점 옵션 HTML 생성 함수
        function generateDealerOptions(selectedValue = '') {
            let options = '<option value="">선택</option>';

            dealersList.forEach(dealer => {
                const selected = selectedValue === dealer.name ? 'selected' : '';
                options += `<option value="${dealer.name}" ${selected}>${dealer.name}</option>`;
            });

            return options;
        }

        // 대리점 목록 로드 함수
        async function loadDealers() {
            try {
                const response = await fetch('/api/calculation/profiles');
                const data = await response.json();

                if (data.success && data.data) {
                    dealersList = data.data
                        .filter(dealer => dealer.status === 'active')
                        .map(dealer => ({
                            code: dealer.dealer_code,
                            name: dealer.dealer_name
                        }));

                    // Dealers loaded successfully
                    return dealersList;
                } else {
                    // 대리점 목록 로드 실패
                    // 폴백: 하드코딩된 목록 사용
                    dealersList = [
                        {code: 'SM', name: 'SM'},
                        {code: 'W', name: 'W'},
                        {code: 'KING', name: '더킹'},
                        {code: 'ENTER', name: '엔터'},
                        {code: 'UP', name: '유피'},
                        {code: 'CHOSI', name: '초시대'},
                        {code: 'TAESUNG', name: '태성'},
                        {code: 'PDM', name: '피디엠'},
                        {code: 'HANJU', name: '한주'},
                        {code: 'HAPPY', name: '해피'}
                    ];
                    return dealersList;
                }
            } catch (error) {
                // 대리점 로드 오류 발생
                // 에러 발생 시에도 기본 목록 반환
                dealersList = [
                    {code: 'SM', name: 'SM'},
                    {code: 'W', name: 'W'},
                    {code: 'KING', name: '더킹'},
                    {code: 'ENTER', name: '엔터'},
                    {code: 'UP', name: '유피'},
                    {code: 'CHOSI', name: '초시대'},
                    {code: 'TAESUNG', name: '태성'},
                    {code: 'PDM', name: '피디엠'},
                    {code: 'HANJU', name: '한주'},
                    {code: 'HAPPY', name: '해피'}
                ];
                return dealersList;
            }
        }

        // 기존 저장된 데이터 로드 함수 (날짜 필터 지원)
        async function loadExistingSalesData(dateFilter = null) {
            try {
                // Loading existing sales data

                // 현재 매장의 최근 개통표 데이터 조회
                const storeId = window.userData?.store_id;
                if (!storeId) {
                    // 매장 정보 없음 - 데이터 로드 건너뛰기
                    return;
                }

                // URL 파라미터 구성
                const params = new URLSearchParams();
                params.append('store_id', storeId);

                if (dateFilter) {
                    if (dateFilter.type === 'single') {
                        params.append('sale_date', dateFilter.date);
                    } else if (dateFilter.type === 'range') {
                        params.append('start_date', dateFilter.startDate);
                        params.append('end_date', dateFilter.endDate);
                    } else if (dateFilter.type === 'days') {
                        params.append('days', dateFilter.days);
                    }
                } else {
                    // 기본값: 오늘 날짜
                    const today = new Date();
                    const year = today.getFullYear();
                    const month = String(today.getMonth() + 1).padStart(2, '0');
                    const day = String(today.getDate()).padStart(2, '0');
                    params.append('sale_date', `${year}-${month}-${day}`);
                }

                const apiUrl = `/api/sales?${params.toString()}`;
                // Fetching data from API

                const response = await fetch(apiUrl, {
                    headers: {
                        'X-CSRF-TOKEN': window.csrfToken,
                        'Content-Type': 'application/json',
                        'Accept': 'application/json'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const data = await response.json();
                // API response received

                // Laravel 페이지네이션 응답 처리
                const salesList = data.data || data;

                if (salesList && Array.isArray(salesList) && salesList.length > 0) {
                    // Existing sales found

                    // 기존 데이터를 그리드에 로드
                    salesData = salesList.map((sale, index) => ({
                        id: sale.id || (Date.now() + index),
                        isPersisted: true, // DB에서 불러온 데이터
                        salesperson: sale.salesperson || '',
                        dealer_name: sale.dealer_name || '',
                        carrier: sale.carrier || 'SK',
                        activation_type: sale.activation_type || '신규',
                        model_name: sale.model_name || '',
                        sale_date: sale.sale_date ? sale.sale_date.split('T')[0] : (() => {
                            const today = new Date();
                            const year = today.getFullYear();
                            const month = String(today.getMonth() + 1).padStart(2, '0');
                            const day = String(today.getDate()).padStart(2, '0');
                            return `${year}-${month}-${day}`;
                        })(),
                        serial_number: sale.serial_number || '',
                        phone_number: sale.phone_number || '',
                        customer_name: sale.customer_name || '',
                        customer_birth_date: sale.customer_birth_date ? sale.customer_birth_date.split('T')[0] : '',
                        base_price: parseFloat(sale.price_setting || 0),
                        verbal1: parseFloat(sale.verbal1 || 0),
                        verbal2: parseFloat(sale.verbal2 || 0),
                        grade_amount: parseFloat(sale.grade_amount || 0),
                        additional_amount: parseFloat(sale.addon_amount || 0),
                        cash_activation: parseFloat(sale.paper_cash || 0),
                        usim_fee: parseFloat(sale.usim_fee || 0),
                        new_mnp_discount: parseFloat(sale.new_mnp_disc || 0),
                        deduction: parseFloat(sale.deduction || 0),
                        rebate_total: parseFloat(sale.rebate_total || 0),
                        settlement_amount: parseFloat(sale.settlement_amount || 0),
                        tax: parseFloat(sale.tax || 0),
                        margin_before_tax: parseFloat(sale.margin_before_tax || 0),
                        cash_received: parseFloat(sale.cash_in || 0),
                        payback: parseFloat(sale.payback || 0),
                        margin_after_tax: parseFloat(sale.margin_after_tax || 0),
                        memo: sale.memo || ''
                    }));

                    // 그리드 렌더링
                    renderTableRows();
                    // Sales data loaded
                    showStatus(`📊 기존 개통표 ${salesData.length}건을 불러왔습니다.`, 'info');
                } else {
                    // 기존 데이터 클리어
                    salesData = [];
                    // 테이블 다시 렌더링 (빈 상태)
                    renderTableRows();
                    showStatus('📊 해당 날짜에 데이터가 없습니다.', 'info');
                    // 통계 업데이트
                    updateStatistics();
                }
            } catch (error) {
                // 에러 발생 시에도 데이터 클리어
                salesData = [];
                renderTableRows();
                showStatus('⚠️ 데이터 로드 실패. 빈 테이블로 시작합니다.', 'warning');
                // 통계 업데이트
                updateStatistics();
            }
        }

        // 날짜 필터 변수
        let selectedDateFilter = null;

        // 날짜 선택 함수들
        function selectToday() {
            // 로컬 시간대 기준으로 오늘 날짜 가져오기 (UTC 변환 방지)
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const day = String(today.getDate()).padStart(2, '0');
            const todayStr = `${year}-${month}-${day}`;

            document.getElementById('sale-date-filter').value = todayStr;
            selectedDateFilter = { type: 'single', date: todayStr };
            updateDateStatus(`${todayStr} 데이터 표시중`);
            loadExistingSalesData(selectedDateFilter);
        }

        function selectYesterday() {
            const yesterday = new Date();
            yesterday.setDate(yesterday.getDate() - 1);
            const year = yesterday.getFullYear();
            const month = String(yesterday.getMonth() + 1).padStart(2, '0');
            const day = String(yesterday.getDate()).padStart(2, '0');
            const dateStr = `${year}-${month}-${day}`;

            document.getElementById('sale-date-filter').value = dateStr;
            selectedDateFilter = { type: 'single', date: dateStr };
            updateDateStatus('어제 데이터 표시중');
            loadExistingSalesData(selectedDateFilter);
        }

        function selectWeek() {
            const today = new Date();
            const dayOfWeek = today.getDay();
            const startOfWeek = new Date(today);
            startOfWeek.setDate(today.getDate() - dayOfWeek);
            const endOfWeek = new Date(today);
            endOfWeek.setDate(today.getDate() + (6 - dayOfWeek));

            const startYear = startOfWeek.getFullYear();
            const startMonth = String(startOfWeek.getMonth() + 1).padStart(2, '0');
            const startDay = String(startOfWeek.getDate()).padStart(2, '0');
            const startDateStr = `${startYear}-${startMonth}-${startDay}`;

            const endYear = endOfWeek.getFullYear();
            const endMonth = String(endOfWeek.getMonth() + 1).padStart(2, '0');
            const endDay = String(endOfWeek.getDate()).padStart(2, '0');
            const endDateStr = `${endYear}-${endMonth}-${endDay}`;

            selectedDateFilter = {
                type: 'range',
                startDate: startDateStr,
                endDate: endDateStr
            };
            document.getElementById('sale-date-filter').value = '';
            updateDateStatus('이번 주 데이터 표시중');
            loadExistingSalesData(selectedDateFilter);
        }

        function selectMonth() {
            const today = new Date();
            const startOfMonth = new Date(today.getFullYear(), today.getMonth(), 1);
            const endOfMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);

            const startYear = startOfMonth.getFullYear();
            const startMonth = String(startOfMonth.getMonth() + 1).padStart(2, '0');
            const startDay = String(startOfMonth.getDate()).padStart(2, '0');
            const startDateStr = `${startYear}-${startMonth}-${startDay}`;

            const endYear = endOfMonth.getFullYear();
            const endMonth = String(endOfMonth.getMonth() + 1).padStart(2, '0');
            const endDay = String(endOfMonth.getDate()).padStart(2, '0');
            const endDateStr = `${endYear}-${endMonth}-${endDay}`;

            selectedDateFilter = {
                type: 'range',
                startDate: startDateStr,
                endDate: endDateStr
            };
            document.getElementById('sale-date-filter').value = '';
            updateDateStatus('이번 달 데이터 표시중');
            loadExistingSalesData(selectedDateFilter);
        }

        function clearDateFilter() {
            selectedDateFilter = { type: 'days', days: 7 };
            document.getElementById('sale-date-filter').value = '';
            updateDateStatus('최근 7일 데이터 표시중');
            loadExistingSalesData(selectedDateFilter);
        }

        function updateDateStatus(message) {
            const statusEl = document.getElementById('dateStatus');
            if (statusEl) {
                statusEl.textContent = message;
            }
        }

        document.addEventListener('DOMContentLoaded', async function() {
            // 🔥 대리점 목록 먼저 로드
            await loadDealers();

            // 대리점 목록 로드 확인

            // 날짜 필터 초기화 (오늘 날짜)
            selectToday();

            // 날짜 선택 이벤트 리스너
            const dateInput = document.getElementById('sale-date-filter');
            if (dateInput) {
                dateInput.addEventListener('change', function() {
                    if (this.value) {
                        selectedDateFilter = { type: 'single', date: this.value };
                        updateDateStatus(`${this.value} 데이터 표시중`);
                        loadExistingSalesData(selectedDateFilter);
                    }
                });
            }

            // 데이터가 없을 때만 기본 행 추가
            if (salesData.length === 0) {
                addNewRow();
            }

            // 버튼 이벤트 - Excel 스타일 UX
            document.getElementById('add-row-btn').addEventListener('click', addNewRow);
            document.getElementById('save-btn').addEventListener('click', saveAllData);
            document.getElementById('bulk-delete-btn').addEventListener('click', bulkDelete);
            document.getElementById('calculate-all-btn').addEventListener('click', () => {
                salesData.forEach(row => calculateRow(row.id));
                showStatus('전체 재계산 완료', 'success');
            });
            
            // PM 요구사항 27컬럼 완전한 개통표 시스템 초기화 완료
        });
    </script>
</body>
</html>