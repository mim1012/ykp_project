import React, { useState } from 'react';
import { useQuery } from '@tanstack/react-query';
import { Card, Button, Badge, Icon, LoadingSpinner } from '../ui';

export const StoreStatisticsModal = ({ store, onClose }) => {
    const [period, setPeriod] = useState('monthly'); // 'daily', 'monthly', 'yearly'
    const [selectedDate, setSelectedDate] = useState(new Date().toISOString().slice(0, 10));
    const [selectedYear, setSelectedYear] = useState(new Date().getFullYear());
    const [selectedMonth, setSelectedMonth] = useState(new Date().getMonth() + 1);

    // Build query params based on period
    const getQueryParams = () => {
        const params = new URLSearchParams({ period });
        if (period === 'daily') {
            params.append('date', selectedDate);
        } else if (period === 'monthly') {
            params.append('year', selectedYear);
            params.append('month', selectedMonth);
        } else if (period === 'yearly') {
            params.append('year', selectedYear);
        }
        return params.toString();
    };

    // Fetch statistics
    const { data: stats, isLoading, error } = useQuery({
        queryKey: ['store-statistics', store.id, period, selectedDate, selectedYear, selectedMonth],
        queryFn: async () => {
            const response = await fetch(`/api/stores/${store.id}/statistics?${getQueryParams()}`);
            if (!response.ok) throw new Error('Failed to fetch statistics');
            const result = await response.json();
            return result.data || {};
        },
        enabled: !!store.id
    });

    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-lg shadow-xl w-full max-w-4xl max-h-[90vh] overflow-y-auto">
                {/* Header */}
                <div className="sticky top-0 bg-white border-b border-gray-200 p-6 flex items-center justify-between">
                    <div>
                        <h2 className="text-xl font-bold text-gray-900">{store.name} 통계</h2>
                        <p className="text-sm text-gray-500 mt-1">{store.region}</p>
                    </div>
                    <button
                        onClick={onClose}
                        className="text-gray-400 hover:text-gray-600 transition-colors"
                    >
                        <Icon name="x" className="w-6 h-6" />
                    </button>
                </div>

                {/* Period Selector */}
                <div className="p-6 border-b border-gray-200 bg-gray-50">
                    <div className="flex items-center justify-between">
                        <div className="flex space-x-2">
                            <Button
                                variant={period === 'daily' ? 'primary' : 'secondary'}
                                size="sm"
                                onClick={() => setPeriod('daily')}
                            >
                                일별
                            </Button>
                            <Button
                                variant={period === 'monthly' ? 'primary' : 'secondary'}
                                size="sm"
                                onClick={() => setPeriod('monthly')}
                            >
                                월별
                            </Button>
                            <Button
                                variant={period === 'yearly' ? 'primary' : 'secondary'}
                                size="sm"
                                onClick={() => setPeriod('yearly')}
                            >
                                연도별
                            </Button>
                        </div>

                        <div className="flex items-center space-x-2">
                            {period === 'daily' && (
                                <input
                                    type="date"
                                    value={selectedDate}
                                    onChange={(e) => setSelectedDate(e.target.value)}
                                    className="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                                />
                            )}
                            {period === 'monthly' && (
                                <>
                                    <input
                                        type="number"
                                        value={selectedYear}
                                        onChange={(e) => setSelectedYear(parseInt(e.target.value))}
                                        className="w-24 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                                        min="2020"
                                        max={new Date().getFullYear()}
                                    />
                                    <select
                                        value={selectedMonth}
                                        onChange={(e) => setSelectedMonth(parseInt(e.target.value))}
                                        className="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                                    >
                                        {Array.from({ length: 12 }, (_, i) => i + 1).map(m => (
                                            <option key={m} value={m}>{m}월</option>
                                        ))}
                                    </select>
                                </>
                            )}
                            {period === 'yearly' && (
                                <input
                                    type="number"
                                    value={selectedYear}
                                    onChange={(e) => setSelectedYear(parseInt(e.target.value))}
                                    className="w-24 px-3 py-2 border border-gray-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500"
                                    min="2020"
                                    max={new Date().getFullYear()}
                                />
                            )}
                        </div>
                    </div>
                </div>

                {/* Content */}
                <div className="p-6">
                    {isLoading ? (
                        <div className="flex items-center justify-center py-12">
                            <LoadingSpinner size="lg" />
                        </div>
                    ) : error ? (
                        <div className="text-center py-12 text-red-600">
                            <Icon name="alert-circle" className="w-12 h-12 mx-auto mb-2" />
                            <p>통계를 불러오는데 실패했습니다.</p>
                        </div>
                    ) : (
                        <div className="space-y-6">
                            {/* Summary Cards */}
                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <Card className="p-4">
                                    <p className="text-sm text-gray-600 mb-1">총 판매 건수</p>
                                    <p className="text-2xl font-bold text-gray-900">
                                        {stats.summary?.total_sales || 0}건
                                    </p>
                                </Card>
                                <Card className="p-4">
                                    <p className="text-sm text-gray-600 mb-1">총 리베총계</p>
                                    <p className="text-2xl font-bold text-yellow-600">
                                        ₩{(stats.summary?.total_rebate || 0).toLocaleString()}
                                    </p>
                                </Card>
                                <Card className="p-4">
                                    <p className="text-sm text-gray-600 mb-1">총 정산 금액</p>
                                    <p className="text-2xl font-bold text-blue-600">
                                        ₩{(stats.summary?.total_settlement_amount || 0).toLocaleString()}
                                    </p>
                                </Card>
                                <Card className="p-4">
                                    <p className="text-sm text-gray-600 mb-1">건당 평균</p>
                                    <p className="text-2xl font-bold text-green-600">
                                        ₩{(stats.summary?.average_settlement_per_sale || 0).toLocaleString()}
                                    </p>
                                </Card>
                            </div>

                            {/* Goal Achievement */}
                            {stats.goal_achievement && (
                                <Card className="p-6">
                                    <h3 className="text-lg font-semibold text-gray-900 mb-4">
                                        목표 달성률
                                    </h3>
                                    <div className="space-y-4">
                                        <div>
                                            <div className="flex items-center justify-between mb-2">
                                                <span className="text-sm text-gray-600">매출 목표</span>
                                                <span className="text-sm font-medium">
                                                    {stats.goal_achievement.sales_achievement_rate}%
                                                </span>
                                            </div>
                                            <div className="w-full bg-gray-200 rounded-full h-3">
                                                <div
                                                    className="bg-blue-600 h-3 rounded-full transition-all"
                                                    style={{ width: `${Math.min(stats.goal_achievement.sales_achievement_rate, 100)}%` }}
                                                />
                                            </div>
                                            <div className="flex items-center justify-between mt-1 text-xs text-gray-500">
                                                <span>₩{(stats.summary?.total_settlement_amount || 0).toLocaleString()}</span>
                                                <span>목표: ₩{(stats.goal_achievement.sales_target || 0).toLocaleString()}</span>
                                            </div>
                                        </div>
                                        <div>
                                            <div className="flex items-center justify-between mb-2">
                                                <span className="text-sm text-gray-600">개통 목표</span>
                                                <span className="text-sm font-medium">
                                                    {stats.goal_achievement.activation_achievement_rate}%
                                                </span>
                                            </div>
                                            <div className="w-full bg-gray-200 rounded-full h-3">
                                                <div
                                                    className="bg-green-600 h-3 rounded-full transition-all"
                                                    style={{ width: `${Math.min(stats.goal_achievement.activation_achievement_rate, 100)}%` }}
                                                />
                                            </div>
                                            <div className="flex items-center justify-between mt-1 text-xs text-gray-500">
                                                <span>{stats.summary?.total_sales || 0}건</span>
                                                <span>목표: {stats.goal_achievement.activation_target}건</span>
                                            </div>
                                        </div>
                                    </div>
                                </Card>
                            )}

                            {/* Carrier Distribution */}
                            {stats.carrier_distribution && Object.keys(stats.carrier_distribution).length > 0 && (
                                <Card className="p-6">
                                    <h3 className="text-lg font-semibold text-gray-900 mb-4">
                                        통신사별 분포
                                    </h3>
                                    <div className="space-y-3">
                                        {Object.entries(stats.carrier_distribution).map(([carrier, count]) => {
                                            const percentage = ((count / (stats.summary?.total_sales || 1)) * 100).toFixed(1);
                                            return (
                                                <div key={carrier}>
                                                    <div className="flex items-center justify-between mb-1">
                                                        <span className="text-sm font-medium text-gray-700">{carrier}</span>
                                                        <span className="text-sm text-gray-600">{count}건 ({percentage}%)</span>
                                                    </div>
                                                    <div className="w-full bg-gray-200 rounded-full h-2">
                                                        <div
                                                            className="bg-indigo-600 h-2 rounded-full transition-all"
                                                            style={{ width: `${percentage}%` }}
                                                        />
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </Card>
                            )}

                            {/* Activation Type Distribution */}
                            {stats.activation_type_distribution && Object.keys(stats.activation_type_distribution).length > 0 && (
                                <Card className="p-6">
                                    <h3 className="text-lg font-semibold text-gray-900 mb-4">
                                        개통유형별 분포
                                    </h3>
                                    <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                        {Object.entries(stats.activation_type_distribution).map(([type, count]) => (
                                            <div key={type} className="text-center p-4 bg-gray-50 rounded-lg">
                                                <p className="text-2xl font-bold text-gray-900">{count}</p>
                                                <p className="text-sm text-gray-600 mt-1">{type}</p>
                                            </div>
                                        ))}
                                    </div>
                                </Card>
                            )}

                            {/* 개별 건별 개통표 */}
                            {stats.sales_list && stats.sales_list.length > 0 && (
                                <Card className="p-6">
                                    <h3 className="text-lg font-semibold text-gray-900 mb-4">
                                        개통 내역 ({stats.sales_list.length}건)
                                    </h3>
                                    <div className="overflow-x-auto">
                                        <table className="min-w-full divide-y divide-gray-200">
                                            <thead className="bg-gray-50">
                                                <tr>
                                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                        개통일
                                                    </th>
                                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                        고객명
                                                    </th>
                                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                        통신사
                                                    </th>
                                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                        개통유형
                                                    </th>
                                                    <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                                        리베총계
                                                    </th>
                                                    <th className="px-3 py-2 text-right text-xs font-medium text-gray-500 uppercase">
                                                        정산금액
                                                    </th>
                                                    <th className="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">
                                                        메모
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="bg-white divide-y divide-gray-200">
                                                {stats.sales_list.map((sale) => (
                                                    <tr key={sale.id} className="hover:bg-gray-50">
                                                        <td className="px-3 py-2 text-sm text-gray-900 whitespace-nowrap">
                                                            {sale.sale_date}
                                                        </td>
                                                        <td className="px-3 py-2 text-sm text-gray-900">
                                                            {sale.customer_name || '-'}
                                                        </td>
                                                        <td className="px-3 py-2 text-sm text-gray-700">
                                                            {sale.carrier || '-'}
                                                        </td>
                                                        <td className="px-3 py-2 text-sm text-gray-700">
                                                            {sale.activation_type || '-'}
                                                        </td>
                                                        <td className="px-3 py-2 text-sm text-right font-medium text-yellow-700">
                                                            ₩{(sale.rebate_total || 0).toLocaleString()}
                                                        </td>
                                                        <td className="px-3 py-2 text-sm text-right font-medium text-gray-900">
                                                            ₩{(sale.settlement_amount || 0).toLocaleString()}
                                                        </td>
                                                        <td className="px-3 py-2 text-sm text-gray-600 max-w-[200px] truncate" title={sale.memo || ''}>
                                                            {sale.memo || '-'}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </Card>
                            )}
                        </div>
                    )}
                </div>

                {/* Footer */}
                <div className="sticky bottom-0 bg-gray-50 border-t border-gray-200 p-6 flex justify-end">
                    <Button variant="secondary" onClick={onClose}>
                        닫기
                    </Button>
                </div>
            </div>
        </div>
    );
};
