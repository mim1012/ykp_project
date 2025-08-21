import React, { useState } from 'react';
import { Card, Button, Badge, Icon, ResponsiveTable } from '../ui';

export const StoreManagement = () => {
    const [selectedStore, setSelectedStore] = useState(null);
    const stores = [
        { id: 1, name: '강남점', region: '서울', status: 'active', todaySales: '₩5.2M' },
        { id: 2, name: '판교점', region: '경기', status: 'active', todaySales: '₩4.8M' },
        { id: 3, name: '송도점', region: '인천', status: 'maintenance', todaySales: '₩0' },
        { id: 4, name: '해운대점', region: '부산', status: 'active', todaySales: '₩3.5M' },
        { id: 5, name: '동성로점', region: '대구', status: 'active', todaySales: '₩2.9M' }
    ];

    // Table columns configuration
    const columns = [
        {
            key: 'name',
            header: '매장명',
            render: (store) => (
                <span className="font-medium text-gray-900">{store.name}</span>
            )
        },
        {
            key: 'region',
            header: '지역',
            render: (store) => (
                <span className="text-gray-600">{store.region}</span>
            )
        },
        {
            key: 'status',
            header: '상태',
            render: (store) => (
                <Badge variant={store.status === 'active' ? 'success' : 'warning'}>
                    {store.status === 'active' ? '운영중' : '점검중'}
                </Badge>
            )
        },
        {
            key: 'todaySales',
            header: '금일 매출',
            render: (store) => (
                <span className="font-medium text-gray-900">{store.todaySales}</span>
            )
        }
    ];

    // Table actions
    const actions = (store) => (
        <div className="flex gap-2">
            <Button 
                variant="ghost" 
                size="sm"
                onClick={(e) => {
                    e.stopPropagation();
                    setSelectedStore(store);
                }}
            >
                <Icon name="eye" className="w-4 h-4" />
                <span className="hidden sm:inline ml-1">보기</span>
            </Button>
            <Button 
                variant="ghost" 
                size="sm"
                onClick={(e) => {
                    e.stopPropagation();
                    // Handle edit
                }}
            >
                <Icon name="edit" className="w-4 h-4" />
                <span className="hidden sm:inline ml-1">수정</span>
            </Button>
        </div>
    );

    return (
        <div className="space-y-6">
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <h1 className="text-xl sm:text-2xl font-bold text-gray-900">매장 관리</h1>
                <Button>
                    <Icon name="plus" className="w-4 h-4 mr-2" />
                    매장 추가
                </Button>
            </div>

            <ResponsiveTable
                columns={columns}
                data={stores}
                actions={actions}
                mobileCardView={true}
                className="space-y-4"
            />

            {selectedStore && (
                <Card className="p-4 sm:p-6">
                    <div className="flex items-center justify-between mb-6">
                        <h3 className="text-base sm:text-lg font-semibold text-gray-900">{selectedStore.name} 상세정보</h3>
                        <button onClick={() => setSelectedStore(null)} className="text-gray-400 hover:text-gray-600 p-1">
                            <Icon name="x" className="w-5 h-5" />
                        </button>
                    </div>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 sm:gap-6">
                        <div>
                            <h4 className="text-sm font-medium text-gray-500 mb-3">기본 정보</h4>
                            <div className="space-y-2">
                                <div className="flex justify-between items-center">
                                    <span className="text-sm text-gray-600">지역</span>
                                    <span className="text-sm font-medium">{selectedStore.region}</span>
                                </div>
                                <div className="flex justify-between items-center">
                                    <span className="text-sm text-gray-600">상태</span>
                                    <Badge variant={selectedStore.status === 'active' ? 'success' : 'warning'}>
                                        {selectedStore.status === 'active' ? '운영중' : '점검중'}
                                    </Badge>
                                </div>
                            </div>
                        </div>
                        <div>
                            <h4 className="text-sm font-medium text-gray-500 mb-3">금일 실적</h4>
                            <div className="space-y-2">
                                <div className="flex justify-between items-center">
                                    <span className="text-sm text-gray-600">매출</span>
                                    <span className="text-sm font-medium">{selectedStore.todaySales}</span>
                                </div>
                                <div className="flex justify-between items-center">
                                    <span className="text-sm text-gray-600">판매건수</span>
                                    <span className="text-sm font-medium">142건</span>
                                </div>
                                <div className="flex justify-between items-center">
                                    <span className="text-sm text-gray-600">평균객단가</span>
                                    <span className="text-sm font-medium">₩36,620</span>
                                </div>
                            </div>
                        </div>
                        <div className="sm:col-span-2 lg:col-span-1">
                            <h4 className="text-sm font-medium text-gray-500 mb-3">업무 현황</h4>
                            <div className="flex flex-wrap gap-2">
                                <Badge variant="success">완료 12</Badge>
                                <Badge variant="info">진행 5</Badge>
                                <Badge variant="default">예정 3</Badge>
                            </div>
                        </div>
                    </div>
                </Card>
            )}
        </div>
    );
};