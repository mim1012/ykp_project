import React, { useState, useCallback, useMemo, useRef, useEffect } from 'react';
import { AgGridReact } from 'ag-grid-react';
import 'ag-grid-community/styles/ag-grid.css';
import 'ag-grid-community/styles/ag-theme-alpine.css';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import axios from 'axios';

const SalesGrid = ({ 
    dealerCode, 
    readonly = false, 
    onRowsChanged 
}) => {
    const gridRef = useRef(null);
    const [gridApi, setGridApi] = useState(null);
    const queryClient = useQueryClient();
    
    // UX 상태 관리
    const [isCalculating, setIsCalculating] = useState(false);
    const [errorMessage, setErrorMessage] = useState('');
    const [successMessage, setSuccessMessage] = useState('');
    const [unsavedChanges, setUnsavedChanges] = useState(false);
    
    // 디바운싱용 타이머
    const calculationTimerRef = useRef(null);
    const autoSaveTimerRef = useRef(null);
    
    // 엑셀 스타일 키보드 단축키 처리
    useEffect(() => {
        const handleKeyDown = (event) => {
            if (!gridApi) return;
            
            // Ctrl+Z: 되돌리기
            if (event.ctrlKey && event.key === 'z') {
                event.preventDefault();
                // 실제 되돌리기 기능은 복잡하므로 기본 브라우저 기능 사용
                console.log('Undo requested');
            }
            
            // Ctrl+Y: 다시 실행
            if (event.ctrlKey && event.key === 'y') {
                event.preventDefault();
                console.log('Redo requested');
            }
            
            // Ctrl+S: 수동 저장
            if (event.ctrlKey && event.key === 's') {
                event.preventDefault();
                const allRows = [];
                gridApi.forEachNode(node => allRows.push(node.data));
                autoSaveMutation.mutate(allRows);
            }
            
            // Delete: 선택된 행 삭제
            if (event.key === 'Delete') {
                const selectedRows = gridApi.getSelectedRows();
                if (selectedRows.length > 0) {
                    event.preventDefault();
                    deleteSelectedRows();
                }
            }
            
            // Insert: 새 행 추가
            if (event.key === 'Insert') {
                event.preventDefault();
                addNewRow();
            }
            
            // Ctrl+A: 모든 행 선택
            if (event.ctrlKey && event.key === 'a') {
                event.preventDefault();
                gridApi.selectAll();
            }
        };
        
        document.addEventListener('keydown', handleKeyDown);
        
        return () => {
            document.removeEventListener('keydown', handleKeyDown);
            // 타이머 정리
            if (calculationTimerRef.current) {
                clearTimeout(calculationTimerRef.current);
            }
            if (autoSaveTimerRef.current) {
                clearTimeout(autoSaveTimerRef.current);
            }
        };
    }, [gridApi, autoSaveMutation]);

    // 데이터 조회 (강화된 에러 처리)
    const {
        data: salesData = [],
        isLoading,
        error: queryError,
        refetch
    } = useQuery({
        queryKey: ['sales', dealerCode],
        queryFn: () => fetchSalesData(dealerCode),
        refetchOnWindowFocus: false,
        staleTime: 30000, // 30초
        retry: (failureCount, error) => {
            // 인증 오류는 재시도하지 않음
            if (error.message.includes('401') || error.message.includes('인증')) {
                setErrorMessage('로그인이 필요합니다. 3초 후 로그인 페이지로 이동합니다.');
                setTimeout(() => {
                    window.location.href = '/login';
                }, 3000);
                return false;
            }
            // 서버 오류는 최대 2회 재시도
            return failureCount < 2;
        },
        onError: (error) => {
            console.error('Sales data loading failed:', error);

            if (error.message.includes('401') || error.message.includes('인증')) {
                setErrorMessage('로그인 세션이 만료되었습니다. 다시 로그인해주세요.');
            } else if (error.message.includes('403')) {
                setErrorMessage('이 데이터에 접근할 권한이 없습니다.');
            } else if (error.message.includes('404')) {
                setErrorMessage('요청한 데이터를 찾을 수 없습니다.');
            } else {
                setErrorMessage(`데이터 로드 실패: ${error.message}`);
            }
        },
        onSuccess: (data) => {
            // 성공 시 에러 메시지 클리어
            setErrorMessage('');
            console.log(`✅ Sales data loaded: ${data?.length || 0} records`);
        }
    });

    // 개선된 실시간 계산 뮤테이션
    const calculateMutation = useMutation({
        mutationFn: (rowData) => calculateRow(rowData, dealerCode),
        onMutate: () => {
            setIsCalculating(true);
            setErrorMessage('');
        },
        onSuccess: (calculatedData, originalData) => {
            // 그리드에서 해당 행 업데이트
            if (gridApi) {
                const rowNode = gridApi.getRowNode(originalData.id?.toString() || '');
                if (rowNode) {
                    rowNode.setData({ ...originalData, ...calculatedData });
                    
                    // 성공 피드백
                    setSuccessMessage('계산이 완료되었습니다.');
                    setTimeout(() => setSuccessMessage(''), 2000);
                }
            }
            setIsCalculating(false);
        },
        onError: (error) => {
            setErrorMessage(`계산 중 오류가 발생했습니다: ${error.message}`);
            setIsCalculating(false);
            setTimeout(() => setErrorMessage(''), 5000);
        }
    });

    // 자동 저장 뮤테이션
    const autoSaveMutation = useMutation({
        mutationFn: (allRowsData) => saveAllRows(allRowsData, dealerCode),
        onSuccess: () => {
            setUnsavedChanges(false);
            setSuccessMessage('자동 저장되었습니다.');
            setTimeout(() => setSuccessMessage(''), 2000);
        },
        onError: (error) => {
            setErrorMessage(`자동 저장 실패: ${error.message}`);
            setTimeout(() => setErrorMessage(''), 5000);
        }
    });

    // 컬럼 정의 (ykp-settlement 기반)
    const columnDefs = useMemo(() => [
        // 기본 정보
        {
            headerName: '판매자',
            field: 'salesperson',
            editable: !readonly,
            width: 100,
            pinned: 'left'
        },
        {
            headerName: '대리점',
            field: 'agency',
            editable: !readonly,
            width: 100,
            cellEditor: 'agSelectCellEditor',
            cellEditorParams: {
                values: ['이앤티', '앤투윈', '초시대', '아엠티', '에프지']
            }
        },
        {
            headerName: '통신사',
            field: 'carrier',
            editable: !readonly,
            width: 80,
            cellEditor: 'agSelectCellEditor',
            cellEditorParams: {
                values: ['SK', 'KT', 'LG']
            }
        },
        {
            headerName: '개통방식',
            field: 'activation_type',
            editable: !readonly,
            width: 100,
            cellEditor: 'agSelectCellEditor',
            cellEditorParams: {
                values: ['신규', 'MNP', '기변']
            }
        },
        {
            headerName: '모델명',
            field: 'model_name',
            editable: !readonly,
            width: 120
        },

        // 금액 필드들
        {
            headerName: '액면/셋팅가',
            field: 'base_price',
            editable: !readonly,
            width: 120,
            type: 'numericColumn',
            cellEditor: 'agNumberCellEditor',
            valueFormatter: (params) => formatCurrency(params.value),
            cellStyle: { backgroundColor: '#f0f8ff' }
        },
        {
            headerName: '구두1',
            field: 'verbal1',
            editable: !readonly,
            width: 100,
            type: 'numericColumn',
            cellEditor: 'agNumberCellEditor',
            valueFormatter: (params) => formatCurrency(params.value),
            cellStyle: { backgroundColor: '#f0f8ff' }
        },
        {
            headerName: '구두2',
            field: 'verbal2',
            editable: !readonly,
            width: 100,
            type: 'numericColumn',
            cellEditor: 'agNumberCellEditor',
            valueFormatter: (params) => formatCurrency(params.value),
            cellStyle: { backgroundColor: '#f0f8ff' }
        },
        {
            headerName: '그레이드',
            field: 'grade_amount',
            editable: !readonly,
            width: 100,
            type: 'numericColumn',
            cellEditor: 'agNumberCellEditor',
            valueFormatter: (params) => formatCurrency(params.value),
            cellStyle: { backgroundColor: '#f0f8ff' }
        },
        {
            headerName: '부가추가',
            field: 'additional_amount',
            editable: !readonly,
            width: 100,
            type: 'numericColumn',
            cellEditor: 'agNumberCellEditor',
            valueFormatter: (params) => formatCurrency(params.value),
            cellStyle: { backgroundColor: '#f0f8ff' }
        },

        // 계산된 필드들 (읽기 전용)
        {
            headerName: '리베총계',
            field: 'rebate_total',
            editable: false,
            width: 120,
            type: 'numericColumn',
            valueFormatter: (params) => formatCurrency(params.value),
            cellStyle: { backgroundColor: '#fff2cc', fontWeight: 'bold' }
        },
        {
            headerName: '정산금',
            field: 'settlement_amount',
            editable: false,
            width: 120,
            type: 'numericColumn',
            valueFormatter: (params) => formatCurrency(params.value),
            cellStyle: { backgroundColor: '#fff2cc', fontWeight: 'bold' }
        },
        {
            headerName: '세금',
            field: 'tax',
            editable: false,
            width: 100,
            type: 'numericColumn',
            valueFormatter: (params) => formatCurrency(params.value),
            cellStyle: { backgroundColor: '#ffe6e6' }
        },
        {
            headerName: '세전마진',
            field: 'margin_before_tax',
            editable: false,
            width: 120,
            type: 'numericColumn',
            valueFormatter: (params) => formatCurrency(params.value),
            cellStyle: { backgroundColor: '#e6ffe6', fontWeight: 'bold' }
        },
        {
            headerName: '세후마진',
            field: 'margin_after_tax',
            editable: false,
            width: 120,
            type: 'numericColumn',
            valueFormatter: (params) => formatCurrency(params.value),
            cellStyle: { backgroundColor: '#e6ffe6', fontWeight: 'bold' },
            pinned: 'right'
        }
    ], [readonly]);

    // 그리드 이벤트 핸들러
    const onGridReady = useCallback((params) => {
        setGridApi(params.api);
        
        // 컬럼 자동 크기 조정
        params.api.sizeColumnsToFit();
    }, []);

    // 디바운싱된 계산 함수
    const debouncedCalculation = useCallback((rowData) => {
        // 기존 타이머 클리어
        if (calculationTimerRef.current) {
            clearTimeout(calculationTimerRef.current);
        }
        
        // 500ms 후에 계산 실행
        calculationTimerRef.current = setTimeout(() => {
            // Laravel API 호출을 위한 필드 매핑
            const mappedData = {
                id: rowData.id,
                price_setting: rowData.base_price || 0,
                verbal1: rowData.verbal1 || 0,
                verbal2: rowData.verbal2 || 0,
                grade_amount: rowData.grade_amount || 0,
                addon_amount: rowData.additional_amount || 0,
                paper_cash: rowData.cash_activation || 0,
                usim_fee: rowData.usim_fee || 0,
                new_mnp_disc: rowData.new_mnp_discount || 0,
                deduction: rowData.deduction || 0,
                cash_in: rowData.cash_received || 0,
                payback: rowData.payback || 0
            };
            
            calculateMutation.mutate(mappedData);
        }, 500);
    }, [calculateMutation]);

    // 디바운싱된 자동 저장 함수
    const debouncedAutoSave = useCallback(() => {
        if (!gridApi) return;
        
        // 기존 타이머 클리어
        if (autoSaveTimerRef.current) {
            clearTimeout(autoSaveTimerRef.current);
        }
        
        // 3초 후에 자동 저장 실행
        autoSaveTimerRef.current = setTimeout(() => {
            const allRows = [];
            gridApi.forEachNode(node => allRows.push(node.data));
            autoSaveMutation.mutate(allRows);
        }, 3000);
    }, [gridApi, autoSaveMutation]);

    const onCellValueChanged = useCallback((event) => {
        const rowData = event.data;
        
        // 변경사항 표시
        setUnsavedChanges(true);
        
        // 디바운싱된 계산 실행
        debouncedCalculation(rowData);
        
        // 디바운싱된 자동 저장 실행
        debouncedAutoSave();
        
        // 부모 컴포넌트에 변경 알림
        if (onRowsChanged && gridApi) {
            const allRows = [];
            gridApi.forEachNode(node => allRows.push(node.data));
            onRowsChanged(allRows);
        }
    }, [debouncedCalculation, debouncedAutoSave, onRowsChanged, gridApi]);

    // 새 행 추가
    const addNewRow = useCallback(() => {
        if (!gridApi) return;

        const newRow = {
            salesperson: '',
            agency: '',
            carrier: '',
            activation_type: '',
            model_name: '',
            base_price: 0,
            verbal1: 0,
            verbal2: 0,
            grade_amount: 0,
            additional_amount: 0,
            cash_activation: 0,
            usim_fee: 0,
            new_mnp_discount: 0,
            deduction: 0,
            cash_received: 0,
            payback: 0
        };

        gridApi.applyTransaction({ add: [newRow] });
    }, [gridApi]);

    // 선택된 행 삭제
    const deleteSelectedRows = useCallback(() => {
        if (!gridApi) return;

        const selectedRows = gridApi.getSelectedRows();
        gridApi.applyTransaction({ remove: selectedRows });
    }, [gridApi]);

    return (
        <div className="w-full h-full">
            {/* 향상된 툴바 */}
            {!readonly && (
                <div className="mb-4 flex items-center justify-between bg-gray-50 p-4 rounded-lg">
                    <div className="flex gap-2">
                        <button
                            onClick={addNewRow}
                            className="px-4 py-2 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors flex items-center gap-2"
                        >
                            <span>+</span> 행 추가 (Insert)
                        </button>
                        <button
                            onClick={deleteSelectedRows}
                            className="px-4 py-2 bg-red-500 text-white rounded hover:bg-red-600 transition-colors flex items-center gap-2"
                        >
                            <span>×</span> 선택 행 삭제 (Del)
                        </button>
                        <button
                            onClick={() => {
                                if (gridApi) {
                                    const allRows = [];
                                    gridApi.forEachNode(node => {
                                        const rowData = node.data;
                                        // 필수 필드 기본값 설정 및 필드명 정리
                                        const cleanedData = {
                                            ...rowData,
                                            sale_date: rowData.sale_date || new Date().toISOString().split('T')[0],
                                            carrier: rowData.carrier || 'SK',
                                            activation_type: rowData.activation_type || '신규',
                                            // 숫자 필드들은 숫자로 변환
                                            base_price: parseFloat(rowData.base_price) || 0,
                                            verbal1: parseFloat(rowData.verbal1) || 0,
                                            verbal2: parseFloat(rowData.verbal2) || 0,
                                            grade_amount: parseFloat(rowData.grade_amount) || 0,
                                            additional_amount: parseFloat(rowData.additional_amount) || 0,
                                            cash_activation: parseFloat(rowData.cash_activation) || 0,
                                            usim_fee: parseFloat(rowData.usim_fee) || 0,
                                            new_mnp_discount: parseFloat(rowData.new_mnp_discount) || 0,
                                            deduction: parseFloat(rowData.deduction) || 0,
                                            cash_received: parseFloat(rowData.cash_received) || 0,
                                            payback: parseFloat(rowData.payback) || 0
                                        };
                                        allRows.push(cleanedData);
                                    });
                                    console.log('Sending cleaned data:', allRows[0]); // 디버깅용
                                    autoSaveMutation.mutate(allRows);
                                }
                            }}
                            className="px-4 py-2 bg-green-500 text-white rounded hover:bg-green-600 transition-colors flex items-center gap-2"
                        >
                            <span>💾</span> 저장 (Ctrl+S)
                        </button>
                    </div>
                    
                    <div className="flex items-center gap-4">
                        {/* 상태 표시 */}
                        {isCalculating && (
                            <div className="px-3 py-1 bg-blue-100 text-blue-800 rounded-full text-sm flex items-center gap-2">
                                <div className="animate-spin w-4 h-4 border-2 border-blue-600 border-t-transparent rounded-full"></div>
                                계산 중...
                            </div>
                        )}
                        
                        {unsavedChanges && (
                            <div className="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm">
                                미저장 변경사항
                            </div>
                        )}
                        
                        {autoSaveMutation.isPending && (
                            <div className="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm">
                                자동 저장 중...
                            </div>
                        )}
                    </div>
                </div>
            )}
            
            {/* 알림 메시지들 */}
            <div className="mb-2">
                {successMessage && (
                    <div className="px-4 py-2 bg-green-100 text-green-800 rounded border-l-4 border-green-500 mb-2">
                        {successMessage}
                    </div>
                )}
                
                {errorMessage && (
                    <div className="px-4 py-2 bg-red-100 text-red-800 rounded border-l-4 border-red-500 mb-2">
                        {errorMessage}
                    </div>
                )}
            </div>

            {/* AgGrid */}
            <div className="ag-theme-alpine" style={{ height: '600px', width: '100%' }}>
                <AgGridReact
                    ref={gridRef}
                    rowData={salesData || []}
                    columnDefs={columnDefs}
                    onGridReady={onGridReady}
                    onCellValueChanged={onCellValueChanged}
                    
                    // 향상된 그리드 옵션
                    defaultColDef={{
                        sortable: true,
                        filter: true,
                        resizable: true,
                        editable: false,
                        menuTabs: ['filterMenuTab', 'generalMenuTab'],
                        floatingFilter: true
                    }}
                    
                    // 선택 옵션
                    rowSelection="multiple"
                    suppressRowClickSelection={true}
                    rowMultiSelectWithClick={false}
                    
                    // 엑셀 스타일 편집 옵션
                    editType="fullRow"
                    suppressClickEdit={false}
                    enterMovesDown={true}
                    enterMovesDownAfterEdit={true}
                    singleClickEdit={true}
                    stopEditingWhenCellsLoseFocus={true}
                    
                    // 엑셀 스타일 키보드 네비게이션
                    suppressRowDeselection={false}
                    enableCellTextSelection={true}
                    
                    // 성능 및 UX 옵션
                    animateRows={true}
                    enableRangeSelection={true}
                    enableFillHandle={true}
                    enableRangeHandle={true}
                    suppressCopyRowsToClipboard={false}
                    suppressCopySingleCellRanges={false}
                    
                    // 가상화 설정 (대용량 데이터)
                    rowBuffer={10}
                    debounceVerticalScrollbar={true}
                    suppressScrollOnNewData={true}
                    
                    // 로딩 상태
                    loading={isLoading}
                    loadingOverlayComponent="agLoadingOverlay"
                    
                    // 추가 UX 개선
                    suppressFieldDotNotation={true}
                    suppressMenuHide={false}
                    suppressMovableColumns={false}
                />
            </div>
        </div>
    );
};

// 유틸리티 함수들
const formatCurrency = (value) => {
    if (value == null) return '';
    return new Intl.NumberFormat('ko-KR', {
        style: 'currency',
        currency: 'KRW'
    }).format(value);
};

const fetchSalesData = async (dealerCode) => {
    try {
        const params = dealerCode ? { dealer_code: dealerCode } : {};
        const response = await axios.get('/api/sales', { params });
        return response.data.data || [];
    } catch (error) {
        console.error('Sales data fetch failed:', error);
        // 더미 데이터 대신 에러 전파
        throw new Error(`판매 데이터 로드 실패: ${error.message}`);
    }
};

const calculateRow = async (rowData, dealerCode) => {
    const response = await axios.post('/api/calculation/row', {
        ...rowData,
        dealer_code: dealerCode
    });
    return response.data.data;
};

const saveAllRows = async (allRowsData, dealerCode) => {
    // 서버 스키마에 맞게 페이로드 수정 (data → sales)
    const response = await axios.post('/api/sales/bulk-save', {
        sales: allRowsData, // 서버가 기대하는 키 이름
        dealer_code: dealerCode
    });
    return response.data;
};

export default SalesGrid;