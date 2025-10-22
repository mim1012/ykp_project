import React, { useState, useEffect } from 'react';
import { Card, Button, Badge, Icon, ResponsiveTable } from '../ui';

export const StoreManagement = () => {
    const [selectedStore, setSelectedStore] = useState(null);
    const [stores, setStores] = useState([]);
    const [branches, setBranches] = useState([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);
    const [viewMode, setViewMode] = useState('branch'); // 'branch' | 'table'
    const [showBulkModal, setShowBulkModal] = useState(false);
    const [bulkType, setBulkType] = useState(null); // 'branch' | 'store'

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

                    setBranches(Object.values(grouped));
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
            {/* 헤더 */}
            <div className="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                <h1 className="text-xl sm:text-2xl font-bold text-gray-900">매장 관리</h1>
                <div className="flex gap-2 flex-wrap">
                    {/* 보기 모드 전환 */}
                    <div className="flex border rounded-lg overflow-hidden">
                        <button
                            onClick={() => setViewMode('branch')}
                            className={`px-4 py-2 text-sm font-medium ${
                                viewMode === 'branch'
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-white text-gray-700 hover:bg-gray-50'
                            }`}
                        >
                            <Icon name="grid" className="w-4 h-4 mr-2 inline" />
                            지사별
                        </button>
                        <button
                            onClick={() => setViewMode('table')}
                            className={`px-4 py-2 text-sm font-medium border-l ${
                                viewMode === 'table'
                                    ? 'bg-blue-600 text-white'
                                    : 'bg-white text-gray-700 hover:bg-gray-50'
                            }`}
                        >
                            <Icon name="list" className="w-4 h-4 mr-2 inline" />
                            목록
                        </button>
                    </div>

                    {/* 대량 생성 버튼들 */}
                    <Button
                        variant="outline"
                        onClick={() => {
                            setBulkType('branch');
                            setShowBulkModal(true);
                        }}
                    >
                        <Icon name="upload" className="w-4 h-4 mr-2" />
                        지사 대량 생성
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() => {
                            setBulkType('store');
                            setShowBulkModal(true);
                        }}
                    >
                        <Icon name="upload" className="w-4 h-4 mr-2" />
                        매장 대량 생성
                    </Button>
                    <Button>
                        <Icon name="plus" className="w-4 h-4 mr-2" />
                        매장 추가
                    </Button>
                </div>
            </div>

            {/* 지사별 카드 뷰 */}
            {viewMode === 'branch' && (
                <div className="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
                    {branches.map((branch) => (
                        <Card key={branch.name} className="p-6">
                            <div className="flex items-center justify-between mb-4">
                                <h3 className="text-lg font-semibold text-gray-900">{branch.name}</h3>
                                <Badge variant="info">{branch.stores.length}개 매장</Badge>
                            </div>
                            <div className="space-y-3">
                                {branch.stores.map((store) => (
                                    <div
                                        key={store.id}
                                        className="flex items-center justify-between p-3 bg-gray-50 rounded-lg hover:bg-gray-100 cursor-pointer transition"
                                        onClick={() => setSelectedStore(store)}
                                    >
                                        <div className="flex-1">
                                            <div className="font-medium text-gray-900">{store.name}</div>
                                            <div className="text-sm text-gray-500">{store.code}</div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Badge variant={store.status === 'active' ? 'success' : 'warning'} className="text-xs">
                                                {store.status === 'active' ? '운영중' : '점검중'}
                                            </Badge>
                                            <Icon name="chevron-right" className="w-4 h-4 text-gray-400" />
                                        </div>
                                    </div>
                                ))}
                                {branch.stores.length === 0 && (
                                    <div className="text-center py-8 text-gray-500">
                                        등록된 매장이 없습니다
                                    </div>
                                )}
                            </div>
                        </Card>
                    ))}
                </div>
            )}

            {/* 테이블 뷰 */}
            {viewMode === 'table' && (
                <ResponsiveTable
                    columns={columns}
                    data={stores}
                    actions={actions}
                    mobileCardView={true}
                    className="space-y-4"
                />
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
                            <div className="bg-blue-50 border border-blue-200 rounded-lg p-4">
                                <h3 className="font-medium text-blue-900 mb-2">📋 업로드 안내</h3>
                                <ul className="text-sm text-blue-800 space-y-1">
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
                                    <div className="border-2 border-dashed border-gray-300 rounded-lg p-8 text-center hover:border-blue-500 cursor-pointer transition">
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
                                    <div className="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
                                    <span className="ml-3 text-gray-600">파일 검증 중...</span>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Step 2: 검증 결과 */}
                    {step === 2 && validationResult && (
                        <div className="space-y-6">
                            <div className="bg-green-50 border border-green-200 rounded-lg p-4">
                                <h3 className="font-medium text-green-900 mb-2">✅ 검증 완료</h3>
                                <p className="text-sm text-green-800">
                                    총 {validationResult.total_rows}개 행이 확인되었습니다.
                                </p>
                            </div>

                            {validationResult.validation_errors && validationResult.validation_errors.length > 0 && (
                                <div className="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                                    <h3 className="font-medium text-yellow-900 mb-2">⚠️ 오류 발견</h3>
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
                            <div className="animate-spin rounded-full h-16 w-16 border-b-2 border-blue-600 mb-4"></div>
                            <p className="text-lg font-medium text-gray-900">생성 중...</p>
                            <p className="text-sm text-gray-600 mt-2">잠시만 기다려주세요</p>
                        </div>
                    )}

                    {/* Step 4: 완료 */}
                    {step === 4 && createResult && (
                        <div className="space-y-6">
                            <div className="text-center py-6">
                                <div className="text-6xl mb-4">🎉</div>
                                <h3 className="text-2xl font-bold text-gray-900 mb-2">생성 완료!</h3>
                                {createResult.status === 'queued' ? (
                                    <p className="text-gray-600">
                                        백그라운드에서 처리 중입니다.<br/>
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
                                        <div className="bg-blue-50 rounded-lg p-4">
                                            <div className="text-2xl font-bold text-blue-600">
                                                {createResult.summary?.total || 0}
                                            </div>
                                            <div className="text-sm text-blue-800">전체</div>
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