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
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white p-4 rounded-lg shadow-lg">
                <div class="text-sm opacity-90">총 개통건수</div>
                <div class="text-2xl font-bold" id="total-count">0</div>
            </div>
            <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 text-white p-4 rounded-lg shadow-lg">
                <div class="text-sm opacity-90">총 정산금</div>
                <div class="text-2xl font-bold" id="total-settlement">₩0</div>
            </div>
            <div class="bg-gradient-to-br from-violet-500 to-violet-600 text-white p-4 rounded-lg shadow-lg">
                <div class="text-sm opacity-90">총 마진</div>
                <div class="text-2xl font-bold" id="total-margin">₩0</div>
            </div>
            <div class="bg-gradient-to-br from-amber-500 to-amber-600 text-white p-4 rounded-lg shadow-lg">
                <div class="text-sm opacity-90">평균 마진율</div>
                <div class="text-2xl font-bold" id="average-margin">0%</div>
            </div>
        </div>

        <!-- 컨트롤 패널 -->
        <div class="bg-white rounded-lg shadow mb-6 p-4">
            <div class="flex justify-between mb-3">
                <div class="flex space-x-4">
                    <button id="add-row-btn" class="px-4 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded hover:from-blue-600 hover:to-blue-700 transition-all shadow">
                        ➕ 새 개통 등록
                    </button>
                    <button id="calculate-all-btn" class="px-4 py-2 bg-gradient-to-r from-purple-500 to-purple-600 text-white rounded hover:from-purple-600 hover:to-purple-700 transition-all shadow">
                        🔄 전체 재계산
                    </button>
                </div>
                <div class="flex space-x-2">
                    @if(auth()->user()->role === 'headquarters')
                    <button onclick="openCarrierManagement()" class="px-4 py-2 bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded hover:from-indigo-600 hover:to-indigo-700 transition-all shadow">
                        📡 통신사 관리
                    </button>
                    @endif
                    @if(in_array(auth()->user()->role, ['headquarters', 'branch']))
                    <button onclick="openDealerManagement()" class="px-4 py-2 bg-gradient-to-r from-indigo-500 to-indigo-600 text-white rounded hover:from-indigo-600 hover:to-indigo-700 transition-all shadow">
                        🏢 대리점 관리
                    </button>
                    @endif
                    <button id="download-template-btn" class="px-4 py-2 bg-gradient-to-r from-slate-500 to-slate-600 text-white rounded hover:from-slate-600 hover:to-slate-700 transition-all shadow">
                        📄 엑셀 템플릿
                    </button>
                    <button id="upload-excel-btn" class="px-4 py-2 bg-gradient-to-r from-emerald-500 to-emerald-600 text-white rounded hover:from-emerald-600 hover:to-emerald-700 transition-all shadow">
                        📤 엑셀 업로드
                    </button>
                    <button id="download-excel-btn" class="px-4 py-2 bg-gradient-to-r from-teal-500 to-teal-600 text-white rounded hover:from-teal-600 hover:to-teal-700 transition-all shadow">
                        📥 엑셀 다운로드
                    </button>
                    <input type="file" id="excel-file-input" accept=".xlsx,.xls,.csv" style="display: none;">
                </div>
            </div>
                <button id="save-btn" class="px-4 py-2 bg-gradient-to-r from-green-500 to-green-600 text-white rounded hover:from-green-600 hover:to-green-700 transition-all shadow font-medium">
                    💾 전체 저장
                </button>
                <button id="bulk-delete-btn" class="px-4 py-2 bg-gradient-to-r from-red-500 to-red-600 text-white rounded hover:from-red-600 hover:to-red-700 transition-all shadow">
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
                <button onclick="selectToday()" class="px-3 py-1.5 bg-gradient-to-r from-blue-500 to-blue-600 text-white text-sm rounded hover:from-blue-600 hover:to-blue-700 transition-all shadow-sm">오늘</button>
                <button onclick="selectYesterday()" class="px-3 py-1.5 bg-gradient-to-r from-violet-500 to-violet-600 text-white text-sm rounded hover:from-violet-600 hover:to-violet-700 transition-all shadow-sm">어제</button>
                <button onclick="selectWeek()" class="px-3 py-1.5 bg-gradient-to-r from-cyan-500 to-cyan-600 text-white text-sm rounded hover:from-cyan-600 hover:to-cyan-700 transition-all shadow-sm">이번 주</button>
                <button onclick="selectMonth()" class="px-3 py-1.5 bg-gradient-to-r from-orange-500 to-orange-600 text-white text-sm rounded hover:from-orange-600 hover:to-orange-700 transition-all shadow-sm">이번 달</button>
                <button onclick="clearDateFilter()" class="px-3 py-1.5 bg-gradient-to-r from-gray-500 to-gray-600 text-white text-sm rounded hover:from-gray-600 hover:to-gray-700 transition-all shadow-sm">전체 보기</button>
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
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">휴대폰번호</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">고객명</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">생년월일</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">액면/셋팅가</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">구두1</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">구두2</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">그레이드</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">부가추가</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">서류상현금개통</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase plus-field">유심비</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase minus-field">신규/번이할인</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase minus-field">차감</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase total-field">리베총계</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase total-field">정산금</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase bg-red-50">부/소세</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase plus-field">현금받음</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase minus-field">페이백</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase margin-field">세전마진</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase margin-field">세후마진</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">메모</th>
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
                        <input type="text" value="${row.salesperson || ''}"
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
                        <select onchange="updateRowData(${row.id}, 'carrier', this.value)" class="field-carrier" id="carrier-select-${row.id}">
                            ${generateCarrierOptions(row.carrier)}
                        </select>
                    </td>
                    <!-- 5. 개통방식 -->
                    <td class="px-2 py-2">
                        <select onchange="updateRowData(${row.id}, 'activation_type', this.value)" class="field-activation">
                            <option value="신규" ${row.activation_type === '신규' ? 'selected' : ''}>신규</option>
                            <option value="번이" ${row.activation_type === '번이' ? 'selected' : ''}>번이</option>
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
                    <!-- 8. 휴대폰번호 -->
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
                    <!-- 18. 유심비 -->
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
                    <!-- 20. 차감 -->
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
                    <!-- 24. 현금받음 -->
                    <td class="px-2 py-2">
                        <input type="number" value="${row.cash_received}" 
                               onchange="updateRowData(${row.id}, 'cash_received', parseInt(this.value) || 0); calculateRow(${row.id})"
                               class="field-money plus-field" placeholder="0">
                    </td>
                    <!-- 25. 페이백 -->
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
                    <!-- 28. 메모 -->
                    <td class="px-2 py-2">
                        <input type="text" value="${row.memo || ''}"
                               onchange="updateRowData(${row.id}, 'memo', this.value)"
                               class="w-32 px-1 py-1 border rounded text-xs" placeholder="메모 입력">
                    </td>
                    <!-- 29. 액션 -->
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

                // 개통방식 변경 시 차감액 자동 설정
                if (field === 'activation_type') {
                    if (value === '신규' || value === '번이') {
                        row['new_mnp_discount'] = -800;
                    } else if (value === '기변') {
                        row['new_mnp_discount'] = 0;
                    }
                    // 차감액 필드 업데이트
                    const discountInput = document.querySelector(`#data-table-body tr:has(input[value="${row.id}"]) input[placeholder="-800"]`);
                    if (discountInput) {
                        discountInput.value = row['new_mnp_discount'];
                    }
                    calculateRow(id);
                }
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

            // 통신사 검증 - 동적 목록 사용
            const validCarriers = carriersList.length > 0
                ? carriersList.map(c => c.name)
                : ['SK', 'KT', 'LG', '알뜰']; // 폴백

            if (!row.carrier || !validCarriers.includes(row.carrier)) {
                errors.push(`유효한 통신사 필수 (${validCarriers.join('/')})`);
            }

            if (!row.activation_type || !['신규', '번이', '기변'].includes(row.activation_type)) {
                errors.push("유효한 개통유형 필수 (신규/번이/기변)");
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
                        // 로드 후 마진 계산 및 통계 업데이트
                        setTimeout(() => {
                            salesData.forEach(row => calculateRow(row.id));
                            updateStatistics();
                        }, 1000);
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
        // 전역 통신사 목록 저장
        let carriersList = [];

        // 대리점 옵션 HTML 생성 함수
        function generateDealerOptions(selectedValue = '') {
            let options = '<option value="">선택</option>';

            dealersList.forEach(dealer => {
                const selected = selectedValue === dealer.name ? 'selected' : '';
                options += `<option value="${dealer.name}" ${selected}>${dealer.name}</option>`;
            });

            return options;
        }

        // 통신사 옵션 HTML 생성 함수
        function generateCarrierOptions(selectedValue = '') {
            let options = '';

            // 통신사 목록이 비어있으면 기본값 사용
            if (carriersList.length === 0) {
                const defaultCarriers = [
                    { code: 'SK', name: 'SK' },
                    { code: 'KT', name: 'KT' },
                    { code: 'LG', name: 'LG' },
                    { code: 'MVNO', name: '알뜰' }
                ];
                defaultCarriers.forEach(carrier => {
                    const selected = selectedValue === carrier.name ? 'selected' : '';
                    options += `<option value="${carrier.name}" ${selected}>${carrier.name}</option>`;
                });
            } else {
                carriersList.forEach(carrier => {
                    const selected = selectedValue === carrier.name ? 'selected' : '';
                    options += `<option value="${carrier.name}" ${selected}>${carrier.name}</option>`;
                });
            }

            return options;
        }

        // 기존 행들의 대리점 드롭다운 업데이트
        function updateDealerDropdowns() {
            // 현재 테이블에 있는 모든 대리점 드롭다운 선택
            const dealerSelects = document.querySelectorAll('[id^="dealer-select-"]');
            dealerSelects.forEach(select => {
                const currentValue = select.value;
                select.innerHTML = generateDealerOptions(currentValue);
            });
        }

        // 대리점 목록 로드 함수
        async function loadDealers() {
            try {
                const response = await fetch('/api/dealers', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    }
                });
                const data = await response.json();

                if (data.success && data.data) {
                    dealersList = data.data
                        .filter(dealer => !dealer.status || dealer.status === 'active' || dealer.status === null)
                        .map(dealer => ({
                            code: dealer.dealer_code,
                            name: dealer.dealer_name
                        }));

                    console.log(`✅ 대리점 ${dealersList.length}개 로드 완료`);

                    // 기존 행들의 대리점 드롭다운 업데이트
                    updateDealerDropdowns();

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

        // 통신사 목록 로드 함수
        async function loadCarriers() {
            try {
                const response = await fetch('/api/carriers', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json'
                    }
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data.success) {
                        // 활성 통신사만 필터링하고 정렬 순서대로 정렬
                        carriersList = data.data
                            .filter(carrier => carrier.is_active)
                            .sort((a, b) => a.sort_order - b.sort_order)
                            .map(carrier => ({
                                code: carrier.code,
                                name: carrier.name
                            }));
                        console.log('✅ 통신사 목록 로드 완료:', carriersList.length, '개');

                        // 기존 행들의 통신사 드롭다운 업데이트
                        updateCarrierDropdowns();
                    }
                }
            } catch (error) {
                console.error('❌ 통신사 목록 로드 실패:', error);
                // 오류 시 기본값은 generateCarrierOptions에서 처리됨
            }
        }

        // 기존 행들의 통신사 드롭다운 업데이트
        function updateCarrierDropdowns() {
            // 현재 테이블에 있는 모든 통신사 드롭다운 선택
            const carrierSelects = document.querySelectorAll('[id^="carrier-select-"]');
            carrierSelects.forEach(select => {
                const currentValue = select.value;
                select.innerHTML = generateCarrierOptions(currentValue);
            });
        }

        // CSV 다운로드/업로드 핸들러 설정
        function setupCsvHandlers() {
            // 템플릿 다운로드
            const templateBtn = document.getElementById('download-template-btn');
            if (templateBtn) {
                templateBtn.addEventListener('click', function() {
                    window.location.href = '/api/sales-export/template';
                });
            }

            // CSV 다운로드
            const downloadBtn = document.getElementById('download-csv-btn');
            if (downloadBtn) {
                downloadBtn.addEventListener('click', function() {
                    const startDate = document.getElementById('date-start')?.value || '';
                    const endDate = document.getElementById('date-end')?.value || '';

                    let url = '/api/sales-export/csv';
                    if (startDate || endDate) {
                        const params = new URLSearchParams();
                        if (startDate) params.append('start_date', startDate);
                        if (endDate) params.append('end_date', endDate);
                        url += '?' + params.toString();
                    }

                    window.location.href = url;
                    console.log('CSV 다운로드 시작...');
                });
            }

            // CSV 업로드 버튼 클릭
            const uploadBtn = document.getElementById('upload-csv-btn');
            if (uploadBtn) {
                uploadBtn.addEventListener('click', function() {
                    document.getElementById('csv-file-input').click();
                });
            }

            // 파일 선택 시 업로드
            const fileInput = document.getElementById('csv-file-input');
            if (fileInput) {
                fileInput.addEventListener('change', async function(e) {
                    const file = e.target.files[0];
                    if (!file) return;

                    if (!file.name.endsWith('.csv')) {
                        alert('CSV 파일만 업로드 가능합니다.');
                        return;
                    }

                    const formData = new FormData();
                    formData.append('file', file);

                    try {
                        console.log('CSV 파일 업로드 중...');

                        const response = await fetch('/api/sales-export/import', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                            },
                            body: formData
                        });

                        const result = await response.json();

                        if (result.success) {
                            alert(result.message);
                            // 페이지 새로고침하여 데이터 반영
                            setTimeout(() => {
                                location.reload();
                            }, 1500);
                        } else {
                            alert('업로드 실패: ' + result.message);
                        }
                    } catch (error) {
                        console.error('Upload error:', error);
                        alert('업로드 중 오류가 발생했습니다.');
                    } finally {
                        // 파일 입력 초기화
                        e.target.value = '';
                    }
                });
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
                        phone_number: sale.phone_number || '',
                        customer_name: sale.customer_name || '',
                        customer_birth_date: sale.customer_birth_date ? sale.customer_birth_date.split('T')[0] : '',
                        base_price: parseFloat(sale.base_price || 0),
                        verbal1: parseFloat(sale.verbal1 || 0),
                        verbal2: parseFloat(sale.verbal2 || 0),
                        grade_amount: parseFloat(sale.grade_amount || 0),
                        additional_amount: parseFloat(sale.additional_amount || 0),
                        cash_activation: parseFloat(sale.cash_activation || 0),
                        usim_fee: parseFloat(sale.usim_fee || 0),
                        new_mnp_discount: parseFloat(sale.new_mnp_discount || 0),
                        deduction: parseFloat(sale.deduction || 0),
                        rebate_total: parseFloat(sale.rebate_total || 0),
                        settlement_amount: parseFloat(sale.settlement_amount || 0),
                        tax: parseFloat(sale.tax || 0),
                        margin_before_tax: parseFloat(sale.margin_before_tax || 0),
                        cash_received: parseFloat(sale.cash_received || 0),
                        payback: parseFloat(sale.payback || 0),
                        margin_after_tax: parseFloat(sale.margin_after_tax || 0),
                        memo: sale.memo || ''
                    }));

                    // 그리드 렌더링
                    renderTableRows();
                    // 로드된 데이터에 대해 마진 계산 및 통계 업데이트
                    salesData.forEach(row => calculateRow(row.id));
                    updateStatistics();
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
            // 전체보기 기본값을 이번달로 변경
            const today = new Date();
            const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);
            const lastDay = new Date(today.getFullYear(), today.getMonth() + 1, 0);

            const startDateStr = `${firstDay.getFullYear()}-${String(firstDay.getMonth() + 1).padStart(2, '0')}-${String(firstDay.getDate()).padStart(2, '0')}`;
            const endDateStr = `${lastDay.getFullYear()}-${String(lastDay.getMonth() + 1).padStart(2, '0')}-${String(lastDay.getDate()).padStart(2, '0')}`;

            selectedDateFilter = {
                type: 'range',
                startDate: startDateStr,
                endDate: endDateStr
            };
            document.getElementById('sale-date-filter').value = '';
            updateDateStatus('이번 달 데이터 표시중');
            loadExistingSalesData(selectedDateFilter);
        }

        function updateDateStatus(message) {
            const statusEl = document.getElementById('dateStatus');
            if (statusEl) {
                statusEl.textContent = message;
            }
        }

        // localStorage 이벤트 리스너 - 다른 탭에서 통신사가 변경되면 업데이트
        window.addEventListener('storage', function(e) {
            if (e.key === 'carriers_updated') {
                console.log('📡 다른 탭에서 통신사 목록이 변경됨');
                loadCarriers();
            }
            if (e.key === 'dealers_updated') {
                console.log('🏢 다른 탭에서 대리점 목록이 변경됨');
                loadDealers();
            }
        });

        document.addEventListener('DOMContentLoaded', async function() {
            // 🔥 대리점 목록 먼저 로드
            await loadDealers();
            await loadCarriers();

            // 대리점 및 통신사 목록 로드 확인

            // 날짜 필터 초기화 (이번 달로 변경)
            selectMonth();

            // 30초마다 통신사 및 대리점 목록 업데이트
            setInterval(() => {
                loadCarriers();
                loadDealers();
            }, 30000);

            // Excel 다운로드/업로드 핸들러는 이미 설정됨

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
                updateStatistics(); // 총 마진 및 평균 마진 업데이트
                showStatus('전체 재계산 완료', 'success');
            });
            
            // 엑셀 업로드/다운로드 핸들러
            document.getElementById('upload-excel-btn').addEventListener('click', () => {
                document.getElementById('excel-file-input').click();
            });

            // Excel 파일 업로드 처리
            document.getElementById('excel-file-input').addEventListener('change', async (e) => {
                const file = e.target.files[0];
                if (file) {
                    // 파일 읽기
                    const reader = new FileReader();
                    reader.onload = async function(event) {
                        try {
                            const text = event.target.result;
                            const rows = text.split('\n').filter(row => row.trim());

                            if (rows.length < 2) {
                                showStatus('데이터가 없습니다', 'warning');
                                return;
                            }

                            // 기존 엑셀 템플릿의 정확한 컬럼 순서
                            const expectedHeaders = [
                                '판매자', '대리점', '통신사', '개통방식', '모델명',
                                '개통일', '휴대폰번호', '고객명', '생년월일',
                                '액면/셋팅가', '구두1', '구두2', '그레이드', '부가추가',
                                '서류상현금개통', '유심비 (+표기)', '신규,번이    (-800표기)', '차감(-표기)',
                                '리베총계', '정산금', '부/소 세', '현금받음(+표기)', '페이백(-표기)',
                                '세전 / 마진', '세후 / 마진', '메모'
                            ];

                            // 헤더 파싱 - 다양한 형식 지원
                            const headers = rows[0].split(/[,\t]/).map(h => h.trim().replace(/^"|"$/g, ''));

                            // 헤더 매핑 함수 - 다양한 표기법 지원
                            const findHeaderIndex = (headerVariations) => {
                                for (const variation of headerVariations) {
                                    const idx = headers.findIndex(h =>
                                        h.includes(variation) ||
                                        h.replace(/\s+/g, '').includes(variation.replace(/\s+/g, ''))
                                    );
                                    if (idx !== -1) return idx;
                                }
                                return -1;
                            };

                            // 데이터 파싱 및 테이블에 추가
                            let addedCount = 0;
                            for (let i = 1; i < rows.length; i++) {
                                const cols = rows[i].split(/[,\t]/).map(c => c.trim().replace(/^"|"$/g, ''));
                                if (cols.length < 10) continue; // 최소 필수 컬럼 수

                                // 컬럼 인덱스 찾기 (순서대로, 다양한 표기법 지원)
                                const getColValue = (index, defaultValue = '') => {
                                    if (index >= 0 && index < cols.length) {
                                        const val = cols[index];
                                        // 빈 값이거나 공백만 있으면 defaultValue 반환
                                        if (!val || val.trim() === '') {
                                            return defaultValue;
                                        }
                                        return val;
                                    }
                                    return defaultValue;
                                };

                                // 날짜 형식 변환 함수
                                const formatDate = (dateStr) => {
                                    if (!dateStr || dateStr.trim() === '') return '';

                                    // 숫자만 있는 경우 (예: 2, 15, 230615)
                                    if (/^\d+$/.test(dateStr)) {
                                        const today = new Date();
                                        const year = today.getFullYear();
                                        const month = String(today.getMonth() + 1).padStart(2, '0');

                                        if (dateStr.length <= 2) {
                                            // 일자만 있는 경우
                                            return `${year}-${month}-${dateStr.padStart(2, '0')}`;
                                        } else if (dateStr.length === 6) {
                                            // YYMMDD 형식
                                            const y = '20' + dateStr.substring(0, 2);
                                            const m = dateStr.substring(2, 4);
                                            const d = dateStr.substring(4, 6);
                                            return `${y}-${m}-${d}`;
                                        }
                                    }

                                    // 이미 날짜 형식인 경우
                                    if (dateStr.includes('-') || dateStr.includes('/')) {
                                        return dateStr.replace(/\//g, '-');
                                    }

                                    return dateStr;
                                };

                                // 생년월일 형식 변환 함수
                                const formatBirthDate = (dateStr) => {
                                    if (!dateStr || dateStr.trim() === '') return '';

                                    // 숫자만 있는 경우 (예: 900101, 19900101)
                                    const cleanStr = dateStr.replace(/[^0-9]/g, '');

                                    if (cleanStr.length === 6) {
                                        // YYMMDD 형식
                                        const year = parseInt(cleanStr.substring(0, 2));
                                        const fullYear = year > 50 ? '19' + cleanStr.substring(0, 2) : '20' + cleanStr.substring(0, 2);
                                        return `${fullYear}-${cleanStr.substring(2, 4)}-${cleanStr.substring(4, 6)}`;
                                    } else if (cleanStr.length === 8) {
                                        // YYYYMMDD 형식
                                        return `${cleanStr.substring(0, 4)}-${cleanStr.substring(4, 6)}-${cleanStr.substring(6, 8)}`;
                                    }

                                    return dateStr;
                                };

                                // 숫자 파싱 함수 (빈 값 처리)
                                const parseNumber = (value, defaultValue = 0) => {
                                    if (!value || value === '' || value === null || value === undefined) {
                                        return defaultValue;
                                    }
                                    const num = parseFloat(String(value).replace(/,/g, ''));
                                    return isNaN(num) ? defaultValue : num;
                                };

                                // 순서대로 매핑 (0부터 시작)
                                const newRowData = {
                                    id: 'row-' + Date.now() + '-' + i,
                                    salesperson: getColValue(0, '{{ Auth::user()->name ?? '' }}'), // 판매자
                                    dealer_name: getColValue(1, ''), // 대리점 (dealer_code가 아닌 dealer_name으로 변경)
                                    carrier: getColValue(2, ''), // 통신사
                                    activation_type: getColValue(3, ''), // 개통방식
                                    model_name: getColValue(4, ''), // 모델명
                                    sale_date: formatDate(getColValue(5, '')), // 개통일
                                    phone_number: getColValue(6, ''), // 휴대폰번호
                                    customer_name: getColValue(7, ''), // 고객명
                                    customer_birth_date: formatBirthDate(getColValue(8, '')), // 생년월일

                                    // 금액 필드들 (순서대로)
                                    base_price: parseNumber(getColValue(9)), // 액면/셋팅가
                                    verbal1: parseNumber(getColValue(10)), // 구두1
                                    verbal2: parseNumber(getColValue(11)), // 구두2
                                    grade_amount: parseNumber(getColValue(12)), // 그레이드
                                    additional_amount: parseNumber(getColValue(13)), // 부가추가
                                    cash_activation: parseNumber(getColValue(14)), // 서류상현금개통
                                    usim_fee: parseNumber(getColValue(15)), // 유심비
                                    new_mnp_discount: parseNumber(getColValue(16)), // 신규/번이할인
                                    deduction: parseNumber(getColValue(17)), // 차감

                                    // 계산 필드들은 엑셀에 있으면 사용, 없으면 자동계산
                                    total_rebate: parseNumber(getColValue(18)), // 리베총계
                                    settlement_amount: parseNumber(getColValue(19)), // 정산금
                                    tax: parseNumber(getColValue(20)), // 부/소세
                                    cash_received: parseNumber(getColValue(21)), // 현금받음
                                    payback: parseNumber(getColValue(22)), // 페이백
                                    margin_before: parseNumber(getColValue(23)), // 세전마진
                                    margin_after: parseNumber(getColValue(24)), // 세후마진

                                    // 메모 필드
                                    memo: getColValue(25, ''), // 메모
                                    isPersisted: false,
                                    serial_number: '' // 일련번호는 사용하지 않음
                                };

                                // 계산 필드가 비어있거나 0인 경우에만 자동 계산
                                if (!newRowData.total_rebate || newRowData.total_rebate === 0) {
                                    // 자동 계산을 위한 값들
                                    const K = parseFloat(newRowData.base_price) || 0;
                                    const L = parseFloat(newRowData.verbal1) || 0;
                                    const M = parseFloat(newRowData.verbal2) || 0;
                                    const N = parseFloat(newRowData.grade_amount) || 0;
                                    const O = parseFloat(newRowData.additional_amount) || 0;
                                    const P = parseFloat(newRowData.cash_activation) || 0;
                                    const Q = parseFloat(newRowData.usim_fee) || 0;
                                    const R = parseFloat(newRowData.new_mnp_discount) || 0;
                                    const S = parseFloat(newRowData.deduction) || 0;
                                    const W = parseFloat(newRowData.cash_received) || 0;
                                    const X = parseFloat(newRowData.payback) || 0;

                                    // 계산 (SalesCalculator.php와 동일한 공식)
                                    const T = K + L + M + N + O; // 리베총계
                                    const U = T - P + Q + R + S; // 정산금
                                    const V = Math.round(U * 0.1); // 세금 (10%)
                                    const Y = U - V + W + X; // 세전마진
                                    const Z = V + Y; // 세후마진

                                    // 계산된 값 업데이트
                                    newRowData.total_rebate = T;
                                    newRowData.settlement_amount = U;
                                    newRowData.tax = V;
                                    newRowData.margin_before = Y;
                                    newRowData.margin_after = Z;
                                }

                                // salesData 배열에 추가
                                salesData.push(newRowData);
                                addedCount++;
                            }

                            // 테이블 다시 그리기
                            renderTableRows();

                            // 총 마진 및 평균 마진 계산 업데이트
                            updateStatistics();

                            showStatus(`${addedCount}개의 데이터를 테이블에 추가했습니다`, 'success');

                            // 파일 입력 초기화
                            e.target.value = '';
                        } catch (error) {
                            showStatus('Excel 파일 처리 중 오류 발생', 'error');
                            console.error('Excel parsing error:', error);
                        }
                    };

                    // UTF-8 인코딩으로 읽기
                    reader.readAsText(file, 'UTF-8');
                }
            });

            document.getElementById('download-excel-btn').addEventListener('click', async () => {
                try {
                    // 현재 표시된 데이터를 CSV로 변환
                    const csvData = convertToCSV(salesData);
                    downloadCSV(csvData, `개통표_${new Date().toISOString().split('T')[0]}.csv`);
                    showStatus('엑셀 다운로드 완료', 'success');
                } catch (error) {
                    showStatus('엑셀 다운로드 실패', 'error');
                    console.error('Excel download error:', error);
                }
            });

            document.getElementById('download-template-btn').addEventListener('click', () => {
                // Excel 템플릿 생성
                const templateData = [
                    ['판매자', '대리점', '통신사', '개통방식', '모델명', '개통일', '휴대폰번호', '고객명', '생년월일',
                     '액면/셋팅가', '구두1', '구두2', '그레이드', '부가추가', '서류상현금개통', '유심비',
                     '신규/번이할인', '차감', '리베총계', '정산금', '부/소세', '현금받음', '페이백',
                     '세전마진', '세후마진', '메모'],
                    ['홍길동', 'SM', 'SK', '신규', 'iPhone 15', (() => {
                        const today = new Date();
                        const year = today.getFullYear();
                        const month = String(today.getMonth() + 1).padStart(2, '0');
                        const day = String(today.getDate()).padStart(2, '0');
                        return `${year}-${month}-${day}`;
                    })(), '010-1234-5678', '김고객', '1990-01-01',
                     '100000', '50000', '30000', '20000', '10000', '30000', '8800',
                     '10000', '5000', '', '', '', '20000', '15000',
                     '', '', '']
                ];

                // CSV 문자열 생성
                let csvContent = '\uFEFF'; // UTF-8 BOM
                templateData.forEach(row => {
                    csvContent += row.map(cell => {
                        if (String(cell).includes(',')) {
                            return `"${cell}"`;
                        }
                        return cell;
                    }).join(',') + '\n';
                });

                // 파일 다운로드
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = `sales_template_${new Date().toISOString().split('T')[0]}.csv`;
                link.style.display = 'none';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                showStatus('Excel 템플릿을 다운로드했습니다', 'success');
            });

            // PM 요구사항 27컬럼 완전한 개통표 시스템 초기화 완료
        });

        // CSV 변환 함수
        function convertToCSV(data) {
            if (!data || data.length === 0) {
                return '';
            }

            // CSV 헤더
            const headers = [
                '날짜', '행번호', '대리점', '통신사', '개통방식', '시리얼넘버',
                '모델명', '용량', '전화번호', '고객명', '생년월일',
                '액면가', '구두1', '구두2', '그레이드', '부가추가',
                '서류상현금개통', '유심비', '신규번이할인', '차감',
                '리베총계', '정산금', '세금', '현금받음', '페이백',
                '세전마진', '세후마진'
            ];

            // CSV 데이터 행
            const rows = data.map(row => [
                row.sale_date || '',
                row.row_number || '',
                row.dealer_name || '',
                row.carrier || '',
                row.activation_type || '',
                row.serial_number || '',
                row.model_name || '',
                row.storage_capacity || '',
                row.phone_number || '',
                row.customer_name || '',
                row.customer_birth_date || '',
                row.base_price || 0,
                row.verbal1 || 0,
                row.verbal2 || 0,
                row.grade_amount || 0,
                row.additional_amount || 0,
                row.cash_activation || 0,
                row.usim_fee || 0,
                row.new_mnp_discount || 0,
                row.deduction || 0,
                row.rebate_total || 0,
                row.settlement_amount || 0,
                row.tax || 0,
                row.cash_received || 0,
                row.payback || 0,
                row.margin_before_tax || 0,
                row.margin_after_tax || 0
            ]);

            // CSV 문자열 생성
            let csvContent = headers.join(',') + '\n';
            rows.forEach(row => {
                const csvRow = row.map(cell => {
                    // 쉼표나 줄바꿈이 포함된 경우 따옴표로 감싸기
                    if (String(cell).includes(',') || String(cell).includes('\n')) {
                        return `"${String(cell).replace(/"/g, '""')}"`;
                    }
                    return cell;
                });
                csvContent += csvRow.join(',') + '\n';
            });

            // UTF-8 BOM 추가 (한글 깨짐 방지)
            return '\uFEFF' + csvContent;
        }

        // CSV 다운로드 함수
        function downloadCSV(csvData, filename) {
            const blob = new Blob([csvData], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = filename;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // 통신사 관리 함수
        function openCarrierManagement() {
            // 대시보드의 통신사 관리로 이동
            window.location.href = '/dashboard#carrier-management';
        }

        // 대리점 관리 함수
        function openDealerManagement() {
            // 대시보드의 대리점 관리로 이동
            window.location.href = '/dashboard#dealer-management';
        }
    </script>
</body>
</html>