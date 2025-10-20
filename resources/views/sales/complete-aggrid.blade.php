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
                <div class="text-sm opacity-90">총 정산금</div>
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
        // 선택된 행의 ID를 저장하는 Set (가상 스크롤링 시 상태 보존용)
        let selectedRowIds = new Set();
        
        // 새로운 행 데이터 구조 (DB 스키마와 1:1 매핑)
        function createNewRow() {
            return {
                id: nextId++,
                isPersisted: false, // 아직 DB에 저장되지 않은 임시 행
                salesperson: '',
                dealer_name: '',
                carrier: '', // 기본값 제거 - 사용자가 선택하도록
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
                               onchange="console.log('Checkbox changed for ID:', '${row.id}'); toggleRowSelection('${row.id}'); updateSelectAllState();">
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
                    <!-- 11. 생년월일 -->
                    <td class="px-2 py-2">
                        <input type="date" value="${safeDate(row.customer_birth_date)}"
                               onchange="updateRowData('${row.id}', 'customer_birth_date', this.value)"
                               class="field-date">
                    </td>
                    <!-- 12. 액면/셋팅가 -->
                    <td class="px-2 py-2">
                        <input type="number" value="${safeNumber(row.base_price)}"
                               onchange="updateRowData('${row.id}', 'base_price', isNaN(parseFloat(this.value)) ? 0 : parseFloat(this.value))"
                               class="field-money" placeholder="300000">
                    </td>
                    <!-- 13. 구두1 -->
                    <td class="px-2 py-2">
                        <input type="number" value="${row.verbal1}" 
                               onchange="updateRowData('${row.id}', 'verbal1', parseInt(this.value) || 0); calculateRow('${row.id}')"
                               class="field-money" placeholder="50000">
                    </td>
                    <!-- 14. 구두2 -->
                    <td class="px-2 py-2">
                        <input type="number" value="${row.verbal2}" 
                               onchange="updateRowData('${row.id}', 'verbal2', parseInt(this.value) || 0); calculateRow('${row.id}')"
                               class="field-money" placeholder="30000">
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
                    <!-- 19. 신규,번이(-800) -->
                    <td class="px-2 py-2">
                        <input type="number" value="${row.new_mnp_discount}" 
                               onchange="updateRowData('${row.id}', 'new_mnp_discount', parseInt(this.value) || 0); calculateRow('${row.id}')"
                               class="field-policy minus-field" placeholder="-800">
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
                               onchange="updateRowData('${row.id}', 'cash_received', parseInt(this.value) || 0); calculateRow('${row.id}')"
                               class="field-money plus-field" placeholder="0">
                    </td>
                    <!-- 25. 페이백 -->
                    <td class="px-2 py-2">
                        <input type="number" value="${row.payback}" 
                               onchange="updateRowData('${row.id}', 'payback', parseInt(this.value) || 0); calculateRow('${row.id}')"
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
                        <div class="flex items-center space-x-1">
                            <input type="text" value="${safeValue(row.memo)}"
                                   id="memo-input-${row.id}"
                                   onchange="updateRowData('${row.id}', 'memo', this.value)"
                                   class="w-24 px-1 py-1 border rounded text-xs"
                                   placeholder="메모 입력"
                                   title="${safeValue(row.memo)}">
                            <button onclick="console.log('Button clicked, ID:', ${row.id}); openMemoPopup('${row.id}')"
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
            console.log(`🔧 updateRowData called: id=${id} (type: ${typeof id}), field=${field}, value=${value}`);

            // ID 타입 변환: 문자열이면 숫자로 변환
            const numericId = typeof id === 'string' ? parseInt(id) : id;
            console.log(`🔍 Searching for row with id=${numericId} (type: ${typeof numericId})`);

            const row = salesData.find(r => {
                console.log(`  Comparing: r.id=${r.id} (${typeof r.id}) === numericId=${numericId} (${typeof numericId}) = ${r.id === numericId}`);
                return r.id === numericId;
            });

            if (row) {
                console.log(`✅ Row found, updating ${field}: ${row[field]} → ${value}`);
                row[field] = value;
                console.log(`✅ Updated successfully: ${field} = ${row[field]}`);

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
                console.error(`❌ Row NOT found! salesData length: ${salesData.length}, searching for id: ${numericId}`);
                console.log('All IDs in salesData:', salesData.map(r => r.id));
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

            // Z = Y - V (세후마진 = 세전마진 - 세금)
            row.margin_after_tax = row.margin_before_tax - row.tax;
            
            // UI 업데이트 (DOM 요소 존재 확인)
            const rebateEl = document.getElementById(`rebate-${id}`);
            if (rebateEl) rebateEl.textContent = rebateTotal.toLocaleString() + '원';

            const settlementEl = document.getElementById(`settlement-${id}`);
            if (settlementEl) settlementEl.textContent = settlementAmount.toLocaleString() + '원';

            const taxEl = document.getElementById(`tax-${id}`);
            if (taxEl) taxEl.textContent = row.tax.toLocaleString() + '원';

            const marginBeforeEl = document.getElementById(`margin-before-${id}`);
            if (marginBeforeEl) marginBeforeEl.textContent = row.margin_before_tax.toLocaleString() + '원';

            const marginAfterEl = document.getElementById(`margin-after-${id}`);
            if (marginAfterEl) marginAfterEl.textContent = row.margin_after_tax.toLocaleString() + '원';
            
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
            console.log('toggleRowSelection called with ID:', idStr);
            console.log('Current selectedRowIds:', Array.from(selectedRowIds));

            if (selectedRowIds.has(idStr)) {
                selectedRowIds.delete(idStr);
                console.log('Removed ID:', idStr);
            } else {
                selectedRowIds.add(idStr);
                console.log('Added ID:', idStr);
            }
            console.log('Updated selectedRowIds:', Array.from(selectedRowIds));
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

            console.log('updateSelectionCount called, selectedCount:', selectedCount);
            console.log('Badge element:', badge);

            if (selectedCount > 0) {
                if (badge) {
                    badge.textContent = selectedCount;
                    badge.classList.remove('hidden');
                    console.log('Badge updated with count:', selectedCount);
                }
                showStatus(`${selectedCount}개 항목 선택됨`, 'info');
            } else {
                if (badge) {
                    badge.classList.add('hidden');
                    console.log('Badge hidden');
                }
            }
        }

        // 전체 삭제 기능
        async function deleteAll() {
            if (!confirm('⚠️ 주의: 모든 데이터가 삭제됩니다.\n\n정말로 전체 데이터를 삭제하시겠습니까?\n이 작업은 되돌릴 수 없습니다.')) {
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
            console.log('bulkDelete called');
            console.log('selectedRowIds size:', selectedRowIds.size);
            console.log('selectedRowIds contents:', Array.from(selectedRowIds));

            if (selectedRowIds.size === 0) {
                console.log('No rows selected, showing warning');
                showStatus('삭제할 행을 선택해주세요.', 'warning');
                return;
            }

            if (confirm(`선택한 ${selectedRowIds.size}개 행을 삭제하시겠습니까?`)) {
                const idsToDelete = Array.from(selectedRowIds);

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

                    // 필터가 적용된 경우 filteredData도 업데이트
                    if (filteredData.length > 0) {
                        filteredData = filteredData.filter(row => !idsToDelete.includes(row.id));
                    }

                    // 삭제된 행들을 selectedRowIds에서도 제거
                    idsToDelete.forEach(id => selectedRowIds.delete(String(id)));

                    // 필터 상태에 따라 적절히 렌더링
                    if (hasActiveFilters()) {
                        renderFilteredData();
                    } else {
                        renderTableRows();
                    }

                    // 전체 선택 체크박스 상태 업데이트
                    updateSelectAllState();
                    showStatus(`${idsToDelete.length}개 행이 삭제되었습니다.`, 'success');
                } catch (error) {
                    // 일괄 삭제 오류 발생
                    showStatus('삭제 중 오류가 발생했습니다.', 'error');
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
                console.log(`판매일자 자동 설정: ${row.sale_date}`);
            }

            // 통신사 검증 - 비어있어도 허용 (선택사항)
            if (row.carrier) {
                const validCarriers = carriersList.length > 0
                    ? carriersList.map(c => c.name)
                    : ['SK', 'KT', 'LG', 'LG U+', '알뜰'];

                // carrier가 'LG U+'인 경우 'LG'로도 매칭되도록 처리
                const isValidCarrier = validCarriers.includes(row.carrier) ||
                                      (row.carrier === 'LG' && validCarriers.includes('LG U+'));

                if (!isValidCarrier) {
                    errors.push(`유효한 통신사를 선택하세요 (${validCarriers.join('/')})`);
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
                console.log(`Row ${index + 1}:`, {
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
                    console.log(`Row ${index + 1} added to validData`);
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
            console.log(`📊 Save operation breakdown:`, {
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

                    console.log(`💾 [저장] Row ${idx + 1}:`, {
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
                    };

                    return rowData;
                })
            };

            // 디버깅: 요청 데이터 확인
            console.log('=== BULK SAVE REQUEST ===');
            console.log('Total valid rows:', validData.length);
            console.log('📊 salesData 상태:', salesData.slice(0, 3).map(r => ({
                id: r.id,
                carrier: r.carrier,
                isPersisted: r.isPersisted
            })));
            console.log('📦 요청 데이터:', requestBody.sales.slice(0, 3).map(row => ({
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
                if (data.success) {
                    showStatus('✅ ' + data.message, 'success');
                    // Data saved successfully

                    // 임시 ID를 실제 DB ID로 교체
                    if (data.id_mappings && Object.keys(data.id_mappings).length > 0) {
                        console.log('🔄 ID 매핑 적용 중...', data.id_mappings);
                        salesData.forEach(row => {
                            // 임시 ID가 매핑에 있으면 실제 DB ID로 교체
                            if (data.id_mappings[row.id]) {
                                const oldId = row.id;
                                const newId = data.id_mappings[row.id];
                                row.id = newId;
                                console.log(`✅ ID 교체: ${oldId} → ${newId}`);
                            }
                            row.isPersisted = true;
                        });
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
                console.log('API 응답 실패, 폴백 대리점 목록 사용');
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
                console.error('❌ 대리점 목록 로드 실패:', error);
                console.log('에러 발생, 폴백 대리점 목록 사용');

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
                console.error('❌ 통신사 목록 로드 실패:', error);
                console.log('에러 발생, 폴백 통신사 목록 사용');

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
                            console.log('First sale object from API:', {
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
                            memo: sale.memo || ''
                        };
                    });

                    // 그리드 렌더링
                    renderTableRows();
                    // DOM 렌더링 완료 후 계산 실행 (비동기)
                    setTimeout(() => {
                        salesData.forEach(row => calculateRow(row.id));
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
                    console.log('파일 정보:', file.name, file.type, file.size);

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
                                console.log('Excel 파일 파싱 완료:', rows.length, '행');
                                console.log('첫 번째 행:', rows[0]);
                                if (rows.length > 5) {
                                    console.log('6번째 행 (데이터):', rows[5]);
                                    console.log('7번째 행 (데이터):', rows[6]);
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
                                '리베총계', '정산금', '부/소 세', '현금받음(+표기)', '페이백(-표기)',
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
                                        console.log(`헤더 찾음 - 행 ${i}:`, row);
                                        break;
                                    }
                                }
                            }

                            // 헤더를 찾지 못했으면 첫 번째 행을 헤더로 가정
                            if (!headerRow) {
                                headerRow = rows[0];
                                dataStartRow = 1;
                                console.log('기본 헤더 사용 - 첫 번째 행');
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
                                        console.log(`행 ${i} 데이터:`, cols);
                                        console.log('컬럼 수:', cols.length);
                                    }

                                    if (cols.length < 10) {
                                        if (addedCount < 5) {
                                            console.log(`행 ${i} 스킵 - 컬럼 수 부족`);
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
                                const formatBirthDate = (dateStr) => {
                                    if (!dateStr) return '';

                                    // 엑셀 날짜 시리얼 번호 처리 (숫자로 들어오는 경우)
                                    if (typeof dateStr === 'number') {
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
                                            return `${year}-${month}-${day}`;
                                        }
                                    }

                                    if (cleanStr.length === 6) {
                                        // YYMMDD 형식
                                        const year = parseInt(cleanStr.substring(0, 2));
                                        const fullYear = year > 50 ? '19' + cleanStr.substring(0, 2) : '20' + cleanStr.substring(0, 2);
                                        return `${fullYear}-${cleanStr.substring(2, 4)}-${cleanStr.substring(4, 6)}`;
                                    } else if (cleanStr.length === 8) {
                                        // YYYYMMDD 형식
                                        return `${cleanStr.substring(0, 4)}-${cleanStr.substring(4, 6)}-${cleanStr.substring(6, 8)}`;
                                    }

                                    // 슬래시나 점으로 구분된 경우
                                    if (str.includes('/') || str.includes('.')) {
                                        const parts = str.split(/[\/\.]/).filter(p => p);
                                        if (parts.length === 3) {
                                            // YYYY/MM/DD 또는 YY/MM/DD 형식
                                            const year = parts[0].length === 4 ? parts[0] : (parseInt(parts[0]) > 50 ? '19' + parts[0] : '20' + parts[0]);
                                            return `${year}-${parts[1].padStart(2, '0')}-${parts[2].padStart(2, '0')}`;
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
                                    console.log(`\n=== 행 ${i} 매핑 시작 ===`);
                                    console.log('0: 판매자 =', getColValue(0));
                                    console.log('1: 대리점 =', getColValue(1));
                                    console.log('2: 통신사 =', getColValue(2));
                                    console.log('3: 개통방식 =', getColValue(3));
                                    console.log('4: 모델명 =', getColValue(4));
                                    console.log('5: 개통일 =', getColValue(5));
                                    console.log('6: 휴대폰번호 =', getColValue(6));
                                    console.log('7: 고객명 =', getColValue(7));
                                    console.log('8: 생년월일 =', getColValue(8));
                                    console.log('9: 액면가 =', getColValue(9));
                                }

                                // 대소문자 구분 없이 대리점 매칭 함수
                                const matchDealer = (inputDealer) => {
                                    if (!inputDealer) return '';
                                    const upperInput = inputDealer.toUpperCase();
                                    const matched = dealersList.find(d =>
                                        d.name.toUpperCase() === upperInput ||
                                        d.code.toUpperCase() === upperInput
                                    );
                                    return matched ? matched.name : inputDealer;
                                };

                                // 대소문자 구분 없이 통신사 매칭 함수
                                const matchCarrier = (inputCarrier) => {
                                    if (!inputCarrier) return '';
                                    const upperInput = inputCarrier.toUpperCase();
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
                                        '번이': '번이',
                                        '기변': '기변',
                                        '번호이동': '번이',
                                        '기기변경': '기변'
                                    };

                                    for (const [key, value] of Object.entries(typeMap)) {
                                        if (key.toUpperCase() === upperInput) {
                                            return value;
                                        }
                                    }
                                    return inputType;
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
                                    console.log(`개통일 변환 - 원본: ${rawSaleDate} (타입: ${typeof rawSaleDate}) → 변환: ${saleDate}`);
                                }

                                const phoneNumber = getColValue(6, ''); // 휴대폰번호 (6번 인덱스)
                                const customerName = getColValue(7, ''); // 고객명 (7번 인덱스)

                                // 생년월일 변환 (디버깅 로그 포함)
                                const rawBirthDate = getColValue(8, '');
                                const birthDate = formatBirthDate(rawBirthDate);
                                if (addedCount < 3) {
                                    console.log(`생년월일 변환 - 원본: ${rawBirthDate} (타입: ${typeof rawBirthDate}) → 변환: ${birthDate}`);
                                }

                                // 판매일자가 없으면 오늘 날짜 자동 설정
                                if (!saleDate) {
                                    const today = new Date();
                                    saleDate = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
                                    console.log(`행 ${i}: 판매일자 자동 설정 - ${saleDate}`);
                                }

                                // 빈 행 체크 - 중요 필드가 모두 비어있으면 스킵
                                // 판매자 이름만 있고 다른 필드가 비어있는 경우를 필터링
                                if (!dealer && !carrier && !modelName && !phoneNumber && !customerName) {
                                    console.log(`행 ${i} 스킵 - 실질적인 데이터 없음 (판매자 이름만 있음)`);
                                    processedRows++;
                                    continue;
                                }

                                // 최소한 하나의 핵심 필드는 있어야 함 (대리점, 통신사, 모델명, 휴대폰번호 중)
                                if (!dealer && !carrier && !modelName && !phoneNumber) {
                                    console.log(`행 ${i} 스킵 - 핵심 데이터 없음`);
                                    processedRows++;
                                    continue;
                                }

                                const newRowData = {
                                    id: 'row-' + Date.now() + '-' + i,
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
                                    new_mnp_discount: parseNumber(getColValue(16)), // 신규/번이할인
                                    deduction: parseNumber(getColValue(17)), // 차감

                                    // 계산 필드들
                                    total_rebate: parseNumber(getColValue(18)), // 리베총계
                                    settlement_amount: parseNumber(getColValue(19)), // 정산금
                                    tax: parseNumber(getColValue(20)), // 부/소세
                                    cash_received: parseNumber(getColValue(21)), // 현금받음
                                    payback: parseNumber(getColValue(22)), // 페이백
                                    margin_before: parseNumber(getColValue(23)), // 세전마진
                                    margin_after: parseNumber(getColValue(24)), // 세후마진

                                    // 메모 필드
                                    memo: getColValue(25, ''), // 메모 (25번)
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
                                    const U = T - P + Q + R + S; // 정산금
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
                    ['판매자', '대리점', '통신사', '개통방식', '모델명', '개통일', '휴대폰번호', '고객명', '생년월일',
                     '액면/셋팅가', '구두1', '구두2', '그레이드', '부가추가', '서류상현금개통', '유심비',
                     '신규/번이할인', '차감', '리베총계', '정산금', '부/소세', '현금받음', '페이백',
                     '세전마진', '세후마진', '메모'],
                    ['홍길동', 'SM', 'SK', '신규', 'iPhone 15', todayStr, '010-1234-5678', '김고객', '1990-01-01',
                     100000, 50000, 30000, 20000, 10000, 30000, 8800,
                     10000, 5000, '', '', '', 20000, 15000,
                     '', '', '예시 메모']
                ];

                // XLSX 워크북 생성
                const wb = XLSX.utils.book_new();
                const ws = XLSX.utils.aoa_to_sheet(templateData);

                // 컬럼 너비 설정
                ws['!cols'] = [
                    { wch: 10 }, // 판매자
                    { wch: 12 }, // 대리점
                    { wch: 8 },  // 통신사
                    { wch: 10 }, // 개통방식
                    { wch: 15 }, // 모델명
                    { wch: 12 }, // 개통일
                    { wch: 15 }, // 휴대폰번호
                    { wch: 10 }, // 고객명
                    { wch: 12 }, // 생년월일
                    { wch: 12 }, // 액면/셋팅가
                    { wch: 12 }, // 구두1
                    { wch: 12 }, // 구두2
                    { wch: 12 }, // 그레이드
                    { wch: 12 }, // 부가추가
                    { wch: 15 }, // 서류상현금개통
                    { wch: 10 }, // 유심비
                    { wch: 15 }, // 신규/번이할인
                    { wch: 10 }, // 차감
                    { wch: 12 }, // 리베총계
                    { wch: 12 }, // 정산금
                    { wch: 10 }, // 부/소세
                    { wch: 12 }, // 현금받음
                    { wch: 10 }, // 페이백
                    { wch: 12 }, // 세전마진
                    { wch: 12 }, // 세후마진
                    { wch: 20 }  // 메모
                ];

                // 숫자 컬럼 서식 설정 (콤마 없는 숫자)
                const range = XLSX.utils.decode_range(ws['!ref']);
                for (let R = 1; R <= range.e.r; R++) { // 헤더 제외 (R=1부터)
                    for (let C = 9; C <= 24; C++) { // 액면가(9)부터 세후마진(24)까지
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

        // 메모 팝업 관련 함수
        let currentMemoRowId = null;

        function openMemoPopup(rowId) {
            try {
                console.log('Opening memo popup for row ID:', rowId);
                currentMemoRowId = rowId;
                const row = salesData.find(r => r.id == rowId); // == 사용으로 타입 변환 허용
                console.log('Found row:', row);

                if (row) {
                    const memoContent = document.getElementById('memo-popup-content');
                    const memoModal = document.getElementById('memo-popup-modal');

                    if (memoContent && memoModal) {
                        memoContent.value = row.memo || '';
                        memoModal.style.display = 'flex';
                        console.log('Memo popup opened successfully');
                    } else {
                        console.error('Memo popup elements not found:', { memoContent, memoModal });
                    }
                } else {
                    console.error('Row not found for ID:', rowId);
                    console.log('Available rows:', salesData.map(r => r.id));
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
                if (salespersonFilter && !row.salesperson.toLowerCase().includes(salespersonFilter)) match = false;
                if (customerFilter && !row.customer_name.toLowerCase().includes(customerFilter)) match = false;
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
            return document.getElementById('carrier-filter').value ||
                   document.getElementById('dealer-filter').value ||
                   document.getElementById('salesperson-filter').value ||
                   document.getElementById('customer-filter').value ||
                   document.getElementById('sale-date-filter').value;
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
