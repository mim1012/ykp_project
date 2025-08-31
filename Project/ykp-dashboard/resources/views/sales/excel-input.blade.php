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
        <h1>📊 판매 데이터 입력 (Excel 스타일)</h1>
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
            <button onclick="addRow()">➕ 행 추가</button>
            <button onclick="deleteRow()">➖ 행 삭제</button>
            <button onclick="saveData()" class="save-btn">💾 저장 (Ctrl+S)</button>
            <button onclick="window.location.href='/admin'">🏠 대시보드</button>
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
            {data: 'tax', type: 'numeric', numericFormat: {pattern: '0,0'}, title: '세금(13.3%)', readOnly: true, renderer: 'calculatedRenderer'},
            {data: 'margin_before_tax', type: 'numeric', numericFormat: {pattern: '0,0'}, title: '세전마진', readOnly: true, renderer: 'calculatedRenderer'},
            {data: 'cash_received', type: 'numeric', numericFormat: {pattern: '0,0'}, title: '현금받은것'},
            {data: 'payback', type: 'numeric', numericFormat: {pattern: '0,0'}, title: '페이백'},
            {data: 'margin_after_tax', type: 'numeric', numericFormat: {pattern: '0,0'}, title: '세후마진', readOnly: true, renderer: 'calculatedRenderer'},
            {data: 'monthly_fee', type: 'numeric', numericFormat: {pattern: '0,0'}, title: '월요금'},
            {data: 'phone_number', type: 'text', title: '전화번호'},
            {data: 'salesperson', type: 'text', title: '영업사원'},
            {data: 'memo', type: 'text', title: '메모'}
        ];

        // 초기 데이터
        let data = [];
        for(let i = 0; i < 20; i++) {
            data.push({
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
            });
        }

        // Handsontable 초기화
        const container = document.getElementById('grid');
        const hot = new Handsontable(container, {
            data: data,
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
                
                // 세금 계산 (13.3%)
                const tax = Math.round(settlementAmount * 0.133);
                
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

        // 행 추가
        function addRow() {
            hot.alter('insert_row_below', hot.countRows() - 1, 5);
        }

        // 행 삭제
        function deleteRow() {
            const selected = hot.getSelected();
            if (selected) {
                hot.alter('remove_row', selected[0][0]);
            }
        }

        // 데이터 저장
        function saveData() {
            const status = document.getElementById('status');
            status.style.display = 'block';
            status.textContent = '저장 중...';
            
            const validData = hot.getSourceData().filter(row => {
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
            })
            .catch(error => {
                status.textContent = '❌ 저장 실패!';
                console.error(error);
            });
        }

        // Ctrl+S 단축키
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                saveData();
            }
        });

        // 5초마다 자동 저장
        setInterval(saveData, 5000);
    </script>
</body>
</html>