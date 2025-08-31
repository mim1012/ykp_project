import React from 'react';
import { createRoot } from 'react-dom/client';
import SalesManagement from './pages/SalesManagement';

// DOM이 로드되면 React 앱 초기화
document.addEventListener('DOMContentLoaded', function() {
    const container = document.getElementById('sales-management-root');
    
    if (container) {
        console.log('AgGrid 컴포넌트 초기화 중...');
        
        try {
            const root = createRoot(container);
            root.render(<SalesManagement />);
            console.log('AgGrid 컴포넌트 로드 완료!');
        } catch (error) {
            console.error('AgGrid 초기화 실패:', error);
            container.innerHTML = `
                <div class="p-4 bg-red-100 border border-red-400 rounded">
                    <h3 class="font-bold text-red-800">AgGrid 로드 실패</h3>
                    <p class="text-red-600">React 컴포넌트를 불러올 수 없습니다: ${error.message}</p>
                </div>
            `;
        }
    } else {
        console.error('sales-management-root 컨테이너를 찾을 수 없습니다');
    }
});

// 전역 AgGrid 유틸리티 함수들
window.AgGridUtils = {
    formatCurrency: (value) => {
        if (value == null) return '';
        return new Intl.NumberFormat('ko-KR', {
            style: 'currency',
            currency: 'KRW'
        }).format(value);
    },
    
    validateRow: (rowData) => {
        const errors = [];
        if (!rowData.base_price || rowData.base_price < 0) {
            errors.push('액면가는 0 이상이어야 합니다');
        }
        return errors;
    }
};

console.log('AgGrid 스크립트 로드 완료');