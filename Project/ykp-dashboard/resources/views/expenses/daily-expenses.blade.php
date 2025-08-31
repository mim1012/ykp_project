<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>일일지출 관리 - YKP ERP</title>
    @vite(['resources/css/app.css'])
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

        <!-- 지출 입력 폼 -->
        <div class="expense-form">
            <h2 class="text-lg font-semibold text-gray-900 mb-4">새 지출 등록</h2>
            <form id="expenseForm">
                <div class="expense-grid">
                    <div class="form-group">
                        <label class="form-label">지출일</label>
                        <input type="date" name="expense_date" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">대리점</label>
                        <select name="dealer_code" class="form-input" required>
                            <option value="">선택해주세요</option>
                        </select>
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
        // 페이지 로드 시 초기화
        document.addEventListener('DOMContentLoaded', function() {
            loadDealers();
            loadExpenses();
            loadSummary();
            
            // 오늘 날짜 기본값 설정
            document.querySelector('input[name="expense_date"]').value = new Date().toISOString().split('T')[0];
        });

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

        // 지출 내역 로드
        async function loadExpenses() {
            try {
                const response = await fetch('/api/dev/daily-expenses?limit=10');
                const data = await response.json();
                
                const container = document.getElementById('expensesList');
                container.innerHTML = '';
                
                if (data.data.length === 0) {
                    container.innerHTML = '<div style="padding: 20px; text-align: center; color: #6b7280;">등록된 지출 내역이 없습니다.</div>';
                    return;
                }
                
                data.data.forEach(expense => {
                    container.innerHTML += `
                        <div class="expense-item">
                            <div class="expense-info">
                                <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 4px;">
                                    <span class="expense-category">${expense.category}</span>
                                    <span style="font-size: 14px; color: #6b7280;">${expense.expense_date}</span>
                                </div>
                                <div style="font-weight: 600; margin-bottom: 2px;">${expense.description || '내용 없음'}</div>
                                <div style="font-size: 12px; color: #9ca3af;">${expense.dealer_code} • ${expense.payment_method || '미기재'}</div>
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
                
                // 오늘 지출
                const todayResponse = await fetch(`/api/daily-expenses?start_date=${today}&end_date=${today}`);
                const todayData = await todayResponse.json();
                const todayTotal = todayData.data.reduce((sum, item) => sum + parseFloat(item.amount), 0);
                
                // 월별 현황
                const monthResponse = await fetch(`/api/daily-expenses/summary/monthly?year_month=${yearMonth}`);
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

        // 폼 제출
        document.getElementById('expenseForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            
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
                    alert('✅ 지출이 등록되었습니다!');
                    this.reset();
                    document.querySelector('input[name="expense_date"]').value = new Date().toISOString().split('T')[0];
                    loadExpenses();
                    loadSummary();
                } else {
                    alert('❌ 등록 실패: ' + (result.message || '알 수 없는 오류'));
                }
            } catch (error) {
                alert('❌ 네트워크 오류: ' + error.message);
            }
        });

        // 폼 초기화
        function resetForm() {
            document.getElementById('expenseForm').reset();
            document.querySelector('input[name="expense_date"]').value = new Date().toISOString().split('T')[0];
        }
    </script>
</body>
</html>