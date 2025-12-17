<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>일일지출 관리 - YKP ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css" rel="stylesheet">
    <style>
        .expense-form {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .expense-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 20px;
        }
        .form-group {
            display: flex;
            flex-direction: column;
        }
        .form-label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 6px;
            font-size: 14px;
        }
        .form-input {
            padding: 10px 12px;
            border: 1px solid #d1d5db;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.2s;
        }
        .form-input:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
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
        .btn-primary:hover {
            background: #2563eb;
        }
        .expense-list {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .expense-item {
            padding: 16px;
            border-bottom: 1px solid #f3f4f6;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .expense-item:last-child {
            border-bottom: none;
        }
        .expense-info {
            flex: 1;
        }
        .expense-category {
            background: #eff6ff;
            color: #1e40af;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .expense-amount {
            font-weight: 700;
            font-size: 16px;
            color: #ef4444;
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
        .summary-label {
            font-size: 14px;
            color: #6b7280;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- 헤더 -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-gray-900">일일지출 관리</h1>
                    <span class="ml-2 px-2 py-1 text-xs bg-orange-100 text-orange-800 rounded">Daily Expenses</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/" class="text-gray-600 hover:text-gray-900">메인 대시보드</a>
                    <a href="/admin" class="text-gray-600 hover:text-gray-900">관리자</a>
                </div>
            </div>
        </div>
    </header>

    <!-- 메인 컨텐츠 -->
    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- 조회 필터 -->
        <div class="expense-form" style="margin-bottom: 20px;">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">조회 필터</h2>
            <div class="expense-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                <!-- 기간 필터 -->
                <div class="form-group">
                    <label class="form-label">시작일</label>
                    <input type="date" id="filterStartDate" class="form-input" onchange="onDateChange()">
                </div>
                <div class="form-group">
                    <label class="form-label">종료일</label>
                    <input type="date" id="filterEndDate" class="form-input" onchange="onDateChange()">
                </div>

                @if(($viewScope ?? 'store') === 'all')
                <!-- 본사용: 지사/매장 검색 -->
                <div class="form-group">
                    <label class="form-label">지사 선택</label>
                    <select id="filterBranch" class="form-input" onchange="onBranchChange()">
                        <option value="">전체 지사</option>
                    </select>
                </div>
                <div class="form-group" style="position: relative;">
                    <label class="form-label">매장 검색</label>
                    <input type="text" id="storeSearchInput" class="form-input" placeholder="매장명 입력..." autocomplete="off" oninput="onStoreSearch(this.value)">
                    <input type="hidden" id="filterStoreId" value="">
                    <div id="storeSearchResults" style="display:none; position:absolute; top:100%; left:0; right:0; background:white; border:1px solid #d1d5db; border-radius:6px; max-height:200px; overflow-y:auto; z-index:100; box-shadow:0 4px 6px rgba(0,0,0,0.1);"></div>
                </div>
                @endif

                <div class="form-group" style="justify-content: flex-end; align-self: flex-end;">
                    <button type="button" onclick="resetFilter()" class="btn" style="background: #f3f4f6; color: #374151;">필터 초기화</button>
                </div>
            </div>
        </div>

        <!-- 요약 카드 -->
        <div class="summary-cards">
            <div class="summary-card">
                <div class="summary-value" id="todayTotal">0원</div>
                <div class="summary-label">오늘 지출</div>
            </div>
            <div class="summary-card">
                <div class="summary-value" id="monthTotal">0원</div>
                <div class="summary-label">이번 달 지출</div>
            </div>
            <div class="summary-card">
                <div class="summary-value" id="expenseCount">0건</div>
                <div class="summary-label">총 지출 건수</div>
            </div>
            <div class="summary-card">
                <div class="summary-value" id="avgAmount">0원</div>
                <div class="summary-label">평균 지출액</div>
            </div>
        </div>

        <!-- 지출 입력 폼 (매장 권한만 등록 가능) -->
        @if(($viewScope ?? 'store') === 'store')
        <div class="expense-form">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">새 지출 등록</h2>
            <form id="expenseForm">
                <div class="expense-grid">
                    <div class="form-group">
                        <label class="form-label">지출일</label>
                        <input type="date" name="expense_date" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">지출 카테고리</label>
                        <select name="category" class="form-input" required>
                            <option value="">선택해주세요</option>
                            <option value="상담비">상담비</option>
                            <option value="메일접수비">메일접수비</option>
                            <option value="기타">기타 운영비</option>
                            <option value="교통비">교통비</option>
                            <option value="식대">식대</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">지출 금액</label>
                        <input type="number" name="amount" class="form-input" placeholder="예: 50000" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">결제 방법</label>
                        <select name="payment_method" class="form-input">
                            <option value="">선택해주세요</option>
                            <option value="현금">현금</option>
                            <option value="카드">카드</option>
                            <option value="계좌이체">계좌이체</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">승인자</label>
                        <input type="text" name="approved_by" class="form-input" placeholder="예: 김점장">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">지출 내용</label>
                    <textarea name="description" class="form-input" rows="2" placeholder="상세 내용을 입력해주세요"></textarea>
                </div>
                
                <div class="form-group">
                    <label class="form-label">영수증 번호</label>
                    <input type="text" name="receipt_number" class="form-input" placeholder="예: RCP-2024-001">
                </div>

                <div class="flex gap-4 mt-6">
                    <button type="submit" class="btn btn-primary">지출 등록</button>
                    <button type="button" onclick="resetForm()" class="btn" style="background: #f3f4f6; color: #374151;">초기화</button>
                </div>
            </form>
        </div>
        @endif

        <!-- 지출 내역 목록 -->
        <div class="expense-list">
            <div style="padding: 20px; border-bottom: 1px solid #f3f4f6;">
                <h3 class="text-lg font-semibold text-gray-900">최근 지출 내역</h3>
            </div>
            <div id="expensesList">
                <!-- 동적으로 로드됨 -->
            </div>
        </div>
    </main>

    <script>
        // 권한 정보 (서버에서 전달)
        const VIEW_SCOPE = '{{ $viewScope ?? "store" }}';
        const USER_ROLE = '{{ $userRole ?? "store" }}';
        const BRANCH_ID = '{{ $branchId ?? "" }}';
        const STORE_ID = '{{ $storeId ?? "" }}';

        // 필터 상태
        let selectedBranchId = '';
        let selectedStoreId = '';
        let filterStartDate = '';
        let filterEndDate = '';
        let allBranches = [];
        let allStores = [];

        // 권한에 따른 API 파라미터 생성
        function getScopeParams() {
            const params = new URLSearchParams();

            // 기간 필터
            if (filterStartDate && filterEndDate) {
                params.append('start_date', filterStartDate);
                params.append('end_date', filterEndDate);
            }

            // 본사의 경우: 선택한 필터 적용
            if (VIEW_SCOPE === 'all') {
                if (selectedStoreId) {
                    params.append('store_id', selectedStoreId);
                } else if (selectedBranchId) {
                    params.append('branch_id', selectedBranchId);
                }
                // 둘 다 없으면 전체 조회
            } else if (VIEW_SCOPE === 'branch' && BRANCH_ID) {
                params.append('branch_id', BRANCH_ID);
            } else if (VIEW_SCOPE === 'store' && STORE_ID) {
                params.append('store_id', STORE_ID);
            }

            return params.toString() ? '&' + params.toString() : '';
        }

        // 기간 변경 이벤트
        function onDateChange() {
            filterStartDate = document.getElementById('filterStartDate')?.value || '';
            filterEndDate = document.getElementById('filterEndDate')?.value || '';
            loadExpenses();
            loadSummary();
        }

        // 매장 검색 (자동완성)
        let searchTimeout = null;
        function onStoreSearch(query) {
            clearTimeout(searchTimeout);
            const resultsDiv = document.getElementById('storeSearchResults');

            if (!query || query.length < 1) {
                resultsDiv.style.display = 'none';
                return;
            }

            searchTimeout = setTimeout(() => {
                let filteredStores = allStores;

                // 지사 선택된 경우 해당 지사만
                if (selectedBranchId) {
                    filteredStores = allStores.filter(s => s.branch_id == selectedBranchId);
                }

                // 검색어로 필터
                const results = filteredStores.filter(s =>
                    s.name.toLowerCase().includes(query.toLowerCase()) ||
                    s.code.toLowerCase().includes(query.toLowerCase())
                ).slice(0, 20); // 최대 20개만

                if (results.length === 0) {
                    resultsDiv.innerHTML = '<div style="padding:10px;color:#6b7280;">검색 결과 없음</div>';
                } else {
                    resultsDiv.innerHTML = results.map(s => `
                        <div style="padding:10px;cursor:pointer;border-bottom:1px solid #f3f4f6;"
                             onmouseover="this.style.background='#f3f4f6'"
                             onmouseout="this.style.background='white'"
                             onclick="selectStore(${s.id}, '${s.name}')">
                            <div style="font-weight:600;">${s.name}</div>
                            <div style="font-size:12px;color:#6b7280;">${s.code} · ${s.branch?.name || ''}</div>
                        </div>
                    `).join('');
                }
                resultsDiv.style.display = 'block';
            }, 200);
        }

        // 매장 선택
        function selectStore(storeId, storeName) {
            selectedStoreId = storeId;
            document.getElementById('storeSearchInput').value = storeName;
            document.getElementById('filterStoreId').value = storeId;
            document.getElementById('storeSearchResults').style.display = 'none';
            loadExpenses();
            loadSummary();
        }

        // 검색창 외부 클릭 시 결과 숨김
        document.addEventListener('click', function(e) {
            const resultsDiv = document.getElementById('storeSearchResults');
            const searchInput = document.getElementById('storeSearchInput');
            if (resultsDiv && searchInput && !searchInput.contains(e.target) && !resultsDiv.contains(e.target)) {
                resultsDiv.style.display = 'none';
            }
        });

        // 페이지 로드 시 초기화
        document.addEventListener('DOMContentLoaded', async function() {
            loadExpenses();
            loadSummary();

            // 본사인 경우 지사/매장 목록 로드
            if (VIEW_SCOPE === 'all') {
                await loadBranches();
                await loadStores();
            }

            // 오늘 날짜 기본값 설정
            const expenseDateInput = document.querySelector('input[name="expense_date"]');
            if (expenseDateInput) {
                expenseDateInput.value = new Date().toISOString().split('T')[0];
            }
        });

        // 지사 목록 로드 (본사용)
        async function loadBranches() {
            try {
                const response = await fetch('/api/dev/stores/branches');
                const data = await response.json();
                allBranches = data.data || data;

                const select = document.getElementById('filterBranch');
                if (!select) return;

                select.innerHTML = '<option value="">전체 지사</option>';
                allBranches.forEach(branch => {
                    select.innerHTML += `<option value="${branch.id}">${branch.name}</option>`;
                });
            } catch (error) {
                console.error('지사 목록 로드 오류:', error);
            }
        }

        // 매장 목록 로드 (본사용 - 검색용 데이터)
        async function loadStores() {
            try {
                const response = await fetch('/api/dev/stores');
                const data = await response.json();
                allStores = data.data || data;
                console.log('매장 목록 로드 완료:', allStores.length + '개');
            } catch (error) {
                console.error('매장 목록 로드 오류:', error);
            }
        }

        // 지사 변경 이벤트
        function onBranchChange() {
            const select = document.getElementById('filterBranch');
            selectedBranchId = select.value;
            selectedStoreId = '';  // 매장 선택 초기화

            // 매장 검색 초기화
            const storeSearchInput = document.getElementById('storeSearchInput');
            const filterStoreIdInput = document.getElementById('filterStoreId');
            const storeSearchResults = document.getElementById('storeSearchResults');
            if (storeSearchInput) storeSearchInput.value = '';
            if (filterStoreIdInput) filterStoreIdInput.value = '';
            if (storeSearchResults) storeSearchResults.style.display = 'none';

            loadExpenses();
            loadSummary();
        }

        // 필터 초기화
        function resetFilter() {
            selectedBranchId = '';
            selectedStoreId = '';
            filterStartDate = '';
            filterEndDate = '';

            // 지사 드롭다운 초기화
            const branchSelect = document.getElementById('filterBranch');
            if (branchSelect) branchSelect.value = '';

            // 날짜 필터 초기화
            const startDateInput = document.getElementById('filterStartDate');
            const endDateInput = document.getElementById('filterEndDate');
            if (startDateInput) startDateInput.value = '';
            if (endDateInput) endDateInput.value = '';

            // 매장 검색 초기화
            const storeSearchInput = document.getElementById('storeSearchInput');
            const filterStoreIdInput = document.getElementById('filterStoreId');
            const storeSearchResults = document.getElementById('storeSearchResults');
            if (storeSearchInput) storeSearchInput.value = '';
            if (filterStoreIdInput) filterStoreIdInput.value = '';
            if (storeSearchResults) storeSearchResults.style.display = 'none';

            loadExpenses();
            loadSummary();
        }

        // 지출 내역 로드
        async function loadExpenses() {
            try {
                const response = await fetch('/api/dev/daily-expenses?limit=10' + getScopeParams());
                const data = await response.json();
                
                const container = document.getElementById('expensesList');
                container.innerHTML = '';
                
                if (data.data.length === 0) {
                    container.innerHTML = '<div style="padding: 20px; text-align: center; color: #6b7280;">등록된 지출 내역이 없습니다.</div>';
                    return;
                }
                
                data.data.forEach(expense => {
                    const storeName = expense.store?.name || '-';
                    const branchName = expense.store?.branch?.name || '-';
                    container.innerHTML += `
                        <div class="expense-item">
                            <div class="expense-info">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                    <span class="expense-category">${expense.category}</span>
                                    <span style="font-size: 14px; color: #6b7280;">${expense.expense_date}</span>
                                    <span style="font-size: 12px; background: #f3f4f6; padding: 2px 6px; border-radius: 4px;">${storeName}</span>
                                </div>
                                <div style="font-weight: 600; margin-bottom: 2px;">${expense.description || '내용 없음'}</div>
                                <div style="font-size: 12px; color: #9ca3af;">${expense.dealer_code} • ${expense.payment_method || '미기재'} • ${branchName}</div>
                            </div>
                            <div class="expense-amount">-${Number(expense.amount).toLocaleString()}원</div>
                        </div>
                    `;
                });
            } catch (error) {
                console.error('지출 내역 로드 오류:', error);
            }
        }

        // 요약 정보 로드
        async function loadSummary() {
            try {
                const today = new Date().toISOString().split('T')[0];
                const yearMonth = new Date().toISOString().slice(0, 7);
                const scopeParams = getScopeParams();

                // 오늘 지출
                const todayResponse = await fetch(`/api/daily-expenses?start_date=${today}&end_date=${today}${scopeParams}`);
                const todayData = await todayResponse.json();
                const todayTotal = todayData.data.reduce((sum, item) => sum + parseFloat(item.amount), 0);

                // 월별 현황
                const monthResponse = await fetch(`/api/daily-expenses/summary/monthly?year_month=${yearMonth}${scopeParams}`);
                const monthData = await monthResponse.json();
                
                // UI 업데이트
                document.getElementById('todayTotal').textContent = todayTotal.toLocaleString() + '원';
                document.getElementById('monthTotal').textContent = monthData.data.total_amount.toLocaleString() + '원';
                document.getElementById('expenseCount').textContent = monthData.data.expense_count + '건';
                document.getElementById('avgAmount').textContent = Math.round(monthData.data.average_amount).toLocaleString() + '원';
                
            } catch (error) {
                console.error('요약 정보 로드 오류:', error);
            }
        }

        // 폼 제출 (매장 권한에서만 사용)
        const expenseForm = document.getElementById('expenseForm');
        if (expenseForm) {
            expenseForm.addEventListener('submit', async function(e) {
                e.preventDefault();

                const formData = new FormData(this);
                const data = Object.fromEntries(formData.entries());

                // 매장 ID 자동 설정
                if (STORE_ID) {
                    data.store_id = STORE_ID;
                }

                try {
                    const response = await fetch('/api/daily-expenses', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify(data)
                    });

                    const result = await response.json();

                    if (result.success) {
                        alert('지출이 등록되었습니다!');
                        this.reset();
                        const expenseDateInput = document.querySelector('input[name="expense_date"]');
                        if (expenseDateInput) {
                            expenseDateInput.value = new Date().toISOString().split('T')[0];
                        }
                        loadExpenses();
                        loadSummary();
                    } else {
                        alert('등록 실패: ' + (result.message || '알 수 없는 오류'));
                    }
                } catch (error) {
                    alert('네트워크 오류: ' + error.message);
                }
            });
        }

        // 폼 초기화
        function resetForm() {
            const form = document.getElementById('expenseForm');
            if (form) {
                form.reset();
                const expenseDateInput = document.querySelector('input[name="expense_date"]');
                if (expenseDateInput) {
                    expenseDateInput.value = new Date().toISOString().split('T')[0];
                }
            }
        }
    </script>
</body>
</html>