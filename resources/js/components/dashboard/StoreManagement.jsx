import React, { useState, useEffect } from 'react';
import { Card, Button, Badge, Icon, ResponsiveTable } from '../ui';

export const StoreManagement = () => {
    const [selectedStore, setSelectedStore] = useState(null);
    const [stores, setStores] = useState([]);
    const [branches, setBranches] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [viewMode, setViewMode] = useState('list'); // 'list' | 'table'
    const [showBulkModal, setShowBulkModal] = useState(false);
    const [bulkType, setBulkType] = useState(null);

    // 리스트 뷰 상태
    const [expandedBranches, setExpandedBranches] = useState({});
    const [searchTerm, setSearchTerm] = useState('');
    const [statusFilter, setStatusFilter] = useState('all');

    // 정렬 상태 (localStorage에서 복원)
    const [sortBy, setSortBy] = useState(() => localStorage.getItem('storeSort_by') || 'name');
    const [sortOrder, setSortOrder] = useState(() => localStorage.getItem('storeSort_order') || 'asc');

    // 페이지네이션 상태
    const [currentPage, setCurrentPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [totalStores, setTotalStores] = useState(0);
    const [perPage, setPerPage] = useState(20);

    // 정렬 옵션
    const sortOptions = [
        { value: 'name_asc', label: '매장명 (가나다)', by: 'name', order: 'asc' },
        { value: 'name_desc', label: '매장명 (역순)', by: 'name', order: 'desc' },
        { value: 'sales_desc', label: '매출 (높은순)', by: 'sales', order: 'desc' },
        { value: 'sales_asc', label: '매출 (낮은순)', by: 'sales', order: 'asc' },
        { value: 'lastEntry_desc', label: '최근 입력순', by: 'lastEntry', order: 'desc' },
        { value: 'lastEntry_asc', label: '오래된 입력순', by: 'lastEntry', order: 'asc' },
        { value: 'status_active', label: '상태 (운영 우선)', by: 'status', order: 'asc' },
        { value: 'status_inactive', label: '상태 (점검 우선)', by: 'status', order: 'desc' },
        { value: 'code_asc', label: '코드 (오름차순)', by: 'code', order: 'asc' },
        { value: 'code_desc', label: '코드 (내림차순)', by: 'code', order: 'desc' },
    ];

    // 모달 상태
    const [showEditModal, setShowEditModal] = useState(false);
    const [showAccountModal, setShowAccountModal] = useState(false);
    const [showStatsModal, setShowStatsModal] = useState(false);
    const [showDeleteConfirm, setShowDeleteConfirm] = useState(false);
    const [modalStore, setModalStore] = useState(null);

    // API에서 매장 데이터 로드
    const fetchStores = async (sortByParam = sortBy, sortOrderParam = sortOrder, page = currentPage) => {
        try {
            setLoading(true);
            const params = new URLSearchParams({
                sort_by: sortByParam,
                sort_order: sortOrderParam,
                page: page.toString(),
                per_page: perPage.toString(),
            });
            const response = await fetch(`/api/stores?${params}`);

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
                        address: store.address,
                        lastEntryAt: store.last_entry_at,
                        daysSinceEntry: store.days_since_entry
                    }));

                    setStores(transformedStores);

                    // 페이지네이션 정보 저장
                    setCurrentPage(data.current_page || 1);
                    setLastPage(data.last_page || 1);
                    setTotalStores(data.total || transformedStores.length);

                    // 지사별로 그룹화
                    const grouped = transformedStores.reduce((acc, store) => {
                        const branchName = store.region;
                        if (!acc[branchName]) {
                            acc[branchName] = {
                                name: branchName,
                                stores: []
                            };
                        }
                        acc[branchName].stores.push(store);
                        return acc;
                    }, {});

                    // 지사명 가나다순 정렬
                    const sortedBranches = Object.values(grouped).sort((a, b) =>
                        a.name.localeCompare(b.name, 'ko')
                    );
                    setBranches(sortedBranches);
                    // 모든 지사 펼치기
                    const expanded = {};
                    Object.keys(grouped).forEach(key => {
                        expanded[key] = true;
                    });
                    setExpandedBranches(expanded);
                } else {
                    throw new Error('매장 데이터 형식 오류');
                }
            } catch (err) {
                console.error('매장 데이터 로드 실패:', err);
                setError(err.message);
        } finally {
            setLoading(false);
        }
    };

    // 초기 로드
    useEffect(() => {
        fetchStores();
    }, []);

    // 지사 토글
    const toggleBranch = (branchName) => {
        setExpandedBranches(prev => ({
            ...prev,
            [branchName]: !prev[branchName]
        }));
    };

    // 전체 펼치기/접기
    const toggleAll = (expand) => {
        const newState = {};
        branches.forEach(branch => {
            newState[branch.name] = expand;
        });
        setExpandedBranches(newState);
    };

    // 정렬 변경 핸들러
    const handleSortChange = (value) => {
        const option = sortOptions.find(opt => opt.value === value);
        if (option) {
            setSortBy(option.by);
            setSortOrder(option.order);
            localStorage.setItem('storeSort_by', option.by);
            localStorage.setItem('storeSort_order', option.order);
            // 정렬 변경 시 1페이지로 리셋
            setCurrentPage(1);
            fetchStores(option.by, option.order, 1);
        }
    };

    // 페이지 변경 핸들러
    const handlePageChange = (page) => {
        if (page >= 1 && page <= lastPage) {
            setCurrentPage(page);
            fetchStores(sortBy, sortOrder, page);
        }
    };

    // 테이블 컬럼 정렬 토글
    const toggleColumnSort = (column) => {
        let newSortBy = column;
        let newSortOrder = 'asc';

        if (sortBy === column) {
            newSortOrder = sortOrder === 'asc' ? 'desc' : 'asc';
        }

        setSortBy(newSortBy);
        setSortOrder(newSortOrder);
        localStorage.setItem('storeSort_by', newSortBy);
        localStorage.setItem('storeSort_order', newSortOrder);
        // 정렬 변경 시 1페이지로 리셋
        setCurrentPage(1);
        fetchStores(newSortBy, newSortOrder, 1);
    };

    // 매장 정렬 함수
    const sortStores = (storeList) => {
        return [...storeList].sort((a, b) => {
            let comparison = 0;

            switch (sortBy) {
                case 'name':
                    comparison = a.name.localeCompare(b.name, 'ko');
                    break;
                case 'sales':
                    const salesA = parseInt(a.todaySales.replace(/[₩,]/g, '')) || 0;
                    const salesB = parseInt(b.todaySales.replace(/[₩,]/g, '')) || 0;
                    comparison = salesA - salesB;
                    break;
                case 'lastEntry':
                    // 입력 기록 없는 경우 맨 뒤로 (오름차순 기준)
                    const dateA = a.lastEntryAt ? new Date(a.lastEntryAt).getTime() : 0;
                    const dateB = b.lastEntryAt ? new Date(b.lastEntryAt).getTime() : 0;
                    comparison = dateA - dateB;
                    break;
                case 'status':
                    const statusOrder = { active: 0, inactive: 1 };
                    comparison = (statusOrder[a.status] || 0) - (statusOrder[b.status] || 0);
                    break;
                case 'code':
                    comparison = (a.code || '').localeCompare(b.code || '', 'ko');
                    break;
                default:
                    comparison = 0;
            }

            return sortOrder === 'asc' ? comparison : -comparison;
        });
    };

    // 현재 정렬 옵션 값
    const currentSortValue = sortOptions.find(
        opt => opt.by === sortBy && opt.order === sortOrder
    )?.value || 'name_asc';

    // 필터링 및 정렬된 지사 데이터
    const getFilteredBranches = () => {
        return branches.map(branch => {
            const filteredStores = branch.stores.filter(store => {
                const matchesSearch = searchTerm === '' ||
                    store.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
                    store.code?.toLowerCase().includes(searchTerm.toLowerCase()) ||
                    store.address?.toLowerCase().includes(searchTerm.toLowerCase());
                const matchesStatus = statusFilter === 'all' || store.status === statusFilter;
                return matchesSearch && matchesStatus;
            });
            return {
                ...branch,
                stores: sortStores(filteredStores)
            };
        }).filter(branch => branch.stores.length > 0);
    };

    // 액션 핸들러
    const handleEdit = (store, e) => {
        e?.stopPropagation();
        setModalStore(store);
        setShowEditModal(true);
    };

    const handleAccount = (store, e) => {
        e?.stopPropagation();
        setModalStore(store);
        setShowAccountModal(true);
    };

    const handleStats = (store, e) => {
        e?.stopPropagation();
        setModalStore(store);
        setShowStatsModal(true);
    };

    const handleDelete = (store, e) => {
        e?.stopPropagation();
        setModalStore(store);
        setShowDeleteConfirm(true);
    };

    const confirmDelete = async () => {
        if (!modalStore) return;
        try {
            const response = await fetch(`/api/stores/${modalStore.id}`, { method: 'DELETE' });
            if (response.ok) {
                setStores(prev => prev.filter(s => s.id !== modalStore.id));
                setBranches(prev => prev.map(branch => ({
                    ...branch,
                    stores: branch.stores.filter(s => s.id !== modalStore.id)
                })));
                setShowDeleteConfirm(false);
                setModalStore(null);
            }
        } catch (err) {
            console.error('매장 삭제 실패:', err);
        }
    };

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
        <div className="flex gap-1">
            <Button variant="ghost" size="sm" onClick={(e) => handleEdit(store, e)}>수정</Button>
            <Button variant="ghost" size="sm" onClick={(e) => handleAccount(store, e)}>계정</Button>
            <Button variant="ghost" size="sm" onClick={(e) => handleStats(store, e)}>성과</Button>
            <Button variant="ghost" size="sm" className="text-red-600 hover:text-red-700" onClick={(e) => handleDelete(store, e)}>삭제</Button>
        </div>
    );

    // 총 매장 수
    const totalStores = stores.length;
    const filteredBranches = getFilteredBranches();
    const filteredStoreCount = filteredBranches.reduce((sum, b) => sum + b.stores.length, 0);

    // 로딩 상태 처리
    if (loading) {
        return (
            <div className="space-y-6">
                <div className="flex items-center justify-center p-8">
                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
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
        <div className="space-y-4">
            {/* 헤더 */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <div>
                    <h1 className="text-xl sm:text-2xl font-bold text-gray-900">매장 관리</h1>
                    <p className="text-sm text-gray-500">총 {totalStores}개 매장</p>
                </div>
                <div className="flex gap-2 flex-wrap">
                    {/* 보기 모드 전환 */}
                    <div className="flex border rounded-lg overflow-hidden">
                        <button
                            onClick={() => setViewMode('list')}
                            className={`px-3 py-2 text-sm font-medium ${viewMode === 'list'
                                    ? 'bg-primary-900 text-white'
                                    : 'bg-white text-gray-700 hover:bg-gray-50'
                                }`}
                        >
                            리스트
                        </button>
                        <button
                            onClick={() => setViewMode('table')}
                            className={`px-3 py-2 text-sm font-medium border-l ${viewMode === 'table'
                                    ? 'bg-primary-900 text-white'
                                    : 'bg-white text-gray-700 hover:bg-gray-50'
                                }`}
                        >
                            테이블
                        </button>
                    </div>

                    {/* 대량 생성 버튼들 */}
                    <Button variant="outline" size="sm" onClick={() => { setBulkType('branch'); setShowBulkModal(true); }}>지사 대량 생성</Button>
                    <Button variant="outline" size="sm" onClick={() => { setBulkType('store'); setShowBulkModal(true); }}>매장 대량 생성</Button>
                    <Button size="sm"><Icon name="plus" className="w-4 h-4 mr-1" />매장 추가</Button>
                </div>
            </div>

            {/* 검색/필터 바 (리스트 뷰에서만) */}
            {viewMode === 'list' && (
                <div className="flex flex-col sm:flex-row gap-2 items-stretch sm:items-center">
                    <div className="flex-1 relative">
                        <Icon name="search" className="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" />
                        <input
                            type="text"
                            placeholder="매장명, 코드, 주소 검색..."
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                            className="w-full pl-9 pr-4 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                        />
                    </div>
                    <select
                        value={statusFilter}
                        onChange={(e) => setStatusFilter(e.target.value)}
                        className="px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                    >
                        <option value="all">전체 상태</option>
                        <option value="active">운영중</option>
                        <option value="inactive">점검중</option>
                    </select>
                    <select
                        value={currentSortValue}
                        onChange={(e) => handleSortChange(e.target.value)}
                        className="px-3 py-2 border rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-primary-500"
                    >
                        {sortOptions.map(option => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    <div className="flex gap-1">
                        <Button variant="ghost" size="sm" onClick={() => toggleAll(true)}>전체 펼치기</Button>
                        <Button variant="ghost" size="sm" onClick={() => toggleAll(false)}>전체 접기</Button>
                    </div>
                </div>
            )}

            {/* 검색 결과 및 정렬 상태 표시 */}
            {viewMode === 'list' && (
                <p className="text-sm text-gray-500">
                    {searchTerm ? `검색 결과: ${filteredStoreCount}개 매장` : `총 ${filteredStoreCount}개 매장`}
                    <span className="text-gray-400 ml-2">
                        ({sortOptions.find(opt => opt.value === currentSortValue)?.label} 정렬)
                    </span>
                </p>
            )}

            {/* 리스트 뷰 */}
            {viewMode === 'list' && (
                <div className="space-y-2">
                    {filteredBranches.map((branch) => (
                        <div key={branch.name} className="border rounded-lg overflow-hidden">
                            <button
                                onClick={() => toggleBranch(branch.name)}
                                className="w-full px-4 py-3 bg-gray-50 hover:bg-gray-100 flex items-center justify-between text-left"
                            >
                                <div className="flex items-center gap-2">
                                    <Icon name={expandedBranches[branch.name] ? 'chevron-down' : 'chevron-right'} className="w-4 h-4 text-gray-500" />
                                    <span className="font-medium text-gray-900">{branch.name}</span>
                                    <Badge variant="info" className="text-xs">{branch.stores.length}</Badge>
                                </div>
                            </button>
                            {expandedBranches[branch.name] && (
                                <div className="divide-y">
                                    {branch.stores.map((store) => (
                                        <div key={store.id} className="px-4 py-2 hover:bg-gray-50 flex items-center justify-between">
                                            <div className="flex items-center gap-4 flex-1 min-w-0">
                                                <div className="min-w-0 flex-1">
                                                    <div className="font-medium text-gray-900 truncate">{store.name}</div>
                                                    <div className="text-xs text-gray-500 truncate">{store.code} {store.address && `| ${store.address}`}</div>
                                                </div>
                                                <div className="flex items-center gap-2 flex-shrink-0">
                                                    {/* 마지막 입력 시간 */}
                                                    <span className={`text-xs ${
                                                        !store.lastEntryAt ? 'text-red-500' :
                                                        store.daysSinceEntry >= 3 ? 'text-orange-500' :
                                                        'text-gray-400'
                                                    }`}>
                                                        {store.lastEntryAt
                                                            ? (store.daysSinceEntry === 0
                                                                ? '오늘'
                                                                : store.daysSinceEntry === 1
                                                                    ? '어제'
                                                                    : `${store.daysSinceEntry}일 전`)
                                                            : '미입력'}
                                                    </span>
                                                    <Badge variant={store.status === 'active' ? 'success' : 'warning'} className="text-xs">
                                                        {store.status === 'active' ? '운영' : '점검'}
                                                    </Badge>
                                                </div>
                                            </div>
                                            <div className="flex gap-1 ml-2 flex-shrink-0">
                                                <button onClick={(e) => handleEdit(store, e)} className="px-2 py-1 text-xs text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded">수정</button>
                                                <button onClick={(e) => handleAccount(store, e)} className="px-2 py-1 text-xs text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded">계정</button>
                                                <button onClick={(e) => handleStats(store, e)} className="px-2 py-1 text-xs text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded">성과</button>
                                                <button onClick={(e) => handleDelete(store, e)} className="px-2 py-1 text-xs text-red-600 hover:text-red-700 hover:bg-red-50 rounded">삭제</button>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    ))}
                    {filteredBranches.length === 0 && (
                        <div className="text-center py-8 text-gray-500">
                            {searchTerm ? '검색 결과가 없습니다' : '등록된 매장이 없습니다'}
                        </div>
                    )}
                </div>
            )}

            {/* 테이블 뷰 */}
            {viewMode === 'table' && (
                <div className="border rounded-lg overflow-hidden">
                    <table className="w-full">
                        <thead className="bg-gray-50 border-b">
                            <tr>
                                <th
                                    onClick={() => toggleColumnSort('name')}
                                    className="px-4 py-3 text-left text-sm font-medium text-gray-700 cursor-pointer hover:bg-gray-100 select-none"
                                >
                                    <div className="flex items-center gap-1">
                                        매장명
                                        {sortBy === 'name' && (
                                            <span className="text-primary-600">{sortOrder === 'asc' ? '▲' : '▼'}</span>
                                        )}
                                        {sortBy !== 'name' && <span className="text-gray-300">↕</span>}
                                    </div>
                                </th>
                                <th className="px-4 py-3 text-left text-sm font-medium text-gray-700">
                                    지역
                                </th>
                                <th
                                    onClick={() => toggleColumnSort('status')}
                                    className="px-4 py-3 text-left text-sm font-medium text-gray-700 cursor-pointer hover:bg-gray-100 select-none"
                                >
                                    <div className="flex items-center gap-1">
                                        상태
                                        {sortBy === 'status' && (
                                            <span className="text-primary-600">{sortOrder === 'asc' ? '▲' : '▼'}</span>
                                        )}
                                        {sortBy !== 'status' && <span className="text-gray-300">↕</span>}
                                    </div>
                                </th>
                                <th
                                    onClick={() => toggleColumnSort('lastEntry')}
                                    className="px-4 py-3 text-left text-sm font-medium text-gray-700 cursor-pointer hover:bg-gray-100 select-none"
                                >
                                    <div className="flex items-center gap-1">
                                        마지막 입력
                                        {sortBy === 'lastEntry' && (
                                            <span className="text-primary-600">{sortOrder === 'asc' ? '▲' : '▼'}</span>
                                        )}
                                        {sortBy !== 'lastEntry' && <span className="text-gray-300">↕</span>}
                                    </div>
                                </th>
                                <th
                                    onClick={() => toggleColumnSort('sales')}
                                    className="px-4 py-3 text-left text-sm font-medium text-gray-700 cursor-pointer hover:bg-gray-100 select-none"
                                >
                                    <div className="flex items-center gap-1">
                                        금일 매출
                                        {sortBy === 'sales' && (
                                            <span className="text-primary-600">{sortOrder === 'asc' ? '▲' : '▼'}</span>
                                        )}
                                        {sortBy !== 'sales' && <span className="text-gray-300">↕</span>}
                                    </div>
                                </th>
                                <th className="px-4 py-3 text-right text-sm font-medium text-gray-700">
                                    액션
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {sortStores(stores).map((store) => (
                                <tr key={store.id} className="hover:bg-gray-50">
                                    <td className="px-4 py-3">
                                        <span className="font-medium text-gray-900">{store.name}</span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className="text-gray-600">{store.region}</span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge variant={store.status === 'active' ? 'success' : 'warning'}>
                                            {store.status === 'active' ? '운영중' : '점검중'}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className={`text-sm ${
                                            !store.lastEntryAt ? 'text-red-500 font-medium' :
                                            store.daysSinceEntry >= 3 ? 'text-orange-500' :
                                            'text-gray-600'
                                        }`}>
                                            {store.lastEntryAt
                                                ? (store.daysSinceEntry === 0
                                                    ? '오늘'
                                                    : store.daysSinceEntry === 1
                                                        ? '어제'
                                                        : `${store.daysSinceEntry}일 전`)
                                                : '미입력'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span className="font-medium text-gray-900">{store.todaySales}</span>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex gap-1 justify-end">
                                            <Button variant="ghost" size="sm" onClick={(e) => handleEdit(store, e)}>수정</Button>
                                            <Button variant="ghost" size="sm" onClick={(e) => handleAccount(store, e)}>계정</Button>
                                            <Button variant="ghost" size="sm" onClick={(e) => handleStats(store, e)}>성과</Button>
                                            <Button variant="ghost" size="sm" className="text-red-600 hover:text-red-700" onClick={(e) => handleDelete(store, e)}>삭제</Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {stores.length === 0 && (
                        <div className="text-center py-8 text-gray-500">등록된 매장이 없습니다</div>
                    )}
                </div>
            )}

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

            {/* 삭제 확인 모달 */}
            {showDeleteConfirm && modalStore && (
                <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
                    <Card className="w-full max-w-md p-6">
                        <h3 className="text-lg font-semibold text-gray-900 mb-2">매장 삭제</h3>
                        <p className="text-gray-600 mb-6">
                            "{modalStore.name}" 매장을 삭제하시겠습니까?<br />
                            <span className="text-red-600 text-sm">이 작업은 되돌릴 수 없습니다.</span>
                        </p>
                        <div className="flex gap-2 justify-end">
                            <Button variant="outline" onClick={() => { setShowDeleteConfirm(false); setModalStore(null); }}>취소</Button>
                            <Button className="bg-red-600 hover:bg-red-700 text-white" onClick={confirmDelete}>삭제</Button>
                        </div>
                    </Card>
                </div>
            )}

            {/* 대량 생성 모달 */}
            {showBulkModal && (
                <BulkCreateModal
                    type={bulkType}
                    onClose={() => {
                        setShowBulkModal(false);
                        setBulkType(null);
                    }}
                    onSuccess={() => {
                        setShowBulkModal(false);
                        setBulkType(null);
                        window.location.reload(); // 데이터 새로고침
                    }}
                />
            )}
        </div>
    );
};

// 대량 생성 모달 컴포넌트
const BulkCreateModal = ({ type, onClose, onSuccess }) => {
    const [step, setStep] = useState(1); // 1: 업로드, 2: 검증, 3: 생성중, 4: 완료
    const [file, setFile] = useState(null);
    const [validationResult, setValidationResult] = useState(null);
    const [createResult, setCreateResult] = useState(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const isBranch = type === 'branch';
    const title = isBranch ? '지사 대량 생성' : '매장 대량 생성';

    // 템플릿 다운로드
    const downloadTemplate = async () => {
        try {
            const endpoint = isBranch ? '/api/branches/bulk/template' : '/api/stores/bulk/template';
            const response = await fetch(endpoint);
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = isBranch ? 'branch-template.xlsx' : 'store-template.xlsx';
            document.body.appendChild(a);
            a.click();
            a.remove();
            window.URL.revokeObjectURL(url);
        } catch (err) {
            console.error('템플릿 다운로드 실패:', err);
            setError('템플릿 다운로드에 실패했습니다.');
        }
    };

    // 파일 업로드 & 검증
    const handleFileUpload = async (e) => {
        const selectedFile = e.target.files[0];
        if (!selectedFile) return;

        setFile(selectedFile);
        setLoading(true);
        setError(null);

        try {
            const formData = new FormData();
            formData.append('file', selectedFile);

            const endpoint = isBranch
                ? '/api/branches/bulk/upload'
                : '/api/stores/bulk/upload';

            const response = await fetch(endpoint, {
                method: 'POST',
                body: formData,
            });

            const data = await response.json();

            if (data.success) {
                setValidationResult(data.data);
                setStep(2);
            } else {
                setError(data.error || '파일 검증에 실패했습니다.');
            }
        } catch (err) {
            console.error('파일 업로드 실패:', err);
            setError('파일 업로드에 실패했습니다.');
        } finally {
            setLoading(false);
        }
    };

    // 대량 생성 실행
    const handleCreate = async () => {
        setLoading(true);
        setError(null);
        setStep(3);

        try {
            const formData = new FormData();
            formData.append('file', file);

            const endpoint = isBranch
                ? '/api/branches/bulk/create'
                : '/api/stores/bulk/create';

            const response = await fetch(endpoint, {
                method: 'POST',
                body: formData,
            });

            const data = await response.json();

            if (data.success) {
                setCreateResult(data);
                setStep(4);
            } else {
                setError(data.error || '생성에 실패했습니다.');
                setStep(2);
            }
        } catch (err) {
            console.error('생성 실패:', err);
            setError('생성에 실패했습니다.');
            setStep(2);
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-50">
            <Card className="w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <div className="p-6">
                    {/* 헤더 */}
                    <div className="flex items-center justify-between mb-6">
                        <h2 className="text-xl font-bold text-gray-900">{title}</h2>
                        <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
                            <Icon name="x" className="w-6 h-6" />
                        </button>
                    </div>

                    {/* Step 1: 파일 업로드 */}
                    {step === 1 && (
                        <div className="space-y-6">
                            <div className="bg-gray-50 border border-gray-200 rounded-lg p-4">
                                <h3 className="font-medium text-gray-900 mb-2">업로드 안내</h3>
                                <ul className="text-sm text-gray-600 space-y-1">
                                    {isBranch ? (
                                        <>
                                            <li>• 필수 입력: 지사명, 지역장 이름 (2개 필드만)</li>
                                            <li>• 자동 생성: 지사 코드, 계정 ID, 이메일, 비밀번호</li>
                                            <li>• 50개 이상은 백그라운드 처리됩니다</li>
                                        </>
                                    ) : (
                                        <>
                                            <li>• 필수 입력: 매장명, 소속 지사 (2개 필드만)</li>
                                            <li>• 자동 생성: 매장 코드, 주소, 전화번호, 계정 정보</li>
                                            <li>• 100개 이상은 백그라운드 처리됩니다</li>
                                        </>
                                    )}
                                </ul>
                            </div>

                            <div>
                                <Button
                                    variant="outline"
                                    onClick={downloadTemplate}
                                    className="w-full mb-4"
                                >
                                    <Icon name="download" className="w-4 h-4 mr-2" />
                                    엑셀 템플릿 다운로드
                                </Button>

                                <label className="block">
                                    <div className="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-primary-500 cursor-pointer transition">
                                        <Icon name="upload" className="w-12 h-12 mx-auto text-gray-400 mb-4" />
                                        <p className="text-sm text-gray-600 mb-2">
                                            작성한 엑셀 파일을 클릭하거나 드래그하세요
                                        </p>
                                        <p className="text-xs text-gray-500">
                                            .xlsx, .xls 형식만 지원 (최대 10MB)
                                        </p>
                                        <input
                                            type="file"
                                            accept=".xlsx,.xls"
                                            onChange={handleFileUpload}
                                            className="hidden"
                                        />
                                    </div>
                                </label>

                                {file && (
                                    <div className="mt-4 p-3 bg-gray-50 rounded-lg flex items-center justify-between">
                                        <div className="flex items-center gap-2">
                                            <Icon name="file" className="w-5 h-5 text-gray-400" />
                                            <span className="text-sm text-gray-700">{file.name}</span>
                                        </div>
                                        <button
                                            onClick={() => setFile(null)}
                                            className="text-gray-400 hover:text-red-500"
                                        >
                                            <Icon name="x" className="w-4 h-4" />
                                        </button>
                                    </div>
                                )}
                            </div>

                            {error && (
                                <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-800">
                                    {error}
                                </div>
                            )}

                            {loading && (
                                <div className="flex items-center justify-center py-4">
                                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-primary-600"></div>
                                    <span className="ml-3 text-gray-600">파일 검증 중...</span>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Step 2: 검증 결과 */}
                    {step === 2 && validationResult && (
                        <div className="space-y-6">
                            <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                                <h3 className="font-medium text-green-900 mb-2">검증 완료</h3>
                                <p className="text-sm text-green-800">
                                    총 {validationResult.total_rows}개 행이 확인되었습니다.
                                </p>
                            </div>

                            {validationResult.validation_errors && validationResult.validation_errors.length > 0 && (
                                <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                    <h3 className="font-medium text-yellow-900 mb-2">오류 발견</h3>
                                    <div className="max-h-60 overflow-y-auto space-y-2">
                                        {validationResult.validation_errors.map((err, idx) => (
                                            <div key={idx} className="text-sm text-yellow-800 bg-yellow-100 p-2 rounded">
                                                <div className="font-medium">행 {err.row}:</div>
                                                <ul className="list-disc list-inside ml-2">
                                                    {err.errors.map((msg, i) => (
                                                        <li key={i}>{msg}</li>
                                                    ))}
                                                </ul>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            {error && (
                                <div className="bg-red-50 border border-red-200 rounded-lg p-4 text-sm text-red-800">
                                    {error}
                                </div>
                            )}

                            <div className="flex gap-2">
                                <Button variant="outline" onClick={() => setStep(1)} className="flex-1">
                                    다시 업로드
                                </Button>
                                <Button
                                    onClick={handleCreate}
                                    disabled={!validationResult.can_proceed || loading}
                                    className="flex-1"
                                >
                                    {isBranch ? '지사' : '매장'} 생성하기
                                </Button>
                            </div>
                        </div>
                    )}

                    {/* Step 3: 생성 중 */}
                    {step === 3 && (
                        <div className="flex flex-col items-center justify-center py-12">
                            <div className="animate-spin rounded-full h-16 w-16 border-b-2 border-primary-600 mb-4"></div>
                            <p className="text-lg font-medium text-gray-900">생성 중...</p>
                            <p className="text-sm text-gray-600 mt-2">잠시만 기다려주세요</p>
                        </div>
                    )}

                    {/* Step 4: 완료 */}
                    {step === 4 && createResult && (
                        <div className="space-y-6">
                            <div className="text-center py-6">
                                
                                <h3 className="text-2xl font-bold text-gray-900 mb-2">생성 완료!</h3>
                                {createResult.status === 'queued' ? (
                                    <p className="text-gray-600">
                                        백그라운드에서 처리 중입니다.<br />
                                        잠시 후 새로고침해주세요.
                                    </p>
                                ) : (
                                    <div className="mt-4 grid grid-cols-3 gap-4">
                                        <div className="bg-green-50 rounded-lg p-4">
                                            <div className="text-2xl font-bold text-green-600">
                                                {createResult.summary?.success || 0}
                                            </div>
                                            <div className="text-sm text-green-800">성공</div>
                                        </div>
                                        <div className="bg-red-50 rounded-lg p-4">
                                            <div className="text-2xl font-bold text-red-600">
                                                {createResult.summary?.errors || 0}
                                            </div>
                                            <div className="text-sm text-red-800">실패</div>
                                        </div>
                                        <div className="bg-gray-50 rounded-lg p-4">
                                            <div className="text-2xl font-bold text-gray-900">
                                                {createResult.summary?.total || 0}
                                            </div>
                                            <div className="text-sm text-gray-600">전체</div>
                                        </div>
                                    </div>
                                )}
                            </div>

                            <Button onClick={onSuccess} className="w-full">
                                확인
                            </Button>
                        </div>
                    )}
                </div>
            </Card>
        </div>
    );
};