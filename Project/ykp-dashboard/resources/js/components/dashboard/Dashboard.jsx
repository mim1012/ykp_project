import React, { useRef, useEffect } from 'react';
import { Card, Button, Icon, Badge } from '../ui';
import { KPICard } from './KPICard';
import { useDashboardData } from '../../hooks/useDashboardData';
import { formatCurrency } from '../../utils/formatters';

export const Dashboard = () => {
    const chartRef = useRef(null);
    const donutRef = useRef(null);
    const { dashboardData, dataStatus } = useDashboardData();

    useEffect(() => {
        // Initialize charts only if Chart.js is available
        if (typeof Chart !== 'undefined') {
            // Line Chart
            if (chartRef.current) {
                new Chart(chartRef.current, {
                    type: 'line',
                    data: {
                        labels: Array.from({length: 30}, (_, i) => `${i+1}일`),
                        datasets: [{
                            label: '매출',
                            data: [], // 🔄 실제 API 데이터로 교체 예정
                            borderColor: '#0ea5e9',
                            backgroundColor: 'rgba(14, 165, 233, 0.1)',
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: { 
                            legend: { display: false },
                            tooltip: {
                                mode: 'index',
                                intersect: false,
                            }
                        },
                        scales: {
                            x: {
                                display: true,
                                grid: {
                                    display: false
                                }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: { 
                                    callback: value => `₩${value}M`,
                                    maxTicksLimit: 6
                                },
                                grid: {
                                    color: '#f3f4f6'
                                }
                            }
                        },
                        interaction: {
                            mode: 'nearest',
                            axis: 'x',
                            intersect: false
                        }
                    }
                });
            }

            // Donut Chart
            if (donutRef.current) {
                new Chart(donutRef.current, {
                    type: 'doughnut',
                    data: {
                        labels: ['서울', '경기', '인천', '부산', '대구'],
                        datasets: [{
                            data: [35, 25, 20, 12, 8],
                            backgroundColor: [
                                '#0ea5e9', '#8b5cf6', '#f59e0b', '#10b981', '#ef4444'
                            ]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { 
                                position: 'bottom',
                                labels: {
                                    padding: 20,
                                    font: {
                                        size: 12
                                    },
                                    boxWidth: 12
                                }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return context.label + ': ' + context.parsed + '%';
                                    }
                                }
                            }
                        },
                        cutout: '60%'
                    }
                });
            }
        }
    }, []);

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between mb-4 gap-4">
                <h1 className="text-xl sm:text-2xl font-bold text-gray-900">대시보드</h1>
                <div className="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 sm:gap-3">
                    <a 
                        href="/sales/advanced-input" 
                        className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center justify-center gap-2 text-sm font-medium"
                    >
                        <Icon name="edit-3" className="w-4 h-4" />
                        개통표 입력
                    </a>
                    <div className="flex gap-2">
                        <Button variant="ghost" size="sm" className="flex-1 sm:flex-none">
                            <Icon name="refresh-cw" className="w-4 h-4 sm:mr-2" />
                            <span className="hidden sm:inline">새로고침</span>
                        </Button>
                        <Button size="sm" className="flex-1 sm:flex-none">
                            <Icon name="download" className="w-4 h-4 sm:mr-2" />
                            <span className="hidden sm:inline">리포트 다운로드</span>
                        </Button>
                    </div>
                </div>
            </div>
            
            {/* 빠른 안내 */}
            <div className="bg-blue-50 border border-blue-200 rounded-lg p-3 mb-6">
                <p className="text-sm text-blue-800">
                    💡 <strong>개통표 입력 방법:</strong> 상단 "개통표 입력" 버튼 클릭 → 새 행 추가 → 데이터 입력 → 자동 저장
                </p>
            </div>

            {/* 데이터 상태 알림 */}
            {!dataStatus.hasData && !dataStatus.loading && (
                <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4 mb-6">
                    <div className="flex items-center">
                        <Icon name="alert-triangle" className="w-5 h-5 text-yellow-600 mr-2" />
                        <div>
                            <h3 className="text-sm font-medium text-yellow-800">데이터가 없습니다</h3>
                            <p className="text-sm text-yellow-700 mt-1">{dataStatus.message}</p>
                            <p className="text-xs text-yellow-600 mt-1">
                                "개통표 입력" 메뉴에서 판매 데이터를 입력하면 대시보드에 실시간으로 반영됩니다.
                            </p>
                        </div>
                    </div>
                </div>
            )}
            
            {dataStatus.loading && (
                <div className="bg-gray-50 border border-gray-200 rounded-lg p-4 mb-6">
                    <div className="flex items-center">
                        <div className="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600 mr-2"></div>
                        <p className="text-sm text-gray-600">{dataStatus.message}</p>
                    </div>
                </div>
            )}

            {/* KPI Cards - Mobile Responsive Grid */}
            <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                <KPICard 
                    title="오늘 매출" 
                    value={formatCurrency(dashboardData.todayRevenue)}
                    change="12.5%" 
                    changeType="positive"
                    icon="trending-up"
                    description="전체 매장 당일 합계"
                />
                <KPICard 
                    title="이번 달 누적" 
                    value={formatCurrency(dashboardData.monthRevenue)}
                    change="8.3%" 
                    changeType="positive"
                    icon="dollar-sign"
                    description="1일부터 현재까지"
                />
                <KPICard 
                    title="일평균 매출" 
                    value={formatCurrency(dashboardData.avgRevenue)}
                    change="2.1%" 
                    changeType="negative"
                    icon="bar-chart-2"
                    description="최근 30일 평균"
                />
                <KPICard 
                    title="활성 매장" 
                    value={`${dashboardData.activeStores} / ${dashboardData.totalStores || '...'}`}
                    icon="store"
                    description="오늘 매출 발생 매장"
                />
            </div>

            {/* Charts - Mobile Responsive */}
            <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 lg:gap-6">
                <Card className="lg:col-span-2 p-4 sm:p-6">
                    <h3 className="text-base sm:text-lg font-semibold text-gray-900 mb-4">30일 매출 추이</h3>
                    <div className="relative h-64 sm:h-80">
                        <canvas ref={chartRef} className="w-full h-full"></canvas>
                    </div>
                </Card>
                <Card className="p-4 sm:p-6">
                    <h3 className="text-base sm:text-lg font-semibold text-gray-900 mb-4">지사별 매출</h3>
                    <div className="relative h-64 sm:h-80">
                        <canvas ref={donutRef} className="w-full h-full"></canvas>
                    </div>
                </Card>
            </div>

            {/* Timeline & Notifications - Mobile Responsive */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:gap-6">
                <Card className="p-4 sm:p-6">
                    <h3 className="text-base sm:text-lg font-semibold text-gray-900 mb-4">최근 활동</h3>
                    <div className="space-y-3 sm:space-y-4">
                        {[
                            { time: '10분 전', text: '서울지점 일일 정산 완료', type: 'success' },
                            { time: '1시간 전', text: '경기지점 신규 매장 등록', type: 'info' },
                            { time: '3시간 전', text: '부산지점 재고 부족 알림', type: 'warning' }
                        ].map((item, i) => (
                            <div key={i} className="flex items-start gap-3">
                                <div className={`w-2 h-2 rounded-full mt-2 flex-shrink-0
                                    ${item.type === 'success' ? 'bg-green-500' : 
                                      item.type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500'}`} 
                                />
                                <div className="flex-1 min-w-0">
                                    <p className="text-sm text-gray-900 break-words">{item.text}</p>
                                    <p className="text-xs text-gray-500 mt-1">{item.time}</p>
                                </div>
                            </div>
                        ))}
                    </div>
                </Card>
                <Card className="p-4 sm:p-6">
                    <h3 className="text-base sm:text-lg font-semibold text-gray-900 mb-4">공지사항</h3>
                    <div className="space-y-2 sm:space-y-3">
                        {[
                            { title: '시스템 점검 안내', date: '2024-01-15', badge: '중요' },
                            { title: '신규 기능 업데이트', date: '2024-01-14', badge: '신규' },
                            { title: '정산 프로세스 변경', date: '2024-01-13' }
                        ].map((item, i) => (
                            <div key={i} className="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors">
                                <div className="flex items-center gap-3 flex-1 min-w-0">
                                    <Icon name="file-text" className="w-4 h-4 text-gray-400 flex-shrink-0" />
                                    <div className="min-w-0 flex-1">
                                        <p className="text-sm font-medium text-gray-900 truncate">{item.title}</p>
                                        <p className="text-xs text-gray-500">{item.date}</p>
                                    </div>
                                </div>
                                {item.badge && (
                                    <Badge variant="danger" className="ml-2 flex-shrink-0">
                                        {item.badge}
                                    </Badge>
                                )}
                            </div>
                        ))}
                    </div>
                </Card>
            </div>
        </div>
    );
};