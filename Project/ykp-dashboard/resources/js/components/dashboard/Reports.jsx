import React, { useState, useRef, useEffect } from 'react';
import { Card, Button, Badge, Icon } from '../ui';

export const Reports = () => {
    const [activeTab, setActiveTab] = useState('sales');
    const barRef = useRef(null);

    useEffect(() => {
        if (typeof Chart !== 'undefined' && barRef.current) {
            new Chart(barRef.current, {
                type: 'bar',
                data: {
                    labels: ['1주차', '2주차', '3주차', '4주차'],
                    datasets: [{
                        label: '매출',
                        data: [320, 450, 380, 520],
                        backgroundColor: '#0ea5e9'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            beginAtZero: true,
                            ticks: { callback: value => `₩${value}M` }
                        }
                    }
                }
            });
        }
    }, []);

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h1 className="text-2xl font-bold text-gray-900">보고서 생성</h1>
                <div className="flex gap-2">
                    <Button variant="secondary">
                        <Icon name="file-spreadsheet" className="w-4 h-4 mr-2" />
                        Excel
                    </Button>
                    <Button variant="secondary">
                        <Icon name="file-text" className="w-4 h-4 mr-2" />
                        PDF
                    </Button>
                    <Button variant="secondary">
                        <Icon name="printer" className="w-4 h-4 mr-2" />
                        인쇄
                    </Button>
                </div>
            </div>

            {/* Tabs */}
            <div className="border-b border-gray-200">
                <nav className="flex gap-8">
                    {[
                        { id: 'sales', label: '매출통계' },
                        { id: 'stores', label: '매장별 실적' },
                        { id: 'period', label: '기간별 분석' }
                    ].map(tab => (
                        <button
                            key={tab.id}
                            onClick={() => setActiveTab(tab.id)}
                            className={`pb-3 px-1 border-b-2 font-medium text-sm transition-colors
                                ${activeTab === tab.id 
                                    ? 'border-primary-600 text-primary-600' 
                                    : 'border-transparent text-gray-500 hover:text-gray-700'}`}
                        >
                            {tab.label}
                        </button>
                    ))}
                </nav>
            </div>

            {/* Filters */}
            <Card className="p-4">
                <div className="flex flex-wrap gap-4">
                    <div className="flex-1 min-w-[200px]">
                        <label className="block text-sm font-medium text-gray-700 mb-1">기간</label>
                        <input type="date" className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500" />
                    </div>
                    <div className="flex-1 min-w-[200px]">
                        <label className="block text-sm font-medium text-gray-700 mb-1">지사</label>
                        <select className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-primary-500 focus:border-primary-500">
                            <option>전체</option>
                            <option>서울</option>
                            <option>경기</option>
                            <option>인천</option>
                        </select>
                    </div>
                    <div className="flex items-end">
                        <Button>
                            <Icon name="search" className="w-4 h-4 mr-2" />
                            조회
                        </Button>
                    </div>
                </div>
            </Card>

            {/* Chart */}
            <Card className="p-6">
                <h3 className="text-lg font-semibold text-gray-900 mb-4">주차별 매출</h3>
                <canvas ref={barRef}></canvas>
            </Card>

            {/* Preview Cards */}
            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                <Card className="p-4">
                    <p className="text-sm text-gray-600 mb-1">총매출</p>
                    <p className="text-xl font-bold text-gray-900">₩1,670M</p>
                    <Badge variant="success">↑ 15.2%</Badge>
                </Card>
                <Card className="p-4">
                    <p className="text-sm text-gray-600 mb-1">판매건수</p>
                    <p className="text-xl font-bold text-gray-900">45,620</p>
                    <Badge variant="success">↑ 8.7%</Badge>
                </Card>
                <Card className="p-4">
                    <p className="text-sm text-gray-600 mb-1">평균 구매액</p>
                    <p className="text-xl font-bold text-gray-900">₩36,620</p>
                    <Badge variant="danger">↓ 2.3%</Badge>
                </Card>
                <Card className="p-4">
                    <p className="text-sm text-gray-600 mb-1">전월 대비</p>
                    <p className="text-xl font-bold text-gray-900">+12.5%</p>
                    <Badge variant="info">성장중</Badge>
                </Card>
            </div>

            {/* Table */}
            <Card className="overflow-hidden">
                <table className="w-full">
                    <thead className="bg-gray-50 border-b border-gray-200">
                        <tr>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">지사</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">매출</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">판매건수</th>
                            <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">평균 구매액</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                        {[
                            { branch: '서울', sales: '₩580M', count: '15,420', avg: '₩37,600' },
                            { branch: '경기', sales: '₩450M', count: '12,340', avg: '₩36,500' },
                            { branch: '인천', sales: '₩320M', count: '8,950', avg: '₩35,750' },
                            { branch: '부산', sales: '₩220M', count: '6,120', avg: '₩35,950' },
                            { branch: '대구', sales: '₩100M', count: '2,790', avg: '₩35,840' }
                        ].map((row, i) => (
                            <tr key={i} className="hover:bg-gray-50">
                                <td className="px-6 py-4 text-sm font-medium text-gray-900">{row.branch}</td>
                                <td className="px-6 py-4 text-sm text-gray-900">{row.sales}</td>
                                <td className="px-6 py-4 text-sm text-gray-600">{row.count}</td>
                                <td className="px-6 py-4 text-sm text-gray-600">{row.avg}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </Card>
        </div>
    );
};