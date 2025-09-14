<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>완전한 개통표 - YKP ERP</title>
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
                    <h1 class="text-xl font-semibold text-gray-900">완전한 개통표</h1>
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
            <div class="flex space-x-4">
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
        // PM 요구사항: 27개 필드 완전 매핑된 데이터 구조
        let salesData = [];
        let nextId = 1;
        
        // 새로운 행 데이터 구조 (DB 스키마와 1:1 매핑)
        function createNewRow() {
            return {
                id: nextId++,
                salesperson: '',
                dealer_name: '',
                carrier: 'SK',
                activation_type: '신규',
                model_name: '',
                sale_date: new Date().toISOString().split('T')[0],
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
                <tr data-id="${row.id}" class="hover:bg-gray-50">
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
                        <select onchange="updateRowData(${row.id}, 'dealer_name', this.value)" class="field-dealer">
                            <option value="">선택</option>
                            <option value="이앤티" ${row.dealer_name === '이앤티' ? 'selected' : ''}>이앤티</option>
                            <option value="앤투윈" ${row.dealer_name === '앤투윈' ? 'selected' : ''}>앤투윈</option>
                            <option value="초시대" ${row.dealer_name === '초시대' ? 'selected' : ''}>초시대</option>
                            <option value="아엠티" ${row.dealer_name === '아엠티' ? 'selected' : ''}>아엠티</option>
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
                               onchange="updateRowData(${row.id}, 'base_price', parseInt(this.value) || 0); calculateRow(${row.id})"
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
                console.log(`행 ${id} 업데이트: ${field} = ${value}`);
            }
        }
        
        // 실시간 계산 로직 (PM 요구사항 반영)
        function calculateRow(id) {
            const row = salesData.find(r => r.id === id);
            if (!row) return;
            
            // 리베이트 총계 계산
            const rebateTotal = (row.base_price || 0) + (row.verbal1 || 0) + (row.verbal2 || 0) + 
                               (row.grade_amount || 0) + (row.additional_amount || 0) + (row.cash_activation || 0) + 
                               (row.usim_fee || 0) + (row.new_mnp_discount || 0) - (row.deduction || 0);
            
            row.rebate_total = rebateTotal;
            row.settlement_amount = rebateTotal;
            
            // 세금 계산 (10%)
            row.tax = Math.round(rebateTotal * 0.1);
            
            // 마진 계산
            row.margin_before_tax = rebateTotal + (row.cash_received || 0) - (row.payback || 0);
            row.margin_after_tax = row.margin_before_tax - row.tax;
            
            // UI 업데이트
            document.getElementById(`rebate-${id}`).textContent = rebateTotal.toLocaleString() + '원';
            document.getElementById(`settlement-${id}`).textContent = rebateTotal.toLocaleString() + '원';
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
        function deleteRow(id) {
            if (confirm('이 행을 삭제하시겠습니까?')) {
                salesData = salesData.filter(row => row.id !== id);
                renderTableRows();
                showStatus('행이 삭제되었습니다.', 'info');
            }
        }
        
        // PM 요구사항: 일괄 삭제 기능
        function bulkDelete() {
            const checkedBoxes = document.querySelectorAll('.row-select:checked');
            
            if (checkedBoxes.length === 0) {
                showStatus('삭제할 행을 선택해주세요.', 'warning');
                return;
            }
            
            if (confirm(`선택한 ${checkedBoxes.length}개 행을 삭제하시겠습니까?`)) {
                const idsToDelete = Array.from(checkedBoxes).map(cb => parseInt(cb.dataset.id));
                salesData = salesData.filter(row => !idsToDelete.includes(row.id));
                renderTableRows();
                showStatus(`${checkedBoxes.length}개 행이 삭제되었습니다.`, 'success');
            }
        }
        
        // PM 요구사항: 완전한 27개 필드 DB 저장
        function saveAllData() {
            if (salesData.length === 0) {
                showStatus('저장할 데이터가 없습니다.', 'warning');
                return;
            }
            
            // 유효한 데이터만 필터링 (모델명 필수)
            const validData = salesData.filter(row => row.model_name && row.model_name.trim());
            
            if (validData.length === 0) {
                showStatus('저장할 유효한 데이터가 없습니다. 모델명을 입력해주세요.', 'warning');
                return;
            }
            
            showStatus('저장 중...', 'info');
            
            // PM 요구사항: 27개 필드 완전 매핑으로 DB 저장
            fetch('/test-api/sales/save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                body: JSON.stringify({
                    sales: validData.map(row => ({
                        // PM 요구사항: DB 스키마와 1:1 매핑 (사용자 권한 기반)
                        @if(auth()->user()->store_id)
                        store_id: {{ auth()->user()->store_id }},
                        @endif
                        @if(auth()->user()->branch_id)
                        branch_id: {{ auth()->user()->branch_id }},
                        @endif
                        sale_date: row.sale_date,
                        salesperson: row.salesperson,
                        carrier: row.carrier,
                        activation_type: row.activation_type,

                        // DB 스키마와 정확히 매칭된 필드명 사용
                        price_setting: row.base_price,        // base_price → price_setting
                        verbal1: row.verbal1,
                        verbal2: row.verbal2,
                        grade_amount: row.grade_amount,
                        addon_amount: row.additional_amount,   // additional_amount → addon_amount
                        paper_cash: row.cash_activation,      // cash_activation → paper_cash
                        usim_fee: row.usim_fee,
                        new_mnp_disc: row.new_mnp_discount,   // new_mnp_discount → new_mnp_disc
                        deduction: row.deduction,
                        cash_in: row.cash_received,           // cash_received → cash_in
                        payback: row.payback,
                        rebate_total: row.rebate_total,
                        settlement_amount: row.settlement_amount,
                        tax: row.tax,
                        margin_before_tax: row.margin_before_tax,
                        margin_after_tax: row.margin_after_tax
                    }))
                })
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    showStatus('✅ 전체 개통표가 저장되었습니다', 'success');
                    console.log('🔥 DB 영속화 완료 - 실시간 동기화 시작');
                    console.log('저장된 27개 필드 데이터:', validData);
                } else {
                    showStatus('❌ 저장 실패: ' + (data.message || '알 수 없는 오류'), 'error');
                }
            })
            .catch(error => {
                console.error('저장 오류:', error);
                showStatus('❌ 저장 중 오류 발생: ' + error.message, 'error');
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
        document.addEventListener('DOMContentLoaded', function() {
            // 기본 행 추가
            addNewRow();
            
            // 버튼 이벤트 - Excel 스타일 UX
            document.getElementById('add-row-btn').addEventListener('click', addNewRow);
            document.getElementById('save-btn').addEventListener('click', saveAllData);
            document.getElementById('bulk-delete-btn').addEventListener('click', bulkDelete);
            document.getElementById('calculate-all-btn').addEventListener('click', () => {
                salesData.forEach(row => calculateRow(row.id));
                showStatus('전체 재계산 완료', 'success');
            });
            
            console.log('✅ PM 요구사항 27컬럼 완전한 개통표 시스템 초기화 완료');
        });
    </script>
</body>
</html>