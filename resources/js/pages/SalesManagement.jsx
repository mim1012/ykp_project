import React, { useState } from 'react';
import SalesGrid from '../components/SalesGrid/SalesGrid';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ReactQueryDevtools } from '@tanstack/react-query-devtools';

// React Query 설정 개선
const queryClient = new QueryClient({
    defaultOptions: {
        queries: {
            refetchOnWindowFocus: false,
            staleTime: 5 * 60 * 1000, // 5분
            retry: 3,
            retryDelay: attemptIndex => Math.min(1000 * 2 ** attemptIndex, 30000),
        },
        mutations: {
            retry: 1,
        },
    },
});

const SalesManagement = () => {
    const [selectedDealer, setSelectedDealer] = useState('');
    const [gridData, setGridData] = useState([]);
    const [gridStats, setGridStats] = useState({
        totalRows: 0,
        modifiedRows: 0,
        calculatingRows: 0
    });

    const handleRowsChanged = (rows) => {
        setGridData(rows);
        
        // 통계 업데이트
        setGridStats(prev => ({
            ...prev,
            totalRows: rows.length,
            modifiedRows: rows.filter(row => row._modified).length
        }));
        
        console.log('Grid data changed:', rows.length, 'rows');
    };

    const dealerOptions = [
        { value: '', label: '전체 대리점', color: 'bg-gray-100' },
        { value: 'ENT', label: '이앤티', color: 'bg-blue-100' },
        { value: 'WIN', label: '앤투윈', color: 'bg-green-100' },
        { value: 'CHOSI', label: '초시대', color: 'bg-yellow-100' },
        { value: 'AMT', label: '아엠티', color: 'bg-purple-100' },
        { value: 'FG', label: '에프지', color: 'bg-pink-100' }
    ];

    return (
        <QueryClientProvider client={queryClient}>
            <div className="h-full flex flex-col">
                {/* 헤더 및 컨트롤 패널 */}
                <div className="bg-white border-b border-gray-200 p-6">
                    <div className="flex items-center justify-between mb-4">
                        <div>
                            <h1 className="text-2xl font-bold text-gray-900">판매 관리 시스템</h1>
                            <p className="text-sm text-gray-600 mt-1">AgGrid 기반 실시간 계산 및 데이터 관리</p>
                        </div>
                        
                        <div className="flex items-center space-x-4">
                            <button
                                onClick={() => window.showShortcutHelp && window.showShortcutHelp()}
                                className="px-3 py-2 text-sm bg-gray-100 text-gray-700 rounded-md hover:bg-gray-200 flex items-center gap-2"
                            >
                                <span>?</span> 단축키 (F1)
                            </button>
                            
                            <div className="text-right">
                                <div className="text-sm text-gray-500">현재 시간</div>
                                <div className="text-sm font-medium">
                                    {new Date().toLocaleTimeString('ko-KR')}
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    {/* 필터 및 상태 표시 */}
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-4">
                            <div className="flex items-center gap-2">
                                <label htmlFor="dealer-select" className="text-sm font-medium text-gray-700">
                                    대리점 필터:
                                </label>
                                <select
                                    id="dealer-select"
                                    value={selectedDealer}
                                    onChange={(e) => setSelectedDealer(e.target.value)}
                                    className="px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                >
                                    {dealerOptions.map(option => (
                                        <option key={option.value} value={option.value}>
                                            {option.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            
                            {selectedDealer && (
                                <div className={`px-3 py-1 rounded-full text-sm ${
                                    dealerOptions.find(opt => opt.value === selectedDealer)?.color || 'bg-gray-100'
                                }`}>
                                    {dealerOptions.find(opt => opt.value === selectedDealer)?.label} 선택됨
                                </div>
                            )}
                        </div>
                        
                        {/* 그리드 통계 */}
                        <div className="flex items-center gap-6 text-sm">
                            <div className="flex items-center gap-2">
                                <div className="w-3 h-3 bg-blue-500 rounded-full"></div>
                                <span>총 {gridStats.totalRows}행</span>
                            </div>
                            
                            {gridStats.modifiedRows > 0 && (
                                <div className="flex items-center gap-2">
                                    <div className="w-3 h-3 bg-yellow-500 rounded-full animate-pulse"></div>
                                    <span>수정된 행: {gridStats.modifiedRows}</span>
                                </div>
                            )}
                            
                            {gridStats.calculatingRows > 0 && (
                                <div className="flex items-center gap-2">
                                    <div className="w-3 h-3 bg-green-500 rounded-full animate-spin"></div>
                                    <span>계산 중: {gridStats.calculatingRows}</span>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                {/* AgGrid 컨테이너 */}
                <div className="flex-1 p-6">
                    <div className="bg-white rounded-lg shadow-sm border border-gray-200 h-full">
                        <SalesGrid
                            dealerCode={selectedDealer}
                            onRowsChanged={handleRowsChanged}
                        />
                    </div>
                </div>
            </div>
            
            {/* 개발 환경에서만 DevTools 표시 */}
            {process.env.NODE_ENV === 'development' && (
                <ReactQueryDevtools initialIsOpen={false} />
            )}
        </QueryClientProvider>
    );
};

export default SalesManagement;