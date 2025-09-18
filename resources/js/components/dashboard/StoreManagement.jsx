import React, { useState, useEffect } from 'react';
import { Card, Button, Badge, Icon, ResponsiveTable } from '../ui';

export const StoreManagement = () => {
    const [selectedStore, setSelectedStore] = useState(null);
    const [stores, setStores] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    // 🔄 실제 API에서 매장 데이터 로드
    useEffect(() => {
        const fetchStores = async () => {
            try {
                setLoading(true);
                const response = await fetch('/api/stores');

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();

                if (data.success && Array.isArray(data.data)) {
                    // API 응답을 컴포넌트 형식으로 변환
                    const transformedStores = data.data.map(store => ({
                        id: store.id,
                        name: store.name,
                        region: store.branch?.name || '미지정',
                        status: store.status || 'active',
                        todaySales: `₩${Number(store.today_sales || 0).toLocaleString()}`,
                        code: store.code,
                        owner_name: store.owner_name,
                        phone: store.phone,
                        address: store.address
                    }));

                    setStores(transformedStores);
                    console.log(`✅ 매장 데이터 ${transformedStores.length}개 로드 완료`);
                } else {
                    throw new Error('매장 데이터 형식 오류');
                }
            } catch (err) {
                console.error('❌ 매장 데이터 로드 실패:', err);
                setError(err.message);
            } finally {
                setLoading(false);
            }
        };

        fetchStores();
    }, []);

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

    // 로딩 상태 처리
    if (loading) {
        return (
            <div className="space-y-6">
                <div className="flex items-center justify-center p-8">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                    <span className="ml-3 text-gray-600">매장 데이터 로딩 중...</span>
                </div>
            </div>
        );
    }

    // 에러 상태 처리
    if (error) {
        return (
            <div className="space-y-6">
                <Card>
                    <div className="p-6 text-center">
                        <div className="text-red-500 text-4xl mb-4">⚠️</div>
                        <h3 className="text-lg font-medium text-gray-900 mb-2">매장 데이터 로드 실패</h3>
                        <p className="text-gray-600 mb-4">{error}</p>
                        <Button onClick={() => window.location.reload()}>
                            <Icon name="refresh" className="w-4 h-4 mr-2" />
                            다시 시도
                        </Button>
                    </div>
                </Card>
            </div>
        );
    }

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