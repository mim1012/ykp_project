import { useState, useEffect } from 'react';
import { secureRequest, handleApiResponse } from '../utils/api';

export const useDashboardData = () => {
    const [dashboardData, setDashboardData] = useState({
        todayRevenue: 0,
        monthRevenue: 0,
        avgRevenue: 0,
        activeStores: 0
    });
    const [dataStatus, setDataStatus] = useState({ 
        hasData: false, 
        message: '', 
        loading: true 
    });

    const loadDashboardData = async () => {
        setDataStatus({ hasData: false, message: '데이터를 로딩 중...', loading: true });
        
        try {
            const response = await secureRequest('/api/sales/statistics');
            const data = await handleApiResponse(response);
            
            if (data && data.summary && data.summary.total_count > 0) {
                setDashboardData({
                    todayRevenue: parseInt(data.summary.total_settlement) || 0,
                    monthRevenue: parseInt(data.summary.total_settlement) * 30 || 0,
                    avgRevenue: parseInt(data.summary.avg_settlement) || 0,
                    activeStores: parseInt(data.summary.active_stores) || 0
                });
                setDataStatus({ hasData: true, message: '데이터 로드 완료', loading: false });
            } else {
                setDashboardData({
                    todayRevenue: 0,
                    monthRevenue: 0,
                    avgRevenue: 0,
                    activeStores: 0
                });
                setDataStatus({ 
                    hasData: false, 
                    message: data?.message || '선택한 기간에 데이터가 없습니다.', 
                    loading: false 
                });
            }
        } catch (error) {
            console.error('대시보드 데이터 로드 실패:', error);
            setDashboardData({
                todayRevenue: 0,
                monthRevenue: 0,
                avgRevenue: 0,
                activeStores: 0
            });
            setDataStatus({ 
                hasData: false, 
                message: error.message || '네트워크 연결을 확인해주세요.', 
                loading: false 
            });
        }
    };

    useEffect(() => {
        // 인증된 사용자만 데이터 로드 (더 엄격한 체크)
        if (window.userData && window.userData.id && window.csrfToken) {
            console.log('Authenticated user detected, loading dashboard data');
            loadDashboardData();
            // 30초 간격을 60초로 늘려서 서버 부하 감소
            const interval = setInterval(loadDashboardData, 60000);
            return () => clearInterval(interval);
        } else {
            console.log('User not authenticated or missing CSRF token, skipping dashboard data load');
            setDataStatus({ 
                hasData: false, 
                message: '로그인이 필요합니다.',
                loading: false 
            });
        }
    }, []);

    return { dashboardData, dataStatus, loadDashboardData };
};