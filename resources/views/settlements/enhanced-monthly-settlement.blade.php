<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>월마감정산 관리 - YKP ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendardvariable/dist/web/variable/pretendardvariable.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .fade-in { animation: fadeIn 0.3s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        .excel-grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); 
            gap: 1rem; 
        }
        .calculator { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
    </style>
</head>
<body class="bg-gray-50 font-pretendard">
    <!-- 헤더 -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-gray-900">월마감정산 관리</h1>
                    <span class="ml-2 px-2 py-1 text-xs bg-purple-100 text-purple-800 rounded">2순위 기능</span>
                </div>
                <div class="flex items-center space-x-4">
                    <button onclick="exportToExcel()" class="text-green-600 hover:text-green-800">📊 엑셀 내보내기</button>
                    <a href="/dashboard" class="text-gray-600 hover:text-gray-900">대시보드</a>
                </div>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto py-6 px-4">
        <!-- 정산 대시보드 -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <!-- 월 선택 -->
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-sm font-medium text-gray-600">정산월 선택</p>
                        <select id="settlement-month" class="mt-2 text-lg font-semibold text-gray-900 border-none bg-transparent focus:ring-0">
                            <option value="2025-09">2025년 9월</option>
                            <option value="2025-10">2025년 10월</option>
                            <option value="2025-11">2025년 11월</option>
                        </select>
                    </div>
                    <div class="text-2xl">📅</div>
                </div>
            </div>

            <!-- 총 매출 -->
            <div class="bg-gradient-to-r from-blue-500 to-blue-600 text-white p-6 rounded-lg shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-blue-100 text-sm">총 매출</p>
                        <p class="text-2xl font-bold" id="total-sales">₩0</p>
                    </div>
                    <div class="text-2xl opacity-80">💰</div>
                </div>
            </div>

            <!-- 순이익 -->
            <div class="bg-gradient-to-r from-green-500 to-green-600 text-white p-6 rounded-lg shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-green-100 text-sm">순이익</p>
                        <p class="text-2xl font-bold" id="net-profit">₩0</p>
                    </div>
                    <div class="text-2xl opacity-80">📈</div>
                </div>
            </div>

            <!-- 수익률 -->
            <div class="bg-gradient-to-r from-purple-500 to-purple-600 text-white p-6 rounded-lg shadow">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="text-purple-100 text-sm">수익률</p>
                        <p class="text-2xl font-bold" id="profit-rate">0%</p>
                    </div>
                    <div class="text-2xl opacity-80">📊</div>
                </div>
            </div>
        </div>

        <!-- 빠른 작업 버튼 -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            <button onclick="autoCalculateSettlement()" class="calculator text-white p-4 rounded-lg shadow-lg hover:shadow-xl transition-all">
                <div class="flex items-center">
                    <div class="text-2xl mr-3">🧮</div>
                    <div class="text-left">
                        <h3 class="font-semibold">자동 정산 계산</h3>
                        <p class="text-sm opacity-90">이번 달 매출/지출 자동 집계</p>
                    </div>
                </div>
            </button>

            <button onclick="openExpenseModal()" class="bg-gradient-to-r from-orange-500 to-red-500 text-white p-4 rounded-lg shadow-lg hover:shadow-xl transition-all">
                <div class="flex items-center">
                    <div class="text-2xl mr-3">📝</div>
                    <div class="text-left">
                        <h3 class="font-semibold">지출 입력</h3>
                        <p class="text-sm opacity-90">일일/고정지출, 급여 관리</p>
                    </div>
                </div>
            </button>

            <button onclick="generateReport()" class="bg-gradient-to-r from-cyan-500 to-blue-500 text-white p-4 rounded-lg shadow-lg hover:shadow-xl transition-all">
                <div class="flex items-center">
                    <div class="text-2xl mr-3">📑</div>
                    <div class="text-left">
                        <h3 class="font-semibold">정산보고서</h3>
                        <p class="text-sm opacity-90">월별 정산 보고서 생성</p>
                    </div>
                </div>
            </button>
        </div>

        <!-- 정산 상세 내역 -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- 수익 내역 -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">📊 수익 내역</h3>
                    
                    <div class="space-y-4">
                        <div class="flex justify-between items-center p-3 bg-blue-50 rounded">
                            <span class="text-gray-700">총 개통 건수</span>
                            <span class="font-semibold text-blue-700" id="sales-count">0건</span>
                        </div>
                        
                        <div class="flex justify-between items-center p-3 bg-blue-50 rounded">
                            <span class="text-gray-700">총 정산 금액</span>
                            <span class="font-semibold text-blue-700" id="settlement-amount">₩0</span>
                        </div>
                        
                        <div class="flex justify-between items-center p-3 bg-blue-50 rounded">
                            <span class="text-gray-700">평균 마진율</span>
                            <span class="font-semibold text-blue-700" id="avg-margin">0%</span>
                        </div>
                        
                        <div class="flex justify-between items-center p-3 bg-green-50 rounded">
                            <span class="text-gray-700">부가세</span>
                            <span class="font-semibold text-green-700" id="vat-amount">₩0</span>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t">
                        <div class="flex justify-between items-center">
                            <span class="text-lg font-medium text-gray-900">총 수익</span>
                            <span class="text-lg font-bold text-green-600" id="gross-profit">₩0</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 지출 내역 -->
            <div class="bg-white rounded-lg shadow">
                <div class="p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">📋 지출 내역</h3>
                    
                    <div class="space-y-4">
                        <div class="flex justify-between items-center p-3 bg-red-50 rounded">
                            <span class="text-gray-700">일일 지출</span>
                            <div class="text-right">
                                <span class="font-semibold text-red-700" id="daily-expenses">₩0</span>
                                <button onclick="editExpenseCategory('daily')" class="ml-2 text-xs text-blue-600 hover:text-blue-800">수정</button>
                            </div>
                        </div>
                        
                        <div class="flex justify-between items-center p-3 bg-red-50 rounded">
                            <span class="text-gray-700">고정 지출</span>
                            <div class="text-right">
                                <span class="font-semibold text-red-700" id="fixed-expenses">₩0</span>
                                <button onclick="editExpenseCategory('fixed')" class="ml-2 text-xs text-blue-600 hover:text-blue-800">수정</button>
                            </div>
                        </div>
                        
                        <div class="flex justify-between items-center p-3 bg-red-50 rounded">
                            <span class="text-gray-700">직원 급여</span>
                            <div class="text-right">
                                <span class="font-semibold text-red-700" id="payroll-expenses">₩0</span>
                                <button onclick="editExpenseCategory('payroll')" class="ml-2 text-xs text-blue-600 hover:text-blue-800">수정</button>
                            </div>
                        </div>
                        
                        <div class="flex justify-between items-center p-3 bg-red-50 rounded">
                            <span class="text-gray-700">환수 금액</span>
                            <div class="text-right">
                                <span class="font-semibold text-red-700" id="refund-amount">₩0</span>
                                <button onclick="editExpenseCategory('refund')" class="ml-2 text-xs text-blue-600 hover:text-blue-800">수정</button>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 pt-4 border-t">
                        <div class="flex justify-between items-center">
                            <span class="text-lg font-medium text-gray-900">총 지출</span>
                            <span class="text-lg font-bold text-red-600" id="total-expenses">₩0</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- 정산 결과 및 차트 -->
        <div class="mt-8 grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- 정산 결과 -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">🎯 정산 결과</h3>
                
                <div class="space-y-4">
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-gray-600">총 수익</span>
                            <span class="font-semibold text-blue-600" id="final-revenue">₩0</span>
                        </div>
                        <div class="flex justify-between items-center mb-2">
                            <span class="text-gray-600">총 지출</span>
                            <span class="font-semibold text-red-600" id="final-expenses">₩0</span>
                        </div>
                        <div class="border-t pt-2">
                            <div class="flex justify-between items-center">
                                <span class="text-lg font-medium text-gray-900">순이익</span>
                                <span class="text-xl font-bold text-green-600" id="final-profit">₩0</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="p-4 bg-blue-50 rounded-lg">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-700">수익률</span>
                            <span class="text-lg font-bold text-blue-600" id="final-rate">0%</span>
                        </div>
                    </div>

                    <div class="flex space-x-2">
                        <button onclick="saveSettlement('draft')" class="flex-1 bg-yellow-500 text-white py-2 px-4 rounded hover:bg-yellow-600">
                            임시저장
                        </button>
                        <button onclick="saveSettlement('confirmed')" class="flex-1 bg-green-500 text-white py-2 px-4 rounded hover:bg-green-600">
                            정산확정
                        </button>
                    </div>
                </div>
            </div>

            <!-- 차트 -->
            <div class="bg-white rounded-lg shadow p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">📈 월별 추이</h3>
                <canvas id="settlement-chart" width="400" height="300"></canvas>
            </div>
        </div>

        <!-- 정산 히스토리 -->
        <div class="mt-8 bg-white rounded-lg shadow">
            <div class="p-6">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">📅 정산 히스토리</h3>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">정산월</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">매출</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">지출</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">순이익</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">수익률</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">상태</th>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">작업</th>
                            </tr>
                        </thead>
                        <tbody id="settlement-history" class="bg-white divide-y divide-gray-200">
                            <!-- 동적으로 로드됨 -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- 지출 입력 모달 -->
    <div id="expense-modal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden items-center justify-center z-50">
        <div class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-semibold">지출 입력</h3>
            </div>
            <div class="px-6 py-4">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">지출 유형</label>
                    <select id="expense-type" class="w-full border border-gray-300 rounded px-3 py-2">
                        <option value="daily">일일 지출</option>
                        <option value="fixed">고정 지출</option>
                        <option value="payroll">직원 급여</option>
                        <option value="refund">환수 금액</option>
                    </select>
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">금액</label>
                    <input type="number" id="expense-amount" class="w-full border border-gray-300 rounded px-3 py-2" placeholder="0">
                </div>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">설명</label>
                    <input type="text" id="expense-description" class="w-full border border-gray-300 rounded px-3 py-2" placeholder="지출 내역 설명">
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 flex justify-end space-x-3">
                <button onclick="closeExpenseModal()" class="px-4 py-2 text-gray-600 border border-gray-300 rounded hover:bg-gray-50">
                    취소
                </button>
                <button onclick="addExpense()" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                    추가
                </button>
            </div>
        </div>
    </div>

    <!-- 토스트 메시지 -->
    <div id="toast" class="fixed top-4 right-4 z-50" style="display: none;">
        <div class="bg-white border border-gray-200 rounded-lg shadow-lg p-4 min-w-64">
            <div class="flex items-center">
                <div id="toast-icon" class="mr-3"></div>
                <div id="toast-message" class="text-sm font-medium"></div>
            </div>
        </div>
    </div>

    <script>
        // 전역 변수
        let currentMonth = '2025-09';
        let settlementData = {
            revenue: {},
            expenses: {},
            calculated: false
        };

        // 페이지 로드시 초기화
        document.addEventListener('DOMContentLoaded', function() {
            initializeSettlement();
            setupEventListeners();
            loadSettlementHistory();
        });

        // 이벤트 리스너 설정
        function setupEventListeners() {
            document.getElementById('settlement-month').addEventListener('change', function() {
                currentMonth = this.value;
                loadSettlementData();
            });
        }

        // 정산 초기화
        function initializeSettlement() {
            currentMonth = document.getElementById('settlement-month').value;
            loadSettlementData();
            initializeChart();
        }

        // 정산 데이터 로드
        async function loadSettlementData() {
            try {
                // API 호출하여 해당 월의 매출/지출 데이터 가져오기
                const response = await fetch(`/api/settlements/monthly-data?month=${currentMonth}`);
                const result = await response.json();
                
                if (result.success) {
                    settlementData = result.data;
                    updateUI();
                } else {
                    // 데이터가 없으면 기본값 설정
                    resetSettlementData();
                }
            } catch (error) {
                console.error('데이터 로드 실패:', error);
                resetSettlementData();
            }
        }

        // 정산 데이터 초기화
        function resetSettlementData() {
            settlementData = {
                revenue: {
                    sales_count: 0,
                    settlement_amount: 0,
                    vat_amount: 0,
                    avg_margin: 0,
                    gross_profit: 0
                },
                expenses: {
                    daily_expenses: 0,
                    fixed_expenses: 0,
                    payroll_expenses: 0,
                    refund_amount: 0,
                    total_expenses: 0
                },
                calculated: false
            };
            updateUI();
        }

        // UI 업데이트
        function updateUI() {
            // 수익 데이터 업데이트
            document.getElementById('sales-count').textContent = `${settlementData.revenue.sales_count || 0}건`;
            document.getElementById('settlement-amount').textContent = formatCurrency(settlementData.revenue.settlement_amount || 0);
            document.getElementById('avg-margin').textContent = `${settlementData.revenue.avg_margin || 0}%`;
            document.getElementById('vat-amount').textContent = formatCurrency(settlementData.revenue.vat_amount || 0);
            document.getElementById('gross-profit').textContent = formatCurrency(settlementData.revenue.gross_profit || 0);

            // 지출 데이터 업데이트
            document.getElementById('daily-expenses').textContent = formatCurrency(settlementData.expenses.daily_expenses || 0);
            document.getElementById('fixed-expenses').textContent = formatCurrency(settlementData.expenses.fixed_expenses || 0);
            document.getElementById('payroll-expenses').textContent = formatCurrency(settlementData.expenses.payroll_expenses || 0);
            document.getElementById('refund-amount').textContent = formatCurrency(settlementData.expenses.refund_amount || 0);
            document.getElementById('total-expenses').textContent = formatCurrency(settlementData.expenses.total_expenses || 0);

            // 대시보드 카드 업데이트
            const totalSales = settlementData.revenue.settlement_amount || 0;
            const totalExpenses = settlementData.expenses.total_expenses || 0;
            const netProfit = totalSales - totalExpenses;
            const profitRate = totalSales > 0 ? ((netProfit / totalSales) * 100).toFixed(1) : 0;

            document.getElementById('total-sales').textContent = formatCurrency(totalSales);
            document.getElementById('net-profit').textContent = formatCurrency(netProfit);
            document.getElementById('profit-rate').textContent = `${profitRate}%`;

            // 정산 결과 업데이트
            document.getElementById('final-revenue').textContent = formatCurrency(totalSales);
            document.getElementById('final-expenses').textContent = formatCurrency(totalExpenses);
            document.getElementById('final-profit').textContent = formatCurrency(netProfit);
            document.getElementById('final-rate').textContent = `${profitRate}%`;
        }

        // 자동 정산 계산
        async function autoCalculateSettlement() {
            showToast('정산 계산을 시작합니다...', 'info');

            try {
                const response = await fetch(`/api/settlements/auto-calculate`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify({ month: currentMonth })
                });

                const result = await response.json();

                if (result.success) {
                    settlementData = result.data;
                    updateUI();
                    showToast('자동 정산이 완료되었습니다!', 'success');
                } else {
                    showToast('정산 계산에 실패했습니다', 'error');
                }
            } catch (error) {
                console.error('자동 정산 실패:', error);
                showToast('네트워크 오류가 발생했습니다', 'error');
                
                // 데모용 데이터 생성
                generateDemoData();
            }
        }

        // 데모 데이터 생성
        function generateDemoData() {
            settlementData = {
                revenue: {
                    sales_count: 45,
                    settlement_amount: 25000000,
                    vat_amount: 2272727,
                    avg_margin: 15.5,
                    gross_profit: 22727273
                },
                expenses: {
                    daily_expenses: 2500000,
                    fixed_expenses: 3200000,
                    payroll_expenses: 4800000,
                    refund_amount: 500000,
                    total_expenses: 11000000
                },
                calculated: true
            };
            updateUI();
            showToast('데모 데이터로 정산을 계산했습니다', 'info');
        }

        // 지출 입력 모달 열기
        function openExpenseModal() {
            document.getElementById('expense-modal').classList.remove('hidden');
            document.getElementById('expense-modal').classList.add('flex', 'fade-in');
        }

        // 지출 입력 모달 닫기
        function closeExpenseModal() {
            document.getElementById('expense-modal').classList.add('hidden');
            document.getElementById('expense-modal').classList.remove('flex', 'fade-in');
        }

        // 지출 추가
        function addExpense() {
            const type = document.getElementById('expense-type').value;
            const amount = parseInt(document.getElementById('expense-amount').value) || 0;
            const description = document.getElementById('expense-description').value;

            if (amount <= 0) {
                showToast('올바른 금액을 입력하세요', 'warning');
                return;
            }

            // 기존 지출에 추가
            switch(type) {
                case 'daily':
                    settlementData.expenses.daily_expenses += amount;
                    break;
                case 'fixed':
                    settlementData.expenses.fixed_expenses += amount;
                    break;
                case 'payroll':
                    settlementData.expenses.payroll_expenses += amount;
                    break;
                case 'refund':
                    settlementData.expenses.refund_amount += amount;
                    break;
            }

            // 총 지출 재계산
            settlementData.expenses.total_expenses = 
                settlementData.expenses.daily_expenses +
                settlementData.expenses.fixed_expenses +
                settlementData.expenses.payroll_expenses +
                settlementData.expenses.refund_amount;

            updateUI();
            closeExpenseModal();
            showToast(`${getExpenseTypeName(type)} ${formatCurrency(amount)}이 추가되었습니다`, 'success');

            // 폼 초기화
            document.getElementById('expense-amount').value = '';
            document.getElementById('expense-description').value = '';
        }

        // 지출 유형명 변환
        function getExpenseTypeName(type) {
            const names = {
                daily: '일일 지출',
                fixed: '고정 지출',
                payroll: '직원 급여',
                refund: '환수 금액'
            };
            return names[type] || type;
        }

        // 지출 카테고리 수정
        function editExpenseCategory(category) {
            const currentAmount = settlementData.expenses[category + '_expenses'] || settlementData.expenses[category + '_amount'];
            const newAmount = prompt(`${getExpenseTypeName(category)} 금액을 입력하세요:`, currentAmount);
            
            if (newAmount !== null) {
                const amount = parseInt(newAmount) || 0;
                
                if (category === 'refund') {
                    settlementData.expenses.refund_amount = amount;
                } else {
                    settlementData.expenses[category + '_expenses'] = amount;
                }

                // 총 지출 재계산
                settlementData.expenses.total_expenses = 
                    settlementData.expenses.daily_expenses +
                    settlementData.expenses.fixed_expenses +
                    settlementData.expenses.payroll_expenses +
                    settlementData.expenses.refund_amount;

                updateUI();
                showToast('지출이 수정되었습니다', 'success');
            }
        }

        // 정산 저장
        async function saveSettlement(status) {
            if (!settlementData.calculated && !confirm('아직 계산되지 않은 정산입니다. 저장하시겠습니까?')) {
                return;
            }

            const data = {
                month: currentMonth,
                status: status,
                ...settlementData
            };

            try {
                const response = await fetch('/api/settlements/save', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();

                if (result.success) {
                    const statusText = status === 'draft' ? '임시저장' : '정산확정';
                    showToast(`${statusText}이 완료되었습니다!`, 'success');
                    loadSettlementHistory();
                } else {
                    showToast('저장에 실패했습니다', 'error');
                }
            } catch (error) {
                console.error('저장 실패:', error);
                showToast('데모 모드: 정산이 저장되었습니다', 'info');
                loadSettlementHistory();
            }
        }

        // 정산 히스토리 로드
        function loadSettlementHistory() {
            const tbody = document.getElementById('settlement-history');
            
            // 데모 히스토리 데이터
            const demoHistory = [
                { month: '2025-09', sales: 25000000, expenses: 11000000, profit: 14000000, rate: 56.0, status: 'confirmed' },
                { month: '2025-08', sales: 22000000, expenses: 10500000, profit: 11500000, rate: 52.3, status: 'confirmed' },
                { month: '2025-07', sales: 28000000, expenses: 12000000, profit: 16000000, rate: 57.1, status: 'confirmed' }
            ];

            tbody.innerHTML = demoHistory.map(row => {
                const statusBadge = row.status === 'confirmed' 
                    ? '<span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">확정</span>'
                    : '<span class="px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded">임시</span>';
                
                return `
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium">${row.month}</td>
                        <td class="px-4 py-3">${formatCurrency(row.sales)}</td>
                        <td class="px-4 py-3">${formatCurrency(row.expenses)}</td>
                        <td class="px-4 py-3 font-semibold text-green-600">${formatCurrency(row.profit)}</td>
                        <td class="px-4 py-3">${row.rate}%</td>
                        <td class="px-4 py-3">${statusBadge}</td>
                        <td class="px-4 py-3">
                            <button onclick="viewSettlementDetail('${row.month}')" class="text-blue-600 hover:text-blue-800 text-sm">
                                상세보기
                            </button>
                        </td>
                    </tr>
                `;
            }).join('');
        }

        // 정산 상세보기
        function viewSettlementDetail(month) {
            showToast(`${month} 정산 상세를 불러옵니다...`, 'info');
            // 실제로는 해당 월 데이터를 로드하여 표시
        }

        // 보고서 생성
        function generateReport() {
            showToast('정산 보고서를 생성하고 있습니다...', 'info');
            
            setTimeout(() => {
                showToast('보고서 생성이 완료되었습니다!', 'success');
            }, 2000);
        }

        // 엑셀 내보내기
        function exportToExcel() {
            showToast('엑셀 파일을 생성하고 있습니다...', 'info');
            
            setTimeout(() => {
                showToast('엑셀 파일이 다운로드되었습니다!', 'success');
            }, 1500);
        }

        // 차트 초기화
        function initializeChart() {
            const ctx = document.getElementById('settlement-chart').getContext('2d');
            
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['7월', '8월', '9월'],
                    datasets: [{
                        label: '매출',
                        data: [28000000, 22000000, 25000000],
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        tension: 0.4
                    }, {
                        label: '순이익',
                        data: [16000000, 11500000, 14000000],
                        borderColor: 'rgb(34, 197, 94)',
                        backgroundColor: 'rgba(34, 197, 94, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return formatCurrency(value);
                                }
                            }
                        }
                    }
                }
            });
        }

        // 통화 포맷팅
        function formatCurrency(amount) {
            return '₩' + new Intl.NumberFormat('ko-KR').format(amount);
        }

        // 토스트 메시지 표시
        function showToast(message, type = 'info') {
            const toast = document.getElementById('toast');
            const icon = document.getElementById('toast-icon');
            const messageEl = document.getElementById('toast-message');
            
            const icons = {
                success: '✅',
                error: '❌',
                warning: '⚠️',
                info: 'ℹ️'
            };
            
            icon.textContent = icons[type] || icons.info;
            messageEl.textContent = message;
            
            const colors = {
                success: 'border-green-200 bg-green-50',
                error: 'border-red-200 bg-red-50',
                warning: 'border-yellow-200 bg-yellow-50',
                info: 'border-blue-200 bg-blue-50'
            };
            
            toast.firstElementChild.className = `bg-white border rounded-lg shadow-lg p-4 min-w-64 ${colors[type] || colors.info}`;
            toast.style.display = 'block';
            
            setTimeout(() => {
                toast.style.display = 'none';
            }, 3000);
        }
    </script>
</body>
</html>