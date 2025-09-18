<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>개통표 - YKP ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
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
        
        /* 현재 포커스 셀 */
        .cell-focused {
            outline: 2px solid #2563eb !important;
            outline-offset: -1px;
            z-index: 20;
        }
        
        /* 드래그 중 커서 */
        body.dragging {
            cursor: cell !important;
        }
        body.dragging * {
            cursor: cell !important;
        }
        
        /* 컬럼 리사이즈 핸들 */
        .resize-handle {
            position: absolute;
            right: 0;
            top: 0;
            bottom: 0;
            width: 5px;
            cursor: col-resize;
            background: transparent;
        }
        .resize-handle:hover {
            background: #3b82f6;
        }
        
        /* 테이블 셀 기본 스타일 */
        .data-cell {
            position: relative;
            padding: 0;
        }
        .data-cell input {
            border: 1px solid transparent;
            outline: none;
            width: 100%;
            padding: 2px 4px;
        }
        .data-cell input:focus {
            border-color: #2563eb;
            background: white;
        }
        
        /* Mobile optimizations */
        @media (max-width: 768px) {
            /* Touch-friendly cell inputs */
            .data-cell input {
                min-height: 44px;
                font-size: 16px; /* Prevent zoom on iOS */
                padding: 8px 12px;
            }
            
            /* Mobile table container */
            .table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                border-radius: 0;
                margin: 0 -1rem;
            }
            
            /* Sticky column improvements */
            .sticky-col {
                border-right: 2px solid #e5e7eb;
                box-shadow: 2px 0 4px rgba(0,0,0,0.1);
            }
            
            /* Mobile toolbar */
            .mobile-toolbar {
                position: sticky;
                top: 0;
                z-index: 20;
                background: white;
                border-bottom: 1px solid #e5e7eb;
                padding: 0.75rem;
            }
            
            /* Compact column headers */
            .sticky-header th {
                padding: 8px 4px;
                font-size: 12px;
                min-width: 80px;
            }
            
            /* Mobile buttons */
            .mobile-btn {
                min-height: 44px;
                padding: 8px 16px;
                border-radius: 8px;
                font-size: 14px;
            }
            
            /* Mobile row actions */
            .row-actions {
                position: sticky;
                right: 0;
                background: white;
                border-left: 1px solid #e5e7eb;
                min-width: 60px;
            }
        }
        
        @media (max-width: 414px) {
            /* Very small screens */
            .data-cell input {
                padding: 6px 8px;
            }
            
            .sticky-header th {
                font-size: 11px;
                padding: 6px 3px;
                min-width: 70px;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <div id="root"></div>

    <script type="text/babel">
        @verbatim
        const { useState, useEffect, useRef, useCallback, useMemo } = React;

        // 숫자 포맷팅 함수
        const formatNumber = (num) => {
            if (!num) return '';
            return new Intl.NumberFormat('ko-KR').format(num);
        };

        // 숫자 파싱 함수
        const parseNumber = (str) => {
            if (!str) return 0;
            return parseInt(str.toString().replace(/[^\d-]/g, '')) || 0;
        };

        function SettlementTable() {
            // 샘플 데이터와 동일한 구조
            const createEmptyRow = (id) => ({
                id,
                // 기본 정보 (A-J열)
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
                
                // 금액 관련 필드 (K-S열)
                액면셋팅가: 0,
                구두1: 0,
                구두2: 0,
                그레이드: 0,
                부가추가: 0,
                서류상현금개통: 0,
                유심비: 0,        // Q열 (+표기)
                신규번이: 0,      // R열 (-800표기)
                차감: 0,          // S열 (-표기)
                
                // 계산 필드 (T-Z열)
                리베총계: 0,      // T열 (자동계산)
                정산금: 0,        // U열 (자동계산)
                부소세: 0,        // V열 (자동계산) 13.3%
                현금받음: 0,      // W열 (+표기)
                페이백: 0,        // X열 (-표기)
                세전마진: 0,      // Y열 (자동계산)
                세후마진: 0,      // Z열 (자동계산)
                메모장: ''        // AA열
            });

            // 컬럼 정의 - 순서와 필드명 매핑
            const columns = [
                { field: '판매자', header: '판매자', width: 80, editable: true },
                { field: '대리점', header: '대리점', width: 80, editable: true },
                { field: '통신사', header: '통신사', width: 70, editable: true, type: 'select' },
                { field: '개통방식', header: '개통방식', width: 80, editable: true, type: 'select' },
                { field: '모델명', header: '모델명', width: 100, editable: true },
                { field: '개통일', header: '개통일', width: 100, editable: true, type: 'date' },
                { field: '일련번호', header: '일련번호', width: 120, editable: true },
                { field: '휴대폰번호', header: '휴대폰번호', width: 120, editable: true },
                { field: '고객명', header: '고객명', width: 80, editable: true },
                { field: '생년월일', header: '생년월일', width: 100, editable: true },
                { field: '액면셋팅가', header: '액면/셋팅가', width: 110, editable: true, type: 'number' },
                { field: '구두1', header: '구두1', width: 90, editable: true, type: 'number' },
                { field: '구두2', header: '구두2', width: 90, editable: true, type: 'number' },
                { field: '그레이드', header: '그레이드', width: 90, editable: true, type: 'number' },
                { field: '부가추가', header: '부가추가', width: 90, editable: true, type: 'number' },
                { field: '서류상현금개통', header: '서류상현금개통', width: 120, editable: true, type: 'number' },
                { field: '유심비', header: '유심비(+)', width: 90, editable: true, type: 'number', format: 'plus' },
                { field: '신규번이', header: '신규,번이(-800)', width: 110, editable: true, type: 'number', format: 'minus' },
                { field: '차감', header: '차감(-)', width: 90, editable: true, type: 'number', format: 'minus' },
                { field: '리베총계', header: '리베총계', width: 110, editable: false, type: 'calc' },
                { field: '정산금', header: '정산금', width: 110, editable: false, type: 'calc' },
                { field: '부소세', header: '부/소세(13.3%)', width: 110, editable: false, type: 'calc' },
                { field: '현금받음', header: '현금받음(+)', width: 100, editable: true, type: 'number', format: 'plus' },
                { field: '페이백', header: '페이백(-)', width: 100, editable: true, type: 'number', format: 'minus' },
                { field: '세전마진', header: '세전/마진', width: 110, editable: false, type: 'calc' },
                { field: '세후마진', header: '세후/마진', width: 110, editable: false, type: 'calc' },
                { field: '메모장', header: '메모장', width: 200, editable: true }
            ];

            const [data, setData] = useState([
                {
                    ...createEmptyRow(1),
                    판매자: '홍길동',
                    대리점: 'w',
                    통신사: 'SK',
                    개통방식: 'mnp',
                    모델명: 's936',
                    고객명: '김철수',
                    휴대폰번호: '010-1234-5678',
                    액면셋팅가: 150000,
                    구두1: 50000,
                    구두2: 30000,
                    그레이드: 20000,
                    부가추가: 10000,
                    유심비: 5500,
                    신규번이: -800,
                    현금받음: 50000,
                    페이백: -30000
                },
                {
                    ...createEmptyRow(2),
                    판매자: '이영희',
                    대리점: '더킹',
                    통신사: 'kt',
                    개통방식: '신규',
                    모델명: 'a165',
                    고객명: '박영수',
                    휴대폰번호: '010-2345-6789',
                    액면셋팅가: 200000,
                    구두1: 70000,
                    구두2: 50000,
                    그레이드: 30000,
                    부가추가: 20000,
                    서류상현금개통: 50000,
                    유심비: 5500,
                    차감: -10000,
                    페이백: -50000
                }
            ]);

            const [selectedCells, setSelectedCells] = useState(new Set());
            const [focusedCell, setFocusedCell] = useState({ row: 0, col: 0 });
            const [isDragging, setIsDragging] = useState(false);
            const [dragStart, setDragStart] = useState(null);
            const [dragEnd, setDragEnd] = useState(null);
            const [columnWidths, setColumnWidths] = useState({});
            const [isResizing, setIsResizing] = useState(false);
            const [resizingColumn, setResizingColumn] = useState(null);
            const [clipboard, setClipboard] = useState(null);
            const [undoStack, setUndoStack] = useState([]);
            const [redoStack, setRedoStack] = useState([]);
            
            const tableRef = useRef(null);
            const cellRefs = useRef({});

            // 컬럼 너비 초기화
            useEffect(() => {
                const widths = {};
                columns.forEach(col => {
                    widths[col.field] = col.width;
                });
                setColumnWidths(widths);
            }, []);

            // 계산 함수들
            const calculateRow = (row) => {
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
            };

            // 지점별 집계 계산
            const calculateSummary = useMemo(() => {
                const 총대수 = data.length;
                const 총정산금 = data.reduce((sum, row) => {
                    const calc = calculateRow(row);
                    return sum + calc.정산금;
                }, 0);
                const 총세금 = data.reduce((sum, row) => {
                    const calc = calculateRow(row);
                    return sum + calc.부소세;
                }, 0);
                const 총세전매출 = data.reduce((sum, row) => {
                    const calc = calculateRow(row);
                    return sum + calc.세전마진;
                }, 0);
                const 평균마진 = 총대수 > 0 ? Math.round(총세전매출 / 총대수) : 0;
                
                return {
                    총대수,
                    총정산금,
                    총세금,
                    총세전매출,
                    총세후매출: 총세전매출 + 총세금,
                    평균마진
                };
            }, [data]);

            // Undo 스택에 현재 상태 저장
            const saveToUndoStack = () => {
                setUndoStack(prev => [...prev.slice(-49), JSON.parse(JSON.stringify(data))]);
                setRedoStack([]);
            };

            // 셀 값 변경 핸들러
            const handleCellChange = (rowId, field, value) => {
                saveToUndoStack();
                setData(prevData => {
                    return prevData.map(row => {
                        if (row.id === rowId) {
                            const updatedRow = { ...row, [field]: value };
                            return calculateRow(updatedRow);
                        }
                        return row;
                    });
                });
            };

            // 행 추가
            const addRow = () => {
                saveToUndoStack();
                const newId = Math.max(...data.map(d => d.id)) + 1;
                setData([...data, createEmptyRow(newId)]);
            };

            // 행 삭제
            const deleteRow = (id) => {
                saveToUndoStack();
                setData(data.filter(row => row.id !== id));
            };

            // Excel 내보내기
            const exportToExcel = () => {
                const ws = XLSX.utils.json_to_sheet(data.map(row => {
                    const calc = calculateRow(row);
                    return {
                        '판매자': row.판매자,
                        '대리점': row.대리점,
                        '통신사': row.통신사,
                        '개통방식': row.개통방식,
                        '모델명': row.모델명,
                        '개통일': row.개통일,
                        '일련번호': row.일련번호,
                        '휴대폰번호': row.휴대폰번호,
                        '고객명': row.고객명,
                        '생년월일': row.생년월일,
                        '액면/셋팅가': row.액면셋팅가,
                        '구두1': row.구두1,
                        '구두2': row.구두2,
                        '그레이드': row.그레이드,
                        '부가추가': row.부가추가,
                        '서류상현금개통': row.서류상현금개통,
                        '유심비(+)': row.유심비,
                        '신규,번이(-800)': row.신규번이,
                        '차감(-)': row.차감,
                        '리베총계': calc.리베총계,
                        '정산금': calc.정산금,
                        '부/소세(13.3%)': calc.부소세,
                        '현금받음(+)': row.현금받음,
                        '페이백(-)': row.페이백,
                        '세전/마진': calc.세전마진,
                        '세후/마진': calc.세후마진,
                        '메모장': row.메모장
                    };
                }));
                const wb = XLSX.utils.book_new();
                XLSX.utils.book_append_sheet(wb, ws, "개통표");
                XLSX.writeFile(wb, `개통표_${new Date().toISOString().split('T')[0]}.xlsx`);
            };

            // 키보드 네비게이션
            const handleKeyNavigation = useCallback((e) => {
                const { row, col } = focusedCell;
                let newRow = row;
                let newCol = col;
                
                switch(e.key) {
                    case 'ArrowUp':
                        e.preventDefault();
                        newRow = Math.max(0, row - 1);
                        break;
                    case 'ArrowDown':
                        e.preventDefault();
                        newRow = Math.min(data.length - 1, row + 1);
                        break;
                    case 'ArrowLeft':
                        if (!e.target.value || e.target.selectionStart === 0) {
                            e.preventDefault();
                            newCol = Math.max(0, col - 1);
                        }
                        break;
                    case 'ArrowRight':
                        if (!e.target.value || e.target.selectionStart === e.target.value.length) {
                            e.preventDefault();
                            newCol = Math.min(columns.length - 1, col + 1);
                        }
                        break;
                    case 'Tab':
                        e.preventDefault();
                        if (e.shiftKey) {
                            newCol = col - 1;
                            if (newCol < 0) {
                                newCol = columns.length - 1;
                                newRow = Math.max(0, row - 1);
                            }
                        } else {
                            newCol = col + 1;
                            if (newCol >= columns.length) {
                                newCol = 0;
                                newRow = Math.min(data.length - 1, row + 1);
                            }
                        }
                        break;
                    case 'Enter':
                        e.preventDefault();
                        if (e.shiftKey) {
                            newRow = Math.max(0, row - 1);
                        } else {
                            newRow = Math.min(data.length - 1, row + 1);
                        }
                        break;
                }
                
                if (newRow !== row || newCol !== col) {
                    setFocusedCell({ row: newRow, col: newCol });
                    const key = `${data[newRow].id}-${columns[newCol].field}`;
                    if (cellRefs.current[key]) {
                        cellRefs.current[key].focus();
                        cellRefs.current[key].select();
                    }
                }
            }, [focusedCell, data, columns]);

            // 엑셀 단축키 핸들러
            const handleShortcuts = useCallback((e) => {
                // Ctrl+C (복사)
                if (e.ctrlKey && e.key === 'c' && !e.shiftKey && !e.altKey) {
                    e.preventDefault();
                    if (selectedCells.size > 0) {
                        const copiedData = {};
                        selectedCells.forEach(cellKey => {
                            const [rowId, field] = cellKey.split('-');
                            const row = data.find(r => r.id === parseInt(rowId));
                            if (row) {
                                if (!copiedData[rowId]) copiedData[rowId] = {};
                                copiedData[rowId][field] = row[field];
                            }
                        });
                        setClipboard(copiedData);
                    }
                }
                
                // Ctrl+V (붙여넣기)
                if (e.ctrlKey && e.key === 'v' && !e.shiftKey && !e.altKey) {
                    e.preventDefault();
                    if (clipboard && selectedCells.size > 0) {
                        saveToUndoStack();
                        const firstCell = Array.from(selectedCells)[0];
                        const [targetRowId, targetField] = firstCell.split('-');
                        
                        setData(prevData => {
                            return prevData.map(row => {
                                const clipboardRowIds = Object.keys(clipboard);
                                if (clipboardRowIds.length > 0) {
                                    const clipboardData = clipboard[clipboardRowIds[0]];
                                    if (row.id === parseInt(targetRowId) && clipboardData[targetField] !== undefined) {
                                        const updatedRow = { ...row, [targetField]: clipboardData[targetField] };
                                        return calculateRow(updatedRow);
                                    }
                                }
                                return row;
                            });
                        });
                    }
                }
                
                // Ctrl+Z (실행 취소)
                if (e.ctrlKey && e.key === 'z' && !e.shiftKey && !e.altKey) {
                    e.preventDefault();
                    if (undoStack.length > 0) {
                        const previousState = undoStack[undoStack.length - 1];
                        setRedoStack(prev => [...prev, JSON.parse(JSON.stringify(data))]);
                        setData(previousState);
                        setUndoStack(prev => prev.slice(0, -1));
                    }
                }
                
                // Ctrl+Y (다시 실행)
                if (e.ctrlKey && e.key === 'y' && !e.shiftKey && !e.altKey) {
                    e.preventDefault();
                    if (redoStack.length > 0) {
                        const nextState = redoStack[redoStack.length - 1];
                        setUndoStack(prev => [...prev, JSON.parse(JSON.stringify(data))]);
                        setData(nextState);
                        setRedoStack(prev => prev.slice(0, -1));
                    }
                }
                
                // Delete 키
                if (e.key === 'Delete' && selectedCells.size > 0) {
                    e.preventDefault();
                    saveToUndoStack();
                    setData(prevData => {
                        return prevData.map(row => {
                            const updatedRow = { ...row };
                            let changed = false;
                            
                            selectedCells.forEach(cellKey => {
                                const [cellRowId, cellField] = cellKey.split('-');
                                if (parseInt(cellRowId) === row.id) {
                                    if (typeof row[cellField] === 'number') {
                                        updatedRow[cellField] = 0;
                                    } else {
                                        updatedRow[cellField] = '';
                                    }
                                    changed = true;
                                }
                            });
                            
                            return changed ? calculateRow(updatedRow) : row;
                        });
                    });
                    setSelectedCells(new Set());
                }
                
                // F2 (셀 편집 모드)
                if (e.key === 'F2') {
                    e.preventDefault();
                    const { row, col } = focusedCell;
                    const key = `${data[row].id}-${columns[col].field}`;
                    if (cellRefs.current[key]) {
                        cellRefs.current[key].focus();
                    }
                }
            }, [selectedCells, clipboard, data, undoStack, redoStack, focusedCell, columns]);

            // 이벤트 리스너 등록
            useEffect(() => {
                document.addEventListener('keydown', handleKeyNavigation);
                document.addEventListener('keydown', handleShortcuts);
                
                return () => {
                    document.removeEventListener('keydown', handleKeyNavigation);
                    document.removeEventListener('keydown', handleShortcuts);
                };
            }, [handleKeyNavigation, handleShortcuts]);

            // 드래그 선택 핸들러
            const handleMouseDown = (rowId, field) => {
                setIsDragging(true);
                setDragStart({ rowId, field });
                setDragEnd({ rowId, field });
                setSelectedCells(new Set([`${rowId}-${field}`]));
                document.body.classList.add('dragging');
            };

            const handleMouseEnter = (rowId, field) => {
                if (isDragging && !isResizing) {
                    setDragEnd({ rowId, field });
                    updateSelectedCells(dragStart, { rowId, field });
                }
            };

            const handleMouseUp = () => {
                if (isDragging) {
                    setIsDragging(false);
                    document.body.classList.remove('dragging');
                }
                if (isResizing) {
                    setIsResizing(false);
                    setResizingColumn(null);
                }
            };

            const updateSelectedCells = (start, end) => {
                if (!start || !end) return;
                
                const newSelected = new Set();
                const startIdx = data.findIndex(r => r.id === start.rowId);
                const endIdx = data.findIndex(r => r.id === end.rowId);
                const minIdx = Math.min(startIdx, endIdx);
                const maxIdx = Math.max(startIdx, endIdx);
                
                for (let i = minIdx; i <= maxIdx; i++) {
                    newSelected.add(`${data[i].id}-${start.field}`);
                }
                
                setSelectedCells(newSelected);
            };

            // 컬럼 리사이즈 핸들러
            const handleColumnResize = (field, e) => {
                e.preventDefault();
                e.stopPropagation();
                setIsResizing(true);
                setResizingColumn(field);
                
                const startX = e.clientX;
                const startWidth = columnWidths[field] || 100;
                
                const handleMouseMove = (e) => {
                    const diff = e.clientX - startX;
                    const newWidth = Math.max(50, startWidth + diff);
                    setColumnWidths(prev => ({ ...prev, [field]: newWidth }));
                };
                
                const handleMouseUp = () => {
                    setIsResizing(false);
                    setResizingColumn(null);
                    document.removeEventListener('mousemove', handleMouseMove);
                    document.removeEventListener('mouseup', handleMouseUp);
                };
                
                document.addEventListener('mousemove', handleMouseMove);
                document.addEventListener('mouseup', handleMouseUp);
            };

            // 셀 클릭 핸들러
            const handleCellClick = (rowIdx, colIdx) => {
                setFocusedCell({ row: rowIdx, col: colIdx });
            };

            // 마우스 업 이벤트 리스너
            useEffect(() => {
                document.addEventListener('mouseup', handleMouseUp);
                return () => {
                    document.removeEventListener('mouseup', handleMouseUp);
                };
            }, []);

            // 셀 렌더링 함수
            const renderCell = (row, rowIdx, column, colIdx) => {
                const value = row[column.field];
                const calc = calculateRow(row);
                const displayValue = column.editable ? value : calc[column.field];
                const key = `${row.id}-${column.field}`;
                const isSelected = selectedCells.has(key);
                const isFocused = focusedCell.row === rowIdx && focusedCell.col === colIdx;
                
                // 셀 배경색 결정
                let bgClass = '';
                if (column.editable) {
                    bgClass = 'bg-yellow-50';
                } else {
                    bgClass = 'bg-gray-100';
                }
                if (isSelected) {
                    bgClass = 'bg-blue-100';
                }
                
                // 셀 내용 렌더링
                if (column.type === 'select') {
                    const options = column.field === '통신사' 
                        ? ['SK', 'kt', 'LG', 'SK알뜰', 'kt알뜰', 'LG알뜰']
                        : ['mnp', '신규', '기변'];
                    
                    return (
                        <td key={key} className={`data-cell border ${bgClass}`}>
                            <select
                                ref={el => cellRefs.current[key] = el}
                                value={value}
                                onChange={(e) => handleCellChange(row.id, column.field, e.target.value)}
                                onClick={() => handleCellClick(rowIdx, colIdx)}
                                className={`w-full px-1 py-0.5 ${isFocused ? 'cell-focused' : ''}`}
                            >
                                {options.map(opt => <option key={opt}>{opt}</option>)}
                            </select>
                        </td>
                    );
                } else if (column.type === 'date') {
                    return (
                        <td key={key} className={`data-cell border ${bgClass}`}>
                            <input
                                ref={el => cellRefs.current[key] = el}
                                type="date"
                                value={value}
                                onChange={(e) => handleCellChange(row.id, column.field, e.target.value)}
                                onClick={() => handleCellClick(rowIdx, colIdx)}
                                className={`w-full px-1 py-0.5 ${isFocused ? 'cell-focused' : ''}`}
                            />
                        </td>
                    );
                } else if (column.type === 'number' || column.type === 'calc') {
                    let displayText = formatNumber(displayValue);
                    let textColor = '';
                    
                    if (column.format === 'plus' && displayValue) {
                        displayText = `+${formatNumber(Math.abs(displayValue))}`;
                        textColor = 'text-blue-600';
                    } else if (column.format === 'minus' && displayValue) {
                        displayText = `-${formatNumber(Math.abs(displayValue))}`;
                        textColor = 'text-red-600';
                    } else if (column.field === '정산금') {
                        textColor = 'text-blue-600 font-bold';
                    } else if (column.field === '부소세') {
                        textColor = 'text-red-600';
                    } else if (column.field === '세후마진') {
                        textColor = 'text-green-600 font-bold';
                    }
                    
                    if (column.editable) {
                        return (
                            <td key={key} className={`data-cell border ${bgClass}`}>
                                <input
                                    ref={el => cellRefs.current[key] = el}
                                    type="text"
                                    value={column.format && displayValue ? displayText : formatNumber(value)}
                                    onChange={(e) => {
                                        let val = parseNumber(e.target.value);
                                        if (column.format === 'minus' && val > 0) val = -val;
                                        handleCellChange(row.id, column.field, val);
                                    }}
                                    onClick={() => handleCellClick(rowIdx, colIdx)}
                                    onMouseDown={() => handleMouseDown(row.id, column.field)}
                                    onMouseEnter={() => handleMouseEnter(row.id, column.field)}
                                    className={`text-right ${textColor} ${isFocused ? 'cell-focused' : ''}`}
                                />
                            </td>
                        );
                    } else {
                        return (
                            <td key={key} className={`data-cell border ${bgClass} text-right ${textColor}`}>
                                {displayText}
                            </td>
                        );
                    }
                } else {
                    return (
                        <td key={key} className={`data-cell border ${bgClass}`}>
                            <input
                                ref={el => cellRefs.current[key] = el}
                                type="text"
                                value={value}
                                onChange={(e) => handleCellChange(row.id, column.field, e.target.value)}
                                onClick={() => handleCellClick(rowIdx, colIdx)}
                                onMouseDown={() => handleMouseDown(row.id, column.field)}
                                onMouseEnter={() => handleMouseEnter(row.id, column.field)}
                                className={`${isFocused ? 'cell-focused' : ''}`}
                            />
                        </td>
                    );
                }
            };

            return (
                <div className="min-h-screen bg-gray-50 p-4">
                    {/* 헤더 */}
                    <div className="mb-4 bg-white rounded-lg shadow-sm p-4">
                        <div className="flex justify-between items-center mb-4">
                            <h1 className="text-2xl font-bold text-gray-800">개통표 (고급 버전)</h1>
                            <div className="flex gap-2">
                                <button
                                    onClick={addRow}
                                    className="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors"
                                >
                                    행 추가
                                </button>
                                <button
                                    onClick={exportToExcel}
                                    className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"
                                >
                                    Excel 내보내기
                                </button>
                            </div>
                        </div>
                        
                        {/* 단축키 안내 */}
                        <div className="text-xs text-gray-500 mb-2">
                            <span className="mr-3">방향키: 셀 이동</span>
                            <span className="mr-3">Tab: 다음 셀</span>
                            <span className="mr-3">Enter: 아래 셀</span>
                            <span className="mr-3">Ctrl+C/V: 복사/붙여넣기</span>
                            <span className="mr-3">Ctrl+Z/Y: 실행취소/다시실행</span>
                            <span className="mr-3">Delete: 삭제</span>
                            <span className="mr-3">F2: 편집모드</span>
                        </div>
                        
                        {/* 지점별 요약 */}
                        <div className="grid grid-cols-6 gap-4 p-4 bg-blue-50 rounded-lg">
                            <div>
                                <span className="text-sm text-gray-600">지점명:</span>
                                <span className="ml-2 font-bold">용산점</span>
                            </div>
                            <div>
                                <span className="text-sm text-gray-600">월:</span>
                                <span className="ml-2 font-bold">2025-08</span>
                            </div>
                            <div>
                                <span className="text-sm text-gray-600">총 대수:</span>
                                <span className="ml-2 font-bold">{calculateSummary.총대수}대</span>
                            </div>
                            <div>
                                <span className="text-sm text-gray-600">평균마진:</span>
                                <span className="ml-2 font-bold">{formatNumber(calculateSummary.평균마진)}원</span>
                            </div>
                            <div>
                                <span className="text-sm text-gray-600">정산금:</span>
                                <span className="ml-2 font-bold text-blue-600">{formatNumber(calculateSummary.총정산금)}원</span>
                            </div>
                            <div>
                                <span className="text-sm text-gray-600">세후매출:</span>
                                <span className="ml-2 font-bold text-green-600">{formatNumber(calculateSummary.총세후매출)}원</span>
                            </div>
                        </div>
                    </div>

                    {/* 테이블 */}
                    <div className="bg-white rounded-lg shadow-sm overflow-hidden">
                        <div className="overflow-x-auto scrollbar-thin" ref={tableRef}>
                            <table className="w-full text-sm no-select">
                                <thead className="bg-gray-100 sticky-header">
                                    <tr>
                                        <th className="px-2 py-2 text-center border sticky-col" style={{ width: '50px' }}>삭제</th>
                                        {columns.map((column, idx) => (
                                            <th 
                                                key={column.field} 
                                                className="relative px-2 py-2 text-center border"
                                                style={{ width: `${columnWidths[column.field] || column.width}px` }}
                                            >
                                                {column.header}
                                                <div 
                                                    className="resize-handle"
                                                    onMouseDown={(e) => handleColumnResize(column.field, e)}
                                                />
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody>
                                    {data.map((row, rowIdx) => (
                                        <tr key={row.id} className="hover:bg-gray-50">
                                            <td className="px-2 py-1 text-center border sticky-col">
                                                <button
                                                    onClick={() => deleteRow(row.id)}
                                                    className="text-red-500 hover:text-red-700"
                                                >
                                                    ✕
                                                </button>
                                            </td>
                                            {columns.map((column, colIdx) => renderCell(row, rowIdx, column, colIdx))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        
                        {/* 하단 합계 */}
                        <div className="p-4 bg-gray-50 border-t">
                            <div className="grid grid-cols-5 gap-4 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-gray-600">총 대수:</span>
                                    <span className="font-semibold">{calculateSummary.총대수}대</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-600">정산금 합계:</span>
                                    <span className="font-semibold text-blue-600">
                                        {formatNumber(calculateSummary.총정산금)}원
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-600">세금 합계:</span>
                                    <span className="font-semibold text-red-600">
                                        {formatNumber(calculateSummary.총세금)}원
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-600">세전매출:</span>
                                    <span className="font-semibold">
                                        {formatNumber(calculateSummary.총세전매출)}원
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-gray-600">세후매출:</span>
                                    <span className="font-semibold text-green-600">
                                        {formatNumber(calculateSummary.총세후매출)}원
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            );
        }

        ReactDOM.createRoot(document.getElementById('root')).render(<SettlementTable />);
        @endverbatim
    </script>
</body>
</html>