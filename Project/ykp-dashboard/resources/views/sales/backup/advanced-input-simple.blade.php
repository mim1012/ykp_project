<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>개통표 Simple - YKP ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css" rel="stylesheet">
    <style>
        * { font-family: 'Pretendard Variable', -apple-system, BlinkMacSystemFont, system-ui, sans-serif; }
        .scrollbar-thin::-webkit-scrollbar { width: 6px; height: 6px; }
        .scrollbar-thin::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        input[type="number"]::-webkit-inner-spin-button { display: none; }
        .sticky-header th { position: sticky; top: 0; z-index: 10; }
        .sticky-col { position: sticky; left: 0; z-index: 5; background: white; }
        
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
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen bg-gray-50 p-4">
        {/* 헤더 */}
        <div class="mb-4 bg-white rounded-lg shadow-sm p-4">
            <div class="flex justify-between items-center mb-4">
                <h1 class="text-2xl font-bold text-gray-800">개통표 (키보드 네비게이션)</h1>
                <div class="flex gap-2">
                    <button
                        onclick="addRow()"
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                    >
                        행 추가
                    </button>
                    <button
                        onclick="exportToExcel()"
                        class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"
                    >
                        Excel 내보내기
                    </button>
                </div>
            </div>
            
            {/* 지점별 요약 */}
            <div class="grid grid-cols-6 gap-4 p-4 bg-blue-50 rounded-lg" id="summary">
                <div>
                    <span class="text-sm text-gray-600">지점명:</span>
                    <span class="ml-2 font-bold">용산점</span>
                </div>
                <div>
                    <span class="text-sm text-gray-600">월:</span>
                    <span class="ml-2 font-bold">2025-08</span>
                </div>
                <div>
                    <span class="text-sm text-gray-600">총 대수:</span>
                    <span class="ml-2 font-bold" id="total-count">0대</span>
                </div>
                <div>
                    <span class="text-sm text-gray-600">평균마진:</span>
                    <span class="ml-2 font-bold" id="avg-margin">₩0</span>
                </div>
                <div>
                    <span class="text-sm text-gray-600">정산금:</span>
                    <span class="ml-2 font-bold text-blue-600" id="total-settlement">₩0</span>
                </div>
                <div>
                    <span class="text-sm text-gray-600">세후매출:</span>
                    <span class="ml-2 font-bold text-green-600" id="total-after-tax">₩0</span>
                </div>
            </div>
            
            {/* 키보드 단축키 안내 */}
            <div class="mt-4 text-xs text-gray-500 bg-gray-100 p-2 rounded">
                <span class="mr-4"><strong>방향키:</strong> 셀 이동</span>
                <span class="mr-4"><strong>Tab:</strong> 다음 셀</span>
                <span class="mr-4"><strong>Enter:</strong> 편집 완료</span>
                <span class="mr-4"><strong>Delete:</strong> 셀 내용 삭제</span>
                <span class="mr-4"><strong>Ctrl+C/V:</strong> 복사/붙여넣기</span>
            </div>
        </div>

        {/* 테이블 */}
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="overflow-x-auto scrollbar-thin">
                <table class="w-full text-sm no-select" id="settlement-table">
                    <thead class="bg-gray-100 sticky-header">
                        <tr>
                            <th class="px-2 py-2 text-center border sticky-col">삭제</th>
                            <th class="px-2 py-2 text-center border">판매자</th>
                            <th class="px-2 py-2 text-center border">대리점</th>
                            <th class="px-2 py-2 text-center border">통신사</th>
                            <th class="px-2 py-2 text-center border">개통방식</th>
                            <th class="px-2 py-2 text-center border">모델명</th>
                            <th class="px-2 py-2 text-center border">개통일</th>
                            <th class="px-2 py-2 text-center border">일련번호</th>
                            <th class="px-2 py-2 text-center border">휴대폰번호</th>
                            <th class="px-2 py-2 text-center border">고객명</th>
                            <th class="px-2 py-2 text-center border">생년월일</th>
                            <th class="px-2 py-2 text-center border bg-yellow-50">액면/셋팅가</th>
                            <th class="px-2 py-2 text-center border bg-yellow-50">구두1</th>
                            <th class="px-2 py-2 text-center border bg-yellow-50">구두2</th>
                            <th class="px-2 py-2 text-center border bg-yellow-50">그레이드</th>
                            <th class="px-2 py-2 text-center border bg-yellow-50">부가추가</th>
                            <th class="px-2 py-2 text-center border bg-yellow-50">서류상현금개통</th>
                            <th class="px-2 py-2 text-center border bg-yellow-50">유심비(+)</th>
                            <th class="px-2 py-2 text-center border bg-yellow-50">신규,번이(-800)</th>
                            <th class="px-2 py-2 text-center border bg-yellow-50">차감(-)</th>
                            <th class="px-2 py-2 text-center border bg-gray-200">리베총계 🔒</th>
                            <th class="px-2 py-2 text-center border bg-gray-200">정산금 🔒</th>
                            <th class="px-2 py-2 text-center border bg-gray-200">부/소세(13.3%) 🔒</th>
                            <th class="px-2 py-2 text-center border bg-yellow-50">현금받음(+)</th>
                            <th class="px-2 py-2 text-center border bg-yellow-50">페이백(-)</th>
                            <th class="px-2 py-2 text-center border bg-gray-200">세전/마진 🔒</th>
                            <th class="px-2 py-2 text-center border bg-gray-200">세후/마진 🔒</th>
                            <th class="px-2 py-2 text-center border">메모장</th>
                        </tr>
                    </thead>
                    <tbody id="table-body">
                        {/* 동적으로 행이 추가됨 */}
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        // 전역 변수
        let data = [];
        let currentRow = 0;
        let currentCol = 0;
        let clipboard = null;

        // 필드 정의
        const fields = [
            'delete', '판매자', '대리점', '통신사', '개통방식', '모델명', '개통일', '일련번호', '휴대폰번호', '고객명', '생년월일',
            '액면셋팅가', '구두1', '구두2', '그레이드', '부가추가', '서류상현금개통', '유심비', '신규번이', '차감',
            '리베총계', '정산금', '부소세', '현금받음', '페이백', '세전마진', '세후마진', '메모장'
        ];

        const editableFields = [
            '판매자', '대리점', '통신사', '개통방식', '모델명', '개통일', '일련번호', '휴대폰번호', '고객명', '생년월일',
            '액면셋팅가', '구두1', '구두2', '그레이드', '부가추가', '서류상현금개통', '유심비', '신규번이', '차감',
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
            return parseInt(str.toString().replace(/[^\d-]/g, '')) || 0;
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

        // 계산 함수
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

        // 요약 업데이트
        function updateSummary() {
            const totalCount = data.length;
            const totalSettlement = data.reduce((sum, row) => sum + row.정산금, 0);
            const totalTax = data.reduce((sum, row) => sum + row.부소세, 0);
            const totalBeforeTax = data.reduce((sum, row) => sum + row.세전마진, 0);
            const avgMargin = totalCount > 0 ? Math.round(totalBeforeTax / totalCount) : 0;

            document.getElementById('total-count').textContent = totalCount + '대';
            document.getElementById('avg-margin').textContent = formatKRW(avgMargin);
            document.getElementById('total-settlement').textContent = formatKRW(totalSettlement);
            document.getElementById('total-after-tax').textContent = formatKRW(totalBeforeTax + totalTax);
        }

        // 테이블 렌더링
        function renderTable() {
            const tbody = document.getElementById('table-body');
            tbody.innerHTML = '';

            data.forEach((row, rowIndex) => {
                const tr = document.createElement('tr');
                tr.className = 'hover:bg-gray-50';
                tr.dataset.row = rowIndex;

                let html = '';
                fields.forEach((field, colIndex) => {
                    let content = '';
                    let cellClass = 'px-2 py-1 border';

                    if (field === 'delete') {
                        cellClass += ' sticky-col';
                        content = `<button onclick="deleteRow(${row.id})" class="text-red-500 hover:text-red-700">✕</button>`;
                    } else if (editableFields.includes(field)) {
                        cellClass += ' input-field';
                        if (['통신사', '개통방식'].includes(field)) {
                            const options = field === '통신사' 
                                ? ['SK', 'kt', 'LG', 'SK알뜰', 'kt알뜰', 'LG알뜰']
                                : ['mnp', '신규', '기변'];
                            content = `<select onchange="updateCell(${rowIndex}, '${field}', this.value)" class="w-full px-1 py-0.5">
                                ${options.map(opt => `<option value="${opt}" ${row[field] === opt ? 'selected' : ''}>${opt}</option>`).join('')}
                            </select>`;
                        } else if (field === '개통일') {
                            content = `<input type="date" value="${row[field]}" onchange="updateCell(${rowIndex}, '${field}', this.value)" class="w-full px-1 py-0.5">`;
                        } else if (['액면셋팅가', '구두1', '구두2', '그레이드', '부가추가', '서류상현금개통', '유심비', '신규번이', '차감', '현금받음', '페이백'].includes(field)) {
                            let displayValue = '';
                            if (field === '유심비' || field === '현금받음') {
                                displayValue = row[field] ? `+${formatKRW(Math.abs(row[field]))}` : '';
                            } else if (field === '신규번이' || field === '차감' || field === '페이백') {
                                displayValue = row[field] ? `-${formatKRW(Math.abs(row[field]))}` : '';
                            } else {
                                displayValue = formatKRW(row[field]);
                            }
                            content = `<input type="text" value="${displayValue}" onchange="updateNumericCell(${rowIndex}, '${field}', this.value)" class="w-full px-1 py-0.5 text-right" data-row="${rowIndex}" data-col="${colIndex}">`;
                        } else {
                            content = `<input type="text" value="${row[field]}" onchange="updateCell(${rowIndex}, '${field}', this.value)" class="w-full px-1 py-0.5" data-row="${rowIndex}" data-col="${colIndex}">`;
                        }
                    } else {
                        cellClass += ' calc-field text-right font-semibold';
                        if (field === '정산금') {
                            cellClass += ' text-blue-600';
                        } else if (field === '부소세') {
                            cellClass += ' text-red-600';
                        } else if (field === '세후마진') {
                            cellClass += ' text-green-600';
                        }
                        content = formatKRW(row[field]);
                    }

                    html += `<td class="${cellClass}" data-row="${rowIndex}" data-col="${colIndex}">${content}</td>`;
                });

                tr.innerHTML = html;
                tbody.appendChild(tr);
            });

            updateSummary();
        }

        // 셀 업데이트
        function updateCell(rowIndex, field, value) {
            data[rowIndex][field] = value;
            data[rowIndex] = calculateRow(data[rowIndex]);
            renderTable();
        }

        function updateNumericCell(rowIndex, field, value) {
            let numValue = parseNumber(value);
            if (['신규번이', '차감', '페이백'].includes(field) && numValue > 0) {
                numValue = -numValue;
            }
            data[rowIndex][field] = numValue;
            data[rowIndex] = calculateRow(data[rowIndex]);
            renderTable();
        }

        // 행 추가
        function addRow() {
            const newId = Date.now();
            const newRow = calculateRow(createEmptyRow(newId));
            data.push(newRow);
            renderTable();
        }

        // 행 삭제
        function deleteRow(id) {
            data = data.filter(row => row.id !== id);
            renderTable();
        }

        // Excel 내보내기
        function exportToExcel() {
            // 간단한 CSV 내보내기
            let csv = fields.join(',') + '\n';
            data.forEach(row => {
                const values = fields.map(field => {
                    if (field === 'delete') return '';
                    return row[field] || '';
                });
                csv += values.join(',') + '\n';
            });

            const blob = new Blob([csv], { type: 'text/csv' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `개통표_${new Date().toISOString().split('T')[0]}.csv`;
            a.click();
            window.URL.revokeObjectURL(url);
        }

        // 키보드 네비게이션
        function focusCell(rowIndex, colIndex) {
            const cell = document.querySelector(`input[data-row="${rowIndex}"][data-col="${colIndex}"]`);
            if (cell) {
                cell.focus();
                cell.select();
                currentRow = rowIndex;
                currentCol = colIndex;
            }
        }

        function moveFocus(rowDelta, colDelta) {
            let newRow = currentRow + rowDelta;
            let newCol = currentCol + colDelta;

            // 경계 확인
            if (newRow < 0) newRow = 0;
            if (newRow >= data.length) newRow = data.length - 1;
            if (newCol < 1) newCol = 1; // delete 열 건너뛰기
            if (newCol >= fields.length) newCol = fields.length - 1;

            // 편집 불가능한 열 건너뛰기
            while (newCol < fields.length && !editableFields.includes(fields[newCol])) {
                newCol += colDelta > 0 ? 1 : -1;
                if (newCol < 1 || newCol >= fields.length) break;
            }

            if (newCol >= 1 && newCol < fields.length && editableFields.includes(fields[newCol])) {
                focusCell(newRow, newCol);
            }
        }

        // 키보드 이벤트 리스너
        document.addEventListener('keydown', function(e) {
            const target = e.target;
            
            // 입력 필드에서만 키보드 네비게이션 처리
            if (target.tagName === 'INPUT' && target.dataset.row && target.dataset.col) {
                const rowIndex = parseInt(target.dataset.row);
                const colIndex = parseInt(target.dataset.col);
                
                switch(e.key) {
                    case 'ArrowUp':
                        e.preventDefault();
                        moveFocus(-1, 0);
                        break;
                    case 'ArrowDown':
                    case 'Enter':
                        e.preventDefault();
                        moveFocus(1, 0);
                        break;
                    case 'ArrowLeft':
                        if (target.selectionStart === 0) {
                            e.preventDefault();
                            moveFocus(0, -1);
                        }
                        break;
                    case 'ArrowRight':
                    case 'Tab':
                        if (!e.shiftKey && (e.key === 'Tab' || target.selectionStart === target.value.length)) {
                            e.preventDefault();
                            moveFocus(0, 1);
                        } else if (e.shiftKey && e.key === 'Tab') {
                            e.preventDefault();
                            moveFocus(0, -1);
                        }
                        break;
                    case 'Delete':
                        if (e.ctrlKey) {
                            e.preventDefault();
                            target.value = '';
                            target.dispatchEvent(new Event('change'));
                        }
                        break;
                    case 'c':
                        if (e.ctrlKey) {
                            clipboard = target.value;
                            console.log('Copied:', clipboard);
                        }
                        break;
                    case 'v':
                        if (e.ctrlKey && clipboard !== null) {
                            e.preventDefault();
                            target.value = clipboard;
                            target.dispatchEvent(new Event('change'));
                        }
                        break;
                }
            }
        });

        // 초기 데이터 로드
        document.addEventListener('DOMContentLoaded', function() {
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
                    메모장: '테스트'
                }
            ];

            data = sampleData.map(calculateRow);
            renderTable();
            
            // 첫 번째 편집 가능한 셀에 포커스
            setTimeout(() => focusCell(0, 1), 100);
        });
    </script>
</body>
</html>