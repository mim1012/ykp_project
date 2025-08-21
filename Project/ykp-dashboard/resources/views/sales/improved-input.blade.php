<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>개통표 입력 (개선) - YKP ERP</title>
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
        
        /* 스티키 헤더 */
        .sticky-header { 
            position: sticky; 
            top: 0; 
            z-index: 20; 
            background: white;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* 스티키 컬럼 */
        .sticky-col-1 { position: sticky; left: 0; z-index: 10; }
        .sticky-col-2 { position: sticky; left: 50px; z-index: 10; }
        .sticky-col-3 { position: sticky; left: 150px; z-index: 10; }
        
        /* 입력 가능 셀 - 연한 노랑 */
        .editable-cell {
            background-color: #fef3c7 !important;
            border: 1px solid #fbbf24;
        }
        .editable-cell:focus {
            background-color: #fde68a !important;
            border: 2px solid #f59e0b;
            outline: none;
        }
        
        /* 계산 셀 - 회색 */
        .calculated-cell {
            background-color: #f3f4f6 !important;
            color: #374151;
            cursor: not-allowed;
            border: 1px solid #d1d5db;
        }
        
        /* 선택된 셀 */
        .cell-selected {
            background-color: #dbeafe !important;
            border: 2px solid #3b82f6 !important;
        }
        
        /* 그룹 헤더 */
        .group-header {
            background: linear-gradient(to bottom, #eff6ff, #dbeafe);
            font-weight: 600;
            border-bottom: 2px solid #3b82f6;
        }
        
        /* 합계 행 */
        .total-row {
            background: linear-gradient(to bottom, #f0f9ff, #e0f2fe);
            font-weight: bold;
            border-top: 2px solid #0284c7;
        }
        
        /* 드래그 중 */
        body.dragging { cursor: cell !important; }
        body.dragging * { cursor: cell !important; }
        
        /* 포커스 경로 하이라이트 */
        .focus-path {
            position: relative;
        }
        .focus-path::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: #3b82f6;
            opacity: 0.5;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div id="root"></div>

    <script type="text/babel">
        @verbatim
        const { useState, useEffect, useRef, useCallback, useMemo } = React;
        
        // 아이콘 컴포넌트
        const Icon = ({ name, className = "w-5 h-5" }) => {
            useEffect(() => {
                lucide.createIcons();
            }, []);
            return <i data-lucide={name} className={className}></i>;
        };

        // 숫자 포맷팅 유틸리티
        const formatters = {
            currency: (num) => {
                if (num === null || num === undefined || num === '') return '';
                return '₩' + Number(num).toLocaleString('ko-KR', { maximumFractionDigits: 0 });
            },
            number: (num) => {
                if (num === null || num === undefined || num === '') return '';
                return Number(num).toLocaleString('ko-KR');
            },
            percent: (num) => {
                if (num === null || num === undefined || num === '') return '';
                return Number(num).toFixed(1) + '%';
            }
        };

        // 계산 로직
        const TAX_RATE = 0.133; // 13.3%
        
        const computeRow = (row) => {
            const K = Number(row.price_setting || 0);
            const L = Number(row.verbal1 || 0);
            const M = Number(row.verbal2 || 0);
            const N = Number(row.grade_amount || 0);
            const O = Number(row.addon_amount || 0);
            const P = Number(row.paper_cash || 0);
            const Q = Number(row.usim_fee || 0);
            const R = Number(row.new_mnp_disc || 0);
            const S = Number(row.deduction || 0);
            const W = Number(row.cash_in || 0);
            const X = Number(row.payback || 0);
            
            const T = K + L + M + N + O; // 리베총계
            const U = T - P + Q + R + S; // 정산금
            const V = Math.round(U * TAX_RATE); // 세금
            const Y = U - V + W + X; // 세전마진
            const Z = V + Y; // 세후마진
            
            return {
                total_rebate: T,
                settlement: U,
                tax: V,
                margin_before: Y,
                margin_after: Z,
                tax_rate: (U > 0 ? (V / U * 100) : 0)
            };
        };

        // 단축키 도움말 모달
        const ShortcutModal = ({ isOpen, onClose }) => {
            if (!isOpen) return null;
            
            const shortcuts = [
                { keys: '방향키', desc: '셀 이동' },
                { keys: 'Tab / Shift+Tab', desc: '다음/이전 입력 필드로 이동' },
                { keys: 'Enter / Shift+Enter', desc: '아래/위로 이동' },
                { keys: 'Ctrl+D', desc: '위 셀 값 복사' },
                { keys: 'Ctrl+R', desc: '왼쪽 셀 값 복사' },
                { keys: 'Ctrl+Space', desc: '열 전체 선택' },
                { keys: 'Shift+Space', desc: '행 전체 선택' },
                { keys: 'Ctrl+Z / Ctrl+Y', desc: '실행취소 / 다시실행' },
                { keys: 'Delete', desc: '선택 영역 값 삭제' },
                { keys: 'Ctrl+C / Ctrl+V', desc: '복사 / 붙여넣기' },
                { keys: 'Alt+Enter', desc: '셀 내 줄바꿈' },
                { keys: 'F2', desc: '셀 편집 모드' },
                { keys: 'Escape', desc: '선택 취소' }
            ];
            
            return (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                    <div className="bg-white rounded-lg p-6 max-w-lg w-full mx-4">
                        <div className="flex justify-between items-center mb-4">
                            <h2 className="text-xl font-bold">키보드 단축키</h2>
                            <button onClick={onClose} className="p-1 hover:bg-gray-100 rounded">
                                <Icon name="x" className="w-5 h-5" />
                            </button>
                        </div>
                        <div className="space-y-2 max-h-96 overflow-y-auto">
                            {shortcuts.map((item, i) => (
                                <div key={i} className="flex justify-between py-2 border-b">
                                    <kbd className="px-2 py-1 bg-gray-100 rounded text-sm font-mono">
                                        {item.keys}
                                    </kbd>
                                    <span className="text-gray-600">{item.desc}</span>
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            );
        };

        // 메인 앱
        const App = () => {
            const [data, setData] = useState([]);
            const [collapsedGroups, setCollapsedGroups] = useState(new Set());
            const [selectedCells, setSelectedCells] = useState(new Set());
            const [isDragging, setIsDragging] = useState(false);
            const [dragStart, setDragStart] = useState(null);
            const [focusedCell, setFocusedCell] = useState({ row: 0, col: 0 });
            const [showShortcuts, setShowShortcuts] = useState(false);
            const [undoStack, setUndoStack] = useState([]);
            const [redoStack, setRedoStack] = useState([]);
            const [copiedValue, setCopiedValue] = useState(null);
            
            // 컬럼 그룹 정의
            const columnGroups = [
                {
                    id: 'basic',
                    name: '기본정보',
                    collapsed: false,
                    columns: ['seller', 'dealer', 'carrier', 'open_type', 'model', 'opened_on']
                },
                {
                    id: 'customer',
                    name: '고객정보',
                    collapsed: true,
                    columns: ['customer_name', 'phone', 'birth']
                },
                {
                    id: 'price',
                    name: '단가정보',
                    collapsed: false,
                    columns: ['price_setting', 'verbal1', 'verbal2', 'grade_amount', 'addon_amount']
                },
                {
                    id: 'settlement',
                    name: '정산정보',
                    collapsed: false,
                    columns: ['total_rebate', 'paper_cash', 'usim_fee', 'new_mnp_disc', 'deduction', 'settlement']
                },
                {
                    id: 'tax',
                    name: '세금/마진',
                    collapsed: false,
                    columns: ['tax', 'tax_rate', 'cash_in', 'payback', 'margin_before', 'margin_after']
                }
            ];

            // 입력 가능 필드 정의
            const editableFields = [
                'seller', 'dealer', 'carrier', 'open_type', 'model', 'opened_on',
                'customer_name', 'phone', 'birth',
                'price_setting', 'verbal1', 'verbal2', 'grade_amount', 'addon_amount',
                'paper_cash', 'usim_fee', 'new_mnp_disc', 'deduction',
                'cash_in', 'payback'
            ];

            // 포커스 순서 (주요 입력 필드만)
            const focusPath = [
                'seller', 'carrier', 'open_type', 'model',
                'price_setting', 'verbal1', 'verbal2', 'grade_amount',
                'paper_cash', 'usim_fee', 'new_mnp_disc'
            ];

            // 초기 데이터 생성
            useEffect(() => {
                const initialData = Array.from({ length: 5 }, (_, i) => ({
                    id: i + 1,
                    seller: '김직원',
                    dealer: 'SK강남점',
                    carrier: 'SK',
                    open_type: '신규',
                    model: 'iPhone 15 Pro',
                    opened_on: new Date().toISOString().slice(0, 10),
                    customer_name: `고객${i + 1}`,
                    phone: `010-${Math.floor(1000 + Math.random() * 9000)}-${Math.floor(1000 + Math.random() * 9000)}`,
                    birth: '1990-01-01',
                    price_setting: 100000,
                    verbal1: 20000,
                    verbal2: 10000,
                    grade_amount: 5000,
                    addon_amount: 3000,
                    paper_cash: 10000,
                    usim_fee: 5500,
                    new_mnp_disc: -8000,
                    deduction: -5000,
                    cash_in: 20000,
                    payback: -5000
                }));
                setData(initialData);
            }, []);

            // 키보드 단축키 처리
            useEffect(() => {
                const handleKeyDown = (e) => {
                    // Ctrl+D: 위 셀 값 복사
                    if (e.ctrlKey && e.key === 'd') {
                        e.preventDefault();
                        handleCopyDown();
                    }
                    // Ctrl+R: 왼쪽 셀 값 복사
                    else if (e.ctrlKey && e.key === 'r') {
                        e.preventDefault();
                        handleCopyRight();
                    }
                    // Ctrl+Z: 실행취소
                    else if (e.ctrlKey && e.key === 'z') {
                        e.preventDefault();
                        handleUndo();
                    }
                    // Ctrl+Y: 다시실행
                    else if (e.ctrlKey && e.key === 'y') {
                        e.preventDefault();
                        handleRedo();
                    }
                    // Delete: 선택 영역 삭제
                    else if (e.key === 'Delete' && selectedCells.size > 0) {
                        handleDeleteSelected();
                    }
                    // Escape: 선택 취소
                    else if (e.key === 'Escape') {
                        setSelectedCells(new Set());
                    }
                    // F1: 도움말
                    else if (e.key === 'F1') {
                        e.preventDefault();
                        setShowShortcuts(true);
                    }
                };

                document.addEventListener('keydown', handleKeyDown);
                return () => document.removeEventListener('keydown', handleKeyDown);
            }, [selectedCells, focusedCell, data, undoStack]);

            // Ctrl+D 구현
            const handleCopyDown = () => {
                if (focusedCell.row > 0) {
                    const field = focusPath[focusedCell.col];
                    const valueAbove = data[focusedCell.row - 1][field];
                    updateCell(focusedCell.row, field, valueAbove);
                }
            };

            // Ctrl+R 구현
            const handleCopyRight = () => {
                if (focusedCell.col > 0) {
                    const field = focusPath[focusedCell.col];
                    const fieldLeft = focusPath[focusedCell.col - 1];
                    const valueLeft = data[focusedCell.row][fieldLeft];
                    updateCell(focusedCell.row, field, valueLeft);
                }
            };

            // 실행취소
            const handleUndo = () => {
                if (undoStack.length > 0) {
                    const lastState = undoStack[undoStack.length - 1];
                    setRedoStack([...redoStack, data]);
                    setData(lastState);
                    setUndoStack(undoStack.slice(0, -1));
                }
            };

            // 다시실행
            const handleRedo = () => {
                if (redoStack.length > 0) {
                    const nextState = redoStack[redoStack.length - 1];
                    setUndoStack([...undoStack, data]);
                    setData(nextState);
                    setRedoStack(redoStack.slice(0, -1));
                }
            };

            // 선택 영역 삭제
            const handleDeleteSelected = () => {
                const newData = data.map((row, rowIdx) => {
                    const newRow = { ...row };
                    editableFields.forEach(field => {
                        if (selectedCells.has(`${rowIdx}-${field}`)) {
                            newRow[field] = field.includes('price') || field.includes('amount') ? 0 : '';
                        }
                    });
                    return newRow;
                });
                setUndoStack([...undoStack, data]);
                setData(newData);
                setSelectedCells(new Set());
            };

            // 셀 업데이트
            const updateCell = (rowIdx, field, value) => {
                setUndoStack([...undoStack, data]);
                setRedoStack([]);
                
                const newData = [...data];
                newData[rowIdx] = { ...newData[rowIdx], [field]: value };
                setData(newData);
            };

            // 그룹 토글
            const toggleGroup = (groupId) => {
                const newCollapsed = new Set(collapsedGroups);
                if (newCollapsed.has(groupId)) {
                    newCollapsed.delete(groupId);
                } else {
                    newCollapsed.add(groupId);
                }
                setCollapsedGroups(newCollapsed);
            };

            // 새 행 추가
            const addNewRow = () => {
                const newRow = {
                    id: data.length + 1,
                    seller: '',
                    dealer: '',
                    carrier: 'SK',
                    open_type: '신규',
                    model: '',
                    opened_on: new Date().toISOString().slice(0, 10),
                    customer_name: '',
                    phone: '',
                    birth: '',
                    price_setting: 0,
                    verbal1: 0,
                    verbal2: 0,
                    grade_amount: 0,
                    addon_amount: 0,
                    paper_cash: 0,
                    usim_fee: 5500,
                    new_mnp_disc: 0,
                    deduction: 0,
                    cash_in: 0,
                    payback: 0
                };
                setData([...data, newRow]);
            };

            // 행 삭제
            const deleteRow = (id) => {
                if (confirm('이 행을 삭제하시겠습니까?')) {
                    setData(data.filter(row => row.id !== id));
                }
            };

            // 합계 계산
            const totals = useMemo(() => {
                return data.reduce((acc, row) => {
                    const calc = computeRow(row);
                    return {
                        count: acc.count + 1,
                        total_rebate: acc.total_rebate + calc.total_rebate,
                        settlement: acc.settlement + calc.settlement,
                        tax: acc.tax + calc.tax,
                        margin_before: acc.margin_before + calc.margin_before,
                        margin_after: acc.margin_after + calc.margin_after
                    };
                }, { count: 0, total_rebate: 0, settlement: 0, tax: 0, margin_before: 0, margin_after: 0 });
            }, [data]);

            // 컬럼 헤더 렌더링
            const renderColumnHeader = (field, label) => {
                const isEditable = editableFields.includes(field);
                const isFocusPath = focusPath.includes(field);
                
                return (
                    <th className={`px-2 py-2 text-xs font-medium border-r ${isEditable ? 'bg-yellow-50' : 'bg-gray-50'} ${isFocusPath ? 'focus-path' : ''}`}>
                        {label}
                        {isEditable && <span className="text-yellow-600 ml-1">✏️</span>}
                    </th>
                );
            };

            // 셀 렌더링
            const renderCell = (row, rowIdx, field, format = 'text') => {
                const isEditable = editableFields.includes(field);
                const calc = computeRow(row);
                let value = row[field];
                
                // 계산 필드 처리
                if (field === 'total_rebate') value = calc.total_rebate;
                if (field === 'settlement') value = calc.settlement;
                if (field === 'tax') value = calc.tax;
                if (field === 'tax_rate') value = calc.tax_rate;
                if (field === 'margin_before') value = calc.margin_before;
                if (field === 'margin_after') value = calc.margin_after;
                
                // 포맷팅
                let displayValue = value;
                if (format === 'currency') displayValue = formatters.currency(value);
                if (format === 'number') displayValue = formatters.number(value);
                if (format === 'percent') displayValue = formatters.percent(value);
                
                if (isEditable) {
                    return (
                        <td className="p-0">
                            <input
                                type={format === 'currency' || format === 'number' ? 'number' : 'text'}
                                value={value || ''}
                                onChange={(e) => updateCell(rowIdx, field, e.target.value)}
                                className={`w-full px-2 py-1 text-sm editable-cell ${selectedCells.has(`${rowIdx}-${field}`) ? 'cell-selected' : ''}`}
                                onMouseDown={() => handleCellMouseDown(rowIdx, field)}
                                onMouseEnter={() => handleCellMouseEnter(rowIdx, field)}
                            />
                        </td>
                    );
                } else {
                    return (
                        <td className="px-2 py-1 text-sm calculated-cell text-right">
                            {displayValue}
                        </td>
                    );
                }
            };

            // 마우스 이벤트 핸들러
            const handleCellMouseDown = (rowIdx, field) => {
                setIsDragging(true);
                setDragStart({ row: rowIdx, field });
                setSelectedCells(new Set([`${rowIdx}-${field}`]));
            };

            const handleCellMouseEnter = (rowIdx, field) => {
                if (isDragging && dragStart) {
                    const newSelected = new Set();
                    const minRow = Math.min(dragStart.row, rowIdx);
                    const maxRow = Math.max(dragStart.row, rowIdx);
                    
                    for (let r = minRow; r <= maxRow; r++) {
                        newSelected.add(`${r}-${field}`);
                    }
                    setSelectedCells(newSelected);
                }
            };

            useEffect(() => {
                const handleMouseUp = () => setIsDragging(false);
                document.addEventListener('mouseup', handleMouseUp);
                return () => document.removeEventListener('mouseup', handleMouseUp);
            }, []);

            return (
                <div className="min-h-screen bg-gray-50">
                    {/* 헤더 */}
                    <header className="bg-white border-b px-6 py-3">
                        <div className="flex items-center justify-between">
                            <div>
                                <h1 className="text-xl font-bold">개통표 입력 (개선버전)</h1>
                                <p className="text-sm text-gray-500 mt-1">
                                    입력 가능: 🟨 노란색 | 자동 계산: 🔒 회색
                                </p>
                            </div>
                            <div className="flex gap-2">
                                <button
                                    onClick={() => setShowShortcuts(true)}
                                    className="px-3 py-1.5 bg-gray-100 hover:bg-gray-200 rounded flex items-center gap-2 text-sm"
                                >
                                    <Icon name="keyboard" className="w-4 h-4" />
                                    단축키 (F1)
                                </button>
                                <button
                                    onClick={addNewRow}
                                    className="px-3 py-1.5 bg-green-600 text-white rounded hover:bg-green-700 flex items-center gap-2 text-sm"
                                >
                                    <Icon name="plus" className="w-4 h-4" />
                                    새 행
                                </button>
                                <a
                                    href="/"
                                    className="px-3 py-1.5 bg-gray-600 text-white rounded hover:bg-gray-700 flex items-center gap-2 text-sm"
                                >
                                    <Icon name="home" className="w-4 h-4" />
                                    대시보드
                                </a>
                            </div>
                        </div>
                    </header>

                    {/* KPI 요약 */}
                    <div className="bg-white border-b px-6 py-3">
                        <div className="grid grid-cols-6 gap-4 text-sm">
                            <div className="text-center">
                                <div className="text-gray-500">총 건수</div>
                                <div className="text-xl font-bold">{totals.count}</div>
                            </div>
                            <div className="text-center">
                                <div className="text-gray-500">리베총계</div>
                                <div className="text-xl font-bold text-blue-600">
                                    {formatters.currency(totals.total_rebate)}
                                </div>
                            </div>
                            <div className="text-center">
                                <div className="text-gray-500">정산금</div>
                                <div className="text-xl font-bold text-green-600">
                                    {formatters.currency(totals.settlement)}
                                </div>
                            </div>
                            <div className="text-center">
                                <div className="text-gray-500">세금</div>
                                <div className="text-xl font-bold text-red-600">
                                    {formatters.currency(totals.tax)}
                                </div>
                            </div>
                            <div className="text-center">
                                <div className="text-gray-500">세전마진</div>
                                <div className="text-xl font-bold">
                                    {formatters.currency(totals.margin_before)}
                                </div>
                            </div>
                            <div className="text-center">
                                <div className="text-gray-500">세후마진</div>
                                <div className="text-xl font-bold text-purple-600">
                                    {formatters.currency(totals.margin_after)}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* 테이블 */}
                    <div className="p-4">
                        <div className="bg-white rounded-lg shadow overflow-hidden">
                            <div className="overflow-x-auto scrollbar-thin">
                                <table className="w-full text-sm">
                                    <thead className="sticky-header">
                                        {/* 그룹 헤더 */}
                                        <tr className="group-header">
                                            <th className="px-2 py-2 border-r sticky-col-1 bg-blue-50" rowSpan={2}>
                                                #
                                            </th>
                                            {columnGroups.map(group => (
                                                <th 
                                                    key={group.id}
                                                    colSpan={collapsedGroups.has(group.id) ? 1 : group.columns.length}
                                                    className="px-4 py-2 border-r cursor-pointer hover:bg-blue-100"
                                                    onClick={() => toggleGroup(group.id)}
                                                >
                                                    <div className="flex items-center justify-center gap-2">
                                                        <Icon 
                                                            name={collapsedGroups.has(group.id) ? 'chevron-right' : 'chevron-down'} 
                                                            className="w-4 h-4"
                                                        />
                                                        {group.name}
                                                    </div>
                                                </th>
                                            ))}
                                            <th className="px-2 py-2 sticky-col-3 bg-blue-50" rowSpan={2}>
                                                작업
                                            </th>
                                        </tr>
                                        {/* 컬럼 헤더 */}
                                        <tr className="border-b">
                                            {columnGroups.map(group => {
                                                if (collapsedGroups.has(group.id)) {
                                                    return <th key={group.id} className="px-2 py-2 bg-gray-100">...</th>;
                                                }
                                                
                                                return group.columns.map(col => {
                                                    const labels = {
                                                        seller: '판매자',
                                                        dealer: '대리점',
                                                        carrier: '통신사',
                                                        open_type: '개통유형',
                                                        model: '모델',
                                                        opened_on: '개통일',
                                                        customer_name: '고객명',
                                                        phone: '전화번호',
                                                        birth: '생년월일',
                                                        price_setting: '액면가',
                                                        verbal1: '구두1',
                                                        verbal2: '구두2',
                                                        grade_amount: '그레이드',
                                                        addon_amount: '부가추가',
                                                        total_rebate: '리베총계',
                                                        paper_cash: '서류현금',
                                                        usim_fee: '유심비',
                                                        new_mnp_disc: '신규/MNP',
                                                        deduction: '차감',
                                                        settlement: '정산금',
                                                        tax: '세금',
                                                        tax_rate: '세율',
                                                        cash_in: '현금받음',
                                                        payback: '페이백',
                                                        margin_before: '세전마진',
                                                        margin_after: '세후마진'
                                                    };
                                                    return renderColumnHeader(col, labels[col]);
                                                });
                                            })}
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {data.map((row, rowIdx) => (
                                            <tr key={row.id} className="border-b hover:bg-gray-50">
                                                <td className="px-2 py-1 text-center sticky-col-1 bg-white border-r font-medium">
                                                    {rowIdx + 1}
                                                </td>
                                                {columnGroups.map(group => {
                                                    if (collapsedGroups.has(group.id)) {
                                                        return <td key={group.id} className="px-2 py-1 bg-gray-100 text-center">...</td>;
                                                    }
                                                    
                                                    return group.columns.map(col => {
                                                        const format = ['price_setting', 'verbal1', 'verbal2', 'grade_amount', 'addon_amount',
                                                                       'total_rebate', 'paper_cash', 'usim_fee', 'new_mnp_disc', 'deduction',
                                                                       'settlement', 'tax', 'cash_in', 'payback', 'margin_before', 'margin_after'].includes(col) 
                                                                       ? 'currency' 
                                                                       : col === 'tax_rate' ? 'percent' : 'text';
                                                        return <React.Fragment key={col}>{renderCell(row, rowIdx, col, format)}</React.Fragment>;
                                                    });
                                                })}
                                                <td className="px-2 py-1 text-center sticky-col-3 bg-white">
                                                    <button
                                                        onClick={() => deleteRow(row.id)}
                                                        className="p-1 text-red-500 hover:bg-red-50 rounded"
                                                        title="삭제"
                                                    >
                                                        <Icon name="trash-2" className="w-4 h-4" />
                                                    </button>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                    {/* 합계 행 */}
                                    <tfoot>
                                        <tr className="total-row">
                                            <td colSpan={collapsedGroups.has('basic') ? 2 : 7} className="px-4 py-2 text-right font-bold">
                                                합계
                                            </td>
                                            {!collapsedGroups.has('customer') && <td colSpan={3}></td>}
                                            {!collapsedGroups.has('price') && (
                                                <>
                                                    <td colSpan={5}></td>
                                                </>
                                            )}
                                            {!collapsedGroups.has('settlement') && (
                                                <>
                                                    <td className="px-2 py-2 text-right font-bold">
                                                        {formatters.currency(totals.total_rebate)}
                                                    </td>
                                                    <td colSpan={4}></td>
                                                    <td className="px-2 py-2 text-right font-bold">
                                                        {formatters.currency(totals.settlement)}
                                                    </td>
                                                </>
                                            )}
                                            {!collapsedGroups.has('tax') && (
                                                <>
                                                    <td className="px-2 py-2 text-right font-bold text-red-600">
                                                        {formatters.currency(totals.tax)}
                                                    </td>
                                                    <td colSpan={3}></td>
                                                    <td className="px-2 py-2 text-right font-bold">
                                                        {formatters.currency(totals.margin_before)}
                                                    </td>
                                                    <td className="px-2 py-2 text-right font-bold text-purple-600">
                                                        {formatters.currency(totals.margin_after)}
                                                    </td>
                                                </>
                                            )}
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </div>

                    {/* 상태바 */}
                    <div className="fixed bottom-0 left-0 right-0 bg-white border-t px-6 py-2">
                        <div className="flex justify-between items-center text-sm">
                            <div className="flex gap-4">
                                {selectedCells.size > 0 && (
                                    <>
                                        <span className="text-blue-600">
                                            선택: {selectedCells.size}개 셀
                                        </span>
                                        <span>Delete키로 삭제</span>
                                    </>
                                )}
                                {undoStack.length > 0 && (
                                    <span className="text-gray-500">
                                        실행취소 가능: {undoStack.length}단계
                                    </span>
                                )}
                            </div>
                            <div className="flex gap-4 text-gray-500">
                                <span>Ctrl+D: 위 복사</span>
                                <span>Ctrl+R: 왼쪽 복사</span>
                                <span>F1: 도움말</span>
                            </div>
                        </div>
                    </div>

                    {/* 단축키 모달 */}
                    <ShortcutModal isOpen={showShortcuts} onClose={() => setShowShortcuts(false)} />
                </div>
            );
        };

        // 렌더링
        ReactDOM.render(<App />, document.getElementById('root'));
        @endverbatim
    </script>
</body>
</html>