<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>개통표 Professional - YKP ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <script src="https://unpkg.com/ag-grid-community/dist/ag-grid-community.min.js"></script>
    <script src="https://unpkg.com/ag-grid-react/dist/ag-grid-react.min.js"></script>
    <link rel="stylesheet" href="https://unpkg.com/ag-grid-community/styles/ag-grid.css">
    <link rel="stylesheet" href="https://unpkg.com/ag-grid-community/styles/ag-theme-quartz.css">
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css" rel="stylesheet">
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        * { font-family: 'Pretendard Variable', -apple-system, BlinkMacSystemFont, system-ui, sans-serif; }
        .ag-theme-quartz {
            --ag-font-family: 'Pretendard Variable', -apple-system, BlinkMacSystemFont, system-ui, sans-serif;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div id="root"></div>

    <script type="text/babel">
        @verbatim
        const { useState, useEffect, useRef, useCallback, useMemo } = React;
        const { AgGridReact } = agGridReact;

        // 숫자 포맷팅 함수
        const formatKRW = (num) => {
            if (!num) return '₩0';
            return `₩${new Intl.NumberFormat('ko-KR').format(num)}`;
        };

        // 아이콘 컴포넌트들
        const Plus = () => (
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M5 12h14M12 5v14"/>
            </svg>
        );

        const Download = () => (
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/>
            </svg>
        );

        const Maximize2 = () => (
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7"/>
            </svg>
        );

        const Minimize2 = () => (
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                <path d="M4 14h6v6M20 10h-6V4M14 10l7-7M10 14l-7 7"/>
            </svg>
        );

        function SettlementGridPro() {
            const gridRef = useRef(null);
            const [rowData, setRowData] = useState([]);
            const [gridApi, setGridApi] = useState(null);
            const [density, setDensity] = useState('comfortable');
            const [showStripes, setShowStripes] = useState(true);
            const [viewPreset, setViewPreset] = useState('all');
            const [clipboard, setClipboard] = useState(null);

            // 계산 함수
            const calculateRow = useCallback((row) => {
                const 리베총계 = (row.액면셋팅가 || 0) + (row.부가추가 || 0) + 
                               (row.구두1 || 0) + (row.그레이드 || 0) + (row.구두2 || 0);
                
                const 정산금 = 리베총계 - (row.서류상현금개통 || 0) + (row.유심비 || 0) + 
                             (row.신규번이 || 0) + (row.차감 || 0);
                
                const 부소세 = Math.round(정산금 * 0.133);
                const 세전마진 = 정산금 - 부소세 + (row.현금받음 || 0) + (row.페이백 || 0);
                const 세후마진 = 부소세 + 세전마진;
                
                return {
                    ...row,
                    리베총계,
                    정산금,
                    부소세,
                    세전마진,
                    세후마진
                };
            }, []);

            // 지점별 집계 계산
            const calculateSummary = useMemo(() => {
                const 총대수 = rowData.length;
                const 총정산금 = rowData.reduce((sum, row) => sum + row.정산금, 0);
                const 총세금 = rowData.reduce((sum, row) => sum + row.부소세, 0);
                const 총세전매출 = rowData.reduce((sum, row) => sum + row.세전마진, 0);
                const 평균마진 = 총대수 > 0 ? Math.round(총세전매출 / 총대수) : 0;
                
                return {
                    총대수,
                    총정산금,
                    총세금,
                    총세전매출,
                    총세후매출: 총세전매출 + 총세금,
                    평균마진
                };
            }, [rowData]);

            // 하단 합계 행
            const pinnedBottomRowData = useMemo(() => [{
                id: 'total',
                판매자: '합계',
                대리점: '',
                통신사: '',
                개통방식: '',
                모델명: '',
                개통일: '',
                일련번호: '',
                휴대폰번호: '',
                고객명: '',
                생년월일: '',
                액면셋팅가: rowData.reduce((sum, row) => sum + row.액면셋팅가, 0),
                구두1: rowData.reduce((sum, row) => sum + row.구두1, 0),
                구두2: rowData.reduce((sum, row) => sum + row.구두2, 0),
                그레이드: rowData.reduce((sum, row) => sum + row.그레이드, 0),
                부가추가: rowData.reduce((sum, row) => sum + row.부가추가, 0),
                서류상현금개통: rowData.reduce((sum, row) => sum + row.서류상현금개통, 0),
                유심비: rowData.reduce((sum, row) => sum + row.유심비, 0),
                신규번이: rowData.reduce((sum, row) => sum + row.신규번이, 0),
                차감: rowData.reduce((sum, row) => sum + row.차감, 0),
                리베총계: rowData.reduce((sum, row) => sum + row.리베총계, 0),
                정산금: rowData.reduce((sum, row) => sum + row.정산금, 0),
                부소세: rowData.reduce((sum, row) => sum + row.부소세, 0),
                현금받음: rowData.reduce((sum, row) => sum + row.현금받음, 0),
                페이백: rowData.reduce((sum, row) => sum + row.페이백, 0),
                세전마진: rowData.reduce((sum, row) => sum + row.세전마진, 0),
                세후마진: rowData.reduce((sum, row) => sum + row.세후마진, 0),
                메모장: ''
            }], [rowData]);

            // 셀 스타일 클래스
            const getCellClass = useCallback((params) => {
                const classes = [];
                const field = params.colDef?.field;
                
                // 입력 셀 (연노랑)
                const inputFields = ['판매자', '대리점', '통신사', '개통방식', '모델명', '개통일', 
                                    '일련번호', '휴대폰번호', '고객명', '생년월일', '액면셋팅가', 
                                    '구두1', '구두2', '그레이드', '부가추가', '서류상현금개통', 
                                    '유심비', '신규번이', '차감', '현금받음', '페이백', '메모장'];
                
                // 계산 셀 (회색)
                const calcFields = ['리베총계', '정산금', '부소세', '세전마진', '세후마진'];
                
                if (inputFields.includes(field)) {
                    classes.push('bg-amber-50');
                } else if (calcFields.includes(field)) {
                    classes.push('bg-slate-100');
                }
                
                // 합계 행 스타일
                if (params.data?.id === 'total') {
                    classes.push('font-bold bg-gray-200');
                }
                
                // 줄무늬
                if (showStripes && params.node.rowIndex % 2 === 0) {
                    classes.push('bg-opacity-50');
                }
                
                return classes.join(' ');
            }, [showStripes]);

            // 값 포맷터
            const currencyFormatter = (params) => {
                if (!params.value) return '';
                return formatKRW(params.value);
            };

            const plusFormatter = (params) => {
                if (!params.value) return '';
                return `+${formatKRW(Math.abs(params.value))}`;
            };

            const minusFormatter = (params) => {
                if (!params.value) return '';
                return `-${formatKRW(Math.abs(params.value))}`;
            };

            // 컬럼 정의 - 2단 헤더 밴드(그룹) 구조
            const columnDefs = useMemo(() => [
                // 삭제 버튼 (고정)
                {
                    field: 'delete',
                    headerName: '',
                    width: 50,
                    pinned: 'left',
                    lockPosition: true,
                    editable: false,
                    cellRenderer: (params) => {
                        if (params.data?.id === 'total') return '';
                        return `<button class="text-red-500 hover:text-red-700">✕</button>`;
                    },
                    onCellClicked: (params) => {
                        if (params.data?.id !== 'total') {
                            deleteRow(params.data.id);
                        }
                    }
                },
                
                // 기본정보 그룹
                {
                    headerName: '기본정보',
                    children: [
                        { field: '판매자', headerName: '판매자', width: 80, pinned: 'left', editable: true, cellClass: getCellClass },
                        { field: '대리점', headerName: '대리점', width: 80, pinned: 'left', editable: true, cellClass: getCellClass },
                        { field: '통신사', headerName: '통신사', width: 70, pinned: 'left', editable: true, 
                          cellEditor: 'agSelectCellEditor',
                          cellEditorParams: { values: ['SK', 'kt', 'LG', 'SK알뜰', 'kt알뜰', 'LG알뜰'] },
                          cellClass: getCellClass 
                        },
                        { field: '개통방식', headerName: '개통방식', width: 80, pinned: 'left', editable: true,
                          cellEditor: 'agSelectCellEditor', 
                          cellEditorParams: { values: ['mnp', '신규', '기변'] },
                          cellClass: getCellClass 
                        },
                        { field: '모델명', headerName: '모델명', width: 100, pinned: 'left', editable: true, cellClass: getCellClass },
                        { field: '개통일', headerName: '개통일', width: 100, editable: true, cellClass: getCellClass },
                        { field: '일련번호', headerName: '일련번호', width: 120, editable: true, cellClass: getCellClass },
                        { field: '휴대폰번호', headerName: '휴대폰번호', width: 120, editable: true, cellClass: getCellClass },
                        { field: '고객명', headerName: '고객명', width: 80, editable: true, cellClass: getCellClass },
                        { field: '생년월일', headerName: '생년월일', width: 100, editable: true, cellClass: getCellClass }
                    ]
                },
                
                // 단가·구두·그레이드 그룹
                {
                    headerName: '단가·구두·그레이드',
                    children: [
                        { field: '액면셋팅가', headerName: '액면/셋팅가', width: 110, editable: true, 
                          valueFormatter: currencyFormatter, type: 'numericColumn', cellClass: getCellClass },
                        { field: '구두1', headerName: '구두1', width: 90, editable: true,
                          valueFormatter: currencyFormatter, type: 'numericColumn', cellClass: getCellClass },
                        { field: '구두2', headerName: '구두2', width: 90, editable: true,
                          valueFormatter: currencyFormatter, type: 'numericColumn', cellClass: getCellClass },
                        { field: '그레이드', headerName: '그레이드', width: 90, editable: true,
                          valueFormatter: currencyFormatter, type: 'numericColumn', cellClass: getCellClass },
                        { field: '부가추가', headerName: '부가추가', width: 90, editable: true,
                          valueFormatter: currencyFormatter, type: 'numericColumn', cellClass: getCellClass }
                    ]
                },
                
                // 정책 그룹
                {
                    headerName: '정책',
                    children: [
                        { field: '서류상현금개통', headerName: '서류상현금개통', width: 120, editable: true,
                          valueFormatter: currencyFormatter, type: 'numericColumn', cellClass: getCellClass },
                        { field: '유심비', headerName: '유심비(+)', width: 90, editable: true,
                          valueFormatter: plusFormatter, type: 'numericColumn', cellClass: getCellClass,
                          cellStyle: { color: '#2563eb' } },
                        { field: '신규번이', headerName: '신규,번이(-800)', width: 110, editable: true,
                          valueFormatter: minusFormatter, type: 'numericColumn', cellClass: getCellClass,
                          cellStyle: { color: '#dc2626' } },
                        { field: '차감', headerName: '차감(-)', width: 90, editable: true,
                          valueFormatter: minusFormatter, type: 'numericColumn', cellClass: getCellClass,
                          cellStyle: { color: '#dc2626' } }
                    ]
                },
                
                // 정산 그룹
                {
                    headerName: '정산',
                    children: [
                        { field: '리베총계', headerName: '리베총계', width: 110, editable: false,
                          valueFormatter: currencyFormatter, type: 'numericColumn', cellClass: getCellClass,
                          cellRendererParams: { icon: '🔒' } },
                        { field: '정산금', headerName: '정산금', width: 110, editable: false,
                          valueFormatter: currencyFormatter, type: 'numericColumn', cellClass: getCellClass,
                          cellStyle: { color: '#2563eb', fontWeight: 'bold' },
                          cellRendererParams: { icon: '🔒' } }
                    ]
                },
                
                // 세금 그룹
                {
                    headerName: '세금',
                    children: [
                        { field: '부소세', headerName: '부/소세(13.3%)', width: 110, editable: false,
                          valueFormatter: currencyFormatter, type: 'numericColumn', cellClass: getCellClass,
                          cellStyle: { color: '#dc2626' },
                          cellRendererParams: { icon: '🔒' } }
                    ]
                },
                
                // 현금흐름 그룹
                {
                    headerName: '현금흐름',
                    children: [
                        { field: '현금받음', headerName: '현금받음(+)', width: 100, editable: true,
                          valueFormatter: plusFormatter, type: 'numericColumn', cellClass: getCellClass,
                          cellStyle: { color: '#2563eb' } },
                        { field: '페이백', headerName: '페이백(-)', width: 100, editable: true,
                          valueFormatter: minusFormatter, type: 'numericColumn', cellClass: getCellClass,
                          cellStyle: { color: '#dc2626' } }
                    ]
                },
                
                // 마진 그룹 (우측 고정)
                {
                    headerName: '마진',
                    children: [
                        { field: '세전마진', headerName: '세전/마진', width: 110, editable: false,
                          pinned: 'right', valueFormatter: currencyFormatter, type: 'numericColumn', 
                          cellClass: getCellClass, cellRendererParams: { icon: '🔒' } },
                        { field: '세후마진', headerName: '세후/마진', width: 110, editable: false,
                          pinned: 'right', valueFormatter: currencyFormatter, type: 'numericColumn',
                          cellClass: getCellClass, cellStyle: { color: '#16a34a', fontWeight: 'bold' },
                          cellRendererParams: { icon: '🔒' } }
                    ]
                },
                
                // 메모
                {
                    field: '메모장',
                    headerName: '메모',
                    width: 200,
                    editable: true,
                    cellClass: getCellClass
                }
            ], [getCellClass]);

            // 기본 컬럼 설정
            const defaultColDef = useMemo(() => ({
                sortable: true,
                filter: true,
                resizable: true,
                suppressMovable: false,
                rowHeight: density === 'comfortable' ? 40 : 32,
                headerHeight: 45,
                groupHeaderHeight: 50
            }), [density]);

            // 그리드 준비 이벤트
            const onGridReady = useCallback((params) => {
                setGridApi(params.api);
                
                // 초기 데이터
                const initialData = [
                    {
                        id: '1',
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
                        리베총계: 0,
                        정산금: 0,
                        부소세: 0,
                        현금받음: 50000,
                        페이백: -30000,
                        세전마진: 0,
                        세후마진: 0,
                        메모장: '테스트'
                    }
                ];
                
                const calculatedData = initialData.map(calculateRow);
                setRowData(calculatedData);
            }, [calculateRow]);

            // 키보드 네비게이션 핸들러
            const onCellKeyDown = useCallback((event) => {
                const key = event.event?.key;
                const api = event.api;
                const currentFocus = api.getFocusedCell();
                
                if (!currentFocus) return;
                
                // Ctrl+C (복사)
                if (key === 'c' && event.event?.ctrlKey) {
                    event.event.preventDefault();
                    const selectedRanges = api.getCellRanges();
                    if (selectedRanges && selectedRanges.length > 0) {
                        const range = selectedRanges[0];
                        const clipboardData = {};
                        
                        range.columns.forEach(col => {
                            const colId = col.getColId();
                            const rowNode = api.getDisplayedRowAtIndex(currentFocus.rowIndex);
                            if (rowNode && rowNode.data) {
                                clipboardData[colId] = rowNode.data[colId];
                            }
                        });
                        
                        setClipboard(clipboardData);
                        console.log('Copied:', clipboardData);
                    }
                    return;
                }
                    
                // Ctrl+V (붙여넣기)
                if (key === 'v' && event.event?.ctrlKey && clipboard) {
                    event.event.preventDefault();
                    const rowNode = api.getDisplayedRowAtIndex(currentFocus.rowIndex);
                    if (rowNode && rowNode.data) {
                        const updatedData = { ...rowNode.data, ...clipboard };
                        const calculatedData = calculateRow(updatedData);
                        
                        setRowData(prev => prev.map(row => 
                            row.id === rowNode.data.id ? calculatedData : row
                        ));
                    }
                    return;
                }
                    
                // Delete
                if (key === 'Delete') {
                    const rowNode = api.getDisplayedRowAtIndex(currentFocus.rowIndex);
                    if (rowNode && rowNode.data && currentFocus.column) {
                        const field = currentFocus.column.getColId();
                        const updatedData = { ...rowNode.data, [field]: 0 };
                        const calculatedData = calculateRow(updatedData);
                        
                        setRowData(prev => prev.map(row => 
                            row.id === rowNode.data.id ? calculatedData : row
                        ));
                    }
                    return;
                }
                
                // 방향키는 AG Grid 기본 네비게이션을 사용
            }, [calculateRow, clipboard]);

            // 셀 편집 완료
            const onCellEditingStopped = useCallback((event) => {
                if (event.data) {
                    const calculatedData = calculateRow(event.data);
                    setRowData(prev => prev.map(row => 
                        row.id === event.data.id ? calculatedData : row
                    ));
                }
            }, [calculateRow]);

            // 행 추가
            const addRow = useCallback(() => {
                const newId = `${Date.now()}`;
                const newRow = {
                    id: newId,
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
                
                setRowData(prev => [...prev, calculateRow(newRow)]);
            }, [calculateRow]);

            // 행 삭제
            const deleteRow = useCallback((id) => {
                setRowData(prev => prev.filter(row => row.id !== id));
            }, []);

            // Excel 내보내기
            const exportToExcel = useCallback(() => {
                if (gridApi) {
                    gridApi.exportDataAsCsv({
                        fileName: `개통표_용산점_2025-08.csv`,
                        allColumns: true
                    });
                }
            }, [gridApi]);

            // 뷰 프리셋 적용
            const applyViewPreset = useCallback((preset) => {
                if (!gridApi) return;
                
                const allColumns = gridApi.getColumnDefs();
                
                switch(preset) {
                    case 'input':
                        // 입력 관련 컬럼만 표시
                        gridApi.setColumnsVisible(['리베총계', '정산금', '부소세', '세전마진', '세후마진'], false);
                        break;
                    case 'review':
                        // 검토용 주요 컬럼만
                        gridApi.setColumnsVisible(['일련번호', '생년월일', '메모장'], false);
                        break;
                    case 'mini':
                        // 최소 컬럼만
                        gridApi.setColumnsVisible([
                            '일련번호', '생년월일', '구두1', '구두2', '그레이드', 
                            '부가추가', '서류상현금개통', '메모장'
                        ], false);
                        break;
                    default:
                        // 모든 컬럼 표시
                        if (allColumns) {
                            allColumns.forEach(col => {
                                if (col.field) {
                                    gridApi.setColumnsVisible([col.field], true);
                                }
                            });
                        }
                }
                
                setViewPreset(preset);
            }, [gridApi]);

            // 그리드 옵션
            const gridOptions = useMemo(() => ({
                animateRows: true,
                enableRangeSelection: true,
                enableCellTextSelection: true,
                stopEditingWhenCellsLoseFocus: true,
                undoRedoCellEditing: true,
                undoRedoCellEditingLimit: 20,
                rowSelection: 'multiple',
                suppressRowClickSelection: true,
                getRowId: (params) => params.data.id,
                pinnedBottomRowData: pinnedBottomRowData,
                rowClassRules: {
                    'bg-gray-100': (params) => params.data?.id === 'total'
                },
                // 키보드 네비게이션 설정
                suppressKeyboardEvent: (params) => {
                    // AG Grid 기본 키보드 네비게이션을 사용
                    return false;
                },
                // 편집 종료 후 다음 셀로 이동
                enterNavigatesVertically: false,
                enterNavigatesVerticallyAfterEdit: false,
                tabToNextCell: (params) => {
                    const allColumns = params.api.getColumnDefs();
                    const visibleColumns = allColumns.filter(col => 
                        !col.hide && col.field !== 'delete' && col.editable !== false
                    );
                    
                    let nextCellPosition = params.nextCellPosition;
                    
                    // Tab으로 편집 가능한 셀만 이동
                    while (nextCellPosition) {
                        const column = nextCellPosition.column;
                        const colDef = column.getColDef();
                        
                        if (colDef.editable !== false && colDef.field !== 'delete') {
                            return nextCellPosition;
                        }
                        
                        // 다음 편집 가능한 컬럼 찾기
                        const currentColIndex = visibleColumns.findIndex(col => col.field === column.getColId());
                        if (currentColIndex < visibleColumns.length - 1) {
                            nextCellPosition = {
                                rowIndex: nextCellPosition.rowIndex,
                                column: params.api.getColumn(visibleColumns[currentColIndex + 1].field)
                            };
                        } else {
                            // 다음 행의 첫 번째 편집 가능한 셀로
                            nextCellPosition = {
                                rowIndex: nextCellPosition.rowIndex + 1,
                                column: params.api.getColumn(visibleColumns[0].field)
                            };
                        }
                    }
                    
                    return nextCellPosition;
                }
            }), [pinnedBottomRowData]);

            return (
                <div className="flex flex-col h-screen bg-gray-50">
                    {/* 상단 요약 바 (고정) */}
                    <div className="bg-white border-b border-gray-200 shadow-sm">
                        <div className="px-6 py-3">
                            <div className="grid grid-cols-8 gap-4 text-sm">
                                <div className="flex items-center">
                                    <span className="text-gray-600 mr-2">지점:</span>
                                    <span className="font-bold">용산점</span>
                                </div>
                                <div className="flex items-center">
                                    <span className="text-gray-600 mr-2">월:</span>
                                    <span className="font-bold">2025-08</span>
                                </div>
                                <div className="flex items-center">
                                    <span className="text-gray-600 mr-2">총대수:</span>
                                    <span className="font-bold text-blue-600">{calculateSummary.총대수}대</span>
                                </div>
                                <div className="flex items-center">
                                    <span className="text-gray-600 mr-2">평균마진:</span>
                                    <span className="font-bold">{formatKRW(calculateSummary.평균마진)}</span>
                                </div>
                                <div className="flex items-center">
                                    <span className="text-gray-600 mr-2">정산금:</span>
                                    <span className="font-bold text-blue-600">{formatKRW(calculateSummary.총정산금)}</span>
                                </div>
                                <div className="flex items-center">
                                    <span className="text-gray-600 mr-2">세금:</span>
                                    <span className="font-bold text-red-600">{formatKRW(calculateSummary.총세금)}</span>
                                </div>
                                <div className="flex items-center">
                                    <span className="text-gray-600 mr-2">세전매출:</span>
                                    <span className="font-bold">{formatKRW(calculateSummary.총세전매출)}</span>
                                </div>
                                <div className="flex items-center bg-yellow-50 px-2 py-1 rounded">
                                    <span className="text-gray-600 mr-2">세후매출:</span>
                                    <span className="font-bold text-red-600">{formatKRW(calculateSummary.총세후매출)}</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* 툴바 */}
                    <div className="bg-white border-b border-gray-200 px-6 py-3">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-2">
                                <button
                                    onClick={addRow}
                                    className="px-3 py-1.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium flex items-center gap-1"
                                >
                                    <Plus />
                                    행 추가
                                </button>
                                
                                <button
                                    onClick={exportToExcel}
                                    className="px-3 py-1.5 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm font-medium flex items-center gap-1"
                                >
                                    <Download />
                                    Excel
                                </button>
                                
                                <div className="border-l border-gray-300 h-6 mx-2" />
                                
                                {/* 밀도 토글 */}
                                <button
                                    onClick={() => setDensity(density === 'comfortable' ? 'compact' : 'comfortable')}
                                    className="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium flex items-center gap-1"
                                >
                                    {density === 'comfortable' ? <Minimize2 /> : <Maximize2 />}
                                    {density === 'comfortable' ? 'Compact' : 'Comfortable'}
                                </button>
                                
                                {/* 줄무늬 토글 */}
                                <button
                                    onClick={() => setShowStripes(!showStripes)}
                                    className="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded-lg text-sm font-medium"
                                >
                                    줄무늬 {showStripes ? 'OFF' : 'ON'}
                                </button>
                                
                                <div className="border-l border-gray-300 h-6 mx-2" />
                                
                                {/* 뷰 프리셋 */}
                                <select
                                    value={viewPreset}
                                    onChange={(e) => applyViewPreset(e.target.value)}
                                    className="px-3 py-1.5 border border-gray-300 rounded-lg text-sm"
                                >
                                    <option value="all">전체 보기</option>
                                    <option value="input">입력 모드</option>
                                    <option value="review">검토 모드</option>
                                    <option value="mini">미니 모드</option>
                                </select>
                            </div>
                            
                            <div className="text-xs text-gray-500">
                                <span className="mr-3">단축키: 방향키/Tab/Enter 이동</span>
                                <span className="mr-3">Ctrl+C/V 복사/붙여넣기</span>
                                <span>Delete 삭제</span>
                            </div>
                        </div>
                    </div>

                    {/* AG Grid */}
                    <div className="flex-1 p-6">
                        <div 
                            className="ag-theme-quartz h-full"
                            style={{ 
                                '--ag-row-height': density === 'comfortable' ? '40px' : '32px',
                                '--ag-header-height': '45px',
                                '--ag-group-header-height': '50px'
                            }}
                        >
                            <AgGridReact
                                ref={gridRef}
                                rowData={rowData}
                                columnDefs={columnDefs}
                                defaultColDef={defaultColDef}
                                onGridReady={onGridReady}
                                onCellKeyDown={onCellKeyDown}
                                onCellEditingStopped={onCellEditingStopped}
                                {...gridOptions}
                            />
                        </div>
                    </div>
                </div>
            );
        }

        ReactDOM.createRoot(document.getElementById('root')).render(<SettlementGridPro />);
        @endverbatim
    </script>
</body>
</html>