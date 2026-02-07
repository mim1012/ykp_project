<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>개통표 입력 - YKP ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/xlsx/0.18.5/xlsx.full.min.js"></script>
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

        /* 모바일 반응형 디자인 */
        @media (max-width: 768px) {
            /* 헤더 반응형 */
            header .flex {
                flex-direction: column;
                height: auto;
                padding: 1rem 0;
            }
            header h1 {
                font-size: 1.125rem;
                margin-bottom: 0.5rem;
            }

            /* 통계 카드 반응형 - 2열로 배치 */
            .grid.grid-cols-5 {
                grid-template-columns: repeat(2, 1fr);
                gap: 0.75rem;
            }
            .grid.grid-cols-5 > div:last-child {
                grid-column: span 2;
            }

            /* 버튼 그룹 반응형 */
            .flex.justify-between {
                flex-direction: column;
                gap: 0.75rem;
            }
            .flex.space-x-4, .flex.space-x-2, .flex.space-x-3 {
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            /* 버튼 크기 조정 */
            button {
                font-size: 0.875rem;
                padding: 0.5rem 0.75rem;
            }

            /* 입력 필드 크기 조정 */
            input[type="month"], select {
                font-size: 0.875rem;
            }

            /* 상태 표시기 반응형 */
            #status-indicator {
                min-width: 100%;
                text-align: center;
            }

            /* 페이지 여백 조정 */
            main {
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }

            /* 컨트롤 패널 반응형 */
            .bg-white.rounded-lg.shadow {
                padding: 0.75rem;
            }

            /* 필터 UI 반응형 */
            .flex.items-center.space-x-3 {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            /* 테이블 컨테이너 스크롤 */
            .table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }

            /* 텍스트 크기 조정 */
            .text-xl {
                font-size: 1.125rem;
            }
            .text-sm {
                font-size: 0.75rem;
            }
        }

        /* 작은 모바일 화면 (320px ~ 480px) */
        @media (max-width: 480px) {
            /* 통계 카드 1열로 배치 */
            .grid.grid-cols-5 {
                grid-template-columns: 1fr;
            }
            .grid.grid-cols-5 > div:last-child {
                grid-column: span 1;
            }

            /* 헤더 텍스트 크기 */
            header h1 {
                font-size: 1rem;
            }

            /* 버튼 전체 너비 */
            button {
                width: 100%;
                justify-content: center;
            }

            /* 입력 필드 전체 너비 */
            input[type="month"], select {
                width: 100%;
            }
        }
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
        <div class="grid grid-cols-5 gap-3 mb-6">
            <div class="bg-gradient-to-br from-blue-500 to-blue-600 text-white p-3 rounded-lg shadow-lg">
                <div class="text-sm opacity-90">총 개통건수</div>
                <div class="text-xl font-bold" id="total-count">0</div>
            </div>
            <div class="bg-gradient-to-br from-indigo-500 to-indigo-600 text-white p-3 rounded-lg shadow-lg">
                <div class="text-sm opacity-90">총 리베총계</div>
                <div class="text-xl font-bold" id="total-rebate">₩0</div>
            </div>
            <div class="bg-gradient-to-br from-emerald-500 to-emerald-600 text-white p-3 rounded-lg shadow-lg">
                <div class="text-sm opacity-90">총 매출</div>
                <div class="text-xl font-bold" id="total-settlement">₩0</div>
            </div>
            <div class="bg-gradient-to-br from-violet-500 to-violet-600 text-white p-3 rounded-lg shadow-lg">
                <div class="text-sm opacity-90">총 마진</div>
                <div class="text-xl font-bold" id="total-margin">₩0</div>
            </div>
            <div class="bg-gradient-to-br from-amber-500 to-amber-600 text-white p-3 rounded-lg shadow-lg">
                <div class="text-sm opacity-90">평균 마진율</div>
                <div class="text-xl font-bold" id="average-margin">0%</div>
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
                    🗑️ 선택 삭제 <span id="delete-count-badge" class="hidden ml-1 px-2 py-0.5 bg-white text-red-600 rounded-full text-xs font-bold"></span>
                </button>
                <button id="delete-all-btn" class="px-4 py-2 bg-gradient-to-r from-red-600 to-red-700 text-white rounded hover:from-red-700 hover:to-red-800 transition-all shadow">
                    ⚠️ 전체 삭제
                </button>
                <div id="status-indicator" class="px-4 py-2 bg-blue-100 text-blue-800 rounded-lg font-semibold text-sm shadow-sm min-w-[200px] text-center">
                    시스템 준비 완료
                </div>
            </div>
            <!-- 월단위 필터 UI (기본) -->
            <div class="flex items-center space-x-3 border-t pt-3">
                <span class="text-sm font-medium text-gray-700">📅 조회 기간:</span>
                <input type="month" id="month-filter" class="px-3 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="YYYY-MM">
                <button onclick="searchByMonth()" class="px-4 py-1.5 bg-gradient-to-r from-green-500 to-green-600 text-white text-sm rounded hover:from-green-600 hover:to-green-700 transition-all shadow-sm font-semibold">
                    🔍 조회
                </button>
                <button onclick="selectThisMonth()" class="px-3 py-1.5 bg-gradient-to-r from-blue-500 to-blue-600 text-white text-sm rounded hover:from-blue-600 hover:to-blue-700 transition-all shadow-sm">이번 달</button>
                <button onclick="selectLastMonth()" class="px-3 py-1.5 bg-gradient-to-r from-violet-500 to-violet-600 text-white text-sm rounded hover:from-violet-600 hover:to-violet-700 transition-all shadow-sm">지난 달</button>
                <button onclick="clearDateFilter()" class="px-3 py-1.5 bg-gradient-to-r from-gray-500 to-gray-600 text-white text-sm rounded hover:from-gray-600 hover:to-gray-700 transition-all shadow-sm">전체 보기</button>
                <span id="monthStatus" class="ml-4 px-3 py-1.5 bg-purple-50 text-purple-700 text-sm rounded font-medium"></span>
            </div>
            <!-- 페이지네이션 UI -->
            <div class="flex items-center justify-between border-t pt-3 mt-3">
                <div class="flex items-center space-x-3">
                    <span class="text-sm font-medium text-gray-700">📄 페이지:</span>
                    <button onclick="goToPreviousPage()" id="prev-page-btn" class="px-3 py-1.5 bg-gradient-to-r from-slate-500 to-slate-600 text-white text-sm rounded hover:from-slate-600 hover:to-slate-700 transition-all shadow-sm disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        ◀ 이전
                    </button>
                    <span id="page-info" class="px-3 py-1.5 bg-blue-50 text-blue-700 text-sm rounded font-medium">페이지 1 / 1</span>
                    <button onclick="goToNextPage()" id="next-page-btn" class="px-3 py-1.5 bg-gradient-to-r from-slate-500 to-slate-600 text-white text-sm rounded hover:from-slate-600 hover:to-slate-700 transition-all shadow-sm disabled:opacity-50 disabled:cursor-not-allowed" disabled>
                        다음 ▶
                    </button>
                    <select id="per-page-select" onchange="changePerPage()" class="px-3 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500 text-sm">
                        <option value="50" selected>50개씩 보기</option>
                        <option value="100">100개씩 보기</option>
                        <option value="200">200개씩 보기</option>
                    </select>
                </div>
                <span id="total-info" class="text-sm text-gray-600 font-medium">전체 0건</span>
            </div>
            <!-- 통신사/대리점 필터 UI 추가 -->
            <div class="flex items-center space-x-3 border-t pt-3 mt-3">
                <span class="text-sm font-medium text-gray-700">필터:</span>
                <!-- 통신사 필터 -->
                <select id="carrier-filter" class="px-3 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="applyFilters()">
                    <option value="">전체 통신사</option>
                    <option value="SK">SK</option>
                    <option value="KT">KT</option>
                    <option value="LG">LG</option>
                    <option value="MVNO">알뜰</option>
                </select>
                <!-- 대리점 필터 -->
                <select id="dealer-filter" class="px-3 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" onchange="applyFilters()">
                    <option value="">전체 대리점</option>
                </select>
                <!-- 판매자 필터 -->
                <input type="text" id="salesperson-filter" placeholder="판매자 검색" class="px-3 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" onkeyup="applyFilters()">
                <!-- 고객명 필터 -->
                <input type="text" id="customer-filter" placeholder="고객명 검색" class="px-3 py-1.5 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" onkeyup="applyFilters()">
                <!-- 필터 초기화 -->
                <button onclick="clearAllFilters()" class="px-3 py-1.5 bg-gradient-to-r from-gray-500 to-gray-600 text-white text-sm rounded hover:from-gray-600 hover:to-gray-700 transition-all shadow-sm">필터 초기화</button>
                <span id="filterStatus" class="ml-4 px-3 py-1.5 bg-green-50 text-green-700 text-sm rounded font-medium"></span>
            </div>
        </div>

        <!-- PM 요구사항: 27개 컬럼 완전한 개통표 테이블 -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto" style="max-height: 600px; overflow-y: auto;">
                <table class="min-w-full divide-y divide-gray-200" style="min-width: 4000px;">
                    <thead class="bg-gray-50 sticky top-0 z-10">
                        <tr>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                <input type="checkbox" id="select-all-checkbox"
                                       onchange="toggleSelectAll()"
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                       title="전체 선택">
                            </th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">판매자</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">대리점</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">통신사</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">개통방식</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">모델명</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">개통일</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">휴대폰번호</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">고객명</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">생년월일</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">방문경로</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">주소</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">액면/셋팅가</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">구두1</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">구두2</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">그레이드</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">부가추가</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase">서류상현금개통</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase plus-field">유심비</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase minus-field">신규/번이할인(-표기)</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase minus-field">차감(-표기)</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase total-field">리베총계</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase total-field">매출</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase plus-field">현금받음</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase minus-field">페이백</th>
                            <th class="px-2 py-2 text-left text-xs font-medium text-gray-500 uppercase margin-field">마진</th>
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

    <!-- 진행률 표시 모달 -->
    <div id="progress-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50" style="display: none;">
        <div class="bg-white rounded-lg p-6 max-w-sm w-full mx-4">
            <div class="mb-4">
                <h3 class="text-lg font-semibold text-gray-900 mb-2">데이터 처리 중...</h3>
                <div class="w-full bg-gray-200 rounded-full h-3 mb-2">
                    <div id="progress-bar" class="bg-blue-600 h-3 rounded-full transition-all duration-300" style="width: 0%"></div>
                </div>
                <p id="progress-text" class="text-sm text-gray-600 text-center">0% 완료</p>
                <p id="progress-detail" class="text-xs text-gray-500 text-center mt-1">0 / 0 행 처리됨</p>
            </div>
        </div>
    </div>

    <!-- 메모 팝업 모달 -->
    <div id="memo-popup-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 items-center justify-center z-50" style="display: none;">
        <div class="bg-white rounded-lg p-6 max-w-2xl w-full mx-4">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-lg font-semibold text-gray-900">메모 상세</h3>
                <button onclick="closeMemoPopup()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="mb-4">
                <textarea id="memo-popup-content" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" rows="10"></textarea>
            </div>
            <div class="flex justify-end space-x-2">
                <button onclick="closeMemoPopup()" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 transition-colors">
                    취소
                </button>
                <button onclick="saveMemoFromPopup()" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors">
                    저장
                </button>
            </div>
        </div>
    </div>

    <!-- 구두1/구두2 메모 팝업 모달 -->
    <div id="verbal-memo-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 items-center justify-center z-50" style="display: none;">
        <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-lg font-semibold text-gray-900" id="verbal-memo-title">구두 메모</h3>
                <button onclick="closeVerbalMemoPopup()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="mb-4">
                <textarea id="verbal-memo-content" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" rows="5" placeholder="구두 관련 메모를 입력하세요"></textarea>
            </div>
            <div class="flex justify-end space-x-2">
                <button onclick="closeVerbalMemoPopup()" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 transition-colors">
                    취소
                </button>
                <button onclick="saveVerbalMemo()" class="px-4 py-2 bg-yellow-500 text-white rounded hover:bg-yellow-600 transition-colors">
                    저장
                </button>
            </div>
        </div>
    </div>

    <!-- 대리점 추가 모달 -->
    <div id="dealer-add-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 items-center justify-center z-50" style="display: none;">
        <div class="bg-white rounded-lg p-6 max-w-lg w-full mx-4">
            <div class="flex justify-between items-start mb-4">
                <h3 class="text-lg font-semibold text-gray-900">대리점 추가</h3>
                <button onclick="closeDealerAddModal()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">통신사 선택</label>
                    <select id="dealer-carrier-filter" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" onchange="filterDealersByCarrier()">
                        <option value="ALL">전체</option>
                        <option value="SK">SK</option>
                        <option value="KT">KT</option>
                        <option value="LG">LG U+</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">대리점 선택</label>
                    <select id="dealer-select-list" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500">
                        <option value="">대리점을 선택하세요</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">신규 대리점명 (선택사항)</label>
                    <input type="text" id="new-dealer-name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500" placeholder="기존 목록에 없는 경우 입력">
                </div>
            </div>
            <div class="flex justify-end space-x-2 mt-6">
                <button onclick="closeDealerAddModal()" class="px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600 transition-colors">
                    취소
                </button>
                <button onclick="addDealerFromModal()" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors">
                    추가
                </button>
            </div>
        </div>
    </div>

    <script>
        // Production debug flag - disable log in production
        window.DEBUG = {{ config('app.debug') ? 'true' : 'false' }};
        const log = (...args) => window.DEBUG && console.log(...args);

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

        // 생년월일 6자리 파싱 (YYMMDD → YYYY-MM-DD) - 전역 함수로 정의
        window.parseBirthDate6 = function(val) {
            if (!val) return '';
            const str = String(val).trim().replace(/[^0-9]/g, '');
            if (str.length === 6) {
                const yy = parseInt(str.slice(0, 2));
                const mm = str.slice(2, 4);
                const dd = str.slice(4, 6);
                // 00~30은 2000년대, 31~99는 1900년대로 추정
                const yyyy = yy <= 30 ? 2000 + yy : 1900 + yy;
                return `${yyyy}-${mm}-${dd}`;
            }
            return val;
        };

        // PM 요구사항: 27개 필드 완전 매핑된 데이터 구조
        let salesData = [];
        // 임시 ID는 큰 값부터 시작해서 실제 DB ID와 충돌 방지
        let nextId = Date.now();
        // 선택된 행의 ID를 저장하는 Set (가상 스크롤링 시 상태 보존용)
        let selectedRowIds = new Set();
        
        // 새로운 행 데이터 구조 (DB 스키마와 1:1 매핑)
        function createNewRow() {
            return {
                id: nextId++,
                isPersisted: false, // 아직 DB에 저장되지 않은 임시 행
                salesperson: '',
                dealer_name: '',
                carrier: 'SK', // 기본값: SK
                activation_type: '', // 기본값 제거 - 사용자가 선택하도록
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
                new_mnp_discount: 0,
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
        
        // 27개 컬럼 순서대로 테이블 행 생성 (가상 스크롤링 최적화)
        let currentVisibleRange = { start: 0, end: 100 };

        function renderTableRows() {
            const tbody = document.getElementById('data-table-body');
            const totalRows = salesData.length;

            // 대용량 데이터인 경우 초기 100개만 렌더링
            if (totalRows > 100) {
                renderVisibleRows(0, 100);
                setupVirtualScrolling();
            } else {
                // 100개 이하는 전체 렌더링
                tbody.innerHTML = salesData.map(row => createRowHTML(row)).join('');
            }

            updateStatistics();
        }

        // 보이는 영역만 렌더링
        function renderVisibleRows(start, end) {
            const tbody = document.getElementById('data-table-body');
            const htmlBuffer = [];

            // 앞쪽 플레이스홀더
            if (start > 0) {
                htmlBuffer.push(`<tr style="height: ${start * 40}px;"><td colspan="28"></td></tr>`);
            }

            // 실제 데이터
            for (let i = start; i < Math.min(end, salesData.length); i++) {
                htmlBuffer.push(createRowHTML(salesData[i]));
            }

            // 뒤쪽 플레이스홀더
            if (end < salesData.length) {
                htmlBuffer.push(`<tr style="height: ${(salesData.length - end) * 40}px;"><td colspan="28"></td></tr>`);
            }

            tbody.innerHTML = htmlBuffer.join('');
            currentVisibleRange = { start, end };
        }

        // 가상 스크롤링 설정
        function setupVirtualScrolling() {
            const tableContainer = document.querySelector('.overflow-x-auto');
            let scrollTimeout;

            tableContainer.addEventListener('scroll', () => {
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(() => {
                    const scrollTop = tableContainer.scrollTop;
                    const containerHeight = tableContainer.clientHeight;
                    const rowHeight = 40; // 예상 행 높이

                    const visibleStart = Math.floor(scrollTop / rowHeight);
                    const visibleEnd = Math.ceil((scrollTop + containerHeight) / rowHeight) + 10; // 버퍼 추가

                    // 범위가 변경된 경우에만 재렌더링
                    if (visibleStart !== currentVisibleRange.start || visibleEnd !== currentVisibleRange.end) {
                        renderVisibleRows(Math.max(0, visibleStart - 10), Math.min(salesData.length, visibleEnd + 10));
                    }
                }, 50); // 디바운싱
            });
        }

        // 개별 행 HTML 생성 함수 (안전한 처리)
        function createRowHTML(row) {
            // null 체크 및 안전한 기본값 설정
            if (!row || typeof row !== 'object') {
                console.error('Invalid row data:', row);
                return '';
            }

            // 모든 값을 안전하게 처리
            const safeValue = (val) => (val === null || val === undefined) ? '' : String(val).replace(/"/g, '&quot;');
            const safeNumber = (val) => (val === null || val === undefined || isNaN(val)) ? 0 : Number(val);

            // 생년월일 6자리 포맷 (YYMMDD)
            const formatBirthDate6 = (val) => {
                if (!val) return '';
                const str = String(val).trim();
                // YYYY-MM-DD 형식인 경우 → YYMMDD로 변환
                if (/^\d{4}-\d{2}-\d{2}$/.test(str)) {
                    return str.slice(2, 4) + str.slice(5, 7) + str.slice(8, 10);
                }
                // 이미 6자리인 경우
                if (/^\d{6}$/.test(str)) {
                    return str;
                }
                // 8자리인 경우 (YYYYMMDD) → YYMMDD
                if (/^\d{8}$/.test(str)) {
                    return str.slice(2);
                }
                return str;
            };

            // 생년월일 6자리 파싱 (YYMMDD → YYYY-MM-DD)
            const parseBirthDate6 = (val) => {
                if (!val) return '';
                const str = String(val).trim().replace(/[^0-9]/g, '');
                if (str.length === 6) {
                    const yy = parseInt(str.slice(0, 2));
                    const mm = str.slice(2, 4);
                    const dd = str.slice(4, 6);
                    // 00~30은 2000년대, 31~99는 1900년대로 추정
                    const yyyy = yy <= 30 ? 2000 + yy : 1900 + yy;
                    return `${yyyy}-${mm}-${dd}`;
                }
                return val;
            };

            // 날짜 값 안전 처리 (엑셀 시리얼 번호 변환 포함)
            const safeDate = (val) => {
                if (!val) return '';

                // 숫자인 경우 (Excel 시리얼 번호)
                if (typeof val === 'number') {
                    const excelEpoch = new Date(1900, 0, 1);
                    const date = new Date(excelEpoch.getTime() + (val - 2) * 86400000);
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    return `${year}-${month}-${day}`;
                }

                const str = String(val).trim();

                // 이미 YYYY-MM-DD 형식인 경우
                if (/^\d{4}-\d{2}-\d{2}$/.test(str)) {
                    return str;
                }

                // 문자열이지만 숫자만 있고 하이픈이 없는 경우 (문자열로 저장된 시리얼 번호)
                // 예: "32874" (1990-01-01의 시리얼 번호)
                if (/^\d+$/.test(str) && !str.includes('-') && parseInt(str) > 1000 && parseInt(str) < 100000) {
                    const excelEpoch = new Date(1900, 0, 1);
                    const date = new Date(excelEpoch.getTime() + (parseInt(str) - 2) * 86400000);
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');
                    return `${year}-${month}-${day}`;
                }

                // ISO 형식에서 시간 부분 제거
                return str.split('T')[0];
            };

            return `
                <tr data-id="${row.id}" class="${row.isPersisted ? 'bg-green-50 hover:bg-green-100' : 'hover:bg-gray-50'}"
                    title="${row.isPersisted ? '저장됨' : '미저장'}">
                    <!-- 1. 선택 -->
                    <td class="px-2 py-2">
                        <input type="checkbox" class="row-select" data-id="${row.id}"
                               ${selectedRowIds.has(String(row.id)) ? 'checked="checked"' : ''}
                               onchange="log('Checkbox changed for ID:', '${row.id}'); toggleRowSelection('${row.id}'); updateSelectAllState();">
                    </td>
                    <!-- 2. 판매자 -->
                    <td class="px-2 py-2">
                        <input type="text" value="${safeValue(row.salesperson)}"
                               onchange="updateRowData('${row.id}', 'salesperson', this.value)"
                               class="field-name" placeholder="판매자명">
                    </td>
                    <!-- 3. 대리점 -->
                    <td class="px-2 py-2">
                        <select onchange="updateRowData('${row.id}', 'dealer_name', this.value)" class="field-dealer" id="dealer-select-${row.id}">
                            ${generateDealerOptions(row.dealer_name)}
                        </select>
                    </td>
                    <!-- 4. 통신사 -->
                    <td class="px-2 py-2">
                        <select onchange="updateRowData('${row.id}', 'carrier', this.value)" class="field-carrier" id="carrier-select-${row.id}">
                            ${generateCarrierOptions(row.carrier)}
                        </select>
                    </td>
                    <!-- 5. 개통방식 -->
                    <td class="px-2 py-2">
                        <select onchange="updateRowData('${row.id}', 'activation_type', this.value)" class="field-activation">
                            <option value="" ${!row.activation_type ? 'selected' : ''}>선택</option>
                            <option value="신규" ${row.activation_type === '신규' ? 'selected' : ''}>신규</option>
                            <option value="번이" ${row.activation_type === '번이' ? 'selected' : ''}>번이</option>
                            <option value="기변" ${row.activation_type === '기변' ? 'selected' : ''}>기변</option>
                            <option value="유선" ${row.activation_type === '유선' ? 'selected' : ''}>유선</option>
                            <option value="2nd" ${row.activation_type === '2nd' ? 'selected' : ''}>2nd</option>
                        </select>
                    </td>
                    <!-- 6. 모델명 -->
                    <td class="px-2 py-2">
                        <input type="text" value="${safeValue(row.model_name)}"
                               onchange="updateRowData('${row.id}', 'model_name', this.value)"
                               class="field-model" placeholder="iPhone15">
                    </td>
                    <!-- 7. 개통일 -->
                    <td class="px-2 py-2">
                        <input type="date" value="${safeDate(row.sale_date)}"
                               onchange="updateRowData('${row.id}', 'sale_date', this.value)"
                               class="field-date">
                    </td>
                    <!-- 8. 휴대폰번호 -->
                    <td class="px-2 py-2">
                        <input type="tel" value="${safeValue(row.phone_number)}"
                               onchange="updateRowData('${row.id}', 'phone_number', this.value)"
                               class="field-phone" placeholder="010-1234-5678">
                    </td>
                    <!-- 10. 고객명 -->
                    <td class="px-2 py-2">
                        <input type="text" value="${safeValue(row.customer_name)}"
                               onchange="updateRowData('${row.id}', 'customer_name', this.value)"
                               class="field-name" placeholder="김고객">
                    </td>
                    <!-- 11. 생년월일 (6자리 YYMMDD) -->
                    <td class="px-2 py-2">
                        <input type="text" value="${formatBirthDate6(row.customer_birth_date)}"
                               onchange="updateRowData('${row.id}', 'customer_birth_date', window.parseBirthDate6(this.value))"
                               class="w-20 px-1 py-1 border rounded text-xs text-center"
                               placeholder="971220"
                               maxlength="6"
                               pattern="[0-9]{6}"
                               title="생년월일 6자리 (예: 971220)">
                    </td>
                    <!-- 12. 방문경로 -->
                    <td class="px-2 py-2">
                        <select onchange="updateRowData('${row.id}', 'visit_path', this.value)" class="field-activation">
                            <option value="" ${!row.visit_path ? 'selected' : ''}>선택</option>
                            <option value="온라인" ${row.visit_path === '온라인' ? 'selected' : ''}>온라인</option>
                            <option value="지인소개" ${row.visit_path === '지인소개' ? 'selected' : ''}>지인소개</option>
                            <option value="매장방문" ${row.visit_path === '매장방문' ? 'selected' : ''}>매장방문</option>
                            <option value="전화문의" ${row.visit_path === '전화문의' ? 'selected' : ''}>전화문의</option>
                            <option value="기타" ${row.visit_path === '기타' ? 'selected' : ''}>기타</option>
                        </select>
                    </td>
                    <!-- 13. 주소 -->
                    <td class="px-2 py-2">
                        <input type="text" value="${safeValue(row.customer_address)}"
                               onchange="updateRowData('${row.id}', 'customer_address', this.value)"
                               class="w-32 px-1 py-1 border rounded text-xs"
                               placeholder="서울 강남구">
                    </td>
                    <!-- 14. 액면/셋팅가 -->
                    <td class="px-2 py-2">
                        <input type="number" value="${safeNumber(row.base_price)}"
                               onchange="updateRowData('${row.id}', 'base_price', isNaN(parseFloat(this.value)) ? 0 : parseFloat(this.value))"
                               class="field-money" placeholder="300000">
                    </td>
                    <!-- 15. 구두1 -->
                    <td class="px-2 py-2">
                        <div class="flex items-center space-x-1">
                            <input type="number" value="${row.verbal1}"
                                   onchange="updateRowData('${row.id}', 'verbal1', parseInt(this.value) || 0); calculateRow('${row.id}')"
                                   class="w-20 px-1 py-1 border rounded text-xs" placeholder="50000">
                            <button onclick="openVerbalMemoPopup('${row.id}', 1)"
                                    class="px-1 py-0.5 ${row.verbal1_memo ? 'bg-yellow-500' : 'bg-gray-400'} text-white rounded text-xs hover:bg-yellow-600"
                                    title="${safeValue(row.verbal1_memo) || '메모 추가'}">
                                📝
                            </button>
                        </div>
                    </td>
                    <!-- 16. 구두2 -->
                    <td class="px-2 py-2">
                        <div class="flex items-center space-x-1">
                            <input type="number" value="${row.verbal2}"
                                   onchange="updateRowData('${row.id}', 'verbal2', parseInt(this.value) || 0); calculateRow('${row.id}')"
                                   class="w-20 px-1 py-1 border rounded text-xs" placeholder="30000">
                            <button onclick="openVerbalMemoPopup('${row.id}', 2)"
                                    class="px-1 py-0.5 ${row.verbal2_memo ? 'bg-yellow-500' : 'bg-gray-400'} text-white rounded text-xs hover:bg-yellow-600"
                                    title="${safeValue(row.verbal2_memo) || '메모 추가'}">
                                📝
                            </button>
                        </div>
                    </td>
                    <!-- 15. 그레이드 -->
                    <td class="px-2 py-2">
                        <input type="number" value="${row.grade_amount}" 
                               onchange="updateRowData('${row.id}', 'grade_amount', parseInt(this.value) || 0); calculateRow('${row.id}')"
                               class="field-amount" placeholder="10000">
                    </td>
                    <!-- 16. 부가추가 -->
                    <td class="px-2 py-2">
                        <input type="number" value="${row.additional_amount}" 
                               onchange="updateRowData('${row.id}', 'additional_amount', parseInt(this.value) || 0); calculateRow('${row.id}')"
                               class="field-amount" placeholder="5000">
                    </td>
                    <!-- 17. 서류상현금개통 -->
                    <td class="px-2 py-2">
                        <input type="number" value="${row.cash_activation}" 
                               onchange="updateRowData('${row.id}', 'cash_activation', parseInt(this.value) || 0); calculateRow('${row.id}')"
                               class="field-amount" placeholder="0">
                    </td>
                    <!-- 18. 유심비 -->
                    <td class="px-2 py-2">
                        <input type="number" value="${row.usim_fee}" 
                               onchange="updateRowData('${row.id}', 'usim_fee', parseInt(this.value) || 0); calculateRow('${row.id}')"
                               class="field-policy plus-field" placeholder="0">
                    </td>
                    <!-- 19. 신규,번이할인 (기본값 0) -->
                    <td class="px-2 py-2">
                        <input type="number" value="${row.new_mnp_discount || 0}"
                               onchange="updateRowData('${row.id}', 'new_mnp_discount', parseInt(this.value) || 0); calculateRow('${row.id}')"
                               class="field-policy minus-field" placeholder="0">
                    </td>
                    <!-- 20. 차감 -->
                    <td class="px-2 py-2">
                        <input type="number" value="${row.deduction}" 
                               onchange="updateRowData('${row.id}', 'deduction', parseInt(this.value) || 0); calculateRow('${row.id}')"
                               class="field-policy minus-field" placeholder="0">
                    </td>
                    <!-- 21. 리베총계 (계산) -->
                    <td class="px-2 py-2 total-field">
                        <span class="field-calculated text-yellow-800" id="rebate-${row.id}">${(row.rebate_total || 0).toLocaleString()}원</span>
                    </td>
                    <!-- 22. 매출 (계산) -->
                    <td class="px-2 py-2 total-field">
                        <span class="field-calculated text-yellow-800" id="settlement-${row.id}">${(row.settlement_amount || 0).toLocaleString()}원</span>
                    </td>
                    <!-- 23. 현금받음 -->
                    <td class="px-2 py-2">
                        <input type="number" value="${row.cash_received}"
                               onchange="updateRowData('${row.id}', 'cash_received', parseInt(this.value) || 0); calculateRow('${row.id}')"
                               class="field-money plus-field" placeholder="0">
                    </td>
                    <!-- 24. 페이백 -->
                    <td class="px-2 py-2">
                        <input type="number" value="${row.payback}"
                               onchange="updateRowData('${row.id}', 'payback', parseInt(this.value) || 0); calculateRow('${row.id}')"
                               class="field-money minus-field" placeholder="0">
                    </td>
                    <!-- 25. 마진 (계산) -->
                    <td class="px-2 py-2 margin-field">
                        <span class="field-calculated text-green-800" id="margin-${row.id}">${(row.margin_after_tax || 0).toLocaleString()}원</span>
                    </td>
                    <!-- 28. 메모 -->
                    <td class="px-2 py-2">
                        <div class="flex items-center space-x-1">
                            <input type="text" value="${safeValue(row.memo)}"
                                   id="memo-input-${row.id}"
                                   onchange="updateRowData('${row.id}', 'memo', this.value)"
                                   class="w-24 px-1 py-1 border rounded text-xs"
                                   placeholder="메모 입력"
                                   title="${safeValue(row.memo)}">
                            <button onclick="log('Button clicked, ID:', ${row.id}); openMemoPopup('${row.id}')"
                                    class="px-1 py-1 bg-blue-500 text-white rounded text-xs hover:bg-blue-600"
                                    title="메모 팝업">
                                📝
                            </button>
                        </div>
                    </td>
                    <!-- 29. 액션 -->
                    <td class="px-2 py-2">
                        <button onclick="deleteRow('${row.id}')" class="px-2 py-1 bg-red-500 text-white rounded text-xs hover:bg-red-600">
                            🗑️ 삭제
                        </button>
                    </td>
                </tr>
            `;
        }
        
        // DB 필드명과 1:1 매핑된 행 데이터 업데이트
        function updateRowData(id, field, value) {
            log(`updateRowData called: id=${id} (type: ${typeof id}), field=${field}, value=${value}`);

            // ID 타입 변환: 문자열이면 숫자로 변환
            const numericId = typeof id === 'string' ? parseInt(id) : id;
            log(`Searching for row with id=${numericId} (type: ${typeof numericId})`);

            const row = salesData.find(r => {
                log(`  Comparing: r.id=${r.id} (${typeof r.id}) === numericId=${numericId} (${typeof numericId}) = ${r.id === numericId}`);
                return r.id === numericId;
            });

            if (row) {
                log(`Row found, updating ${field}: ${row[field]} → ${value}`);
                row[field] = value;
                log(`Updated successfully: ${field} = ${row[field]}`);

                // 개통방식 변경 시 차감액 자동 설정 (초기값 0으로 변경됨)
                if (field === 'activation_type') {
                    // 신규/번이/기변 모두 초기값 0 (사용자가 필요시 직접 입력)
                    if (value === '신규' || value === '번이' || value === '기변') {
                        row['new_mnp_discount'] = 0;
                    }
                    // 차감액 필드 업데이트
                    const discountInput = document.querySelector(`#data-table-body tr:has(input[value="${row.id}"]) input[placeholder="0"]`);
                    if (discountInput) {
                        discountInput.value = row['new_mnp_discount'];
                    }
                }

                // 계산에 영향을 주는 필드들이 변경되면 자동 재계산
                const calculationFields = [
                    'base_price', 'verbal1', 'verbal2', 'grade_amount', 'additional_amount',
                    'cash_activation', 'usim_fee', 'new_mnp_discount', 'deduction',
                    'cash_received', 'payback', 'activation_type'
                ];

                if (calculationFields.includes(field)) {
                    calculateRow(numericId);
                    // 통계도 업데이트
                    updateStatistics();
                }
            } else {
                console.error(`Row NOT found! salesData length: ${salesData.length}, searching for id: ${numericId}`);
                log('All IDs in salesData:', salesData.map(r => r.id));
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

            // U = T - P + Q - R - S + W - X (매출)
            // R(신규/번이할인), S(차감)은 마이너스 항목이므로 빼기
            const settlementAmount = rebateTotal - (row.cash_activation || 0) + (row.usim_fee || 0) -
                                   (row.new_mnp_discount || 0) - (row.deduction || 0) +
                                   (row.cash_received || 0) - (row.payback || 0);

            row.rebate_total = rebateTotal;
            row.settlement_amount = settlementAmount;

            // 세금 계산 중단
            row.tax = 0;

            // 마진 = 매출 (세금 없음)
            row.margin_before_tax = settlementAmount;
            row.margin_after_tax = settlementAmount;

            // UI 업데이트 (DOM 요소 존재 확인)
            const rebateEl = document.getElementById(`rebate-${id}`);
            if (rebateEl) rebateEl.textContent = rebateTotal.toLocaleString() + '원';

            const settlementEl = document.getElementById(`settlement-${id}`);
            if (settlementEl) settlementEl.textContent = settlementAmount.toLocaleString() + '원';

            const marginEl = document.getElementById(`margin-${id}`);
            if (marginEl) marginEl.textContent = settlementAmount.toLocaleString() + '원';
            
            updateStatistics();
        }
        
        // 통계 업데이트
        function updateStatistics() {
            const totalCount = salesData.length;
            const totalRebate = salesData.reduce((sum, row) => sum + (row.rebate_total || 0), 0);
            const totalSettlement = salesData.reduce((sum, row) => sum + (row.settlement_amount || 0), 0);
            const totalMargin = salesData.reduce((sum, row) => sum + (row.margin_after_tax || 0), 0);
            const avgMarginRate = totalSettlement > 0 ? ((totalMargin / totalSettlement) * 100).toFixed(1) : 0;

            const totalCountEl = document.getElementById('total-count');
            if (totalCountEl) totalCountEl.textContent = totalCount;

            const totalRebateEl = document.getElementById('total-rebate');
            if (totalRebateEl) totalRebateEl.textContent = '₩' + totalRebate.toLocaleString();

            const totalSettlementEl = document.getElementById('total-settlement');
            if (totalSettlementEl) totalSettlementEl.textContent = '₩' + totalSettlement.toLocaleString();

            const totalMarginEl = document.getElementById('total-margin');
            if (totalMarginEl) totalMarginEl.textContent = '₩' + totalMargin.toLocaleString();

            const avgMarginEl = document.getElementById('average-margin');
            if (avgMarginEl) avgMarginEl.textContent = avgMarginRate + '%';
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

                            // 필터가 적용된 경우 filteredData도 업데이트
                            if (filteredData.length > 0) {
                                filteredData = filteredData.filter(row => row.id !== id);
                            }

                            // 삭제된 행을 selectedRowIds에서도 제거
                            selectedRowIds.delete(String(id));

                            // 필터 상태에 따라 적절히 렌더링
                            if (hasActiveFilters()) {
                                renderFilteredData();
                            } else {
                                renderTableRows();
                            }

                            updateSelectAllState();
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

                    // 필터가 적용된 경우 filteredData도 업데이트
                    if (filteredData.length > 0) {
                        filteredData = filteredData.filter(row => row.id !== id);
                    }

                    // 삭제된 행을 selectedRowIds에서도 제거
                    selectedRowIds.delete(id);

                    // 필터 상태에 따라 적절히 렌더링
                    if (hasActiveFilters()) {
                        renderFilteredData();
                    } else {
                        renderTableRows();
                    }

                    updateSelectAllState();
                    showStatus('임시 행이 삭제되었습니다.', 'success');
                }
            }
        }

        // 개별 행 선택/해제 기능 (가상 스크롤링 호환)
        function toggleRowSelection(rowId) {
            // rowId를 문자열로 받으므로 일관되게 처리
            const idStr = String(rowId);
            log('toggleRowSelection called with ID:', idStr);
            log('Current selectedRowIds:', Array.from(selectedRowIds));

            if (selectedRowIds.has(idStr)) {
                selectedRowIds.delete(idStr);
                log('Removed ID:', idStr);
            } else {
                selectedRowIds.add(idStr);
                log('Added ID:', idStr);
            }
            log('Updated selectedRowIds:', Array.from(selectedRowIds));
            updateSelectionCount();
        }

        // 전체 선택/해제 기능
        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById('select-all-checkbox');
            const allCheckboxes = document.querySelectorAll('.row-select');

            if (selectAllCheckbox.checked) {
                // 전체 선택 - 모든 salesData의 ID를 문자열로 selectedRowIds에 추가
                salesData.forEach(row => selectedRowIds.add(String(row.id)));
                allCheckboxes.forEach(checkbox => {
                    checkbox.checked = true;
                });
            } else {
                // 전체 해제 - selectedRowIds 비우기
                selectedRowIds.clear();
                allCheckboxes.forEach(checkbox => {
                    checkbox.checked = false;
                });
            }
            updateSelectionCount();
        }

        // 개별 체크박스 변경 시 전체 선택 체크박스 상태 업데이트
        function updateSelectAllState() {
            const selectAllCheckbox = document.getElementById('select-all-checkbox');
            const totalRows = salesData.length;
            const selectedCount = selectedRowIds.size;

            if (totalRows === 0) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
            } else if (selectedCount === 0) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
            } else if (selectedCount === totalRows) {
                selectAllCheckbox.checked = true;
                selectAllCheckbox.indeterminate = false;
            } else {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = true;
            }

            updateSelectionCount();
        }

        // 선택된 개수 표시 (selectedRowIds 기반)
        function updateSelectionCount() {
            const selectedCount = selectedRowIds.size;
            const badge = document.getElementById('delete-count-badge');

            log('updateSelectionCount called, selectedCount:', selectedCount);
            log('Badge element:', badge);

            if (selectedCount > 0) {
                if (badge) {
                    badge.textContent = selectedCount;
                    badge.classList.remove('hidden');
                    log('Badge updated with count:', selectedCount);
                }
                showStatus(`${selectedCount}개 항목 선택됨`, 'info');
            } else {
                if (badge) {
                    badge.classList.add('hidden');
                    log('Badge hidden');
                }
            }
        }

        // 전체 삭제 기능
        async function deleteAll() {
            if (!confirm('주의: 모든 데이터가 삭제됩니다.\n\n정말로 전체 데이터를 삭제하시겠습니까?\n이 작업은 되돌릴 수 없습니다.')) {
                return;
            }

            // 2차 확인
            if (!confirm('다시 한번 확인합니다.\n정말로 전체 데이터를 삭제하시겠습니까?')) {
                return;
            }

            try {
                showStatus('전체 데이터 삭제 중...', 'info');

                // DB에 저장된 데이터가 있는지 확인
                const savedData = salesData.filter(row => row.isPersisted);

                if (savedData.length > 0) {
                    // DB에 저장된 모든 데이터 ID 수집
                    const allSavedIds = savedData.map(row => row.id);

                    // 서버에 전체 삭제 요청
                    const response = await fetch('/api/sales/bulk-delete', {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': window.csrfToken,
                            'Content-Type': 'application/json',
                            'Accept': 'application/json'
                        },
                        body: JSON.stringify({
                            sale_ids: allSavedIds,
                            delete_all: true // 전체 삭제 플래그
                        })
                    });

                    if (!response.ok) {
                        throw new Error(`서버 오류: ${response.status}`);
                    }
                }

                // 메모리에서 모든 데이터 삭제
                salesData = [];
                selectedRowIds.clear(); // 선택된 행 ID도 모두 클리어
                renderTableRows();
                updateStatistics();

                showStatus(`전체 데이터가 삭제되었습니다.`, 'success');

            } catch (error) {
                console.error('전체 삭제 오류:', error);
                showStatus('전체 삭제 중 오류가 발생했습니다.', 'error');
            }
        }

        // PM 요구사항: 선택 삭제 기능 (가상 스크롤링 호환)
        async function bulkDelete() {
            log('bulkDelete called');
            log('selectedRowIds size:', selectedRowIds.size);
            log('selectedRowIds contents:', Array.from(selectedRowIds));

            if (selectedRowIds.size === 0) {
                log('No rows selected, showing warning');
                showStatus('삭제할 행을 선택해주세요.', 'warning');
                return;
            }

            if (confirm(`선택한 ${selectedRowIds.size}개 행을 삭제하시겠습니까?`)) {
                const idsToDelete = Array.from(selectedRowIds);

                log('salesData 확인:', {
                    length: salesData.length,
                    firstFewRows: salesData.slice(0, 3),
                    idsToDelete: idsToDelete,
                    idsToDeleteTypes: idsToDelete.map(id => typeof id)
                });

                // isPersisted 플래그를 사용해 DB에 저장된 ID와 미저장 ID 분리
                const savedIds = [];
                const unsavedIds = [];

                idsToDelete.forEach(id => {
                    // 문자열 ID를 숫자로 변환해서 찾기 시도
                    const row = salesData.find(r => r.id === id || r.id === Number(id) || String(r.id) === id);
                    log(`행 ${id} 확인:`, {
                        found: !!row,
                        id_type: typeof id,
                        row_id: row?.id,
                        row_id_type: row?.id ? typeof row.id : 'N/A',
                        isPersisted: row?.isPersisted,
                        customer_name: row?.customer_name || 'N/A'
                    });
                    if (row && row.isPersisted) {
                        // 실제 DB ID를 사용 (임시 ID가 아닌 row.id) - integer로 변환
                        const dbId = Number(row.id);

                        // NaN 체크 - 숫자 변환 실패 시 오류 로깅
                        if (isNaN(dbId)) {
                            console.error(`ID 변환 실패: ${id} → NaN (row.id: ${row.id}, type: ${typeof row.id})`);
                        } else if (!Number.isInteger(dbId)) {
                            console.warn(`정수가 아닌 ID: ${dbId}`);
                            savedIds.push(Math.floor(dbId)); // 정수로 변환
                        } else {
                            savedIds.push(dbId);
                            log(`DB 저장된 행 추가: ${id} → ${dbId}`);
                        }
                    } else {
                        unsavedIds.push(id);
                        log(`미저장 행: ${id}` + (row ? '' : ' (행을 찾을 수 없음)'));
                    }
                });

                log('분류 결과:', {
                    savedIds,
                    unsavedIds,
                    totalIds: idsToDelete
                });

                try {
                    // DB에 저장된 데이터가 있으면 백엔드 호출
                    if (savedIds.length > 0) {
                        const requestBody = { sale_ids: savedIds };
                        const requestBodyString = JSON.stringify(requestBody);

                        log('삭제 API 요청:', {
                            url: '/api/sales/bulk-delete',
                            savedIds: savedIds,
                            count: savedIds.length,
                            requestBody: requestBody,
                            requestBodyString: requestBodyString,
                            firstIdType: typeof savedIds[0],
                            firstIdValue: savedIds[0]
                        });

                        const response = await fetch('/api/sales/bulk-delete', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': window.csrfToken,
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: requestBodyString
                        });

                        log('응답 상태:', response.status, response.statusText);

                        if (!response.ok) {
                            const errorText = await response.text();
                            console.error('HTTP 에러:', {
                                status: response.status,
                                statusText: response.statusText,
                                body: errorText
                            });
                            throw new Error(`HTTP ${response.status}: ${errorText}`);
                        }

                        const result = await response.json();
                        log('삭제 API 응답:', result);

                        if (!result.success) {
                            throw new Error(result.message || '삭제 실패');
                        }
                    } else {
                        log('저장된 행이 없어서 API 요청 건너뜀');
                    }

                    log('if/else 블록 완료, UI 업데이트 시작 예정');
                    log('현재 salesData:', { length: salesData.length, idsToDelete });

                    // 모든 선택된 행 제거 (ID 타입 변환 포함)
                    const beforeCount = salesData.length;
                    log('beforeCount:', beforeCount);

                    // ID를 문자열로 통일해서 비교
                    const idsToDeleteStrings = idsToDelete.map(id => String(id));
                    salesData = salesData.filter(row => {
                        const rowIdString = String(row.id);
                        const shouldKeep = !idsToDeleteStrings.includes(rowIdString);
                        log(`행 ${row.id} (${rowIdString}): shouldKeep=${shouldKeep}`);
                        return shouldKeep;
                    });

                    const afterCount = salesData.length;
                    log('afterCount:', afterCount);

                    log('UI 업데이트:', {
                        beforeCount,
                        afterCount,
                        removed: beforeCount - afterCount,
                        idsToDelete
                    });

                    // 필터가 적용된 경우 filteredData도 업데이트 (ID 타입 변환 포함)
                    if (filteredData.length > 0) {
                        const beforeFilteredCount = filteredData.length;
                        filteredData = filteredData.filter(row => {
                            const rowIdString = String(row.id);
                            return !idsToDeleteStrings.includes(rowIdString);
                        });
                        const afterFilteredCount = filteredData.length;
                        log('filteredData도 업데이트:', {
                            before: beforeFilteredCount,
                            after: afterFilteredCount,
                            removed: beforeFilteredCount - afterFilteredCount
                        });
                    }

                    // 삭제된 행들을 selectedRowIds에서도 제거
                    idsToDelete.forEach(id => selectedRowIds.delete(String(id)));

                    // 필터 상태에 따라 적절히 렌더링
                    const hasFilters = hasActiveFilters();
                    log('렌더링 시작:', { hasFilters });

                    if (hasFilters) {
                        log('renderFilteredData() 호출');
                        renderFilteredData();
                    } else {
                        log('renderTableRows() 호출');
                        renderTableRows();
                    }

                    // 통계 업데이트 (총 개수, 총 리베이트, 총 정산금 등)
                    updateStatistics();

                    // 전체 선택 체크박스 상태 업데이트
                    updateSelectAllState();

                    // 삭제 성공 메시지 (개수 변화 포함)
                    const deletedCount = idsToDelete.length;
                    const remainingCount = salesData.length;

                    if (remainingCount === 0) {
                        // 모든 데이터가 삭제된 경우
                        showStatus(`✅ ${deletedCount}개 행이 삭제되었습니다. 📭 현재 표시할 항목이 없습니다.`, 'success');
                    } else {
                        // 일부 데이터만 삭제된 경우
                        showStatus(`✅ ${deletedCount}개 행이 삭제되었습니다. (총 ${beforeCount}건 → ${remainingCount}건)`, 'success');
                    }
                } catch (error) {
                    // 일괄 삭제 오류 발생
                    console.error('삭제 실패:', error);
                    console.error('Error details:', {
                        message: error.message,
                        stack: error.stack
                    });
                    showStatus(`삭제 중 오류가 발생했습니다: ${error.message}`, 'error');
                }
            }
        }
        
        // 데이터 무결성 검증 함수
        function validateSaleData(row) {
            const errors = [];

            // 판매일자가 없으면 오늘 날짜 자동 설정
            if (!row.sale_date) {
                const today = new Date();
                row.sale_date = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
                log(`판매일자 자동 설정: ${row.sale_date}`);
            }

            // 통신사 검증 - 필수 입력
            if (!row.carrier) {
                errors.push("통신사를 선택하세요");
            } else {
                const validCarriers = carriersList.length > 0
                    ? carriersList.map(c => c.name)
                    : ['SK', 'KT', 'LG', 'MVNO'];

                // carrier가 'LG U+'인 경우 'LG'로도 매칭되도록 처리
                const isValidCarrier = validCarriers.includes(row.carrier) ||
                                      (row.carrier === 'LG' && validCarriers.includes('LG U+'));

                if (!isValidCarrier) {
                    errors.push(`유효한 통신사를 선택하세요 (SK/KT/LG/알뜰)`);
                }
            }

            // 개통유형은 선택사항으로 변경 (비어있어도 허용)
            if (row.activation_type && !['신규', '번이', '기변', '유선', '2nd'].includes(row.activation_type)) {
                errors.push("유효한 개통유형: 신규/번이/기변/유선/2nd 중 선택");
            }

            // 모델명도 선택사항으로 변경 (비어있어도 허용)
            // 최소한 하나의 핵심 필드는 있어야 함
            if (!row.model_name && !row.phone_number && !row.customer_name && !row.carrier) {
                errors.push("최소한 모델명, 휴대폰번호, 고객명, 통신사 중 하나는 입력해야 합니다");
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

            // 전체 저장: 신규 데이터와 수정된 데이터 모두 저장
            // 백엔드에서 id가 있으면 업데이트, 없으면 생성

            // 데이터 유효성 검증
            const validData = [];
            const invalidRows = [];

            salesData.forEach((row, index) => {
                // 디버깅: 각 행의 상태 로깅
                log(`Row ${index + 1}:`, {
                    isPersisted: row.isPersisted,
                    id: row.id,
                    sale_date: row.sale_date,
                    carrier: row.carrier,
                    model_name: row.model_name
                });

                const validationErrors = validateSaleData(row);
                if (validationErrors) {
                    console.error(`Row ${index + 1} validation failed:`, validationErrors);
                    invalidRows.push({
                        rowIndex: index + 1,
                        errors: validationErrors
                    });
                } else {
                    validData.push(row);
                    log(`Row ${index + 1} added to validData`);
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

            // 디버깅: UPDATE vs CREATE 카운트
            const rowsWithId = validData.filter(row => row.isPersisted && row.id).length;
            const rowsWithoutId = validData.length - rowsWithId;
            log(`Save operation breakdown:`, {
                total: validData.length,
                updates: rowsWithId,
                creates: rowsWithoutId
            });

            const requestBody = {
                // HQ/Branch 계정 지원: 저장 시 서버에 컨텍스트 전달
                store_id: window.userData?.store_id || null,
                branch_id: window.userData?.branch_id || null,
                sales: validData.map((row, idx) => {
                    // 디버깅: 각 행의 ID 포함 여부 로깅 (모든 행 체크)
                    const hasId = !!row.id;
                    const willIncludeId = !!(row.isPersisted && row.id);

                    log(`[저장] Row ${idx + 1}:`, {
                        has_id: hasId,
                        id_value: row.id,
                        id_type: typeof row.id,
                        isPersisted: row.isPersisted,
                        will_include_id: willIncludeId,
                        action: willIncludeId ? 'UPDATE' : 'INSERT',
                        sale_date: row.sale_date,
                        customer_name: row.customer_name
                    });

                    const rowData = {
                        // id가 있으면 백엔드에서 업데이트, 없으면 생성
                        ...(row.isPersisted && row.id ? { id: row.id } : {}),

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
                        // 신규 필드: 방문경로, 주소
                        visit_path: row.visit_path || null,
                        customer_address: row.customer_address || null,
                        // 금액 필드
                        base_price: row.base_price,
                        verbal1: row.verbal1,
                        verbal1_memo: row.verbal1_memo || null,  // 구두1 메모
                        verbal2: row.verbal2,
                        verbal2_memo: row.verbal2_memo || null,  // 구두2 메모
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
                    };

                    return rowData;
                })
            };

            // 디버깅: 요청 데이터 확인
            log('=== BULK SAVE REQUEST ===');
            log('Total valid rows:', validData.length);
            log('salesData 상태:', salesData.slice(0, 3).map(r => ({
                id: r.id,
                carrier: r.carrier,
                isPersisted: r.isPersisted
            })));
            log('요청 데이터:', requestBody.sales.slice(0, 3).map(row => ({
                id: row.id,
                carrier: row.carrier,
                sale_date: row.sale_date,
                model_name: row.model_name
            })));

            fetch('/api/sales/bulk-save', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '',
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
                log('저장 응답 전체:', data);
                log('id_mappings 존재 여부:', !!data.id_mappings);
                log('id_mappings 내용:', data.id_mappings);
                log('id_mappings 키 개수:', data.id_mappings ? Object.keys(data.id_mappings).length : 0);

                if (data.success) {
                    showStatus('✅ ' + data.message, 'success');
                    // Data saved successfully

                    // 임시 ID를 실제 DB ID로 교체
                    if (data.id_mappings && Object.keys(data.id_mappings).length > 0) {
                        log('ID 매핑 적용 중...', data.id_mappings);

                        // 모든 매핑을 먼저 처리
                        const updatedSelections = new Set();
                        salesData.forEach(row => {
                            // 임시 ID가 매핑에 있으면 실제 DB ID로 교체
                            if (data.id_mappings[row.id]) {
                                const oldId = row.id;
                                const newId = data.id_mappings[row.id];

                                // 선택된 행이었으면 새로운 ID로 추적
                                if (selectedRowIds.has(String(oldId))) {
                                    updatedSelections.add(String(newId));
                                    log(`selectedRowIds 업데이트: ${oldId} → ${newId}`);
                                }

                                row.id = newId;
                                log(`ID 교체: ${oldId} → ${newId}`);
                            } else if (selectedRowIds.has(String(row.id))) {
                                // 매핑이 없는 행(UPDATE된 행)도 선택 상태 유지
                                updatedSelections.add(String(row.id));
                            }
                            row.isPersisted = true;
                        });

                        // selectedRowIds를 완전히 교체 (임시 ID 제거)
                        selectedRowIds.clear();
                        updatedSelections.forEach(id => selectedRowIds.add(id));
                        log('최종 selectedRowIds:', Array.from(selectedRowIds));
                    } else {
                        // ID 매핑이 없으면 (모두 UPDATE인 경우) 단순히 isPersisted만 설정
                        salesData.forEach(row => {
                            row.isPersisted = true;
                        });
                    }

                    // 테이블 다시 렌더링하여 배경색 업데이트 (녹색으로 표시)
                    renderTableRows();

                    // 통계 업데이트
                    updateStatistics();
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
        // 진행률 모달 표시 함수
        function showProgressModal(show = true) {
            const modal = document.getElementById('progress-modal');
            if (show) {
                modal.style.display = 'flex';
                modal.classList.remove('hidden');
            } else {
                modal.style.display = 'none';
                modal.classList.add('hidden');
            }
        }

        // 진행률 업데이트 함수
        function updateProgress(current, total) {
            const percent = Math.round((current / total) * 100);

            const progressBar = document.getElementById('progress-bar');
            if (progressBar) progressBar.style.width = `${percent}%`;

            const progressText = document.getElementById('progress-text');
            if (progressText) progressText.textContent = `${percent}% 완료`;

            const progressDetail = document.getElementById('progress-detail');
            if (progressDetail) progressDetail.textContent = `${current} / ${total} 행 처리됨`;
        }

        function showStatus(message, type = 'info') {
            const indicator = document.getElementById('status-indicator');
            indicator.textContent = message;
            indicator.className = `px-4 py-2 rounded-lg font-semibold text-sm shadow-sm min-w-[200px] text-center ${
                type === 'success' ? 'bg-green-100 text-green-800' :
                type === 'error' ? 'bg-red-100 text-red-800' :
                type === 'warning' ? 'bg-yellow-100 text-yellow-800' :
                type === 'info' ? 'bg-blue-100 text-blue-800' :
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

            // dealersList의 모든 대리점 추가
            dealersList.forEach(dealer => {
                const selected = selectedValue === dealer.name ? 'selected' : '';
                options += `<option value="${dealer.name}" ${selected}>${dealer.name}</option>`;
            });

            // DB에 없는 값은 선택 안함 (드롭다운에 추가하지 않음)

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
                    const selected = selectedValue === carrier.code ? 'selected' : '';
                    options += `<option value="${carrier.code}" ${selected}>${carrier.name}</option>`;
                });
            } else {
                carriersList.forEach(carrier => {
                    // MVNO는 "알뜰"로 표시
                    const displayName = carrier.name === 'MVNO' ? '알뜰' : carrier.name;
                    const selected = selectedValue === carrier.name ? 'selected' : '';
                    options += `<option value="${carrier.name}" ${selected}>${displayName}</option>`;
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
                // API를 통해 DB에서 대리점 목록 가져오기
                const response = await fetch('/api/dealers', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    credentials: 'same-origin'
                });

                if (response.ok) {
                    const data = await response.json();

                    if (data.success && data.data) {
                        dealersList = data.data
                            .filter(dealer => !dealer.status || dealer.status === 'active')
                            .map(dealer => ({
                                code: dealer.dealer_code,
                                name: dealer.dealer_name
                            }));

                        // 기존 행들의 대리점 드롭다운 업데이트
                        updateDealerDropdowns();

                        return dealersList;
                    }
                }

                // API 응답이 성공이 아닌 경우 폴백으로 하드코딩된 목록 사용
                log('API 응답 실패, 폴백 대리점 목록 사용');
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
                    {code: 'HAPPY', name: '해피'},
                    {code: 'DAECHAN', name: '대찬'},
                    {code: 'SUSEOK', name: '수석'},
                    {code: 'FACTORY', name: '팩토리'},
                    {code: 'PS', name: 'PS'},
                    {code: 'TVIBE', name: '티바이브'},
                    {code: 'HCK', name: 'HCK'},
                    {code: 'ANSUNG', name: '안성'}
                ];
                updateDealerDropdowns();
                return dealersList;
            } catch (error) {
                // 대리점 로드 오류 발생
                console.error('대리점 목록 로드 실패:', error);
                log('에러 발생, 폴백 대리점 목록 사용');

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
                    {code: 'HAPPY', name: '해피'},
                    {code: 'DAECHAN', name: '대찬'},
                    {code: 'SUSEOK', name: '수석'},
                    {code: 'FACTORY', name: '팩토리'},
                    {code: 'PS', name: 'PS'},
                    {code: 'TVIBE', name: '티바이브'},
                    {code: 'HCK', name: 'HCK'},
                    {code: 'ANSUNG', name: '안성'}
                ];

                updateDealerDropdowns();
                return dealersList;
            }
        }

        // 통신사 목록 로드 함수
        async function loadCarriers() {
            try {
                // API를 통해 DB에서 통신사 목록 가져오기
                const response = await fetch('/api/carriers', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    credentials: 'same-origin'
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data.success && data.data) {
                        // 활성 통신사만 필터링하고 정렬 순서대로 정렬
                        carriersList = data.data
                            .filter(carrier => carrier.is_active)
                            .sort((a, b) => a.sort_order - b.sort_order)
                            .map(carrier => ({
                                code: carrier.code,
                                name: carrier.name
                            }));

                        // 기존 행들의 통신사 드롭다운 업데이트
                        updateCarrierDropdowns();
                        return carriersList;
                    }
                }

                // API 응답이 성공이 아닌 경우 폴백으로 하드코딩된 목록 사용
                carriersList = [
                    { name: 'SK', is_active: true, sort_order: 1 },
                    { name: 'KT', is_active: true, sort_order: 2 },
                    { name: 'LG', is_active: true, sort_order: 3 },
                    { name: '알뜰', is_active: true, sort_order: 4 }
                ];
                updateCarrierDropdowns();
                return carriersList;
            } catch (error) {
                console.error('통신사 목록 로드 실패:', error);
                log('에러 발생, 폴백 통신사 목록 사용');

                // 에러 발생 시에도 기본 목록 반환
                carriersList = [
                    { name: 'SK', is_active: true, sort_order: 1 },
                    { name: 'KT', is_active: true, sort_order: 2 },
                    { name: 'LG', is_active: true, sort_order: 3 },
                    { name: '알뜰', is_active: true, sort_order: 4 }
                ];
                updateCarrierDropdowns();
                return carriersList;
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
                    log('CSV 다운로드 시작...');
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
                        log('CSV 파일 업로드 중...');

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
                params.append('page', currentPage); // 페이지 번호 추가
                params.append('per_page', perPage); // 페이지당 항목 수 추가

                if (dateFilter) {
                    if (dateFilter.type === 'single') {
                        params.append('sale_date', dateFilter.date);
                    } else if (dateFilter.type === 'range') {
                        params.append('start_date', dateFilter.startDate);
                        params.append('end_date', dateFilter.endDate);
                    } else if (dateFilter.type === 'days') {
                        params.append('days', dateFilter.days);
                    } else if (dateFilter.type === 'month') {
                        params.append('start_date', dateFilter.startDate);
                        params.append('end_date', dateFilter.endDate);
                    }
                } else {
                    // 전체보기: 날짜 파라미터 없이 전체 데이터 조회
                    // 백엔드에서 all_data=true 파라미터가 있으면 날짜 필터를 적용하지 않음
                    params.append('all_data', 'true');
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

                // 페이지네이션 정보 업데이트
                if (data.current_page) {
                    updatePaginationUI(data);
                } else {
                    // 페이지네이션 정보가 없으면 기본값 설정
                    updatePaginationUI({
                        current_page: 1,
                        last_page: 1,
                        total: Array.isArray(salesList) ? salesList.length : 0
                    });
                }

                if (salesList && Array.isArray(salesList) && salesList.length > 0) {
                    // Existing sales found

                    // 기존 데이터를 그리드에 로드
                    salesData = salesList.map((sale, index) => {
                        // 디버깅: API에서 받은 sale 객체 확인
                        if (index === 0) {
                            log('First sale object from API:', {
                                id: sale.id,
                                id_type: typeof sale.id,
                                has_id: 'id' in sale,
                                sale_date: sale.sale_date,
                                keys: Object.keys(sale)
                            });
                        }

                        // ID가 없으면 에러 발생 (DB에서 온 데이터는 반드시 ID가 있어야 함)
                        if (!sale.id && sale.id !== 0) {
                            console.error('Sale record without ID from API:', sale);
                        }

                        return {
                            id: sale.id, // 실제 DB ID 사용 (fallback 제거)
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
                            customer_birth_date: (() => {
                                if (!sale.customer_birth_date) return '';

                                const value = sale.customer_birth_date;

                                // 숫자인 경우 시리얼 번호 변환
                                if (typeof value === 'number') {
                                    const excelEpoch = new Date(1900, 0, 1);
                                    const date = new Date(excelEpoch.getTime() + (value - 2) * 86400000);
                                    const year = date.getFullYear();
                                    const month = String(date.getMonth() + 1).padStart(2, '0');
                                    const day = String(date.getDate()).padStart(2, '0');
                                    return `${year}-${month}-${day}`;
                                }

                                const str = String(value).trim();

                                // 문자열이지만 숫자만 있고 YYYY-MM-DD 형식이 아닌 경우 (시리얼 번호일 가능성)
                                if (/^\d+$/.test(str) && !str.includes('-') && parseInt(str) > 1000 && parseInt(str) < 100000) {
                                    const excelEpoch = new Date(1900, 0, 1);
                                    const date = new Date(excelEpoch.getTime() + (parseInt(str) - 2) * 86400000);
                                    const year = date.getFullYear();
                                    const month = String(date.getMonth() + 1).padStart(2, '0');
                                    const day = String(date.getDate()).padStart(2, '0');
                                    return `${year}-${month}-${day}`;
                                }

                                // 이미 YYYY-MM-DD 형식인 경우
                                if (/^\d{4}-\d{2}-\d{2}$/.test(str)) {
                                    return str;
                                }

                                // ISO 형식에서 T 제거
                                return str.split('T')[0];
                            })(),
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
                            memo: sale.memo || '',
                            visit_path: sale.visit_path || '',
                            customer_address: sale.customer_address || ''
                        };
                    });

                    // 그리드 렌더링
                    renderTableRows();
                    // DOM 렌더링 완료 후 통계만 업데이트 (비동기)
                    // DB에서 로드한 계산 값을 그대로 사용 (재계산 안 함)
                    setTimeout(() => {
                        updateStatistics();
                    }, 100);
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

        // 페이지네이션 변수
        let currentPage = 1;
        let lastPage = 1;
        let perPage = 50;
        let totalRecords = 0;

        // 월단위 선택 함수들
        function selectThisMonth() {
            const today = new Date();
            const year = today.getFullYear();
            const month = String(today.getMonth() + 1).padStart(2, '0');
            const monthStr = `${year}-${month}`;

            document.getElementById('month-filter').value = monthStr;
            searchByMonth();
        }

        function selectLastMonth() {
            const today = new Date();
            const lastMonth = new Date(today.getFullYear(), today.getMonth() - 1, 1);
            const year = lastMonth.getFullYear();
            const month = String(lastMonth.getMonth() + 1).padStart(2, '0');
            const monthStr = `${year}-${month}`;

            document.getElementById('month-filter').value = monthStr;
            searchByMonth();
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
            // 전체보기: 날짜 필터 완전 제거하여 모든 데이터 가져오기
            selectedDateFilter = null; // 날짜 필터 없음
            currentPage = 1; // 페이지 리셋

            document.getElementById('month-filter').value = '';
            updateMonthStatus('전체 데이터 표시중');
            loadExistingSalesData(null); // null을 전달하여 날짜 필터 없이 조회
        }

        function updateMonthStatus(message) {
            const statusEl = document.getElementById('monthStatus');
            if (statusEl) {
                statusEl.textContent = message;
            }
        }

        // 페이지네이션 함수들
        function goToPreviousPage() {
            if (currentPage > 1) {
                currentPage--;
                loadExistingSalesData(selectedDateFilter);
            }
        }

        function goToNextPage() {
            if (currentPage < lastPage) {
                currentPage++;
                loadExistingSalesData(selectedDateFilter);
            }
        }

        function changePerPage() {
            const newPerPage = parseInt(document.getElementById('per-page-select').value);
            if (newPerPage !== perPage) {
                perPage = newPerPage;
                currentPage = 1; // 페이지 리셋
                loadExistingSalesData(selectedDateFilter);
            }
        }

        function updatePaginationUI(paginationData) {
            // 페이지네이션 변수 업데이트
            currentPage = paginationData.current_page || 1;
            lastPage = paginationData.last_page || 1;
            totalRecords = paginationData.total || 0;

            // UI 업데이트
            document.getElementById('page-info').textContent = `페이지 ${currentPage} / ${lastPage}`;
            document.getElementById('total-info').textContent = `전체 ${totalRecords}건`;

            // 버튼 활성화/비활성화
            const prevBtn = document.getElementById('prev-page-btn');
            const nextBtn = document.getElementById('next-page-btn');

            if (currentPage <= 1) {
                prevBtn.disabled = true;
            } else {
                prevBtn.disabled = false;
            }

            if (currentPage >= lastPage) {
                nextBtn.disabled = true;
            } else {
                nextBtn.disabled = false;
            }
        }

        // 월단위 조회 함수
        function searchByMonth() {
            const monthInput = document.getElementById('month-filter').value;

            if (!monthInput) {
                showStatus('조회할 년도와 월을 선택해주세요.', 'warning');
                return;
            }

            // YYYY-MM 형식을 파싱
            const [year, month] = monthInput.split('-');

            // 해당 월의 1일
            const startDate = new Date(year, month - 1, 1);
            const startYear = startDate.getFullYear();
            const startMonth = String(startDate.getMonth() + 1).padStart(2, '0');
            const startDay = '01';
            const startDateStr = `${startYear}-${startMonth}-${startDay}`;

            // 해당 월의 마지막 날
            const endDate = new Date(year, month, 0);
            const endYear = endDate.getFullYear();
            const endMonth = String(endDate.getMonth() + 1).padStart(2, '0');
            const endDay = String(endDate.getDate()).padStart(2, '0');
            const endDateStr = `${endYear}-${endMonth}-${endDay}`;

            selectedDateFilter = {
                type: 'month',
                startDate: startDateStr,
                endDate: endDateStr
            };

            // 페이지 리셋
            currentPage = 1;

            // 상태 표시
            const monthName = `${year}년 ${month}월`;
            updateMonthStatus(`${monthName} 데이터 조회중`);

            // 데이터 로드
            loadExistingSalesData(selectedDateFilter);

            showStatus(`${monthName} 데이터를 조회합니다.`, 'info');
        }

        // localStorage 이벤트 리스너 - 다른 탭에서 통신사가 변경되면 업데이트
        window.addEventListener('storage', function(e) {
            if (e.key === 'carriers_updated') {
                log('다른 탭에서 통신사 목록이 변경됨');
                loadCarriers();
            }
            if (e.key === 'dealers_updated') {
                log('다른 탭에서 대리점 목록이 변경됨');
                loadDealers();
            }
        });

        document.addEventListener('DOMContentLoaded', async function() {
            // 대리점 목록 먼저 로드
            await loadDealers();
            await loadCarriers();

            // 대리점 및 통신사 목록 로드 확인

            // 페이지 로드 시 전체 데이터 자동 표시
            clearDateFilter();

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

            // 대리점 필터 옵션 초기화
            setTimeout(async () => {
                await updateDealerFilterOptions();
            }, 500);

            // 버튼 이벤트 - Excel 스타일 UX
            document.getElementById('add-row-btn').addEventListener('click', addNewRow);
            document.getElementById('save-btn').addEventListener('click', saveAllData);
            document.getElementById('bulk-delete-btn').addEventListener('click', bulkDelete);
            document.getElementById('delete-all-btn').addEventListener('click', deleteAll);
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
                    log('파일 정보:', file.name, file.type, file.size);
                    log(`대리점 목록 상태: ${dealersList.length}개 로드됨`, dealersList.map(d => d.name).join(', '));

                    // XLSX 파일인지 CSV 파일인지 확인
                    const isExcel = file.name.match(/\.(xlsx?|xls)$/i);
                    const isCSV = file.name.match(/\.csv$/i);

                    const reader = new FileReader();

                    reader.onload = async function(event) {
                        try {
                            let rows = [];

                            if (isExcel) {
                                // XLSX 파일 처리
                                const data = new Uint8Array(event.target.result);
                                const workbook = XLSX.read(data, { type: 'array' });
                                const firstSheetName = workbook.SheetNames[0];
                                const worksheet = workbook.Sheets[firstSheetName];
                                const jsonData = XLSX.utils.sheet_to_json(worksheet, { header: 1, raw: true, defval: '' });

                                rows = jsonData;
                                log('Excel 파일 파싱 완료:', rows.length, '행');
                                log('첫 번째 행:', rows[0]);
                                if (rows.length > 5) {
                                    log('6번째 행 (데이터):', rows[5]);
                                    log('7번째 행 (데이터):', rows[6]);
                                }
                            } else {
                                // CSV 또는 텍스트 파일 처리
                                const text = event.target.result;
                                rows = text.split('\n').map(row => {
                                    return row.split(/[,\t]/).map(c => c.trim().replace(/^"|"$/g, ''));
                                });
                            }

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
                                '리베총계', '매출', '부/소 세', '현금받음(+표기)', '페이백(-표기)',
                                '세전 / 마진', '세후 / 마진', '메모'
                            ];

                            // 실제 데이터가 시작되는 행 찾기 (용산점 엑셀처럼 헤더가 5번째 행에 있을 수 있음)
                            let dataStartRow = 0;
                            let headerRow = null;

                            // 헤더 행 찾기 (판매자, 대리점 등의 텍스트가 있는 행)
                            for (let i = 0; i < Math.min(10, rows.length); i++) {
                                const row = rows[i];
                                if (row && row.length > 10) {
                                    // 판매자와 대리점이 포함된 행을 헤더로 간주
                                    if (row[0] === '판매자' || (row[0] && row[0].includes('판매자'))) {
                                        headerRow = row;
                                        dataStartRow = i + 1;
                                        log(`헤더 찾음 - 행 ${i}:`, row);
                                        break;
                                    }
                                }
                            }

                            // 헤더를 찾지 못했으면 첫 번째 행을 헤더로 가정
                            if (!headerRow) {
                                headerRow = rows[0];
                                dataStartRow = 1;
                                log('기본 헤더 사용 - 첫 번째 행');
                            }

                            // 대용량 데이터 처리 최적화
                            const totalRows = rows.length - dataStartRow;

                            // 데이터가 많으면 진행률 모달 표시
                            if (totalRows > 50) {
                                showProgressModal(true);
                                updateProgress(0, totalRows);
                            } else {
                                showStatus('데이터 처리 중...', 'info');
                            }

                            // 배치 처리를 위한 설정
                            const BATCH_SIZE = 100; // 한 번에 처리할 행 수
                            let processedRows = 0;
                            let addedCount = 0;
                            const newDataBatch = [];

                            // 비동기 처리 함수
                            const processBatch = async (startIdx, endIdx) => {
                                for (let i = startIdx; i < endIdx && i < rows.length; i++) {
                                    const cols = rows[i];

                                    // 배열이 아니면 스킵
                                    if (!Array.isArray(cols)) continue;

                                    // 디버깅 로그는 처음 5개만
                                    if (addedCount < 5) {
                                        log(`행 ${i} 데이터:`, cols);
                                        log('컬럼 수:', cols.length);
                                    }

                                    if (cols.length < 10) {
                                        if (addedCount < 5) {
                                            log(`행 ${i} 스킵 - 컬럼 수 부족`);
                                        }
                                        continue;
                                    }

                                // 컬럼 인덱스 찾기 (순서대로, 다양한 표기법 지원)
                                const getColValue = (index, defaultValue = '') => {
                                    if (index >= 0 && index < cols.length) {
                                        const val = cols[index];
                                        // 빈 값이거나 공백만 있으면 defaultValue 반환
                                        if (val === undefined || val === null || val === '') {
                                            return defaultValue;
                                        }
                                        // 문자열로 변환
                                        return String(val).trim();
                                    }
                                    return defaultValue;
                                };

                                // 날짜 형식 변환 함수 (캘린더 input을 위한 YYYY-MM-DD 형식)
                                const formatDate = (dateStr) => {
                                    if (!dateStr) return '';

                                    // 엑셀 날짜 시리얼 번호 처리 (숫자로 들어오는 경우)
                                    if (typeof dateStr === 'number') {
                                        // 엑셀 날짜 시리얼 번호를 Date로 변환
                                        // 1900년 1월 1일을 기준으로 하는 일수
                                        const excelEpoch = new Date(1900, 0, 1);
                                        const date = new Date(excelEpoch.getTime() + (dateStr - 2) * 86400000);
                                        const year = date.getFullYear();
                                        const month = String(date.getMonth() + 1).padStart(2, '0');
                                        const day = String(date.getDate()).padStart(2, '0');
                                        return `${year}-${month}-${day}`;
                                    }

                                    const str = String(dateStr).trim();
                                    if (str === '') return '';

                                    // 이미 YYYY-MM-DD 형식인 경우
                                    if (/^\d{4}-\d{2}-\d{2}$/.test(str)) {
                                        return str;
                                    }

                                    // 숫자만 있는 경우
                                    const numbers = str.replace(/[^0-9]/g, '');

                                    // Excel 시리얼 번호로 보이는 경우 (5자리 숫자 문자열)
                                    // 예: "45943" (2025-10-13), "32874" (1990-01-01)
                                    if (numbers.length === 5 && parseInt(numbers) > 10000 && parseInt(numbers) < 100000) {
                                        const serialNum = parseInt(numbers);
                                        const excelEpoch = new Date(1900, 0, 1);
                                        const date = new Date(excelEpoch.getTime() + (serialNum - 2) * 86400000);
                                        const year = date.getFullYear();
                                        const month = String(date.getMonth() + 1).padStart(2, '0');
                                        const day = String(date.getDate()).padStart(2, '0');
                                        return `${year}-${month}-${day}`;
                                    }

                                    if (numbers.length === 1 || numbers.length === 2) {
                                        // 일자만 있는 경우 (예: 2, 15)
                                        const today = new Date();
                                        const year = today.getFullYear();
                                        const month = String(today.getMonth() + 1).padStart(2, '0');
                                        return `${year}-${month}-${numbers.padStart(2, '0')}`;
                                    } else if (numbers.length === 4) {
                                        // MMDD 형식
                                        const today = new Date();
                                        const year = today.getFullYear();
                                        return `${year}-${numbers.substring(0, 2)}-${numbers.substring(2, 4)}`;
                                    } else if (numbers.length === 6) {
                                        // YYMMDD 형식
                                        const year = parseInt(numbers.substring(0, 2));
                                        const fullYear = year > 50 ? '19' + numbers.substring(0, 2) : '20' + numbers.substring(0, 2);
                                        return `${fullYear}-${numbers.substring(2, 4)}-${numbers.substring(4, 6)}`;
                                    } else if (numbers.length === 8) {
                                        // YYYYMMDD 형식
                                        return `${numbers.substring(0, 4)}-${numbers.substring(4, 6)}-${numbers.substring(6, 8)}`;
                                    }

                                    // 슬래시나 점으로 구분된 경우
                                    if (str.includes('/') || str.includes('.')) {
                                        const parts = str.split(/[\/\.]/).filter(p => p);
                                        if (parts.length === 2) {
                                            // MM/DD 또는 M/D 형식 (연도 없음)
                                            const today = new Date();
                                            const year = today.getFullYear();
                                            return `${year}-${parts[0].padStart(2, '0')}-${parts[1].padStart(2, '0')}`;
                                        } else if (parts.length === 3) {
                                            // YYYY/MM/DD 또는 YY/MM/DD 형식
                                            const year = parts[0].length === 4 ? parts[0] : '20' + parts[0];
                                            return `${year}-${parts[1].padStart(2, '0')}-${parts[2].padStart(2, '0')}`;
                                        }
                                    }

                                    return '';
                                };

                                // 생년월일 형식 변환 함수
                                // 날짜 유효성 검증 함수 (2월 30일 같은 잘못된 날짜 감지)
                                const isValidDate = (dateStr) => {
                                    if (!/^\d{4}-\d{2}-\d{2}$/.test(dateStr)) return false;

                                    const [year, month, day] = dateStr.split('-').map(num => parseInt(num, 10));

                                    // 기본 범위 검증
                                    if (month < 1 || month > 12) return false;
                                    if (day < 1 || day > 31) return false;
                                    if (year < 1900 || year > 2100) return false;

                                    // JavaScript Date 객체로 실제 날짜 유효성 확인
                                    const date = new Date(year, month - 1, day);
                                    return (
                                        date.getFullYear() === year &&
                                        date.getMonth() === month - 1 &&
                                        date.getDate() === day
                                    );
                                };

                                const formatBirthDate = (dateStr) => {
                                    if (!dateStr) return '';

                                    // 엑셀 날짜 시리얼 번호 처리 (숫자로 들어오는 경우)
                                    if (typeof dateStr === 'number') {
                                        const excelEpoch = new Date(1900, 0, 1);
                                        const date = new Date(excelEpoch.getTime() + (dateStr - 2) * 86400000);
                                        const year = date.getFullYear();
                                        const month = String(date.getMonth() + 1).padStart(2, '0');
                                        const day = String(date.getDate()).padStart(2, '0');
                                        const result = `${year}-${month}-${day}`;
                                        return isValidDate(result) ? result : '';
                                    }

                                    const str = String(dateStr).trim();
                                    if (str === '') return '';

                                    // 이미 YYYY-MM-DD 형식인 경우 (유효성 검증)
                                    if (/^\d{4}-\d{2}-\d{2}$/.test(str)) {
                                        return isValidDate(str) ? str : '';
                                    }

                                    // 숫자만 있는 경우
                                    const cleanStr = str.replace(/[^0-9]/g, '');

                                    // Excel 시리얼 번호로 보이는 경우 (예: "32874")
                                    if (cleanStr.length === 4 || cleanStr.length === 5) {
                                        const serialNum = parseInt(cleanStr);
                                        // Excel 시리얼 범위: 1000-100000 (1902년~2173년 정도)
                                        if (serialNum > 1000 && serialNum < 100000) {
                                            const excelEpoch = new Date(1900, 0, 1);
                                            const date = new Date(excelEpoch.getTime() + (serialNum - 2) * 86400000);
                                            const year = date.getFullYear();
                                            const month = String(date.getMonth() + 1).padStart(2, '0');
                                            const day = String(date.getDate()).padStart(2, '0');
                                            const result = `${year}-${month}-${day}`;
                                            return isValidDate(result) ? result : '';
                                        }
                                    }

                                    if (cleanStr.length === 6) {
                                        // YYMMDD 형식
                                        const year = parseInt(cleanStr.substring(0, 2));
                                        // 50을 기준으로 19XX / 20XX 판단 (00-50 → 2000-2050, 51-99 → 1951-1999)
                                        const fullYear = year >= 51 ? '19' + cleanStr.substring(0, 2) : '20' + cleanStr.substring(0, 2);
                                        const result = `${fullYear}-${cleanStr.substring(2, 4)}-${cleanStr.substring(4, 6)}`;

                                        // 유효성 검증 후 반환
                                        if (isValidDate(result)) {
                                            log(`YYMMDD 변환 성공: ${cleanStr} → ${result} (year=${year}, century=${year >= 51 ? '19' : '20'})`);
                                            return result;
                                        } else {
                                            console.error(`유효하지 않은 날짜: ${cleanStr} → ${result} (예: 2월 30일 같은 존재하지 않는 날짜)`);
                                            return ''; // 유효하지 않은 날짜는 빈 문자열 반환
                                        }
                                    } else if (cleanStr.length === 8) {
                                        // YYYYMMDD 형식
                                        const result = `${cleanStr.substring(0, 4)}-${cleanStr.substring(4, 6)}-${cleanStr.substring(6, 8)}`;

                                        if (isValidDate(result)) {
                                            log(`YYYYMMDD 변환 성공: ${cleanStr} → ${result}`);
                                            return result;
                                        } else {
                                            console.error(`유효하지 않은 날짜: ${cleanStr} → ${result}`);
                                            return '';
                                        }
                                    }

                                    // 슬래시나 점으로 구분된 경우
                                    if (str.includes('/') || str.includes('.')) {
                                        const parts = str.split(/[\/\.]/).filter(p => p);
                                        if (parts.length === 3) {
                                            // YYYY/MM/DD 또는 YY/MM/DD 형식
                                            let year;
                                            if (parts[0].length === 4) {
                                                year = parts[0]; // 이미 4자리면 그대로
                                            } else {
                                                const yy = parseInt(parts[0]);
                                                year = yy >= 51 ? '19' + parts[0].padStart(2, '0') : '20' + parts[0].padStart(2, '0');
                                            }
                                            const result = `${year}-${parts[1].padStart(2, '0')}-${parts[2].padStart(2, '0')}`;

                                            if (isValidDate(result)) {
                                                log(`구분자 변환 성공: ${str} → ${result}`);
                                                return result;
                                            } else {
                                                console.error(`유효하지 않은 날짜: ${str} → ${result}`);
                                                return '';
                                            }
                                        }
                                    }

                                    return str;
                                };

                                // 숫자 파싱 함수 (빈 값 처리)
                                const parseNumber = (value, defaultValue = 0) => {
                                    if (!value || value === '' || value === null || value === undefined) {
                                        return defaultValue;
                                    }
                                    const num = parseFloat(String(value).replace(/,/g, ''));
                                    return isNaN(num) ? defaultValue : num;
                                };

                                // 엑셀 파일 매핑
                                // 순서: 판매자(0), 대리점(1), 통신사(2), 개통방식(3), 모델명(4), 개통일(5), 휴대폰번호(6), 고객명(7), 생년월일(8)
                                // 디버깅 로그는 처음 3개만
                                if (addedCount < 3) {
                                    log(`\n=== 행 ${i} 매핑 시작 ===`);
                                    log('0: 판매자 =', getColValue(0));
                                    log('1: 대리점 =', getColValue(1));
                                    log('2: 통신사 =', getColValue(2));
                                    log('3: 개통방식 =', getColValue(3));
                                    log('4: 모델명 =', getColValue(4));
                                    log('5: 개통일 =', getColValue(5));
                                    log('6: 휴대폰번호 =', getColValue(6));
                                    log('7: 고객명 =', getColValue(7));
                                    log('8: 생년월일 =', getColValue(8));
                                    log('9: 액면가 =', getColValue(9));
                                }

                                // 대소문자 구분 없이 대리점 매칭 함수
                                const matchDealer = (inputDealer) => {
                                    if (!inputDealer) return '';
                                    const upperInput = inputDealer.toUpperCase();
                                    const matched = dealersList.find(d =>
                                        d.name.toUpperCase() === upperInput ||
                                        d.code.toUpperCase() === upperInput
                                    );

                                    if (matched) {
                                        return matched.name;
                                    } else {
                                        // 매칭 실패 시 로그 (처음 3개 행만)
                                        if (addedCount < 3) {
                                            log(`대리점 매칭 실패: "${inputDealer}" (dealersList에 없음, 드롭다운 "선택없음"으로 표시)`);
                                        }
                                        return ''; // DB에 없는 값은 빈 문자열 반환 (드롭다운 "선택없음")
                                    }
                                };

                                // 대소문자 구분 없이 통신사 매칭 함수
                                const matchCarrier = (inputCarrier) => {
                                    if (!inputCarrier) return '';
                                    const upperInput = inputCarrier.toUpperCase().trim();

                                    // 부분 문자열 매칭 (오타 허용)
                                    if (upperInput.includes('SK') || upperInput === 'S') return 'SK';
                                    if (upperInput.includes('KT') || upperInput === 'K') return 'KT';
                                    if (upperInput.includes('LG') || upperInput === 'L' || upperInput.includes('LGU')) return 'LG';
                                    if (upperInput.includes('알뜰') || upperInput.includes('MVNO')) return '알뜰';

                                    // 정확한 매칭
                                    const carrierMap = {
                                        'SK': 'SK',
                                        'KT': 'KT',
                                        'LG': 'LG',
                                        'LG U+': 'LG',
                                        'LGU+': 'LG',
                                        '알뜰': '알뜰',
                                        'MVNO': '알뜰'
                                    };

                                    for (const [key, value] of Object.entries(carrierMap)) {
                                        if (key.toUpperCase() === upperInput) {
                                            return value;
                                        }
                                    }
                                    return inputCarrier;
                                };

                                // 대소문자 구분 없이 개통방식 매칭 함수
                                const matchActivationType = (inputType) => {
                                    if (!inputType) return '';
                                    const upperInput = inputType.toUpperCase();
                                    const typeMap = {
                                        '신규': '신규',
                                        'NEW': '신규',
                                        '번이': '번이',
                                        'MNP': '번이',
                                        '번호이동': '번이',
                                        '기변': '기변',
                                        'UPGRADE': '기변',
                                        '기기변경': '기변',
                                        '유선': '유선',
                                        'WIRED': '유선',
                                        '2ND': '2nd',
                                        'SECOND': '2nd'
                                    };

                                    for (const [key, value] of Object.entries(typeMap)) {
                                        if (key.toUpperCase() === upperInput) {
                                            return value;
                                        }
                                    }
                                    return inputType;
                                };

                                // 휴대폰번호 포맷팅 함수 (숫자만 반환, 하이픈 제거 + 앞의 0 복구)
                                const formatPhoneNumber = (phoneStr) => {
                                    if (!phoneStr) return '';

                                    // 숫자만 추출
                                    let numbers = String(phoneStr).replace(/[^0-9]/g, '');
                                    if (numbers.length === 0) return '';

                                    // 엑셀에서 숫자로 읽혀서 앞의 0이 사라진 경우 (10자리 → 11자리로 복구)
                                    if (numbers.length === 10 && !numbers.startsWith('02')) {
                                        // 1052072940 → 01052072940
                                        numbers = '0' + numbers;
                                        log(`휴대폰번호 0 복구: ${phoneStr} → ${numbers}`);
                                    }

                                    // 하이픈 없이 숫자만 반환
                                    return numbers;
                                };

                                // 필수 필드 추출 (대소문자 매칭 적용)
                                const dealer = matchDealer(getColValue(1, ''));
                                const carrier = matchCarrier(getColValue(2, ''));
                                const activationType = matchActivationType(getColValue(3, ''));
                                const modelName = getColValue(4, '');

                                // 개통일 변환 (디버깅 로그 포함)
                                const rawSaleDate = getColValue(5, '');
                                let saleDate = formatDate(rawSaleDate);
                                if (addedCount < 3) {
                                    log(`개통일 변환 - 원본: ${rawSaleDate} (타입: ${typeof rawSaleDate}) → 변환: ${saleDate}`);
                                }

                                const phoneNumber = formatPhoneNumber(getColValue(6, '')); // 휴대폰번호 (6번 인덱스, 하이픈 자동 추가)
                                const customerName = getColValue(7, ''); // 고객명 (7번 인덱스)

                                // 생년월일 변환 (디버깅 로그 포함)
                                const rawBirthDate = getColValue(8, '');
                                const birthDate = formatBirthDate(rawBirthDate);

                                // 2000년 이후 데이터 특별 로깅
                                if (birthDate && birthDate.startsWith('20')) {
                                    log(`2000년 이후 생년월일 발견 - 원본: ${rawBirthDate} → 변환: ${birthDate}`);
                                }

                                if (addedCount < 3) {
                                    log(`생년월일 변환 - 원본: ${rawBirthDate} (타입: ${typeof rawBirthDate}) → 변환: ${birthDate}`);
                                }

                                // 판매일자가 없으면 오늘 날짜 자동 설정
                                if (!saleDate) {
                                    const today = new Date();
                                    saleDate = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
                                    log(`행 ${i}: 판매일자 자동 설정 - ${saleDate}`);
                                }

                                // 빈 행 체크 - 중요 필드가 모두 비어있으면 스킵
                                // 판매자 이름만 있고 다른 필드가 비어있는 경우를 필터링
                                if (!dealer && !carrier && !modelName && !phoneNumber && !customerName) {
                                    log(`행 ${i} 스킵 - 실질적인 데이터 없음 (판매자 이름만 있음)`);
                                    processedRows++;
                                    continue;
                                }

                                // 최소한 하나의 핵심 필드는 있어야 함 (대리점, 통신사, 모델명, 휴대폰번호 중)
                                if (!dealer && !carrier && !modelName && !phoneNumber) {
                                    log(`행 ${i} 스킵 - 핵심 데이터 없음`);
                                    processedRows++;
                                    continue;
                                }

                                // 컬럼 수에 따라 템플릿 버전 감지
                                // 새 템플릿 (21컬럼): 리베총계, 매출, 부/소세, 세전마진, 세후마진 제외
                                // 기존 템플릿 (26컬럼): 모든 컬럼 포함
                                const isNewTemplate = cols.length <= 22;

                                const newRowData = {
                                    id: nextId++, // 숫자 ID 사용 (문자열 ID는 Number() 변환 시 NaN 발생)
                                    salesperson: getColValue(0, '{{ Auth::user()->name ?? '' }}'), // 판매자
                                    dealer_name: dealer, // 대리점
                                    carrier: carrier, // 통신사
                                    activation_type: activationType, // 개통방식 (대소문자 매칭 적용)
                                    model_name: modelName, // 모델명
                                    sale_date: saleDate, // 개통일
                                    phone_number: phoneNumber, // 휴대폰번호 (6번 인덱스)
                                    customer_name: customerName, // 고객명 (7번 인덱스)
                                    customer_birth_date: birthDate, // 생년월일 (변환된 값 사용)

                                    // 금액 필드들
                                    base_price: parseNumber(getColValue(9)), // 액면/셋팅가 (9번)
                                    verbal1: parseNumber(getColValue(10)), // 구두1
                                    verbal2: parseNumber(getColValue(11)), // 구두2
                                    grade_amount: parseNumber(getColValue(12)), // 그레이드
                                    additional_amount: parseNumber(getColValue(13)), // 부가추가
                                    cash_activation: parseNumber(getColValue(14)), // 서류상현금개통
                                    usim_fee: parseNumber(getColValue(15)), // 유심비
                                    new_mnp_discount: parseNumber(getColValue(16)), // 신규/번이할인(-)
                                    deduction: parseNumber(getColValue(17)), // 차감(-)

                                    // 계산 필드들 - 새 템플릿은 자동계산, 기존 템플릿은 파일값 사용
                                    total_rebate: isNewTemplate ? 0 : parseNumber(getColValue(18)), // 리베총계
                                    settlement_amount: isNewTemplate ? 0 : parseNumber(getColValue(19)), // 매출
                                    tax: isNewTemplate ? 0 : parseNumber(getColValue(20)), // 부/소세
                                    cash_received: parseNumber(getColValue(isNewTemplate ? 18 : 21)), // 현금받음
                                    payback: parseNumber(getColValue(isNewTemplate ? 19 : 22)), // 페이백
                                    margin_before: isNewTemplate ? 0 : parseNumber(getColValue(23)), // 세전마진
                                    margin_after: isNewTemplate ? 0 : parseNumber(getColValue(24)), // 세후마진

                                    // 메모 필드
                                    memo: getColValue(isNewTemplate ? 20 : 25, ''), // 메모
                                    isPersisted: false
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
                                    const U = T - P + Q - R - S; // 매출 (신규/번이할인, 차감은 빼기)
                                    const V = Math.round(U * 0.1); // 세금 (10%)
                                    const Y = U - V + W + X; // 세전마진
                                    const Z = Y - V; // 세후마진 (세전마진 - 세금)

                                    // 계산된 값 업데이트
                                    newRowData.total_rebate = T;
                                    newRowData.settlement_amount = U;
                                    newRowData.tax = V;
                                    newRowData.margin_before = Y;
                                    newRowData.margin_after = Z;
                                }

                                    // 배치에 추가 (렌더링 최적화)
                                    newDataBatch.push(newRowData);
                                    addedCount++;
                                    processedRows++;
                                }

                                // 진행 상태 업데이트
                                if (totalRows > 50) {
                                    updateProgress(processedRows, totalRows);
                                }
                            };

                            // 배치 단위로 비동기 처리
                            const processAllBatches = async () => {
                                for (let i = dataStartRow; i < rows.length; i += BATCH_SIZE) {
                                    await processBatch(i, Math.min(i + BATCH_SIZE, rows.length));

                                    // UI가 멈추지 않도록 짧은 대기
                                    await new Promise(resolve => setTimeout(resolve, 10));
                                }

                                // 모든 데이터를 한 번에 salesData에 추가 (렌더링 최적화)
                                salesData.push(...newDataBatch);

                                // 테이블 한 번만 렌더링
                                renderTableRows();

                                // DOM 렌더링 완료 후 계산 실행 (비동기)
                                setTimeout(() => {
                                    salesData.forEach(row => {
                                        if (row.id) {
                                            calculateRow(row.id);
                                        }
                                    });
                                    // 통계 업데이트
                                    updateStatistics();
                                }, 100); // 100ms 후 실행

                                // 진행률 모달 닫기
                                showProgressModal(false);

                                // 성공 메시지 표시
                                showStatus(`${addedCount}개의 데이터를 성공적으로 추가했습니다`, 'success');

                                // 파일 입력 초기화
                                e.target.value = '';
                            };

                            // 비동기 처리 시작
                            await processAllBatches();
                        } catch (error) {
                            showStatus('Excel 파일 처리 중 오류 발생', 'error');
                            console.error('Excel parsing error:', error);
                        }
                    };

                    // 파일 형식에 따라 다르게 읽기
                    if (isExcel) {
                        reader.readAsArrayBuffer(file);
                    } else {
                        reader.readAsText(file, 'UTF-8');
                    }
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
                // Excel 템플릿 생성 (XLSX 형식)
                const today = new Date();
                const year = today.getFullYear();
                const month = String(today.getMonth() + 1).padStart(2, '0');
                const day = String(today.getDate()).padStart(2, '0');
                const todayStr = `${year}-${month}-${day}`;

                const templateData = [
                    ['판매자', '대리점', '통신사', '개통방식', '모델명', '개통일', '휴대폰번호', '고객명', '생년월일(6자리)',
                     '방문경로', '주소', '액면/셋팅가', '구두1', '구두2', '그레이드', '부가추가', '서류상현금개통', '유심비',
                     '신규/번이할인(-)', '차감(-)', '현금받음', '페이백', '메모'],
                    ['홍길동', 'SM', 'SK', '신규', 'iPhone 15', todayStr, '010-1234-5678', '김고객', '900101',
                     '매장방문', '서울 강남구 테헤란로 123', 100000, 50000, 30000, 20000, 10000, 30000, 8800,
                     0, 0, 20000, 15000, '예시 메모']
                ];

                // XLSX 워크북 생성
                const wb = XLSX.utils.book_new();
                const ws = XLSX.utils.aoa_to_sheet(templateData);

                // 컬럼 너비 설정 (방문경로, 주소 추가)
                ws['!cols'] = [
                    { wch: 10 }, // 판매자
                    { wch: 12 }, // 대리점
                    { wch: 8 },  // 통신사
                    { wch: 10 }, // 개통방식
                    { wch: 15 }, // 모델명
                    { wch: 12 }, // 개통일
                    { wch: 15 }, // 휴대폰번호
                    { wch: 10 }, // 고객명
                    { wch: 14 }, // 생년월일(6자리)
                    { wch: 12 }, // 방문경로
                    { wch: 25 }, // 주소
                    { wch: 12 }, // 액면/셋팅가
                    { wch: 12 }, // 구두1
                    { wch: 12 }, // 구두2
                    { wch: 12 }, // 그레이드
                    { wch: 12 }, // 부가추가
                    { wch: 15 }, // 서류상현금개통
                    { wch: 10 }, // 유심비
                    { wch: 15 }, // 신규/번이할인(-)
                    { wch: 10 }, // 차감(-)
                    { wch: 12 }, // 현금받음
                    { wch: 10 }, // 페이백
                    { wch: 20 }  // 메모
                ];

                // 숫자 컬럼 서식 설정 (콤마 없는 숫자)
                const range = XLSX.utils.decode_range(ws['!ref']);
                for (let R = 1; R <= range.e.r; R++) { // 헤더 제외 (R=1부터)
                    for (let C = 11; C <= 21; C++) { // 액면가(11)부터 페이백(21)까지 (방문경로, 주소 추가로 인덱스 +2)
                        const cellAddr = XLSX.utils.encode_cell({ r: R, c: C });
                        if (ws[cellAddr] && ws[cellAddr].v !== '') {
                            ws[cellAddr].t = 'n'; // 숫자 타입
                            ws[cellAddr].z = '0';  // 콤마 없는 숫자 서식
                        }
                    }
                }

                // 워크북에 시트 추가
                XLSX.utils.book_append_sheet(wb, ws, "개통표");

                // XLSX 파일 다운로드
                XLSX.writeFile(wb, `sales_template_${todayStr}.xlsx`);

                showStatus('Excel 템플릿(XLSX)을 다운로드했습니다', 'success');
            });

            // PM 요구사항 27컬럼 완전한 개통표 시스템 초기화 완료
        });

        // CSV 변환 함수
        function convertToCSV(data) {
            if (!data || data.length === 0) {
                return '';
            }

            // CSV 헤더 (기획: 리베총계, 매출, 부/소세, 세전마진, 세후마진 제외)
            const headers = [
                '날짜', '행번호', '대리점', '통신사', '개통방식', '시리얼넘버',
                '모델명', '용량', '전화번호', '고객명', '생년월일',
                '액면가', '구두1', '구두2', '그레이드', '부가추가',
                '서류상현금개통', '유심비', '신규번이할인', '차감',
                '현금받음', '페이백', '메모'
            ];

            // CSV 데이터 행 (기획: 리베총계, 매출, 부/소세, 세전마진, 세후마진 제외)
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
                row.cash_received || 0,
                row.payback || 0,
                row.memo || ''
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

        // 메모 팝업 관련 함수
        let currentMemoRowId = null;

        function openMemoPopup(rowId) {
            try {
                log('Opening memo popup for row ID:', rowId);
                currentMemoRowId = rowId;
                const row = salesData.find(r => r.id == rowId); // == 사용으로 타입 변환 허용
                log('Found row:', row);

                if (row) {
                    const memoContent = document.getElementById('memo-popup-content');
                    const memoModal = document.getElementById('memo-popup-modal');

                    if (memoContent && memoModal) {
                        memoContent.value = row.memo || '';
                        memoModal.style.display = 'flex';
                        log('Memo popup opened successfully');
                    } else {
                        console.error('Memo popup elements not found:', { memoContent, memoModal });
                    }
                } else {
                    console.error('Row not found for ID:', rowId);
                    log('Available rows:', salesData.map(r => r.id));
                }
            } catch (error) {
                console.error('Error opening memo popup:', error);
            }
        }

        function closeMemoPopup() {
            document.getElementById('memo-popup-modal').style.display = 'none';
            currentMemoRowId = null;
        }

        function saveMemoFromPopup() {
            if (currentMemoRowId) {
                const newMemo = document.getElementById('memo-popup-content').value;
                updateRowData(currentMemoRowId, 'memo', newMemo);

                // 입력 필드도 업데이트
                const inputField = document.getElementById(`memo-input-${currentMemoRowId}`);
                if (inputField) {
                    inputField.value = newMemo;
                    inputField.title = newMemo;
                }

                closeMemoPopup();
                showStatus('메모가 저장되었습니다.', 'success');
            }
        }

        // 구두1/구두2 메모 관련 변수 및 함수
        let currentVerbalMemoRowId = null;
        let currentVerbalMemoType = null; // 1 또는 2

        function openVerbalMemoPopup(rowId, verbalType) {
            log(`Opening verbal${verbalType} memo popup for row:`, rowId);
            try {
                currentVerbalMemoRowId = typeof rowId === 'string' ? parseInt(rowId) : rowId;
                currentVerbalMemoType = verbalType;

                const row = salesData.find(r => r.id === currentVerbalMemoRowId);
                if (row) {
                    const fieldName = `verbal${verbalType}_memo`;
                    const memoContent = document.getElementById('verbal-memo-content');
                    const memoTitle = document.getElementById('verbal-memo-title');
                    const memoModal = document.getElementById('verbal-memo-modal');

                    if (memoContent && memoModal && memoTitle) {
                        memoContent.value = row[fieldName] || '';
                        memoTitle.textContent = `구두${verbalType} 메모`;
                        memoModal.style.display = 'flex';
                    }
                }
            } catch (error) {
                console.error('Error opening verbal memo popup:', error);
            }
        }

        function closeVerbalMemoPopup() {
            document.getElementById('verbal-memo-modal').style.display = 'none';
            currentVerbalMemoRowId = null;
            currentVerbalMemoType = null;
        }

        function saveVerbalMemo() {
            if (currentVerbalMemoRowId && currentVerbalMemoType) {
                const newMemo = document.getElementById('verbal-memo-content').value;
                const fieldName = `verbal${currentVerbalMemoType}_memo`;
                updateRowData(currentVerbalMemoRowId, fieldName, newMemo);

                // 버튼 색상 업데이트 (메모가 있으면 노란색, 없으면 회색)
                const btn = document.querySelector(`button[onclick*="openVerbalMemoPopup(${currentVerbalMemoRowId}, ${currentVerbalMemoType})"]`);
                if (btn) {
                    if (newMemo && newMemo.trim()) {
                        btn.classList.remove('bg-gray-400');
                        btn.classList.add('bg-yellow-500');
                        btn.title = newMemo;
                    } else {
                        btn.classList.remove('bg-yellow-500');
                        btn.classList.add('bg-gray-400');
                        btn.title = '메모 추가';
                    }
                }

                closeVerbalMemoPopup();
                showStatus(`구두${currentVerbalMemoType} 메모가 저장되었습니다.`, 'success');
            }
        }

        // 대리점 관련 데이터 (예시)
        const dealerData = [
            { name: 'SK텔레콤 강남점', carrier: 'SK' },
            { name: 'SK텔레콤 서초점', carrier: 'SK' },
            { name: 'SK텔레콤 용산점', carrier: 'SK' },
            { name: 'KT 강남점', carrier: 'KT' },
            { name: 'KT 홍대점', carrier: 'KT' },
            { name: 'KT 신촌점', carrier: 'KT' },
            { name: 'LG U+ 강남점', carrier: 'LG' },
            { name: 'LG U+ 명동점', carrier: 'LG' },
            { name: 'LG U+ 종로점', carrier: 'LG' },
        ];

        // 대리점 필터링 함수
        function filterDealersByCarrier() {
            const selectedCarrier = document.getElementById('dealer-carrier-filter').value;
            const dealerSelect = document.getElementById('dealer-select-list');

            // 기존 옵션 초기화
            dealerSelect.innerHTML = '<option value="">대리점을 선택하세요</option>';

            // 필터링된 대리점 추가
            const filteredDealers = selectedCarrier === 'ALL'
                ? dealerData
                : dealerData.filter(d => d.carrier === selectedCarrier);

            filteredDealers.forEach(dealer => {
                const option = document.createElement('option');
                option.value = dealer.name;
                option.textContent = dealer.name;
                dealerSelect.appendChild(option);
            });
        }

        // 대리점 추가 모달 열기
        function openDealerAddModal() {
            document.getElementById('dealer-add-modal').style.display = 'flex';
            filterDealersByCarrier(); // 초기 로드
        }

        // 대리점 추가 모달 닫기
        function closeDealerAddModal() {
            document.getElementById('dealer-add-modal').style.display = 'none';
            document.getElementById('dealer-carrier-filter').value = 'ALL';
            document.getElementById('dealer-select-list').value = '';
            document.getElementById('new-dealer-name').value = '';
        }

        // 대리점 추가 처리
        function addDealerFromModal() {
            const selectedDealer = document.getElementById('dealer-select-list').value;
            const newDealerName = document.getElementById('new-dealer-name').value.trim();

            const dealerToAdd = newDealerName || selectedDealer;

            if (!dealerToAdd) {
                alert('대리점을 선택하거나 새 대리점명을 입력해주세요.');
                return;
            }

            // TODO: 실제 대리점 추가 로직 구현
            showStatus(`대리점 "${dealerToAdd}"가 추가되었습니다.`, 'success');
            closeDealerAddModal();
        }

        // 대리점 관리 함수
        function openDealerManagement() {
            // 대리점 추가 모달 열기
            openDealerAddModal();
        }

        // 필터링 관련 함수
        let filteredData = [];
        let allDealersFromDB = []; // DB에서 가져온 모든 대리점 목록

        // 대리점 목록 업데이트
        async function updateDealerFilterOptions() {
            const dealerFilter = document.getElementById('dealer-filter');
            const carrierFilter = document.getElementById('carrier-filter').value;

            // DB에서 대리점 목록 가져오기 (처음만 로드)
            if (allDealersFromDB.length === 0) {
                try {
                    const response = await fetch('/api/dealers', {
                        method: 'GET',
                        headers: {
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': window.csrfToken
                        }
                    });
                    const data = await response.json();
                    if (data.success && data.data) {
                        allDealersFromDB = data.data.map(dealer => ({
                            name: dealer.dealer_name,
                            carrier: dealer.carrier || ''
                        }));
                    }
                } catch (error) {
                    console.error('대리점 목록 로드 오류:', error);
                }
            }

            // 현재 데이터에 있는 대리점 + DB에 있는 대리점 병합
            const dealersFromData = [...new Set(salesData.map(row => row.dealer_name).filter(d => d))];
            const dealersFromDB = allDealersFromDB.map(d => d.name);
            const allDealers = [...new Set([...dealersFromData, ...dealersFromDB])];

            // 통신사 필터링
            let filteredDealers = allDealers;
            if (carrierFilter) {
                filteredDealers = allDealers.filter(dealer => {
                    // 현재 데이터에서 확인
                    const inData = salesData.some(row => row.dealer_name === dealer && row.carrier === carrierFilter);
                    // DB 데이터에서 확인
                    const inDB = allDealersFromDB.some(d => d.name === dealer && d.carrier === carrierFilter);
                    return inData || inDB;
                });
            }

            // 옵션 업데이트
            dealerFilter.innerHTML = '<option value="">전체 대리점</option>';
            filteredDealers.sort().forEach(dealer => {
                const option = document.createElement('option');
                option.value = dealer;
                option.textContent = dealer;
                dealerFilter.appendChild(option);
            });
        }

        // 필터 적용
        function applyFilters() {
            const carrierFilter = document.getElementById('carrier-filter').value;
            const dealerFilter = document.getElementById('dealer-filter').value;
            const salespersonFilter = document.getElementById('salesperson-filter').value.toLowerCase();
            const customerFilter = document.getElementById('customer-filter').value.toLowerCase();
            const dateFilter = document.getElementById('sale-date-filter').value;

            // 통신사 변경시 대리점 목록 업데이트
            if (event && event.target && event.target.id === 'carrier-filter') {
                updateDealerFilterOptions();
            }

            // 필터링 수행
            filteredData = salesData.filter(row => {
                let match = true;

                if (carrierFilter && row.carrier !== carrierFilter) match = false;
                if (dealerFilter && row.dealer_name !== dealerFilter) match = false;
                if (salespersonFilter && !(row.salesperson || '').toLowerCase().includes(salespersonFilter)) match = false;
                if (customerFilter && !(row.customer_name || '').toLowerCase().includes(customerFilter)) match = false;
                if (dateFilter && row.sale_date !== dateFilter) match = false;

                return match;
            });

            // 테이블 재렌더링
            renderFilteredData();
            updateFilterStatus();
        }

        // 필터링된 데이터 렌더링
        function renderFilteredData() {
            const tbody = document.getElementById('data-table-body');
            const dataToRender = filteredData.length > 0 || hasActiveFilters() ? filteredData : salesData;

            if (dataToRender.length === 0) {
                tbody.innerHTML = '<tr><td colspan="29" class="text-center py-4 text-gray-500">검색 결과가 없습니다.</td></tr>';
            } else if (dataToRender.length > 100) {
                // 가상 스크롤링 적용
                const htmlBuffer = [];
                for (let i = 0; i < Math.min(100, dataToRender.length); i++) {
                    htmlBuffer.push(createRowHTML(dataToRender[i]));
                }
                tbody.innerHTML = htmlBuffer.join('');
            } else {
                tbody.innerHTML = dataToRender.map(row => createRowHTML(row)).join('');
            }

            updateStatistics();
            updateSelectAllState();
        }

        // 필터 상태 확인
        function hasActiveFilters() {
            const carrierFilter = document.getElementById('carrier-filter');
            const dealerFilter = document.getElementById('dealer-filter');
            const salespersonFilter = document.getElementById('salesperson-filter');
            const customerFilter = document.getElementById('customer-filter');
            const saleDateFilter = document.getElementById('sale-date-filter');

            return (carrierFilter && carrierFilter.value) ||
                   (dealerFilter && dealerFilter.value) ||
                   (salespersonFilter && salespersonFilter.value) ||
                   (customerFilter && customerFilter.value) ||
                   (saleDateFilter && saleDateFilter.value);
        }

        // 필터 상태 표시
        function updateFilterStatus() {
            const status = document.getElementById('filterStatus');
            const count = filteredData.length;
            const total = salesData.length;

            if (hasActiveFilters()) {
                status.textContent = `${count}개 검색됨 (전체 ${total}개)`;
                status.style.display = 'inline-block';
            } else {
                status.style.display = 'none';
            }
        }

        // 모든 필터 초기화
        function clearAllFilters() {
            document.getElementById('carrier-filter').value = '';
            document.getElementById('dealer-filter').value = '';
            document.getElementById('salesperson-filter').value = '';
            document.getElementById('customer-filter').value = '';
            document.getElementById('sale-date-filter').value = '';

            updateDealerFilterOptions().then(() => applyFilters());
            showStatus('모든 필터가 초기화되었습니다.', 'info');
        }
    </script>
</body>
</html>
