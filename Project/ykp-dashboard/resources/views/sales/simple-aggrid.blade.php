<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>간단 AgGrid - YKP ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css" rel="stylesheet">
</head>
<body class="bg-gray-50">
    <!-- 헤더 -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <h1 class="text-xl font-semibold text-gray-900">AgGrid 판매관리 (Simple)</h1>
                    <span class="ml-2 px-2 py-1 text-xs bg-green-100 text-green-800 rounded">WORKING</span>
                </div>
                <div class="flex items-center space-x-4">
                    <a href="/dashboard" class="text-gray-600 hover:text-gray-900">대시보드</a>
                    <a href="/admin" class="text-gray-600 hover:text-gray-900">관리자</a>
                </div>
            </div>
        </div>
    </header>

    <!-- 메인 컨텐츠 -->
    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- 통계 카드 -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="text-sm font-medium text-gray-500">총 개통건수</div>
                <div class="text-2xl font-bold text-gray-900" id="total-count">2</div>
            </div>
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="text-sm font-medium text-gray-500">총 정산금</div>
                <div class="text-2xl font-bold text-blue-600" id="total-settlement">415,000원</div>
            </div>
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="text-sm font-medium text-gray-500">총 마진</div>
                <div class="text-2xl font-bold text-green-600" id="total-margin">415,000원</div>
            </div>
            <div class="bg-white p-6 rounded-lg shadow">
                <div class="text-sm font-medium text-gray-500">평균 마진율</div>
                <div class="text-2xl font-bold text-purple-600" id="avg-margin">100.0%</div>
            </div>
        </div>

        <!-- 컨트롤 패널 -->
        <div class="bg-white rounded-lg shadow mb-6">
            <div class="p-4 border-b">
                <div class="flex justify-between items-center">
                    <div class="flex space-x-4">
                        <select id="dealer-select" class="px-3 py-2 border rounded-md">
                            <option value="">전체 대리점</option>
                            <option value="ENT">이앤티</option>
                            <option value="WIN">앤투윈</option>
                            <option value="CHOSI">초시대</option>
                            <option value="AMT">아엠티</option>
                        </select>
                        
                        <button id="add-row-btn" class="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                            ➕ 새 행 추가
                        </button>
                        
                        <button id="calculate-all-btn" class="px-4 py-2 bg-purple-500 text-white rounded hover:bg-purple-600">
                            🔄 전체 재계산
                        </button>
                        
                        <button id="save-btn" class="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600">
                            💾 저장
                        </button>
                    </div>
                    
                    <div class="flex items-center space-x-2">
                        <div id="status-indicator" class="px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded">
                            준비됨
                        </div>
                        
                        <button id="help-btn" class="px-3 py-2 text-gray-600 hover:text-gray-800" title="도움말 (F1)">
                            ❓
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- 데이터 테이블 -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">선택</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">판매자</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">대리점</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">통신사</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">액면가</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">구두1</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">구두2</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-yellow-50">리베총계</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-yellow-50">정산금</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-red-50">세금</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider bg-green-50">세후마진</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">액션</th>
                        </tr>
                    </thead>
                    <tbody id="data-table-body" class="bg-white divide-y divide-gray-200">
                        <!-- 데이터 행들이 여기에 동적으로 추가됩니다 -->
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- 도움말 모달 -->
    <div id="shortcut-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50">
        <div class="flex items-center justify-center min-h-screen">
            <div class="bg-white rounded-lg p-6 max-w-md w-full mx-4">
                <h3 class="text-lg font-semibold mb-4">사용법 및 단축키</h3>
                <div class="space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span>새 행 추가:</span>
                        <kbd class="px-2 py-1 bg-gray-100 rounded">클릭 또는 Insert</kbd>
                    </div>
                    <div class="flex justify-between">
                        <span>행 삭제:</span>
                        <kbd class="px-2 py-1 bg-gray-100 rounded">삭제 버튼</kbd>
                    </div>
                    <div class="flex justify-between">
                        <span>실시간 계산:</span>
                        <kbd class="px-2 py-1 bg-gray-100 rounded">값 입력 시 자동</kbd>
                    </div>
                    <div class="flex justify-between">
                        <span>전체 재계산:</span>
                        <kbd class="px-2 py-1 bg-gray-100 rounded">🔄 버튼</kbd>
                    </div>
                </div>
                <button onclick="closeHelpModal()" class="mt-4 w-full px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600">
                    닫기
                </button>
            </div>
        </div>
    </div>

    <script>
        // 전역 데이터
        let tableData = [
            {
                id: 1,
                salesperson: '김대리',
                agency: 'ENT',
                carrier: 'SK',
                activation_type: '신규',
                model_name: 'iPhone 15',
                base_price: 100000,
                verbal1: 30000,
                verbal2: 20000,
                grade_amount: 15000,
                additional_amount: 5000,
                rebate_total: 170000,
                settlement_amount: 170000,
                tax: 22610,
                margin_after_tax: 170000
            },
            {
                id: 2,
                salesperson: '박과장',
                agency: 'WIN',
                carrier: 'KT',
                activation_type: 'MNP',
                model_name: 'Galaxy S24',
                base_price: 150000,
                verbal1: 40000,
                verbal2: 25000,
                grade_amount: 20000,
                additional_amount: 10000,
                rebate_total: 245000,
                settlement_amount: 245000,
                tax: 32585,
                margin_after_tax: 245000
            }
        ];
        
        // 새 행 추가
        function addNewRow() {
            const newId = Math.max(...tableData.map(r => r.id || 0)) + 1;
            const newRow = {
                id: newId,
                salesperson: '',
                agency: '',
                carrier: 'SK',
                activation_type: '신규',
                model_name: '',
                base_price: 0,
                verbal1: 0,
                verbal2: 0,
                grade_amount: 0,
                additional_amount: 0,
                rebate_total: 0,
                settlement_amount: 0,
                tax: 0,
                margin_after_tax: 0
            };
            
            tableData.push(newRow);
            updateTable();
            updateStats();
            
            // 상태 표시
            showStatus('새 행이 추가되었습니다! (ID: ' + newId + ')', 'success');
        }
        
        // 행 삭제
        function deleteRow(id) {
            if (confirm('이 행을 삭제하시겠습니까?')) {
                tableData = tableData.filter(row => row.id !== id);
                updateTable();
                updateStats();
                showStatus('행이 삭제되었습니다.', 'success');
            }
        }
        
        // 테이블 업데이트
        function updateTable() {
            const tbody = document.getElementById('data-table-body');
            tbody.innerHTML = tableData.map(row => `
                <tr data-id="${row.id}">
                    <td class="px-4 py-4">
                        <input type="checkbox" class="row-select">
                    </td>
                    <td class="px-4 py-4">
                        <input type="text" value="${row.salesperson}" 
                               onchange="updateRowData(${row.id}, 'salesperson', this.value)"
                               class="w-full px-2 py-1 border rounded">
                    </td>
                    <td class="px-4 py-4">
                        <select onchange="updateRowData(${row.id}, 'agency', this.value)"
                                class="w-full px-2 py-1 border rounded">
                            <option value="" ${!row.agency ? 'selected' : ''}>선택</option>
                            <option value="ENT" ${row.agency === 'ENT' ? 'selected' : ''}>이앤티</option>
                            <option value="WIN" ${row.agency === 'WIN' ? 'selected' : ''}>앤투윈</option>
                            <option value="CHOSI" ${row.agency === 'CHOSI' ? 'selected' : ''}>초시대</option>
                        </select>
                    </td>
                    <td class="px-4 py-4">
                        <select onchange="updateRowData(${row.id}, 'carrier', this.value)"
                                class="w-full px-2 py-1 border rounded">
                            <option value="SK" ${row.carrier === 'SK' ? 'selected' : ''}>SK</option>
                            <option value="KT" ${row.carrier === 'KT' ? 'selected' : ''}>KT</option>
                            <option value="LG" ${row.carrier === 'LG' ? 'selected' : ''}>LG</option>
                        </select>
                    </td>
                    <td class="px-4 py-4">
                        <input type="number" value="${row.base_price || 0}" 
                               onchange="updateRowData(${row.id}, 'base_price', parseInt(this.value) || 0); calculateRow(${row.id})"
                               class="w-full px-2 py-1 border rounded bg-blue-50">
                    </td>
                    <td class="px-4 py-4">
                        <input type="number" value="${row.verbal1 || 0}" 
                               onchange="updateRowData(${row.id}, 'verbal1', parseInt(this.value) || 0); calculateRow(${row.id})"
                               class="w-full px-2 py-1 border rounded bg-blue-50">
                    </td>
                    <td class="px-4 py-4">
                        <input type="number" value="${row.verbal2 || 0}" 
                               onchange="updateRowData(${row.id}, 'verbal2', parseInt(this.value) || 0); calculateRow(${row.id})"
                               class="w-full px-2 py-1 border rounded bg-blue-50">
                    </td>
                    <td class="px-4 py-4 bg-yellow-50">
                        <span class="font-bold text-yellow-800" id="rebate-${row.id}">${row.rebate_total?.toLocaleString() || '0'}원</span>
                    </td>
                    <td class="px-4 py-4 bg-yellow-50">
                        <span class="font-bold text-yellow-800" id="settlement-${row.id}">${row.settlement_amount?.toLocaleString() || '0'}원</span>
                    </td>
                    <td class="px-4 py-4 bg-red-50">
                        <span class="font-bold text-red-800" id="tax-${row.id}">${row.tax?.toLocaleString() || '0'}원</span>
                    </td>
                    <td class="px-4 py-4 bg-green-50">
                        <span class="font-bold text-green-800" id="margin-${row.id}">${row.margin_after_tax?.toLocaleString() || '0'}원</span>
                    </td>
                    <td class="px-4 py-4">
                        <button onclick="deleteRow(${row.id})" 
                                class="px-2 py-1 bg-red-500 text-white rounded hover:bg-red-600 text-xs">
                            🗑️ 삭제
                        </button>
                    </td>
                </tr>
            `).join('');
        }
        
        // 행 데이터 업데이트
        function updateRowData(id, field, value) {
            const row = tableData.find(r => r.id === id);
            if (row) {
                row[field] = value;
                console.log(`행 ${id} 업데이트:`, field, '=', value);
            }
        }
        
        // 실시간 계산
        async function calculateRow(id) {
            const row = tableData.find(r => r.id === id);
            if (!row) return;
            
            try {
                showStatus('계산 중...', 'loading');
                
                const response = await fetch('/api/calculation/row', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        price_setting: row.base_price || 0,
                        verbal1: row.verbal1 || 0,
                        verbal2: row.verbal2 || 0,
                        grade_amount: row.grade_amount || 0,
                        addon_amount: row.additional_amount || 0
                    })
                });
                
                const result = await response.json();
                if (result.success) {
                    // 계산 결과 반영
                    row.rebate_total = result.data.total_rebate;
                    row.settlement_amount = result.data.settlement;
                    row.tax = result.data.tax;
                    row.margin_after_tax = result.data.margin_after;
                    
                    // UI 업데이트
                    document.getElementById(`rebate-${id}`).textContent = row.rebate_total.toLocaleString() + '원';
                    document.getElementById(`settlement-${id}`).textContent = row.settlement_amount.toLocaleString() + '원';
                    document.getElementById(`tax-${id}`).textContent = row.tax.toLocaleString() + '원';
                    document.getElementById(`margin-${id}`).textContent = row.margin_after_tax.toLocaleString() + '원';
                    
                    updateStats();
                    showStatus('계산 완료!', 'success');
                    
                    console.log('실시간 계산 완료:', result.data);
                } else {
                    showStatus('계산 실패: ' + result.message, 'error');
                }
            } catch (error) {
                console.error('계산 API 호출 실패:', error);
                showStatus('계산 실패: 네트워크 오류', 'error');
            }
        }
        
        // 전체 재계산
        async function calculateAll() {
            showStatus('전체 재계산 중...', 'loading');
            
            for (const row of tableData) {
                await calculateRow(row.id);
                await new Promise(resolve => setTimeout(resolve, 100)); // 100ms 딜레이
            }
            
            showStatus('전체 재계산 완료!', 'success');
        }
        
        // 통계 업데이트
        function updateStats() {
            const totalCount = tableData.length;
            const totalSettlement = tableData.reduce((sum, row) => sum + (row.settlement_amount || 0), 0);
            const totalMargin = tableData.reduce((sum, row) => sum + (row.margin_after_tax || 0), 0);
            const avgMargin = totalSettlement > 0 ? (totalMargin / totalSettlement * 100) : 0;
            
            document.getElementById('total-count').textContent = totalCount.toLocaleString();
            document.getElementById('total-settlement').textContent = totalSettlement.toLocaleString() + '원';
            document.getElementById('total-margin').textContent = totalMargin.toLocaleString() + '원';
            document.getElementById('avg-margin').textContent = avgMargin.toFixed(1) + '%';
        }
        
        // 상태 표시
        function showStatus(message, type = 'info') {
            const indicator = document.getElementById('status-indicator');
            indicator.textContent = message;
            
            // 스타일 초기화
            indicator.className = 'px-2 py-1 text-xs rounded';
            
            if (type === 'success') {
                indicator.className += ' bg-green-100 text-green-800';
            } else if (type === 'error') {
                indicator.className += ' bg-red-100 text-red-800';
            } else if (type === 'loading') {
                indicator.className += ' bg-blue-100 text-blue-800';
            } else {
                indicator.className += ' bg-gray-100 text-gray-600';
            }
            
            // 3초 후 기본 상태로 복원
            if (type !== 'loading') {
                setTimeout(() => {
                    indicator.textContent = '준비됨';
                    indicator.className = 'px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded';
                }, 3000);
            }
        }
        
        // 모달 함수
        function closeHelpModal() {
            document.getElementById('shortcut-modal').classList.add('hidden');
        }
        
        // 이벤트 리스너
        document.addEventListener('DOMContentLoaded', function() {
            // 버튼 이벤트
            document.getElementById('add-row-btn').addEventListener('click', addNewRow);
            document.getElementById('calculate-all-btn').addEventListener('click', calculateAll);
            document.getElementById('help-btn').addEventListener('click', function() {
                document.getElementById('shortcut-modal').classList.remove('hidden');
            });
            
            // 키보드 단축키
            document.addEventListener('keydown', function(e) {
                if (e.key === 'F1') {
                    e.preventDefault();
                    document.getElementById('shortcut-modal').classList.remove('hidden');
                }
                if (e.key === 'Escape') {
                    closeHelpModal();
                }
                if (e.key === 'Insert') {
                    e.preventDefault();
                    addNewRow();
                }
            });
            
            // 초기 테이블 렌더링
            updateTable();
            updateStats();
            
            console.log('Simple AgGrid 시스템 초기화 완료');
            showStatus('시스템 준비 완료', 'success');
        });
    </script>
</body>
</html>