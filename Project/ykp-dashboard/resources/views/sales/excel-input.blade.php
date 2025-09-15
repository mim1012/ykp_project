<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>판매 데이터 입력 - YKP ERP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.css">
    <style>
        body {
            font-family: 'Malgun Gothic', sans-serif;
            margin: 0;
            padding: 20px;
            background: #f5f5f5;
        }
        .header {
            background: white;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            margin: 0;
            color: #333;
            font-size: 24px;
        }
        .controls {
            margin-top: 15px;
        }
        button {
            background: #4CAF50;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            margin-right: 10px;
            font-size: 14px;
        }
        button:hover {
            background: #45a049;
        }
        .save-btn {
            background: #2196F3;
        }
        .save-btn:hover {
            background: #0b7dda;
        }
        #grid {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .status {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 10px 20px;
            background: #333;
            color: white;
            border-radius: 4px;
            display: none;
        }
        .shortcuts {
            background: #fff3cd;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
            font-size: 13px;
        }
        .shortcuts strong {
            color: #856404;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 style="font-weight:600;color:#0f172a">판매 데이터 입력</h1>
        <div class="shortcuts">
            <strong>단축키:</strong> 
            Tab/Enter: 다음 셀 | 
            ↑↓←→: 이동 | 
            Ctrl+C/V: 복사/붙여넣기 | 
            Ctrl+Z: 실행취소 | 
            Delete: 삭제 | 
            F2: 편집
        </div>
        <div class="controls">
            <button onclick="addRow()" data-testid="add-row">행 추가</button>
            <button onclick="deleteRow()" data-testid="delete-row">행 삭제</button>
            <button onclick="saveData()" class="save-btn" data-testid="save">저장 (Ctrl+S)</button>
            <button onclick="window.location.href='/dashboard'">대시보드</button>
            <span style="margin-left:16px;color:#555">페이지 크기:</span>
            <input id="pageSize" data-testid="page-size" type="number" min="10" max="200" value="50" style="width:70px" oninput="changePageSize(this.value)" />
            <button onclick="prevPage()" data-testid="prev-page">이전</button>
            <span id="pageInfo" data-testid="page-info">1 / 1</span>
            <button onclick="nextPage()" data-testid="next-page">다음</button>
        </div>
    </div>

    <div id="grid"></div>
    <div class="status" id="status">저장 중...</div>

    <script src="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.js"></script>
    <script>
        // 사용자 정보 설정
        window.userData = {
            id: {{ auth()->user()->id ?? 1 }},
            name: '{{ auth()->user()->name ?? "테스트 사용자" }}',
            role: '{{ auth()->user()->role ?? "headquarters" }}',
            store_id: {{ auth()->user()->store_id ?? 'null' }},
            branch_id: {{ auth()->user()->branch_id ?? 'null' }},
            store_name: '{{ auth()->user()->store->name ?? "본사" }}',
            branch_name: '{{ auth()->user()->branch->name ?? "본사" }}'
        };
        
        console.log('사용자 정보:', window.userData);
        
        // 컬럼 정의
        const columns = [
            {data: 'sale_date', type: 'date', dateFormat: 'YYYY-MM-DD', title: '판매일'},
            {data: 'store_name', type: 'text', title: '매장명', readOnly: true, renderer: 'calculatedRenderer'},
            {data: 'branch_name', type: 'text', title: '지사명', readOnly: true, renderer: 'calculatedRenderer'},
            {data: 'carrier', type: 'dropdown', source: ['SK', 'KT', 'LG', 'MVNO'], title: '통신사'},
            {data: 'activation_type', type: 'dropdown', source: ['신규', '기변', 'MNP'], title: '개통유형'},
            {data: 'model_name', type: 'text', title: '모델명'},
            {data: 'base_price', type: 'numeric', numericFormat: {pattern: '0,0'}, title: '기본료'},
            {data: 'verbal1', type: 'numeric', numericFormat: {pattern: '0,0'}, title: '구두1'},
            {data: 'verbal2', type: 'numeric', numericFormat: {pattern: '0,0'}, title: '구두2'},
            {data: 'grade_amount', type: 'numeric', numericFormat: {pattern: '0,0'}, title: '등급'},
            {data: 'additional_amount', type: 'numeric', numericFormat: {pattern: '0,0'}, title: '추가'},
            {data: 'rebate_total', type: 'numeric', numericFormat: {pattern: '0,0'}, title: '리베이트합계', readOnly: true, renderer: 'calculatedRenderer'},
            {data: 'cash_activation', type: 'numeric', numericFormat: {pattern: '0,0'}, title: '현금개통비'},
            {data: 'usim_fee', type: 'numeric', numericFormat: {pattern: '0,0'}, title: '유심비'},
            {data: 'new_mnp_discount', type: 'numeric', numericFormat: {pattern: '0,0'}, title: '신규/MNP할인'},
            {data: 'deduction', type: 'numeric', numericFormat: {pattern: '0,0'}, title: '차감'},
            {data: 'settlement_amount', type: 'numeric', numericFormat: {pattern: '0,0'}, title: '정산금액', readOnly: true, renderer: 'calculatedRenderer'},
            {data: 'tax', type: 'numeric', numericFormat: {pattern: '0,0'}, title: '세금(10%)', readOnly: true, renderer: 'calculatedRenderer'},
            {data: 'margin_before_tax', type: 'numeric', numericFormat: {pattern: '0,0'}, title: '세전마진', readOnly: true, renderer: 'calculatedRenderer'},
            {data: 'cash_received', type: 'numeric', numericFormat: {pattern: '0,0'}, title: '현금받은것'},
            {data: 'payback', type: 'numeric', numericFormat: {pattern: '0,0'}, title: '페이백'},
            {data: 'margin_after_tax', type: 'numeric', numericFormat: {pattern: '0,0'}, title: '세후마진', readOnly: true, renderer: 'calculatedRenderer'},
            {data: 'monthly_fee', type: 'numeric', numericFormat: {pattern: '0,0'}, title: '월요금'},
            {data: 'phone_number', type: 'text', title: '전화번호'},
            {data: 'salesperson', type: 'text', title: '영업사원'},
            {data: 'memo', type: 'text', title: '메모'}
        ];

        // 페이징 포함 데이터 관리
        let allData = [];
        let currentPage = 1;
        let pageSize = 50;

        function makeEmptyRow() {
            return {
                sale_date: new Date().toISOString().split('T')[0],
                store_id: window.userData.store_id,
                branch_id: window.userData.branch_id,
                store_name: window.userData.store_name,
                branch_name: window.userData.branch_name,
                carrier: 'SK',
                activation_type: '신규',
                model_name: '',
                base_price: 0,
                verbal1: 0,
                verbal2: 0,
                grade_amount: 0,
                additional_amount: 0,
                rebate_total: 0,
                cash_activation: 0,
                usim_fee: 0,
                new_mnp_discount: 0,
                deduction: 0,
                settlement_amount: 0,
                tax: 0,
                margin_before_tax: 0,
                cash_received: 0,
                payback: 0,
                margin_after_tax: 0,
                monthly_fee: 0,
                phone_number: '',
                salesperson: '',
                memo: ''
            };
        }

        for(let i = 0; i < 200; i++) { allData.push(makeEmptyRow()); }

        // Handsontable 초기화
        const container = document.getElementById('grid');
        const hot = new Handsontable(container, {
            data: [],
            columns: columns,
            rowHeaders: true,
            colHeaders: columns.map(col => col.title),
            width: '100%',
            height: 600,
            stretchH: 'all',
            autoWrapRow: true,
            autoWrapCol: true,
            contextMenu: true,
            manualRowResize: true,
            manualColumnResize: true,
            licenseKey: 'non-commercial-and-evaluation',
            afterChange: function(changes, source) {
                if (source === 'loadData') return;
                calculateRow(changes);
                // 변경 내용을 allData에 반영 (페이지 오프셋 고려)
                if (changes) {
                    const offset = (currentPage - 1) * pageSize;
                    changes.forEach(([row, prop, oldValue, newValue]) => {
                        const absIndex = offset + row;
                        if (allData[absIndex]) {
                            allData[absIndex][prop] = newValue;
                        }
                    });
                }
            },
            cells: function(row, col) {
                const cellProperties = {};
                // 계산 필드는 회색 배경
                if ([9, 14, 15, 16, 19].includes(col)) {
                    cellProperties.renderer = function(instance, td, row, col, prop, value, cellProperties) {
                        Handsontable.renderers.NumericRenderer.apply(this, arguments);
                        td.style.background = '#f0f0f0';
                    };
                }
                return cellProperties;
            }
        });

        // 계산 함수
        function calculateRow(changes) {
            if (!changes) return;
            
            changes.forEach(([row, prop, oldValue, newValue]) => {
                const rowData = hot.getDataAtRow(row);
                
                // 리베이트 합계 계산
                const rebateTotal = (parseFloat(rowData[4]) || 0) + // base_price
                                  (parseFloat(rowData[5]) || 0) + // verbal1
                                  (parseFloat(rowData[6]) || 0) + // verbal2
                                  (parseFloat(rowData[7]) || 0) + // grade_amount
                                  (parseFloat(rowData[8]) || 0);  // additional_amount
                
                // 정산금액 계산
                const settlementAmount = rebateTotal - 
                                       (parseFloat(rowData[10]) || 0) + // cash_activation
                                       (parseFloat(rowData[11]) || 0) + // usim_fee
                                       (parseFloat(rowData[12]) || 0) + // new_mnp_discount
                                       (parseFloat(rowData[13]) || 0);  // deduction
                
                // 세금 계산 (10%)
                const tax = Math.round(settlementAmount * 0.10);
                
                // 세전마진
                const marginBeforeTax = settlementAmount - tax;
                
                // 세후마진
                const marginAfterTax = marginBeforeTax + 
                                     (parseFloat(rowData[17]) || 0) + // cash_received
                                     (parseFloat(rowData[18]) || 0);  // payback
                
                // 값 업데이트
                hot.setDataAtCell(row, 9, rebateTotal, 'calculation');
                hot.setDataAtCell(row, 14, settlementAmount, 'calculation');
                hot.setDataAtCell(row, 15, tax, 'calculation');
                hot.setDataAtCell(row, 16, marginBeforeTax, 'calculation');
                hot.setDataAtCell(row, 19, marginAfterTax, 'calculation');
            });
        }

        function refreshPageInfo() {
            const totalPages = Math.max(1, Math.ceil(allData.length / pageSize));
            document.getElementById('pageInfo').textContent = `${currentPage} / ${totalPages}`;
        }

        function loadPage(page) {
            const totalPages = Math.max(1, Math.ceil(allData.length / pageSize));
            currentPage = Math.min(Math.max(1, page), totalPages);
            const start = (currentPage - 1) * pageSize;
            const slice = allData.slice(start, start + pageSize);
            hot.loadData(slice);
            refreshPageInfo();
        }

        function changePageSize(val) {
            pageSize = Math.min(Math.max(parseInt(val || '50', 10), 10), 200);
            loadPage(1);
        }

        function nextPage() { loadPage(currentPage + 1); }
        function prevPage() { loadPage(currentPage - 1); }

        // 행 추가: 전체 데이터에 5개 추가 후 현재 페이지 재로딩
        function addRow() {
            for (let i = 0; i < 5; i++) allData.push(makeEmptyRow());
            loadPage(currentPage);
        }

        // 행 삭제: 선택된 행을 allData에서 삭제(절대 인덱스)
        function deleteRow() {
            const selected = hot.getSelected();
            if (selected) {
                const offset = (currentPage - 1) * pageSize;
                const absIndex = offset + selected[0][0];
                allData.splice(absIndex, 1);
                loadPage(currentPage);
            }
        }

        // 데이터 저장
        function saveData() {
            const status = document.getElementById('status');
            status.style.display = 'block';
            status.textContent = '저장 중...';
            
            // 전체 데이터에서 유효한 행만 저장
            const validData = allData.filter(row => {
                return row.model_name && row.model_name.trim(); // model_name이 있는 행만
            });

            fetch('/api/sales/bulk', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                },
                body: JSON.stringify({
                    sales: validData.map(row => ({
                        sale_date: row.sale_date,
                        store_id: window.userData.store_id,
                        branch_id: window.userData.branch_id,
                        carrier: row.carrier,
                        activation_type: row.activation_type,
                        model_name: row.model_name,
                        base_price: row.base_price || 0,
                        verbal1: row.verbal1 || 0,
                        verbal2: row.verbal2 || 0,
                        grade_amount: row.grade_amount || 0,
                        additional_amount: row.additional_amount || 0,
                        rebate_total: row.rebate_total || 0,
                        cash_activation: row.cash_activation || 0,
                        usim_fee: row.usim_fee || 0,
                        new_mnp_discount: row.new_mnp_discount || 0,
                        deduction: row.deduction || 0,
                        settlement_amount: row.settlement_amount || 0,
                        tax: row.tax || 0,
                        margin_before_tax: row.margin_before_tax || 0,
                        cash_received: row.cash_received || 0,
                        payback: row.payback || 0,
                        margin_after_tax: row.margin_after_tax || 0,
                        monthly_fee: row.monthly_fee || 0,
                        phone_number: row.phone_number,
                        salesperson: row.salesperson,
                        memo: row.memo
                    }))
                })
            })
            .then(response => response.json())
            .then(data => {
                status.textContent = '✅ 저장 완료!';
                setTimeout(() => {
                    status.style.display = 'none';
                }, 2000);
                console.log('저장 성공:', data);

                // 🔄 저장 후 대시보드 실시간 업데이트 트리거
                if (data.success && validData.length > 0) {
                    console.log('💡 대시보드 실시간 업데이트 시작...');
                    refreshDashboardStats(validData.length);
                }
            })
            .catch(error => {
                status.textContent = '❌ 저장 실패!';
                setTimeout(() => {
                    status.style.display = 'none';
                }, 3000);
                console.error('저장 실패:', error);
            });
        }

        // 🔄 대시보드 실시간 업데이트 함수
        function refreshDashboardStats(savedCount = 0) {
            console.log(`📊 대시보드 통계 업데이트 중... (저장된 데이터: ${savedCount}건)`);

            // 1. 실시간 활동 알림 (있다면)
            try {
                if (window.opener && window.opener.addRealtimeActivity) {
                    const storeName = window.userData.store_name || '알 수 없는 매장';
                    window.opener.addRealtimeActivity({
                        type: 'sales_update',
                        message: `${storeName}에서 개통표 ${savedCount}건 입력`,
                        timestamp: new Date().toISOString(),
                        user: window.userData.name,
                        store: storeName
                    });
                    console.log('✅ 실시간 활동 알림 전송 완료');
                }
            } catch (e) {
                console.warn('⚠️ 실시간 활동 알림 실패:', e.message);
            }

            // 2. 대시보드 통계 새로고침 요청 (부모 창)
            try {
                if (window.opener && window.opener.refreshDashboard) {
                    window.opener.refreshDashboard();
                    console.log('✅ 대시보드 새로고침 요청 완료');
                } else if (window.parent && window.parent.refreshDashboard) {
                    window.parent.refreshDashboard();
                    console.log('✅ 부모 대시보드 새로고침 요청 완료');
                }
            } catch (e) {
                console.warn('⚠️ 대시보드 새로고침 요청 실패:', e.message);
            }

            // 3. localStorage 이벤트로 다른 탭에 알림
            try {
                const updateEvent = {
                    type: 'dashboard_update',
                    timestamp: Date.now(),
                    data: {
                        store_id: window.userData.store_id,
                        store_name: window.userData.store_name,
                        saved_count: savedCount,
                        user: window.userData.name
                    }
                };
                localStorage.setItem('dashboard_update_trigger', JSON.stringify(updateEvent));
                console.log('✅ 크로스 탭 업데이트 이벤트 발송 완료');
            } catch (e) {
                console.warn('⚠️ 크로스 탭 업데이트 실패:', e.message);
            }

            // 4. 통계 API 캐시 무효화 요청
            try {
                fetch('/api/dashboard/cache-invalidate', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        store_id: window.userData.store_id,
                        saved_count: savedCount
                    })
                }).then(response => {
                    if (response.ok) {
                        console.log('✅ 통계 캐시 무효화 완료');
                    } else {
                        console.warn('⚠️ 통계 캐시 무효화 실패');
                    }
                }).catch(e => {
                    console.warn('⚠️ 통계 캐시 무효화 네트워크 오류:', e.message);
                });
            } catch (e) {
                console.warn('⚠️ 통계 캐시 무효화 요청 실패:', e.message);
            }

            console.log('🎯 대시보드 실시간 업데이트 처리 완료');
        }

        // 초기 로드
        loadPage(1);

        // Ctrl+S 단축키
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                saveData();
            }
        });

        // 20초마다 자동 저장 (부하 감소)
        setInterval(saveData, 20000);
    </script>
</body>
</html>
