import React, { useCallback, useMemo, useRef, useEffect } from 'react';
import { AgGridReact } from 'ag-grid-react';
import { ColDef, GridApi, GridReadyEvent, CellEditingStoppedEvent, PasteEndEvent } from 'ag-grid-community';
import 'ag-grid-community/styles/ag-grid.css';
import 'ag-grid-community/styles/ag-theme-alpine.css';
import { useSettlementStore } from '../store/useSettlementStore';
import { SettlementRow } from '../types';
import { formatKRW, formatPercent, parseKRW, normalizeCarrier, normalizeActivationType } from '../utils/formatters';
import { autoCorrectCarrier, autoCorrectActivationType } from '../utils/validation';

export const SettlementGrid: React.FC = () => {
  const gridRef = useRef<AgGridReact<SettlementRow>>(null);
  const gridApiRef = useRef<GridApi<SettlementRow>>();
  
  const { 
    rows, 
    updateRow, 
    addRow, 
    deleteRow,
    errors,
    undo,
    redo,
    canUndo,
    canRedo
  } = useSettlementStore();

  // 컬럼 정의
  const columnDefs = useMemo<ColDef<SettlementRow>[]>(() => [
    // 기본정보 그룹
    {
      headerName: '기본정보',
      children: [
        {
          field: 'seller',
          headerName: '판매자',
          editable: true,
          cellClass: 'editable-cell',
          width: 100,
        },
        {
          field: 'dealer',
          headerName: '대리점',
          editable: true,
          cellClass: 'editable-cell',
          width: 120,
        },
        {
          field: 'carrier',
          headerName: '통신사',
          editable: true,
          cellClass: 'editable-cell',
          width: 80,
          cellEditor: 'agSelectCellEditor',
          cellEditorParams: {
            values: ['SKT', 'KT', 'LGU+', 'MVNO']
          },
          valueSetter: (params) => {
            const corrected = autoCorrectCarrier(params.newValue);
            params.data.carrier = corrected;
            return true;
          }
        },
        {
          field: 'activationType',
          headerName: '개통방식',
          editable: true,
          cellClass: 'editable-cell',
          width: 90,
          cellEditor: 'agSelectCellEditor',
          cellEditorParams: {
            values: ['신규', 'MNP', '기변']
          },
          valueSetter: (params) => {
            const corrected = autoCorrectActivationType(params.newValue);
            params.data.activationType = corrected;
            return true;
          }
        },
        {
          field: 'modelName',
          headerName: '모델명',
          editable: true,
          cellClass: 'editable-cell',
          width: 150,
        },
        {
          field: 'activationDate',
          headerName: '개통일',
          editable: true,
          cellClass: 'editable-cell',
          width: 110,
        },
        {
          field: 'customerName',
          headerName: '고객명',
          editable: true,
          cellClass: 'editable-cell',
          width: 100,
        },
      ]
    },
    // 단가정보 그룹
    {
      headerName: '단가정보',
      children: [
        {
          field: 'priceSettling',
          headerName: '액면/셋팅가',
          editable: true,
          cellClass: 'editable-cell',
          width: 110,
          valueFormatter: (params) => formatKRW(params.value),
          valueParser: (params) => parseKRW(params.newValue),
          type: 'numericColumn',
        },
        {
          field: 'verbal1',
          headerName: '구두1',
          editable: true,
          cellClass: 'editable-cell',
          width: 90,
          valueFormatter: (params) => formatKRW(params.value),
          valueParser: (params) => parseKRW(params.newValue),
          type: 'numericColumn',
        },
        {
          field: 'verbal2',
          headerName: '구두2',
          editable: true,
          cellClass: 'editable-cell',
          width: 90,
          valueFormatter: (params) => formatKRW(params.value),
          valueParser: (params) => parseKRW(params.newValue),
          type: 'numericColumn',
        },
        {
          field: 'gradeAmount',
          headerName: '그레이드',
          editable: true,
          cellClass: 'editable-cell',
          width: 90,
          valueFormatter: (params) => formatKRW(params.value),
          valueParser: (params) => parseKRW(params.newValue),
          type: 'numericColumn',
        },
        {
          field: 'additionalAmount',
          headerName: '부가추가',
          editable: true,
          cellClass: 'editable-cell',
          width: 90,
          valueFormatter: (params) => formatKRW(params.value),
          valueParser: (params) => parseKRW(params.newValue),
          type: 'numericColumn',
        },
      ]
    },
    // 정책 그룹
    {
      headerName: '정책',
      children: [
        {
          field: 'simFee',
          headerName: '유심비(+)',
          editable: true,
          cellClass: 'editable-cell',
          width: 90,
          valueFormatter: (params) => formatKRW(params.value),
          valueParser: (params) => Math.abs(parseKRW(params.newValue)),
          type: 'numericColumn',
        },
        {
          field: 'mnpDiscount',
          headerName: '신규·번이(-)',
          editable: true,
          cellClass: 'editable-cell',
          width: 110,
          valueFormatter: (params) => formatKRW(params.value),
          valueParser: (params) => -Math.abs(parseKRW(params.newValue)),
          type: 'numericColumn',
        },
        {
          field: 'documentCash',
          headerName: '서류상현금',
          editable: true,
          cellClass: 'editable-cell',
          width: 100,
          valueFormatter: (params) => formatKRW(params.value),
          valueParser: (params) => parseKRW(params.newValue),
          type: 'numericColumn',
        },
        {
          field: 'taxRate',
          headerName: '세율',
          editable: true,
          cellClass: 'editable-cell',
          width: 70,
          valueFormatter: (params) => formatPercent(params.value),
          valueParser: (params) => {
            const value = parseFloat(params.newValue);
            return value > 1 ? value / 100 : value;
          },
          type: 'numericColumn',
        },
      ]
    },
    // 계산 그룹
    {
      headerName: '계산',
      children: [
        {
          field: 'totalRebate',
          headerName: '리베총계',
          editable: false,
          cellClass: 'calculated-cell',
          width: 100,
          valueFormatter: (params) => formatKRW(params.value),
          type: 'numericColumn',
        },
        {
          field: 'settlementAmount',
          headerName: '정산금',
          editable: false,
          cellClass: 'calculated-cell',
          width: 100,
          valueFormatter: (params) => formatKRW(params.value),
          type: 'numericColumn',
        },
        {
          field: 'tax',
          headerName: '부가세',
          editable: false,
          cellClass: 'calculated-cell',
          width: 90,
          valueFormatter: (params) => formatKRW(params.value),
          type: 'numericColumn',
        },
      ]
    },
    // 마진 그룹
    {
      headerName: '마진',
      children: [
        {
          field: 'cashReceived',
          headerName: '현금받음(+)',
          editable: true,
          cellClass: 'editable-cell',
          width: 110,
          valueFormatter: (params) => formatKRW(params.value),
          valueParser: (params) => Math.abs(parseKRW(params.newValue)),
          type: 'numericColumn',
        },
        {
          field: 'payback',
          headerName: '페이백(-)',
          editable: true,
          cellClass: 'editable-cell',
          width: 100,
          valueFormatter: (params) => formatKRW(params.value),
          valueParser: (params) => -Math.abs(parseKRW(params.newValue)),
          type: 'numericColumn',
        },
        {
          field: 'marginBeforeTax',
          headerName: '세전마진',
          editable: false,
          cellClass: 'calculated-cell',
          width: 100,
          valueFormatter: (params) => formatKRW(params.value),
          type: 'numericColumn',
        },
        {
          field: 'marginAfterTax',
          headerName: '세후마진',
          editable: false,
          cellClass: 'calculated-cell',
          width: 100,
          valueFormatter: (params) => formatKRW(params.value),
          type: 'numericColumn',
        },
      ]
    },
    // 기타
    {
      field: 'memo',
      headerName: '메모',
      editable: true,
      cellClass: 'editable-cell',
      width: 150,
    },
    // 액션
    {
      headerName: '',
      field: 'actions',
      width: 60,
      cellRenderer: (params: any) => {
        return (
          <button
            onClick={() => deleteRow(params.data.id)}
            className="text-red-500 hover:text-red-700 p-1"
          >
            삭제
          </button>
        );
      },
    },
  ], [deleteRow]);

  // 그리드 준비
  const onGridReady = useCallback((params: GridReadyEvent) => {
    gridApiRef.current = params.api;
  }, []);

  // 셀 편집 완료
  const onCellEditingStopped = useCallback((event: CellEditingStoppedEvent) => {
    if (event.data && event.colDef.field) {
      updateRow(event.data.id, {
        [event.colDef.field]: event.value
      });
    }
  }, [updateRow]);

  // 붙여넣기 처리
  const onPasteEnd = useCallback((event: PasteEndEvent) => {
    // 붙여넣기 후 자동 계산 트리거
    event.api.refreshCells({ force: true });
  }, []);

  // 키보드 단축키
  useEffect(() => {
    const handleKeyDown = (e: KeyboardEvent) => {
      // Ctrl+Z: 실행취소
      if (e.ctrlKey && e.key === 'z' && !e.shiftKey) {
        e.preventDefault();
        if (canUndo()) undo();
      }
      // Ctrl+Y or Ctrl+Shift+Z: 다시실행
      if ((e.ctrlKey && e.key === 'y') || (e.ctrlKey && e.shiftKey && e.key === 'z')) {
        e.preventDefault();
        if (canRedo()) redo();
      }
      // Ctrl+D: 위 셀 복사
      if (e.ctrlKey && e.key === 'd') {
        e.preventDefault();
        handleCopyDown();
      }
      // Ctrl+R: 왼쪽 셀 복사
      if (e.ctrlKey && e.key === 'r') {
        e.preventDefault();
        handleCopyRight();
      }
    };

    window.addEventListener('keydown', handleKeyDown);
    return () => window.removeEventListener('keydown', handleKeyDown);
  }, [canUndo, canRedo, undo, redo]);

  // Ctrl+D 처리
  const handleCopyDown = () => {
    if (!gridApiRef.current) return;
    
    const focusedCell = gridApiRef.current.getFocusedCell();
    if (!focusedCell || focusedCell.rowIndex === 0) return;
    
    const column = focusedCell.column;
    const currentRowNode = gridApiRef.current.getDisplayedRowAtIndex(focusedCell.rowIndex);
    const aboveRowNode = gridApiRef.current.getDisplayedRowAtIndex(focusedCell.rowIndex - 1);
    
    if (currentRowNode && aboveRowNode && column.getColId()) {
      const field = column.getColId();
      const valueAbove = aboveRowNode.data[field];
      updateRow(currentRowNode.data.id, { [field]: valueAbove });
    }
  };

  // Ctrl+R 처리
  const handleCopyRight = () => {
    if (!gridApiRef.current) return;
    
    const focusedCell = gridApiRef.current.getFocusedCell();
    if (!focusedCell) return;
    
    const columns = gridApiRef.current.getColumnDefs();
    const currentColIndex = columns?.findIndex(col => col.field === focusedCell.column.getColId());
    
    if (currentColIndex === undefined || currentColIndex <= 0) return;
    
    const leftColumn = columns![currentColIndex - 1];
    const currentRowNode = gridApiRef.current.getDisplayedRowAtIndex(focusedCell.rowIndex);
    
    if (currentRowNode && leftColumn.field) {
      const valueLeft = currentRowNode.data[leftColumn.field];
      updateRow(currentRowNode.data.id, { [focusedCell.column.getColId()]: valueLeft });
    }
  };

  // 에러 셀 스타일
  const getCellClass = useCallback((params: any) => {
    const field = params.colDef.field;
    const rowErrors = errors[params.data.id];
    
    if (rowErrors && rowErrors[field]) {
      return 'error-cell';
    }
    
    return params.colDef.cellClass;
  }, [errors]);

  return (
    <div className="ag-theme-alpine h-full w-full">
      <AgGridReact
        ref={gridRef}
        rowData={rows}
        columnDefs={columnDefs}
        defaultColDef={{
          resizable: true,
          sortable: true,
          filter: true,
        }}
        onGridReady={onGridReady}
        onCellEditingStopped={onCellEditingStopped}
        onPasteEnd={onPasteEnd}
        getCellClass={getCellClass}
        animateRows={true}
        undoRedoCellEditing={true}
        undoRedoCellEditingLimit={20}
        enableCellChangeFlash={true}
        suppressRowTransform={true}
        rowHeight={35}
        headerHeight={40}
        groupHeaderHeight={40}
      />
    </div>
  );
};