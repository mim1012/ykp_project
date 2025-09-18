<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>개통표 Professional - YKP ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+KR:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { font-family: 'Noto Sans KR', -apple-system, BlinkMacSystemFont, system-ui, sans-serif; }
        .mono-font { font-family: 'JetBrains Mono', monospace; }
        .scrollbar-thin::-webkit-scrollbar { width: 6px; height: 6px; }
        .scrollbar-thin::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        input[type="number"]::-webkit-inner-spin-button { display: none; }
        .sticky-header th { position: sticky; top: 0; z-index: 10; backdrop-filter: blur(8px); }
        .sticky-col { position: sticky; left: 0; z-index: 5; background: white; box-shadow: 2px 0 4px rgba(0,0,0,0.1); }
        .sticky-col-2 { position: sticky; left: 80px; z-index: 4; background: white; box-shadow: 2px 0 4px rgba(0,0,0,0.1); }
        .sticky-col-3 { position: sticky; left: 160px; z-index: 3; background: white; box-shadow: 2px 0 4px rgba(0,0,0,0.1); }
        .sticky-col-4 { position: sticky; left: 240px; z-index: 2; background: white; box-shadow: 2px 0 4px rgba(0,0,0,0.1); }
        
        /* 드래그 선택 시 텍스트 선택 방지 */
        .no-select {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }
        
        /* 선택된 셀 스타일 */
        .cell-selected {
            background-color: #bfdbfe !important;
            border-color: #60a5fa !important;
        }
        
        /* 키보드 네비게이션을 위한 포커스 스타일 */
        .cell-focused {
            outline: 2px solid #3b82f6;
            outline-offset: -2px;
        }
        
        /* 계산 필드 스타일 */
        .calc-field {
            background-color: #f8fafc !important;
            color: #475569;
            font-weight: 600;
            pointer-events: none;
        }
        
        /* 입력 필드 스타일 */
        .input-field {
            background-color: #fefce8 !important;
        }
        
        /* 행 높이 증가 (15% 더 큼) */
        .table-row {
            height: 46px; /* 기본 40px에서 15% 증가 */
        }
        
        /* 조건부 포매팅 */
        .negative-margin {
            background-color: #fef2f2 !important; /* 빨간색 배경 */
            color: #dc2626 !important;
        }
        
        .high-margin {
            background-color: #f0fdf4 !important; /* 초록색 배경 */
            color: #16a34a !important;
            font-weight: 600;
        }
        
        /* 마우스 호버 효과 */
        .hover-row:hover {
            background-color: #f8fafc !important;
            transform: scale(1.002);
            transition: all 0.2s ease;
        }
        
        /* 컬럼 필터 */
        .column-filter {
            min-width: 100px;
            font-size: 11px;
            padding: 2px 4px;
            border: 1px solid #d1d5db;
            border-radius: 3px;
        }
        
        /* 고정 요약 바 */
        .summary-bar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            z-index: 50;
            backdrop-filter: blur(8px);
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen bg-gray-50 p-4">
        <!-- KPI 카드 영역 -->
        <div class="mb-6 grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4" id="kpi-cards">
            <!-- 이번달 개통 대수 -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h3 class="text-sm font-medium text-gray-500">이번달 개통</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-2 mono-font" id="kpi-count">0대</p>
                        <p class="text-xs text-blue-600 mt-1" id="kpi-count-change">전월 대비 계산중...</p>
                    </div>
                    <div class="p-2 bg-blue-50 rounded-lg">
                        <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- 대당 평균 정산금 -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h3 class="text-sm font-medium text-gray-500">대당 평균</h3>
                        <p class="text-2xl font-bold text-gray-900 mt-2 mono-font" id="kpi-avg">₩0</p>
                        <p class="text-xs text-gray-500 mt-1">정산금 기준</p>
                    </div>
                    <div class="p-2 bg-green-50 rounded-lg">
                        <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- VAT 포함 매출 -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h3 class="text-sm font-medium text-gray-500">VAT 포함 매출</h3>
                        <p class="text-2xl font-bold text-red-600 mt-2 mono-font" id="kpi-vat">₩0</p>
                        <p class="text-xs text-gray-500 mt-1">VAT 13.3% 포함</p>
                    </div>
                    <div class="p-2 bg-red-50 rounded-lg">
                        <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>

            <!-- 순이익 -->
            <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-5">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h3 class="text-sm font-medium text-gray-500">순이익</h3>
                        <div class="bg-yellow-50 border border-yellow-200 rounded-md px-3 py-2 mt-2">
                            <p class="text-2xl font-bold text-green-600 mono-font" id="kpi-profit">₩0</p>
                            <p class="text-xs text-green-500" id="kpi-margin">마진율 0%</p>
                        </div>
                    </div>
                    <div class="p-2 bg-yellow-50 rounded-lg">
                        <svg class="w-5 h-5 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 8h6m-5 0a3 3 0 110 6H9l3 3m-3-6h6m6 1a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- 헤더 -->
        <div class="mb-4 bg-white rounded-lg shadow-sm p-4">
            <div class="flex justify-between items-center mb-4">
                <h1 class="text-2xl font-bold text-gray-800">개통표 Professional</h1>
                <div class="flex gap-2">
                    <button
                        onclick="addRow()"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-1"
                    >
                        행 추가
                    </button>
                    <button
                        id="undo-btn"
                        onclick="undo()"
                        disabled
                        class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors flex items-center gap-1 disabled:bg-gray-300"
                    >
                        실행취소
                    </button>
                    <button
                        id="redo-btn"
                        onclick="redo()"
                        disabled
                        class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors flex items-center gap-1 disabled:bg-gray-300"
                    >
                        다시실행
                    </button>
                    <button
                        onclick="exportToExcel()"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors flex items-center gap-1"
                    >
                        Excel 내보내기
                    </button>
                </div>
            </div>
            
            <!-- 컬럼 필터 영역 -->
            <div class="mb-4 p-3 bg-gradient-to-r from-gray-50 to-blue-50 rounded-lg border">
                <div class="flex items-center justify-between mb-2">
                    <h3 class="text-sm font-semibold text-gray-700">실시간 필터</h3>
                    <button onclick="clearAllFilters()" class="text-xs text-blue-600 hover:text-blue-800">전체 초기화</button>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-2">
                    <div>
                        <label class="text-xs text-gray-600">판매자</label>
                        <input type="text" id="filter-판매자" placeholder="전체" class="column-filter w-full" onchange="applyFilters()">
                    </div>
                    <div>
                        <label class="text-xs text-gray-600">대리점</label>
                        <input type="text" id="filter-대리점" placeholder="전체" class="column-filter w-full" onchange="applyFilters()">
                    </div>
                    <div>
                        <label class="text-xs text-gray-600">통신사</label>
                        <select id="filter-통신사" class="column-filter w-full" onchange="applyFilters()">
                            <option value="">전체</option>
                            <option value="SK">SK</option>
                            <option value="kt">kt</option>
                            <option value="LG">LG</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-600">개통방식</label>
                        <select id="filter-개통방식" class="column-filter w-full" onchange="applyFilters()">
                            <option value="">전체</option>
                            <option value="mnp">mnp</option>
                            <option value="신규">신규</option>
                            <option value="기변">기변</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-600">마진 범위</label>
                        <select id="filter-마진" class="column-filter w-full" onchange="applyFilters()">
                            <option value="">전체</option>
                            <option value="high">50만원 이상</option>
                            <option value="middle">10-50만원</option>
                            <option value="low">10만원 미만</option>
                            <option value="negative">마이너스</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-600">기간</label>
                        <input type="date" id="filter-date" class="column-filter w-full" onchange="applyFilters()">
                    </div>
                </div>
            </div>
            
            <!-- 키보드 단축키 안내 -->
            <div class="mt-4 text-xs text-gray-500 bg-gradient-to-r from-blue-50 to-green-50 p-3 rounded-lg border border-blue-200">
                <div class="font-semibold mb-1 text-blue-800">Excel 스타일 키보드 네비게이션</div>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <span class="font-medium text-blue-700">방향키:</span> 셀 이동<br>
                        <span class="font-medium text-blue-700">Tab:</span> 다음 편집 가능한 셀<br>
                        <span class="font-medium text-blue-700">Enter:</span> 아래로 이동
                    </div>
                    <div>
                        <span class="font-medium text-green-700">Ctrl+C/V:</span> 복사/붙여넣기<br>
                        <span class="font-medium text-red-700">Delete:</span> 셀 내용 삭제<br>
                        <span class="font-medium text-gray-700">회색셀:</span> 자동계산 필드
                    </div>
                    <div>
                        <span class="font-medium text-orange-700">Ctrl+Z:</span> 실행 취소<br>
                        <span class="font-medium text-orange-700">Ctrl+Shift+Z:</span> 다시 실행<br>
                        <span class="font-medium text-orange-700">Ctrl+Y:</span> 다시 실행
                    </div>
                </div>
            </div>
        </div>

        <!-- 테이블 -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="overflow-x-auto scrollbar-thin">
                <table class="w-full text-sm no-select" id="settlement-table">
                    <thead class="bg-gradient-to-r from-gray-100 to-gray-200 sticky-header">
                        <tr>
                            <th class="px-2 py-3 text-center border sticky-col bg-gradient-to-b from-gray-100 to-gray-200">삭제</th>
                            <th class="px-2 py-3 text-center border sticky-col-2 bg-gradient-to-b from-blue-50 to-blue-100 text-blue-800 font-semibold">판매자</th>
                            <th class="px-2 py-3 text-center border sticky-col-3 bg-gradient-to-b from-blue-50 to-blue-100 text-blue-800 font-semibold">대리점</th>
                            <th class="px-2 py-3 text-center border sticky-col-4 bg-gradient-to-b from-blue-50 to-blue-100 text-blue-800 font-semibold">통신사</th>
                            <th class="px-2 py-3 text-center border bg-gradient-to-b from-blue-50 to-blue-100 text-blue-800 font-semibold">개통방식</th>
                            <th class="px-2 py-3 text-center border bg-gradient-to-b from-blue-50 to-blue-100 text-blue-800 font-semibold">모델명</th>
                            <th class="px-2 py-2 text-center border">개통일</th>
                            <th class="px-2 py-2 text-center border">일련번호</th>
                            <th class="px-2 py-2 text-center border">휴대폰번호</th>
                            <th class="px-2 py-2 text-center border">고객명</th>
                            <th class="px-2 py-2 text-center border">생년월일</th>
                            <th class="px-2 py-2 text-center border bg-yellow-50">액면/셋팅가</th>
                            <th class="px-2 py-2 text-center border bg-yellow-50">부가추가</th>
                            <th class="px-2 py-2 text-center border bg-yellow-50">구두1</th>
                            <th class="px-2 py-2 text-center border bg-yellow-50">그레이드</th>
                            <th class="px-2 py-2 text-center border bg-yellow-50">구두2</th>
                            <th class="px-2 py-2 text-center border bg-yellow-50">서류상현금개통</th>
                            <th class="px-2 py-2 text-center border bg-yellow-50">유심비(+)</th>
                            <th class="px-2 py-2 text-center border bg-yellow-50">신규,번이(-800)</th>
                            <th class="px-2 py-2 text-center border bg-yellow-50">차감(-)</th>
                            <th class="px-2 py-2 text-center border bg-gray-200">리베총계</th>
                            <th class="px-2 py-2 text-center border bg-gray-200">정산금</th>
                            <th class="px-2 py-2 text-center border bg-gray-200">부/소세(13.3%)</th>
                            <th class="px-2 py-2 text-center border bg-yellow-50">현금받음(+)</th>
                            <th class="px-2 py-2 text-center border bg-yellow-50">페이백(-)</th>
                            <th class="px-2 py-2 text-center border bg-gray-200">세전/마진</th>
                            <th class="px-2 py-2 text-center border bg-gray-200">세후/마진</th>
                            <th class="px-2 py-2 text-center border">메모장</th>
                        </tr>
                    </thead>
                    <tbody id="table-body">
                        <!-- 동적으로 행이 추가됨 -->
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- 고정 요약 바 -->
        <div class="summary-bar bg-white border-t border-gray-200 shadow-lg p-3">
            <div class="max-w-7xl mx-auto">
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-8 gap-4 text-sm">
                    <div class="flex flex-col">
                        <span class="text-xs text-gray-500">총 대수</span>
                        <span class="font-bold text-blue-600 mono-font" id="summary-count">0대</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-gray-500">평균마진</span>
                        <span class="font-bold mono-font" id="summary-avg">₩0</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-gray-500">리베총계</span>
                        <span class="font-bold text-blue-600 mono-font" id="summary-total-revenue">₩0</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-gray-500">정산금</span>
                        <span class="font-bold text-blue-700 mono-font" id="summary-settlement">₩0</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-gray-500">세금</span>
                        <span class="font-bold text-red-600 mono-font" id="summary-tax">₩0</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-gray-500">세전마진</span>
                        <span class="font-bold text-green-600 mono-font" id="summary-before-tax">₩0</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-gray-500">세후마진</span>
                        <span class="font-bold text-green-700 mono-font" id="summary-after-tax">₩0</span>
                    </div>
                    <div class="flex flex-col">
                        <span class="text-xs text-gray-500">마진율</span>
                        <span class="font-bold text-purple-600 mono-font" id="summary-margin-rate">0%</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 전역 변수
        let data = [];
        let currentRow = 0;
        let currentCol = 0;
        let clipboard = null;
        let undoStack = [];
        let redoStack = [];
        const MAX_UNDO_STEPS = 20;

        // 필드 정의
        const fields = [
            'delete', '판매자', '대리점', '통신사', '개통방식', '모델명', '개통일', '일련번호', '휴대폰번호', '고객명', '생년월일',
            '액면셋팅가', '부가추가', '구두1', '그레이드', '구두2', '서류상현금개통', '유심비', '신규번이', '차감',
            '리베총계', '정산금', '부소세', '현금받음', '페이백', '세전마진', '세후마진', '메모장'
        ];

        const editableFields = [
            '판매자', '대리점', '통신사', '개통방식', '모델명', '개통일', '일련번호', '휴대폰번호', '고객명', '생년월일',
            '액면셋팅가', '부가추가', '구두1', '그레이드', '구두2', '서류상현금개통', '유심비', '신규번이', '차감',
            '현금받음', '페이백', '메모장'
        ];

        // 숫자 포맷팅
        function formatKRW(num) {
            if (!num) return '₩0';
            return `₩${new Intl.NumberFormat('ko-KR').format(num)}`;
        }

        // 숫자 파싱
        function parseNumber(str) {
            if (!str) return 0;
            // 문자열에서 숫자와 마이너스 기호만 추출
            const cleanStr = str.toString().replace(/[^\d-]/g, '');
            const num = parseInt(cleanStr) || 0;
            console.log('숫자 파싱:', str, '->', num);
            return num;
        }

        // 빈 행 생성
        function createEmptyRow(id) {
            return {
                id: id,
                판매자: '',
                대리점: '',
                통신사: 'SK',
                개통방식: 'mnp',
                모델명: '',
                개통일: new Date().toISOString().split('T')[0],
                일련번호: '',
                휴대폰번호: '',
                고객명: '',
                생년월일: '',
                액면셋팅가: 0,
                구두1: 0,
                구두2: 0,
                그레이드: 0,
                부가추가: 0,
                서류상현금개통: 0,
                유심비: 0,
                신규번이: 0,
                차감: 0,
                리베총계: 0,
                정산금: 0,
                부소세: 0,
                현금받음: 0,
                페이백: 0,
                세전마진: 0,
                세후마진: 0,
                메모장: ''
            };
        }

        // 계산 함수 - 정확한 Excel 공식 적용
        function calculateRow(row) {
            // 리베총계 = K+O+L+N+M (액면/셋팅가 + 부가추가 + 구두1 + 그레이드 + 구두2)
            const 리베총계 = (row.액면셋팅가 || 0) + (row.부가추가 || 0) + 
                           (row.구두1 || 0) + (row.그레이드 || 0) + (row.구두2 || 0);
            
            // 정산금 = T-P+Q+R+S (리베총계 - 서류상현금개통 + 유심비 + 신규번이 + 차감)
            const 정산금 = 리베총계 - (row.서류상현금개통 || 0) + (row.유심비 || 0) + 
                         (row.신규번이 || 0) + (row.차감 || 0);
            
            // 부소세 = U*13.3% (정산금의 13.3%)
            const 부소세 = Math.round(정산금 * 0.133);
            
            // 세전마진 = U-V+W+X (정산금 - 세금 + 현금받음 + 페이백)
            const 세전마진 = 정산금 - 부소세 + (row.현금받음 || 0) + (row.페이백 || 0);
            
            // 세후마진 = V+Y (세금 + 세전마진)
            const 세후마진 = 부소세 + 세전마진;
            
            return {
                ...row,
                리베총계,
                정산금,
                부소세,
                세전마진,
                세후마진
            };
        }

        // 필터링 함수들
        function isRowFiltered(row) {
            const filters = {
                판매자: document.getElementById('filter-판매자')?.value.toLowerCase() || '',
                대리점: document.getElementById('filter-대리점')?.value.toLowerCase() || '',
                통신사: document.getElementById('filter-통신사')?.value || '',
                개통방식: document.getElementById('filter-개통방식')?.value || '',
                마진: document.getElementById('filter-마진')?.value || '',
                date: document.getElementById('filter-date')?.value || ''
            };

            // 텍스트 필터
            if (filters.판매자 && !row.판매자.toLowerCase().includes(filters.판매자)) return true;
            if (filters.대리점 && !row.대리점.toLowerCase().includes(filters.대리점)) return true;
            if (filters.통신사 && row.통신사 !== filters.통신사) return true;
            if (filters.개통방식 && row.개통방식 !== filters.개통방식) return true;
            
            // 마진 범위 필터
            if (filters.마진) {
                const margin = row.세후마진;
                switch(filters.마진) {
                    case 'high': if (margin < 500000) return true; break;
                    case 'middle': if (margin < 100000 || margin >= 500000) return true; break;
                    case 'low': if (margin < 0 || margin >= 100000) return true; break;
                    case 'negative': if (margin >= 0) return true; break;
                }
            }
            
            // 날짜 필터
            if (filters.date && row.개통일 !== filters.date) return true;
            
            return false;
        }

        function applyFilters() {
            renderTable();
        }

        function clearAllFilters() {
            document.getElementById('filter-판매자').value = '';
            document.getElementById('filter-대리점').value = '';
            document.getElementById('filter-통신사').value = '';
            document.getElementById('filter-개통방식').value = '';
            document.getElementById('filter-마진').value = '';
            document.getElementById('filter-date').value = '';
            renderTable();
        }
        
        // KPI 카드 업데이트
        function updateKPICards() {
            const visibleData = data.filter(row => !isRowFiltered(row));
            const totalCount = visibleData.length;
            const totalSettlement = visibleData.reduce((sum, row) => sum + row.정산금, 0);
            const totalTax = visibleData.reduce((sum, row) => sum + row.부소세, 0);
            const totalAfterTax = visibleData.reduce((sum, row) => sum + row.세후마진, 0);
            const avgMargin = totalCount > 0 ? Math.round(totalAfterTax / totalCount) : 0;
            const marginRate = totalSettlement > 0 ? ((totalAfterTax / totalSettlement) * 100) : 0;

            // KPI 카드 업데이트
            document.getElementById('kpi-count').textContent = totalCount + '대';
            document.getElementById('kpi-avg').textContent = '₩' + avgMargin.toLocaleString();
            document.getElementById('kpi-vat').textContent = '₩' + (totalSettlement + totalTax).toLocaleString();
            document.getElementById('kpi-profit').textContent = '₩' + totalAfterTax.toLocaleString();
            document.getElementById('kpi-margin').textContent = '마진율 ' + marginRate.toFixed(1) + '%';
            
            // 전월 대비 계산 (샘플 데이터)
            const prevMonthCount = Math.floor(totalCount * 0.85); // 샘플 비교
            const changePercent = prevMonthCount > 0 ? (((totalCount - prevMonthCount) / prevMonthCount) * 100) : 0;
            document.getElementById('kpi-count-change').textContent = 
                `전월 대비 ${changePercent >= 0 ? '+' : ''}${changePercent.toFixed(1)}%`;
        }

        // 테이블 렌더링
        function renderTable() {
            const tbody = document.getElementById('table-body');
            tbody.innerHTML = '';

            let visibleRowIndex = 0;
            data.forEach((row, rowIndex) => {
                if (isRowFiltered(row)) return; // 필터링된 행은 건너뛰기
                
                const tr = document.createElement('tr');
                let rowClass = `table-row hover-row ${visibleRowIndex % 2 === 0 ? 'bg-white' : 'bg-gray-25'}`;
                
                // 조건부 포매팅 적용
                if (row.세후마진 < 0) {
                    rowClass += ' negative-margin';
                } else if (row.세후마진 >= 500000) {
                    rowClass += ' high-margin';
                }
                
                tr.className = rowClass;
                tr.dataset.row = rowIndex;
                visibleRowIndex++;

                let html = '';
                fields.forEach((field, colIndex) => {
                    let content = '';
                    let cellClass = 'px-2 py-1 border transition-colors';

                    if (field === 'delete') {
                        cellClass += ' sticky-col bg-gray-100';
                        content = `<button onclick="deleteRow(${row.id})" class="text-red-500 hover:text-red-700 hover:bg-red-50 px-2 py-1 rounded">X</button>`;
                    } else if (editableFields.includes(field)) {
                        cellClass += ' input-field hover:bg-yellow-100';
                        
                        if (['통신사', '개통방식'].includes(field)) {
                            const options = field === '통신사' 
                                ? ['SK', 'kt', 'LG', 'SK알뜰', 'kt알뜰', 'LG알뜰']
                                : ['mnp', '신규', '기변'];
                            content = `<select onchange="updateCell(${rowIndex}, '${field}', this.value)" class="w-full px-1 py-0.5 border-0 bg-transparent focus:bg-white" data-row="${rowIndex}" data-col="${colIndex}">
                                ${options.map(opt => `<option value="${opt}" ${row[field] === opt ? 'selected' : ''}>${opt}</option>`).join('')}
                            </select>`;
                        } else if (field === '개통일') {
                            content = `<input type="date" value="${row[field]}" onchange="updateCell(${rowIndex}, '${field}', this.value)" class="w-full px-1 py-0.5 border-0 bg-transparent focus:bg-white" data-row="${rowIndex}" data-col="${colIndex}">`;
                        } else if (['액면셋팅가', '부가추가', '구두1', '그레이드', '구두2', '서류상현금개통', '유심비', '신규번이', '차감', '현금받음', '페이백'].includes(field)) {
                            let displayValue = '';
                            let textColor = '';
                            let placeholder = '';
                            
                            if (field === '유심비' || field === '현금받음') {
                                displayValue = row[field] ? Math.abs(row[field]).toLocaleString() : '';
                                textColor = 'text-blue-600';
                                placeholder = '0';
                            } else if (field === '신규번이' || field === '차감' || field === '페이백') {
                                displayValue = row[field] ? Math.abs(row[field]).toLocaleString() : '';
                                textColor = 'text-red-600';
                                placeholder = '0';
                            } else {
                                displayValue = row[field] ? row[field].toLocaleString() : '';
                                textColor = 'text-gray-900';
                                placeholder = '0';
                            }
                            
                            content = `<input type="text" value="${displayValue}" placeholder="${placeholder}" onchange="updateNumericCell(${rowIndex}, '${field}', this.value)" class="w-full px-1 py-0.5 text-right border-0 bg-transparent focus:bg-white ${textColor}" data-row="${rowIndex}" data-col="${colIndex}">`;
                        } else {
                            content = `<input type="text" value="${row[field]}" onchange="updateCell(${rowIndex}, '${field}', this.value)" class="w-full px-1 py-0.5 border-0 bg-transparent focus:bg-white" data-row="${rowIndex}" data-col="${colIndex}">`;
                        }
                    } else {
                        cellClass += ' calc-field text-right font-semibold';
                        let prefix = '';
                        if (field === '정산금') {
                            cellClass += ' text-blue-700 bg-blue-50';
                        } else if (field === '부소세') {
                            cellClass += ' text-red-700 bg-red-50';
                            prefix = '세금: ';
                        } else if (field === '세후마진') {
                            cellClass += ' text-green-700 bg-green-50';
                            prefix = '최종: ';
                        }
                        // 천 단위 쉼표만 추가, 원화 기호 제거
                        content = prefix + (row[field] ? row[field].toLocaleString() : '0');
                    }

                    html += `<td class="${cellClass}" data-row="${rowIndex}" data-col="${colIndex}">${content}</td>`;
                });

                tr.innerHTML = html;
                tbody.appendChild(tr);
            });

            updateKPICards();
        }

        // 셀 업데이트 (Undo 지원)
        function updateCell(rowIndex, field, value) {
            const oldValue = data[rowIndex][field];
            
            // 값이 실제로 변경된 경우만 undo 스택에 저장
            if (oldValue !== value) {
                saveState(`셀 수정: ${field}`);
            }
            
            console.log('셀 업데이트:', rowIndex, field, value);
            data[rowIndex][field] = value;
            data[rowIndex] = calculateRow(data[rowIndex]);
            
            // 현재 포커스 저장
            const focusRow = currentRow;
            const focusCol = currentCol;
            
            renderTable();
            
            // 포커스 복원
            setTimeout(() => {
                focusCell(focusRow, focusCol);
            }, 50);
        }

        function updateNumericCell(rowIndex, field, value) {
            let numValue = parseNumber(value);
            if (['신규번이', '차감', '페이백'].includes(field) && numValue > 0) {
                numValue = -numValue;
            }
            
            const oldValue = data[rowIndex][field];
            
            // 값이 실제로 변경된 경우만 undo 스택에 저장
            if (oldValue !== numValue) {
                saveState(`금액 수정: ${field}`);
            }
            
            console.log('숫자 셀 업데이트:', rowIndex, field, value, '->', numValue);
            data[rowIndex][field] = numValue;
            data[rowIndex] = calculateRow(data[rowIndex]);
            
            // 현재 포커스 저장
            const focusRow = currentRow;
            const focusCol = currentCol;
            
            renderTable();
            
            // 포커스 복원
            setTimeout(() => {
                focusCell(focusRow, focusCol);
            }, 50);
        }

        // 행 추가 (Undo 지원)
        function addRow() {
            saveState('행 추가');
            
            const newId = Date.now();
            const newRow = calculateRow(createEmptyRow(newId));
            data.push(newRow);
            renderTable();
            
            // 새 행의 첫 번째 편집 가능한 셀로 포커스 이동
            setTimeout(() => focusCell(data.length - 1, 1), 100);
            
            console.log('새 행 추가됨');
        }

        // Undo/Redo 시스템
        function saveState(action) {
            const state = {
                data: JSON.parse(JSON.stringify(data)),
                action: action,
                timestamp: new Date().toLocaleTimeString()
            };
            
            undoStack.push(state);
            if (undoStack.length > MAX_UNDO_STEPS) {
                undoStack.shift();
            }
            
            // 새 액션이 추가되면 redo 스택 클리어
            redoStack = [];
            
            updateUndoRedoButtons();
            console.log('상태 저장:', action);
        }

        function undo() {
            if (undoStack.length > 0) {
                const currentState = {
                    data: JSON.parse(JSON.stringify(data)),
                    action: 'current',
                    timestamp: new Date().toLocaleTimeString()
                };
                redoStack.push(currentState);

                const prevState = undoStack.pop();
                data = prevState.data;
                renderTable();
                
                updateUndoRedoButtons();
                console.log('실행 취소:', prevState.action);
            }
        }

        function redo() {
            if (redoStack.length > 0) {
                const currentState = {
                    data: JSON.parse(JSON.stringify(data)),
                    action: 'current',
                    timestamp: new Date().toLocaleTimeString()
                };
                undoStack.push(currentState);

                const nextState = redoStack.pop();
                data = nextState.data;
                renderTable();
                
                updateUndoRedoButtons();
                console.log('다시 실행:', nextState.action);
            }
        }

        function updateUndoRedoButtons() {
            const undoBtn = document.getElementById('undo-btn');
            const redoBtn = document.getElementById('redo-btn');
            
            if (undoBtn) {
                undoBtn.disabled = undoStack.length === 0;
                undoBtn.title = undoStack.length > 0 ? 
                    `실행 취소: ${undoStack[undoStack.length - 1].action}` : '실행 취소 불가';
            }
            
            if (redoBtn) {
                redoBtn.disabled = redoStack.length === 0;
                redoBtn.title = redoStack.length > 0 ? 
                    `다시 실행: ${redoStack[redoStack.length - 1].action}` : '다시 실행 불가';
            }
        }


        // 행 삭제 (Undo 지원)
        function deleteRow(id) {
            const rowToDelete = data.find(row => row.id === id);
            if (!rowToDelete) return;
            
            if (confirm(`"${rowToDelete.판매자 || '(빈 행)'}" 행을 삭제하시겠습니까?\n\n실행 취소(Ctrl+Z)로 복구할 수 있습니다.`)) {
                // 삭제 전 상태 저장
                saveState(`행 삭제: ${rowToDelete.판매자 || '(빈 행)'}`);
                
                data = data.filter(row => row.id !== id);
                renderTable();
                
                // 첫 번째 행이 있으면 포커스 설정
                if (data.length > 0) {
                    setTimeout(() => focusCell(0, 1), 100);
                }
                
                console.log('행 삭제됨');
            }
        }

        // Excel 내보내기
        function exportToExcel() {
            try {
                // 헤더 생성
                const headers = ['판매자', '대리점', '통신사', '개통방식', '모델명', '개통일', '일련번호', '휴대폰번호', '고객명', '생년월일',
                               '액면/셋팅가', '부가추가', '구두1', '그레이드', '구두2', '서류상현금개통', '유심비(+)', '신규,번이(-800)', '차감(-)',
                               '리베총계', '정산금', '부/소세(13.3%)', '현금받음(+)', '페이백(-)', '세전/마진', '세후/마진', '메모장'];
                
                let csv = headers.join(',') + '\n';
                
                // 데이터 행 생성
                data.forEach(row => {
                    const values = [
                        row.판매자, row.대리점, row.통신사, row.개통방식, row.모델명, row.개통일, row.일련번호, row.휴대폰번호, row.고객명, row.생년월일,
                        row.액면셋팅가, row.부가추가, row.구두1, row.그레이드, row.구두2, row.서류상현금개통, row.유심비, row.신규번이, row.차감,
                        row.리베총계, row.정산금, row.부소세, row.현금받음, row.페이백, row.세전마진, row.세후마진, row.메모장
                    ];
                    csv += values.map(v => `"${v || ''}"`).join(',') + '\n';
                });
                
                // 합계 행 추가
                const totalRow = ['합계', '', '', '', '', '', '', '', '', '',
                                data.reduce((sum, row) => sum + row.액면셋팅가, 0),
                                data.reduce((sum, row) => sum + row.부가추가, 0),
                                data.reduce((sum, row) => sum + row.구두1, 0),
                                data.reduce((sum, row) => sum + row.그레이드, 0),
                                data.reduce((sum, row) => sum + row.구두2, 0),
                                data.reduce((sum, row) => sum + row.서류상현금개통, 0),
                                data.reduce((sum, row) => sum + row.유심비, 0),
                                data.reduce((sum, row) => sum + row.신규번이, 0),
                                data.reduce((sum, row) => sum + row.차감, 0),
                                data.reduce((sum, row) => sum + row.리베총계, 0),
                                data.reduce((sum, row) => sum + row.정산금, 0),
                                data.reduce((sum, row) => sum + row.부소세, 0),
                                data.reduce((sum, row) => sum + row.현금받음, 0),
                                data.reduce((sum, row) => sum + row.페이백, 0),
                                data.reduce((sum, row) => sum + row.세전마진, 0),
                                data.reduce((sum, row) => sum + row.세후마진, 0),
                                ''];
                csv += totalRow.map(v => `"${v || ''}"`).join(',') + '\n';

                // 파일 다운로드
                const blob = new Blob(['\ufeff' + csv], { type: 'text/csv;charset=utf-8' });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `개통표_용산점_${new Date().toISOString().split('T')[0]}.csv`;
                a.click();
                window.URL.revokeObjectURL(url);
                
                alert('Excel 파일이 다운로드되었습니다!');
            } catch (error) {
                console.error('Excel 내보내기 오류:', error);
                alert('Excel 내보내기 중 오류가 발생했습니다.');
            }
        }

        // 키보드 네비게이션 - Excel 스타일
        function focusCell(rowIndex, colIndex) {
            // 먼저 input과 select 둘 다 찾아보기
            const cell = document.querySelector(`input[data-row="${rowIndex}"][data-col="${colIndex}"]`) || 
                        document.querySelector(`select[data-row="${rowIndex}"][data-col="${colIndex}"]`);
            
            if (cell) {
                cell.focus();
                if (cell.tagName === 'INPUT' && cell.type !== 'date') {
                    cell.select();
                }
                currentRow = rowIndex;
                currentCol = colIndex;
                
                // 시각적 피드백
                document.querySelectorAll('.cell-focused').forEach(el => el.classList.remove('cell-focused'));
                cell.classList.add('cell-focused');
                
                // 스크롤 위치 조정
                cell.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'nearest' });
            }
        }

        function moveFocus(rowDelta, colDelta) {
            let newRow = currentRow + rowDelta;
            let newCol = currentCol + colDelta;

            // 행 경계 확인
            if (newRow < 0) newRow = 0;
            if (newRow >= data.length) {
                // 마지막 행에서 아래로 이동하면 새 행 추가
                if (rowDelta > 0 && data.length > 0) {
                    addRow();
                    return;
                }
                newRow = data.length - 1;
            }
            
            // 열 경계 확인
            if (newCol < 1) newCol = 1; // delete 열 건너뛰기
            if (newCol >= fields.length) newCol = fields.length - 1;

            // 편집 불가능한 열 건너뛰기
            const direction = colDelta > 0 ? 1 : -1;
            while (newCol >= 1 && newCol < fields.length && !editableFields.includes(fields[newCol])) {
                newCol += direction;
                if (newCol < 1 || newCol >= fields.length) break;
            }

            if (newCol >= 1 && newCol < fields.length && editableFields.includes(fields[newCol])) {
                focusCell(newRow, newCol);
            }
        }

        // 키보드 이벤트 리스너 - 향상된 Excel 스타일
        document.addEventListener('keydown', function(e) {
            const target = e.target;
            
            // 입력 필드에서만 키보드 네비게이션 처리
            if ((target.tagName === 'INPUT' || target.tagName === 'SELECT') && target.dataset.row !== undefined) {
                const rowIndex = parseInt(target.dataset.row);
                const colIndex = parseInt(target.dataset.col);
                
                // 현재 포커스 업데이트
                currentRow = rowIndex;
                currentCol = colIndex;
                
                console.log('키 이벤트:', e.key, '현재 셀:', rowIndex, colIndex);
                
                switch(e.key) {
                    case 'ArrowUp':
                        e.preventDefault();
                        moveFocus(-1, 0);
                        break;
                        
                    case 'ArrowDown':
                        e.preventDefault();
                        moveFocus(1, 0);
                        break;
                        
                    case 'Enter':
                        e.preventDefault();
                        if (e.shiftKey) {
                            moveFocus(-1, 0);
                        } else {
                            moveFocus(1, 0);
                        }
                        break;
                        
                    case 'ArrowLeft':
                        if (target.tagName === 'SELECT' || target.selectionStart === 0) {
                            e.preventDefault();
                            moveFocus(0, -1);
                        }
                        break;
                        
                    case 'ArrowRight':
                        if (target.tagName === 'SELECT' || target.selectionStart === target.value.length) {
                            e.preventDefault();
                            moveFocus(0, 1);
                        }
                        break;
                        
                    case 'Tab':
                        e.preventDefault();
                        if (e.shiftKey) {
                            moveFocus(0, -1);
                        } else {
                            moveFocus(0, 1);
                        }
                        break;
                        
                    case 'Delete':
                    case 'Backspace':
                        if (e.ctrlKey || target.value === '') {
                            e.preventDefault();
                            target.value = '';
                            target.dispatchEvent(new Event('change'));
                        }
                        break;
                        
                    case 'c':
                        if (e.ctrlKey) {
                            e.preventDefault();
                            clipboard = target.value;
                            console.log('복사됨:', clipboard);
                            
                            // 시각적 피드백
                            target.style.backgroundColor = '#dbeafe';
                            target.style.border = '2px solid #3b82f6';
                            setTimeout(() => {
                                target.style.backgroundColor = '';
                                target.style.border = '';
                            }, 300);
                        }
                        break;
                        
                    case 'v':
                        if (e.ctrlKey && clipboard !== null) {
                            e.preventDefault();
                            target.value = clipboard;
                            target.dispatchEvent(new Event('change'));
                            console.log('붙여넣기 완료:', clipboard);
                            
                            // 시각적 피드백
                            target.style.backgroundColor = '#dcfce7';
                            target.style.border = '2px solid #16a34a';
                            setTimeout(() => {
                                target.style.backgroundColor = '';
                                target.style.border = '';
                            }, 300);
                        }
                        break;
                        
                    case 'Home':
                        e.preventDefault();
                        focusCell(rowIndex, 1);
                        break;
                        
                    case 'End':
                        e.preventDefault();
                        focusCell(rowIndex, fields.length - 1);
                        break;
                        
                    case 'PageUp':
                        e.preventDefault();
                        moveFocus(-5, 0);
                        break;
                        
                    case 'PageDown':
                        e.preventDefault();
                        moveFocus(5, 0);
                        break;
                        
                    case 'z':
                        if (e.ctrlKey && !e.shiftKey) {
                            e.preventDefault();
                            undo();
                        } else if (e.ctrlKey && e.shiftKey) {
                            e.preventDefault();
                            redo();
                        }
                        break;
                        
                    case 'y':
                        if (e.ctrlKey) {
                            e.preventDefault();
                            redo();
                        }
                        break;
                }
            } else {
                // 전역 단축키
                switch(e.key) {
                    case 'z':
                        if (e.ctrlKey && !e.shiftKey) {
                            e.preventDefault();
                            undo();
                        } else if (e.ctrlKey && e.shiftKey) {
                            e.preventDefault();
                            redo();
                        }
                        break;
                        
                    case 'y':
                        if (e.ctrlKey) {
                            e.preventDefault();
                            redo();
                        }
                        break;
                }
            }
        });

        // 초기 데이터 로드
        document.addEventListener('DOMContentLoaded', function() {
            console.log('YKP 개통표 Professional 시스템 시작');
            
            // 샘플 데이터 추가
            const sampleData = [
                {
                    id: 1,
                    판매자: '홍길동',
                    대리점: 'w',
                    통신사: 'SK',
                    개통방식: 'mnp',
                    모델명: 's936',
                    개통일: '2025-08-01',
                    일련번호: 'SN123456',
                    휴대폰번호: '010-1234-5678',
                    고객명: '김철수',
                    생년월일: '1990-01-01',
                    액면셋팅가: 150000,
                    구두1: 50000,
                    구두2: 30000,
                    그레이드: 20000,
                    부가추가: 10000,
                    서류상현금개통: 0,
                    유심비: 5500,
                    신규번이: -800,
                    차감: 0,
                    현금받음: 50000,
                    페이백: -30000,
                    메모장: '테스트 데이터'
                },
                {
                    id: 2,
                    판매자: '이영희',
                    대리점: '더킹',
                    통신사: 'kt',
                    개통방식: '신규',
                    모델명: 'a165',
                    개통일: '2025-08-02',
                    일련번호: 'SN789012',
                    휴대폰번호: '010-2345-6789',
                    고객명: '박영수',
                    생년월일: '1985-05-05',
                    액면셋팅가: 200000,
                    구두1: 70000,
                    구두2: 50000,
                    그레이드: 30000,
                    부가추가: 20000,
                    서류상현금개통: 50000,
                    유심비: 5500,
                    신규번이: 0,
                    차감: -10000,
                    현금받음: 0,
                    페이백: -50000,
                    메모장: '단체 가입'
                }
            ];

            data = sampleData.map(calculateRow);
            renderTable();
            updateUndoRedoButtons();
            
            // 첫 번째 편집 가능한 셀에 포커스
            setTimeout(() => {
                if (data.length > 0) {
                    focusCell(0, 1);
                }
                console.log('초기화 완료');
                console.log('데이터 개수:', data.length);
                console.log('키보드 단축키 활성화됨');
                console.log('사용법: 방향키로 이동, Ctrl+C/V로 복사/붙여넣기, Delete로 삭제');
                console.log('Ctrl+Z로 실행취소, Ctrl+Shift+Z로 다시실행');
            }, 100);
        });
    </script>
</body>
</html>