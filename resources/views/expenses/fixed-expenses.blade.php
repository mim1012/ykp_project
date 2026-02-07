<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>고정지출 관리 - YKP ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css" rel="stylesheet">
    <style>
        .fixed-expense-form {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .expense-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-warning {
            background: #f59e0b;
            color: white;
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
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }
        .status-paid {
            background: #d1fae5;
            color: #065f46;
        }
        .status-overdue {
            background: #fee2e2;
            color: #991b1b;
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
                    <h1 class="text-xl font-semibold text-gray-900">고정지출 관리</h1>
                    <span class="ml-2 px-2 py-1 text-xs bg-purple-100 text-purple-800 rounded">Fixed Expenses</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/" class="text-gray-600 hover:text-gray-900">메인 대시보드</a>
                    <a href="/daily-expenses" class="text-gray-600 hover:text-gray-900">일일지출</a>
                    <a href="/admin" class="text-gray-600 hover:text-gray-900">관리자</a>
                </div>
            </div>
        </div>
    </header>

    <!-- 메인 컨텐츠 -->
    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- 요약 카드 -->
        <div class="summary-cards">
            <div class="summary-card">
                <div class="summary-value" id="totalFixed">0원</div>
                <div class="summary-label">이번 달 고정지출</div>
            </div>
            <div class="summary-card">
                <div class="summary-value" id="pendingCount">0건</div>
                <div class="summary-label">지급 대기</div>
            </div>
            <div class="summary-card">
                <div class="summary-value" id="paidCount">0건</div>
                <div class="summary-label">지급 완료</div>
            </div>
            <div class="summary-card">
                <div class="summary-value" id="upcomingAmount">0원</div>
                <div class="summary-label">30일 내 지급 예정</div>
            </div>
        </div>

        <!-- 고정지출 입력 폼 -->
        <div class="fixed-expense-form">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">고정지출 등록</h2>
            <form id="fixedExpenseForm">
                <div class="expense-grid">
                    <div class="form-group">
                        <label class="form-label">정산 월</label>
                        <input type="month" name="year_month" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">대리점</label>
                        <select name="dealer_code" class="form-input" required>
                            <option value="">선택해주세요</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">지출 유형</label>
                        <select name="expense_type" class="form-input" required>
                            <option value="">선택해주세요</option>
                            <option value="임대료">임대료</option>
                            <option value="인건비">인건비</option>
                            <option value="통신비">통신비</option>
                            <option value="전기료">전기료</option>
                            <option value="보험료">보험료</option>
                            <option value="기타">기타</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">지출 금액</label>
                        <input type="number" name="amount" class="form-input" placeholder="예: 1000000" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">지급 예정일</label>
                        <input type="date" name="due_date" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">지급 상태</label>
                        <select name="payment_status" class="form-input">
                            <option value="pending">지급 대기</option>
                            <option value="paid">지급 완료</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">상세 내용</label>
                    <textarea name="description" class="form-input" rows="2" placeholder="상세 내용을 입력해주세요"></textarea>
                </div>

                <div class="flex gap-4 mt-6">
                    <button type="submit" class="btn btn-primary">고정지출 등록</button>
                    <button type="button" onclick="resetForm()" class="btn" style="background: #f3f4f6; color: #374151;">초기화</button>
                </div>
            </form>
        </div>

        <!-- 현재 월 고정지출 내역 -->
        <div class="expense-list">
            <div style="padding: 20px; border-bottom: 1px solid #f3f4f6; display: flex; justify-content: space-between; align-items: center;">
                <h3 class="text-lg font-semibold text-gray-900">이번 달 고정지출 내역</h3>
                <div>
                    <select id="monthFilter" onchange="loadFixedExpenses()" class="form-input" style="width: auto;">
                        <!-- 동적으로 로드됨 -->
                    </select>
                </div>
            </div>
            <div id="fixedExpensesList">
                <!-- 동적으로 로드됨 -->
            </div>
        </div>

        <!-- 지급 예정 내역 -->
        <div class="expense-list" style="margin-top: 20px;">
            <div style="padding: 20px; border-bottom: 1px solid #f3f4f6;">
                <h3 class="text-lg font-semibold text-gray-900">30일 내 지급 예정</h3>
            </div>
            <div id="upcomingPaymentsList">
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

        // 권한에 따른 API 파라미터 생성
        function getScopeParams() {
            const params = new URLSearchParams();
            if (VIEW_SCOPE === 'branch' && BRANCH_ID) {
                params.append('branch_id', BRANCH_ID);
            } else if (VIEW_SCOPE === 'store' && STORE_ID) {
                params.append('store_id', STORE_ID);
            }
            return params.toString() ? '&' + params.toString() : '';
        }

        // 페이지 로드 시 초기화
        document.addEventListener('DOMContentLoaded', function() {
            loadDealers();
            loadFixedExpenses();
            loadUpcomingPayments();
            loadSummary();
            initMonthFilter();
            
            // 현재 월 기본값 설정
            document.querySelector('input[name="year_month"]').value = new Date().toISOString().slice(0, 7);
        });

        // 월 필터 초기화
        function initMonthFilter() {
            const select = document.getElementById('monthFilter');
            const currentDate = new Date();
            
            for (let i = -6; i <= 6; i++) {
                const date = new Date(currentDate.getFullYear(), currentDate.getMonth() + i, 1);
                const yearMonth = date.toISOString().slice(0, 7);
                const label = date.toLocaleDateString('ko-KR', { year: 'numeric', month: 'long' });
                
                const option = document.createElement('option');
                option.value = yearMonth;
                option.textContent = label;
                if (i === 0) option.selected = true;
                
                select.appendChild(option);
            }
        }

        // 대리점 목록 로드
        async function loadDealers() {
            try {
                const response = await fetch('/api/dev/calculation/profiles');
                const data = await response.json();
                
                const select = document.querySelector('select[name="dealer_code"]');
                select.innerHTML = '<option value="">선택해주세요</option>';
                
                data.data.forEach(dealer => {
                    select.innerHTML += `<option value="${dealer.dealer_code}">${dealer.dealer_name}</option>`;
                });
            } catch (error) {
                console.error('대리점 목록 로드 오류:', error);
            }
        }

        // 고정지출 내역 로드
        async function loadFixedExpenses() {
            try {
                const yearMonth = document.getElementById('monthFilter').value || new Date().toISOString().slice(0, 7);
                const response = await fetch(`/api/dev/fixed-expenses?year_month=${yearMonth}&limit=50${getScopeParams()}`);
                const data = await response.json();
                
                const container = document.getElementById('fixedExpensesList');
                container.innerHTML = '';
                
                if (data.data.length === 0) {
                    container.innerHTML = '<div style="padding: 20px; text-align: center; color: #6b7280;">등록된 고정지출이 없습니다.</div>';
                    return;
                }
                
                data.data.forEach(expense => {
                    const statusClass = expense.payment_status === 'paid' ? 'status-paid' : 
                                      expense.payment_status === 'overdue' ? 'status-overdue' : 'status-pending';
                    const statusText = expense.payment_status === 'paid' ? '지급 완료' : 
                                     expense.payment_status === 'overdue' ? '연체' : '지급 대기';
                    
                    container.innerHTML += `
                        <div class="expense-item">
                            <div style="flex: 1;">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                    <span style="font-weight: 600; color: #1f2937;">${expense.expense_type}</span>
                                    <span class="status-badge ${statusClass}">${statusText}</span>
                                </div>
                                <div style="font-size: 14px; color: #6b7280; margin-bottom: 2px;">${expense.description || '상세 내용 없음'}</div>
                                <div style="font-size: 12px; color: #9ca3af;">
                                    ${expense.dealer_code} • 
                                    ${expense.due_date ? '지급예정: ' + expense.due_date : '날짜 미정'}
                                    ${expense.payment_date ? ' • 지급완료: ' + expense.payment_date : ''}
                                </div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-weight: 700; font-size: 16px; color: #ef4444;">
                                    ${Number(expense.amount).toLocaleString()}원
                                </div>
                                ${expense.payment_status === 'pending' ? 
                                    `<button onclick="markAsPaid(${expense.id})" class="btn btn-success" style="margin-top: 8px; padding: 4px 8px; font-size: 12px;">지급 완료</button>` : 
                                    ''}
                            </div>
                        </div>
                    `;
                });
            } catch (error) {
                console.error('고정지출 내역 로드 오류:', error);
            }
        }

        // 지급 예정 내역 로드
        async function loadUpcomingPayments() {
            try {
                const response = await fetch('/api/dev/fixed-expenses/upcoming/payments?days=30' + getScopeParams());
                const data = await response.json();
                
                const container = document.getElementById('upcomingPaymentsList');
                container.innerHTML = '';
                
                if (data.data.upcoming_payments.length === 0) {
                    container.innerHTML = '<div style="padding: 20px; text-align: center; color: #6b7280;">30일 내 지급 예정인 고정지출이 없습니다.</div>';
                    return;
                }
                
                data.data.upcoming_payments.forEach(payment => {
                    const daysLeft = Math.ceil((new Date(payment.due_date) - new Date()) / (1000 * 60 * 60 * 24));
                    const urgencyClass = daysLeft <= 3 ? 'status-overdue' : daysLeft <= 7 ? 'status-warning' : 'status-pending';
                    
                    container.innerHTML += `
                        <div class="expense-item">
                            <div style="flex: 1;">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                    <span style="font-weight: 600; color: #1f2937;">${payment.expense_type}</span>
                                    <span class="status-badge ${urgencyClass}">${daysLeft}일 후</span>
                                </div>
                                <div style="font-size: 14px; color: #6b7280;">${payment.dealer_code} • ${payment.due_date}</div>
                            </div>
                            <div style="text-align: right;">
                                <div style="font-weight: 700; font-size: 16px; color: #ef4444;">
                                    ${Number(payment.amount).toLocaleString()}원
                                </div>
                                <button onclick="markAsPaid(${payment.id})" class="btn btn-success" style="margin-top: 8px; padding: 4px 8px; font-size: 12px;">
                                    지급 완료
                                </button>
                            </div>
                        </div>
                    `;
                });
            } catch (error) {
                console.error('지급 예정 내역 로드 오류:', error);
            }
        }

        // 요약 정보 로드
        async function loadSummary() {
            try {
                const yearMonth = new Date().toISOString().slice(0, 7);
                const scopeParams = getScopeParams();

                // 이번 달 고정지출
                const monthResponse = await fetch(`/api/dev/fixed-expenses?year_month=${yearMonth}&limit=100${scopeParams}`);
                const monthData = await monthResponse.json();
                
                const totalAmount = monthData.data.reduce((sum, item) => sum + parseFloat(item.amount), 0);
                const pendingCount = monthData.data.filter(item => item.payment_status === 'pending').length;
                const paidCount = monthData.data.filter(item => item.payment_status === 'paid').length;
                
                // 지급 예정 금액
                const upcomingResponse = await fetch('/api/dev/fixed-expenses/upcoming/payments?days=30' + scopeParams);
                const upcomingData = await upcomingResponse.json();
                const upcomingAmount = upcomingData.data.total_amount;
                
                // UI 업데이트
                document.getElementById('totalFixed').textContent = totalAmount.toLocaleString() + '원';
                document.getElementById('pendingCount').textContent = pendingCount + '건';
                document.getElementById('paidCount').textContent = paidCount + '건';
                document.getElementById('upcomingAmount').textContent = Number(upcomingAmount).toLocaleString() + '원';
                
            } catch (error) {
                console.error('요약 정보 로드 오류:', error);
            }
        }

        // 폼 제출
        document.getElementById('fixedExpenseForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            
            try {
                const response = await fetch('/api/dev/fixed-expenses', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(data)
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('고정지출이 등록되었습니다!');
                    this.reset();
                    document.querySelector('input[name="year_month"]').value = new Date().toISOString().slice(0, 7);
                    loadFixedExpenses();
                    loadUpcomingPayments();
                    loadSummary();
                } else {
                    alert('등록 실패: ' + (result.message || '알 수 없는 오류'));
                }
            } catch (error) {
                alert('네트워크 오류: ' + error.message);
            }
        });

        // 지급 완료 처리
        async function markAsPaid(expenseId) {
            if (!confirm('지급 완료로 처리하시겠습니까?')) return;
            
            try {
                const response = await fetch(`/api/dev/fixed-expenses/${expenseId}/payment-status`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        payment_status: 'paid',
                        payment_date: new Date().toISOString().split('T')[0]
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('지급 완료 처리되었습니다!');
                    loadFixedExpenses();
                    loadUpcomingPayments();
                    loadSummary();
                } else {
                    alert('처리 실패: ' + (result.message || '알 수 없는 오류'));
                }
            } catch (error) {
                alert('네트워크 오류: ' + error.message);
            }
        }

        // 폼 초기화
        function resetForm() {
            document.getElementById('fixedExpenseForm').reset();
            document.querySelector('input[name="year_month"]').value = new Date().toISOString().slice(0, 7);
        }
    </script>
</body>
</html>