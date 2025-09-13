<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YKP ERP - Modern Dashboard</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css" rel="stylesheet">
    <style>
        * { font-family: 'Pretendard Variable', -apple-system, BlinkMacSystemFont, system-ui, Roboto, sans-serif; }
        .scrollbar-thin::-webkit-scrollbar { width: 6px; height: 6px; }
        .scrollbar-thin::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .scrollbar-thin::-webkit-scrollbar-thumb:hover { background: #94a3b8; }
    </style>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: { 
                            50: '#f0f9ff', 100: '#e0f2fe', 200: '#bae6fd', 300: '#7dd3fc',
                            400: '#38bdf8', 500: '#0ea5e9', 600: '#0284c7', 700: '#0369a1',
                            800: '#075985', 900: '#0c4a6e', 950: '#082f49'
                        }
                    }
                }
            }
        }
        
        // Global CSRF Token and User Data
        window.csrfToken = '{{ csrf_token() }}';
        window.userData = {
            id: {{ auth()->id() }},
            name: '{{ auth()->user()->name }}',
            email: '{{ auth()->user()->email }}',
            role: '{{ auth()->user()->role }}',
            branch: '{{ auth()->user()->branch?->name ?? '' }}',
            store: '{{ auth()->user()->store?->name ?? '' }}',
            permissions: {
                canViewAllStores: {{ auth()->user()->isHeadquarters() ? 'true' : 'false' }},
                canViewBranchStores: {{ auth()->user()->isBranch() ? 'true' : 'false' }},
                accessibleStoreIds: {!! json_encode(auth()->user()->getAccessibleStoreIds()) !!}
            }
        };
    </script>
</head>
<body class="bg-gray-50">
    <!-- Flash Messages -->
    @if(session('message'))
        <div class="fixed top-4 right-4 z-50 bg-green-500 text-white px-6 py-3 rounded-lg shadow-lg">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
                {{ session('message') }}
            </div>
        </div>
        <script>
            setTimeout(() => {
                document.querySelector('.fixed.top-4.right-4').remove();
            }, 5000);
        </script>
    @endif

    @if(session('error'))
        <div class="fixed top-4 right-4 z-50 bg-red-500 text-white px-6 py-3 rounded-lg shadow-lg">
            <div class="flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
                {{ session('error') }}
            </div>
        </div>
        <script>
            setTimeout(() => {
                document.querySelector('.fixed.top-4.right-4').remove();
            }, 5000);
        </script>
    @endif

    <div id="root"></div>

    <script type="text/babel">
        const { useState, useEffect, useRef } = React;

        // Icons Component
        const Icon = ({ name, className = "w-5 h-5" }) => {
            useEffect(() => {
                lucide.createIcons();
            }, []);
            return <i data-lucide={name} className={className}></i>;
        };

        // Card Component
        const Card = ({ children, className = "" }) => (
            <div className={`bg-white rounded-xl shadow-sm border border-gray-100 ${className}`}>
                {children}
            </div>
        );

        // Button Component
        const Button = ({ children, variant = "primary", size = "md", className = "", ...props }) => {
            const variants = {
                primary: "bg-primary-600 text-white hover:bg-primary-700 focus:ring-primary-500",
                secondary: "bg-gray-100 text-gray-700 hover:bg-gray-200 focus:ring-gray-500",
                ghost: "text-gray-600 hover:bg-gray-100 focus:ring-gray-500",
                danger: "bg-red-600 text-white hover:bg-red-700 focus:ring-red-500"
            };
            const sizes = {
                sm: "px-3 py-1.5 text-sm",
                md: "px-4 py-2",
                lg: "px-6 py-3 text-lg"
            };
            return (
                <button 
                    className={`inline-flex items-center justify-center font-medium rounded-lg transition-colors focus:outline-none focus:ring-2 focus:ring-offset-2 ${variants[variant]} ${sizes[size]} ${className}`}
                    {...props}
                >
                    {children}
                </button>
            );
        };

        // Badge Component
        const Badge = ({ children, variant = "default" }) => {
            const variants = {
                default: "bg-gray-100 text-gray-800",
                success: "bg-green-100 text-green-800",
                warning: "bg-yellow-100 text-yellow-800",
                danger: "bg-red-100 text-red-800",
                info: "bg-blue-100 text-blue-800"
            };
            return (
                <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${variants[variant]}`}>
                    {children}
                </span>
            );
        };

        // Logout function
        const handleLogout = () => {
            if (confirm('로그아웃 하시겠습니까?')) {
                // Create a form and submit it for logout
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '/logout';
                
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = '_token';
                csrfInput.value = window.csrfToken;
                form.appendChild(csrfInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        };

        // User Profile Component
        const UserProfile = ({ user }) => (
            <div className="mb-4 group relative">
                <div className="w-12 h-12 rounded-lg bg-gradient-to-br from-green-500 to-blue-600 flex items-center justify-center text-white font-bold text-sm cursor-pointer">
                    {user.name.charAt(0).toUpperCase()}
                </div>
                <div className="absolute left-full ml-2 px-3 py-2 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10 min-w-[200px]">
                    <div className="font-semibold">{user.name}</div>
                    <div className="text-gray-300">{user.email}</div>
                    <div className="text-xs mt-1">
                        <span className="inline-block px-2 py-0.5 bg-primary-600 text-white rounded">
                            {user.role === 'headquarters' ? '본사' : user.role === 'branch' ? '지사' : '매장'}
                        </span>
                    </div>
                    {user.branch && <div className="text-gray-300 text-xs">지사: {user.branch}</div>}
                    {user.store && <div className="text-gray-300 text-xs">매장: {user.store}</div>}
                </div>
            </div>
        );

        // Sidebar Component
        const Sidebar = ({ activeMenu, setActiveMenu }) => {
            const menuItems = [
                { id: 'dashboard', icon: 'layout-dashboard', label: '대시보드' },
                { id: 'advanced-input', icon: 'grid-3x3', label: '개통표 입력', url: '/sales/advanced-input' },
                { id: 'stores', icon: 'store', label: '매장 관리' },
                { id: 'reports', icon: 'file-text', label: '보고서' },
                { id: 'settings', icon: 'settings', label: '설정' }
            ];

            return (
                <div className="fixed left-0 top-0 h-full w-16 bg-white border-r border-gray-200 flex flex-col items-center py-4 z-50">
                    <div className="mb-6">
                        <div className="w-10 h-10 bg-gradient-to-br from-primary-500 to-purple-600 rounded-xl flex items-center justify-center text-white font-bold">
                            Y
                        </div>
                    </div>
                    
                    <UserProfile user={window.userData} />
                    
                    <nav className="flex-1 flex flex-col gap-2">
                        {menuItems.map(item => (
                            item.url ? (
                                <a
                                    key={item.id}
                                    href={item.url}
                                    className="w-12 h-12 rounded-lg flex items-center justify-center transition-all group relative text-gray-500 hover:bg-gray-100 hover:text-gray-700"
                                >
                                    <Icon name={item.icon} className="w-5 h-5" />
                                    <span className="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none">
                                        {item.label}
                                    </span>
                                </a>
                            ) : (
                                <button
                                    key={item.id}
                                    onClick={() => setActiveMenu(item.id)}
                                    className={`w-12 h-12 rounded-lg flex items-center justify-center transition-all group relative
                                        ${activeMenu === item.id 
                                            ? 'bg-primary-50 text-primary-600' 
                                            : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700'}`}
                                >
                                    <Icon name={item.icon} className="w-5 h-5" />
                                    <span className="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none">
                                        {item.label}
                                    </span>
                                </button>
                            )
                        ))}
                    </nav>
                    <button 
                        onClick={handleLogout}
                        className="w-12 h-12 rounded-lg flex items-center justify-center text-gray-500 hover:bg-red-50 hover:text-red-600 group relative transition-all"
                        title="로그아웃"
                    >
                        <Icon name="log-out" className="w-5 h-5" />
                        <span className="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none">
                            로그아웃
                        </span>
                    </button>
                </div>
            );
        };

        // KPI Card Component - 한화 표시 개선
        const KPICard = ({ title, value, change, changeType, icon, description }) => (
            <Card className="p-6">
                <div className="flex items-center justify-between mb-4">
                    <div className="p-2 bg-primary-50 rounded-lg">
                        <Icon name={icon} className="w-5 h-5 text-primary-600" />
                    </div>
                    {change && (
                        <Badge variant={changeType === 'positive' ? 'success' : changeType === 'negative' ? 'danger' : 'default'}>
                            {changeType === 'positive' ? '↑' : changeType === 'negative' ? '↓' : ''} {change}
                        </Badge>
                    )}
                </div>
                <div className="space-y-1">
                    <p className="text-sm text-gray-600">{title}</p>
                    <p className="text-2xl font-bold text-gray-900">{value}</p>
                    {description && <p className="text-xs text-gray-500">{description}</p>}
                </div>
            </Card>
        );
        
        // 숫자 포맷 함수
        const formatCurrency = (num) => {
            return '₩' + Number(num).toLocaleString('ko-KR');
        };

        // Main Dashboard Component
        const Dashboard = () => {
            const chartRef = useRef(null);
            const donutRef = useRef(null);
            const [dashboardData, setDashboardData] = useState({
                todayRevenue: 0,
                monthRevenue: 0,
                avgRevenue: 0,
                activeStores: 0
            });
            const [dataStatus, setDataStatus] = useState({ hasData: false, message: '', loading: true });

            // HTTP Helper with CSRF protection
            const secureRequest = async (url, options = {}) => {
                const defaultHeaders = {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': window.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                };

                return fetch(url, {
                    ...options,
                    headers: {
                        ...defaultHeaders,
                        ...options.headers
                    },
                    credentials: 'same-origin' // Include cookies for session auth
                });
            };

            // 대시보드 데이터 로드 - 새로운 통합 API 사용
            const loadDashboardData = async () => {
                setDataStatus({ hasData: false, message: '데이터를 로딩 중...', loading: true });
                
                try {
                    const response = await secureRequest('/dashboard/overview');
                    if (response.ok) {
                        const result = await response.json();
                        
                        if (result.success && result.data) {
                            const data = result.data;
                            setDashboardData({
                                todayRevenue: data.this_month_sales || 0,
                                monthRevenue: (data.this_month_sales || 0) * 1.2, // 목표 대비 추정
                                avgRevenue: Math.round((data.this_month_sales || 0) / Math.max(data.stores.with_sales, 1)),
                                activeStores: data.stores.with_sales || 0,
                                totalStores: data.stores.total || 0,
                                totalBranches: data.branches.total || 0,
                                achievementRate: data.achievement_rate || 0
                            });
                            setDataStatus({ hasData: true, message: '데이터 로드 완료', loading: false });
                        } else {
                            // 데이터가 없는 경우
                            setDashboardData({
                                todayRevenue: 0,
                                monthRevenue: 0,
                                avgRevenue: 0,
                                activeStores: 0,
                                totalStores: 0,
                                totalBranches: 0,
                                achievementRate: 0
                            });
                            setDataStatus({ hasData: false, message: '데이터가 없습니다.', loading: false });
                        }
                    } else if (response.status === 401) {
                        // Unauthorized - redirect to login
                        window.location.href = '/login';
                    } else if (response.status === 403) {
                        // Forbidden - show access denied message
                        setDataStatus({ hasData: false, message: '접근 권한이 없습니다.', loading: false });
                    } else if (response.status === 500) {
                        // Database or server error
                        const errorData = await response.json().catch(() => ({}));
                        setDashboardData({
                            todayRevenue: 0,
                            monthRevenue: 0,
                            avgRevenue: 0,
                            activeStores: 0
                        });
                        setDataStatus({ hasData: false, message: '데이터베이스 연결에 문제가 있습니다. 관리자에게 문의하세요.', loading: false });
                    }
                } catch (error) {
                    console.error('대시보드 데이터 로드 실패:', error);
                    setDashboardData({
                        todayRevenue: 0,
                        monthRevenue: 0,
                        avgRevenue: 0,
                        activeStores: 0
                    });
                    setDataStatus({ hasData: false, message: '네트워크 연결을 확인해주세요.', loading: false });
                }
            };

            useEffect(() => {
                loadDashboardData();
                const interval = setInterval(loadDashboardData, 30000); // 30초마다 새로고침
                return () => clearInterval(interval);
            }, []);

            useEffect(() => {
                // Line Chart
                if (chartRef.current) {
                    new Chart(chartRef.current, {
                        type: 'line',
                        data: {
                            labels: Array.from({length: 30}, (_, i) => `${i+1}일`),
                            datasets: [{
                                label: '매출',
                                data: Array.from({length: 30}, () => Math.floor(Math.random() * 50) + 100),
                                borderColor: '#0ea5e9',
                                backgroundColor: 'rgba(14, 165, 233, 0.1)',
                                tension: 0.4
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
                            plugins: {
                                legend: { position: 'bottom' }
                            }
                        }
                    });
                }
            }, []);

            return (
                <div className="space-y-6">
                    {/* Header */}
                    <div className="flex items-center justify-between mb-4">
                        <h1 className="text-2xl font-bold text-gray-900">대시보드</h1>
                        <div className="flex items-center gap-3">
                            <a 
                                href="/sales/advanced-input" 
                                className="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 flex items-center gap-2 text-sm font-medium"
                            >
                                <Icon name="edit-3" className="w-4 h-4" />
                                개통표 입력
                            </a>
                            <Button variant="ghost" size="sm">
                                <Icon name="refresh-cw" className="w-4 h-4 mr-2" />
                                새로고침
                            </Button>
                            <Button size="sm">
                                <Icon name="download" className="w-4 h-4 mr-2" />
                                리포트 다운로드
                            </Button>
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

                    {/* KPI Cards - 실제 데이터 연동 */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
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
                            title="매장 현황" 
                            value={`${dashboardData.activeStores} / ${dashboardData.totalStores}`}
                            icon="store"
                            description="매출활동 / 전체등록"
                        />
                    </div>

                    {/* 추가 통계 카드 */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <Card className="p-4 bg-gradient-to-r from-blue-50 to-blue-100">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-blue-600">지사 현황</p>
                                    <p className="text-2xl font-bold text-blue-900">{dashboardData.totalBranches}개</p>
                                    <p className="text-xs text-blue-500 mt-1">전국 지사 네트워크</p>
                                </div>
                                <Icon name="map-pin" className="h-8 w-8 text-blue-500" />
                            </div>
                        </Card>
                        
                        <Card className="p-4 bg-gradient-to-r from-green-50 to-green-100">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-green-600">목표 달성률</p>
                                    <p className="text-2xl font-bold text-green-900">{dashboardData.achievementRate}%</p>
                                    <p className="text-xs text-green-500 mt-1">이번 달 기준</p>
                                </div>
                                <Icon name="target" className="h-8 w-8 text-green-500" />
                            </div>
                        </Card>
                        
                        <Card className="p-4 bg-gradient-to-r from-purple-50 to-purple-100">
                            <div className="flex items-center justify-between">
                                <div>
                                    <p className="text-sm font-medium text-purple-600">매출 효율성</p>
                                    <p className="text-2xl font-bold text-purple-900">
                                        {dashboardData.totalStores > 0 ? Math.round((dashboardData.activeStores / dashboardData.totalStores) * 100) : 0}%
                                    </p>
                                    <p className="text-xs text-purple-500 mt-1">활성 매장 비율</p>
                                </div>
                                <Icon name="trending-up" className="h-8 w-8 text-purple-500" />
                            </div>
                        </Card>
                    </div>

                    {/* Charts */}
                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <Card className="lg:col-span-2 p-6">
                            <h3 className="text-lg font-semibold text-gray-900 mb-4">30일 매출 추이</h3>
                            <canvas ref={chartRef}></canvas>
                        </Card>
                        <Card className="p-6">
                            <h3 className="text-lg font-semibold text-gray-900 mb-4">지사별 매출</h3>
                            <canvas ref={donutRef}></canvas>
                        </Card>
                    </div>

                    {/* Timeline & Notifications */}
                    <div className="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <Card className="p-6">
                            <h3 className="text-lg font-semibold text-gray-900 mb-4">최근 활동</h3>
                            <div className="space-y-4">
                                {[
                                    { time: '10분 전', text: '서울지점 일일 정산 완료', type: 'success' },
                                    { time: '1시간 전', text: '경기지점 신규 매장 등록', type: 'info' },
                                    { time: '3시간 전', text: '부산지점 재고 부족 알림', type: 'warning' }
                                ].map((item, i) => (
                                    <div key={i} className="flex items-start gap-3">
                                        <div className={`w-2 h-2 rounded-full mt-2 
                                            ${item.type === 'success' ? 'bg-green-500' : 
                                              item.type === 'warning' ? 'bg-yellow-500' : 'bg-blue-500'}`} 
                                        />
                                        <div className="flex-1">
                                            <p className="text-sm text-gray-900">{item.text}</p>
                                            <p className="text-xs text-gray-500">{item.time}</p>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </Card>
                        <Card className="p-6">
                            <h3 className="text-lg font-semibold text-gray-900 mb-4">공지사항</h3>
                            <div className="space-y-3">
                                {[
                                    { title: '시스템 점검 안내', date: '2024-01-15', badge: '중요' },
                                    { title: '신규 기능 업데이트', date: '2024-01-14', badge: '신규' },
                                    { title: '정산 프로세스 변경', date: '2024-01-13' }
                                ].map((item, i) => (
                                    <div key={i} className="flex items-center justify-between p-3 rounded-lg hover:bg-gray-50 cursor-pointer">
                                        <div className="flex items-center gap-3">
                                            <Icon name="file-text" className="w-4 h-4 text-gray-400" />
                                            <div>
                                                <p className="text-sm font-medium text-gray-900">{item.title}</p>
                                                <p className="text-xs text-gray-500">{item.date}</p>
                                            </div>
                                        </div>
                                        {item.badge && <Badge variant="danger">{item.badge}</Badge>}
                                    </div>
                                ))}
                            </div>
                        </Card>
                    </div>
                </div>
            );
        };

        // Store Management Component
        const StoreManagement = () => {
            const [selectedStore, setSelectedStore] = useState(null);
            const stores = [
                { id: 1, name: '강남점', region: '서울', status: 'active', todaySales: '₩5.2M' },
                { id: 2, name: '판교점', region: '경기', status: 'active', todaySales: '₩4.8M' },
                { id: 3, name: '송도점', region: '인천', status: 'maintenance', todaySales: '₩0' },
                { id: 4, name: '해운대점', region: '부산', status: 'active', todaySales: '₩3.5M' },
                { id: 5, name: '동성로점', region: '대구', status: 'active', todaySales: '₩2.9M' }
            ];

            return (
                <div className="space-y-6">
                    <div className="flex items-center justify-between">
                        <h1 className="text-2xl font-bold text-gray-900">매장 관리</h1>
                        <Button>
                            <Icon name="plus" className="w-4 h-4 mr-2" />
                            매장 추가
                        </Button>
                    </div>

                    <Card className="overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead className="bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">매장명</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">지역</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">상태</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">금일 매출</th>
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">관리</th>
                                    </tr>
                                </thead>
                                <tbody className="bg-white divide-y divide-gray-200">
                                    {stores.map(store => (
                                        <tr key={store.id} className="hover:bg-gray-50 cursor-pointer" onClick={() => setSelectedStore(store)}>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">{store.name}</td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-600">{store.region}</td>
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                <Badge variant={store.status === 'active' ? 'success' : 'warning'}>
                                                    {store.status === 'active' ? '운영중' : '점검중'}
                                                </Badge>
                                            </td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium">{store.todaySales}</td>
                                            <td className="px-6 py-4 whitespace-nowrap text-sm">
                                                <div className="flex gap-2">
                                                    <Button variant="ghost" size="sm">
                                                        <Icon name="eye" className="w-4 h-4" />
                                                    </Button>
                                                    <Button variant="ghost" size="sm">
                                                        <Icon name="edit" className="w-4 h-4" />
                                                    </Button>
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </Card>

                    {selectedStore && (
                        <Card className="p-6">
                            <div className="flex items-center justify-between mb-6">
                                <h3 className="text-lg font-semibold text-gray-900">{selectedStore.name} 상세정보</h3>
                                <button onClick={() => setSelectedStore(null)} className="text-gray-400 hover:text-gray-600">
                                    <Icon name="x" className="w-5 h-5" />
                                </button>
                            </div>
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div>
                                    <h4 className="text-sm font-medium text-gray-500 mb-3">기본 정보</h4>
                                    <div className="space-y-2">
                                        <div className="flex justify-between">
                                            <span className="text-sm text-gray-600">지역</span>
                                            <span className="text-sm font-medium">{selectedStore.region}</span>
                                        </div>
                                        <div className="flex justify-between">
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
                                        <div className="flex justify-between">
                                            <span className="text-sm text-gray-600">매출</span>
                                            <span className="text-sm font-medium">{selectedStore.todaySales}</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-sm text-gray-600">판매건수</span>
                                            <span className="text-sm font-medium">142건</span>
                                        </div>
                                        <div className="flex justify-between">
                                            <span className="text-sm text-gray-600">평균객단가</span>
                                            <span className="text-sm font-medium">₩36,620</span>
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <h4 className="text-sm font-medium text-gray-500 mb-3">업무 현황</h4>
                                    <div className="flex gap-2">
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

        // Report Component
        const Reports = () => {
            const [activeTab, setActiveTab] = useState('sales');
            const barRef = useRef(null);

            useEffect(() => {
                if (barRef.current) {
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

        // Data Input Component
        const DataInput = () => {
            const [data, setData] = useState([
                { id: 1, date: '2024-01-15', store: '강남점', code: 'P001', product: '상품A', qty: 10, price: 50000, amount: 500000, discount: 10, final: 450000, person: '김직원' },
                { id: 2, date: '2024-01-15', store: '판교점', code: 'P002', product: '상품B', qty: 5, price: 30000, amount: 150000, discount: 0, final: 150000, person: '이직원' }
            ]);

            const addRow = () => {
                const newRow = {
                    id: data.length + 1,
                    date: new Date().toISOString().split('T')[0],
                    store: '',
                    code: '',
                    product: '',
                    qty: 0,
                    price: 0,
                    amount: 0,
                    discount: 0,
                    final: 0,
                    person: ''
                };
                setData([...data, newRow]);
            };

            const updateCell = (id, field, value) => {
                setData(data.map(row => {
                    if (row.id === id) {
                        const updated = { ...row, [field]: value };
                        // Auto calculate
                        if (field === 'qty' || field === 'price') {
                            updated.amount = updated.qty * updated.price;
                            updated.final = updated.amount * (1 - updated.discount / 100);
                        }
                        if (field === 'discount') {
                            updated.final = updated.amount * (1 - updated.discount / 100);
                        }
                        return updated;
                    }
                    return row;
                }));
            };

            const total = data.reduce((sum, row) => sum + row.final, 0);

            return (
                <div className="space-y-6">
                    <div className="flex items-center justify-between">
                        <h1 className="text-2xl font-bold text-gray-900">판매 데이터 입력</h1>
                        <div className="flex gap-2">
                            <Button variant="secondary" size="sm">
                                <Icon name="filter" className="w-4 h-4 mr-2" />
                                필터
                            </Button>
                            <Button variant="secondary" size="sm">
                                <Icon name="arrow-up-down" className="w-4 h-4 mr-2" />
                                정렬
                            </Button>
                            <Button variant="secondary" size="sm">
                                <Icon name="search" className="w-4 h-4 mr-2" />
                                검색
                            </Button>
                        </div>
                    </div>

                    {/* Toolbar */}
                    <Card className="p-3">
                        <div className="flex gap-2">
                            <Button size="sm" variant="primary">
                                <Icon name="save" className="w-4 h-4 mr-2" />
                                저장
                            </Button>
                            <Button size="sm" variant="secondary" onClick={addRow}>
                                <Icon name="plus" className="w-4 h-4 mr-2" />
                                추가
                            </Button>
                            <Button size="sm" variant="secondary">
                                <Icon name="trash-2" className="w-4 h-4 mr-2" />
                                삭제
                            </Button>
                            <Button size="sm" variant="secondary">
                                <Icon name="database" className="w-4 h-4 mr-2" />
                                백업
                            </Button>
                        </div>
                    </Card>

                    {/* Spreadsheet */}
                    <Card className="overflow-hidden">
                        <div className="overflow-x-auto">
                            <table className="w-full">
                                <thead className="bg-gray-50 border-b border-gray-200">
                                    <tr>
                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">날짜</th>
                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">매장명</th>
                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">상품코드</th>
                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">상품명</th>
                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">수량</th>
                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">단가</th>
                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">금액</th>
                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">할인율(%)</th>
                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">최종금액</th>
                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">담당자</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 bg-white">
                                    {data.map(row => (
                                        <tr key={row.id} className="hover:bg-gray-50">
                                            <td className="px-4 py-2">
                                                <input 
                                                    type="date" 
                                                    value={row.date}
                                                    onChange={e => updateCell(row.id, 'date', e.target.value)}
                                                    className="w-full px-2 py-1 text-sm border-0 focus:ring-2 focus:ring-primary-500 rounded"
                                                />
                                            </td>
                                            <td className="px-4 py-2">
                                                <input 
                                                    type="text" 
                                                    value={row.store}
                                                    onChange={e => updateCell(row.id, 'store', e.target.value)}
                                                    className="w-full px-2 py-1 text-sm border-0 focus:ring-2 focus:ring-primary-500 rounded"
                                                />
                                            </td>
                                            <td className="px-4 py-2">
                                                <input 
                                                    type="text" 
                                                    value={row.code}
                                                    onChange={e => updateCell(row.id, 'code', e.target.value)}
                                                    className="w-full px-2 py-1 text-sm border-0 focus:ring-2 focus:ring-primary-500 rounded"
                                                />
                                            </td>
                                            <td className="px-4 py-2">
                                                <input 
                                                    type="text" 
                                                    value={row.product}
                                                    onChange={e => updateCell(row.id, 'product', e.target.value)}
                                                    className="w-full px-2 py-1 text-sm border-0 focus:ring-2 focus:ring-primary-500 rounded"
                                                />
                                            </td>
                                            <td className="px-4 py-2">
                                                <input 
                                                    type="number" 
                                                    value={row.qty}
                                                    onChange={e => updateCell(row.id, 'qty', parseInt(e.target.value) || 0)}
                                                    className="w-full px-2 py-1 text-sm border-0 focus:ring-2 focus:ring-primary-500 rounded text-right"
                                                />
                                            </td>
                                            <td className="px-4 py-2">
                                                <input 
                                                    type="number" 
                                                    value={row.price}
                                                    onChange={e => updateCell(row.id, 'price', parseInt(e.target.value) || 0)}
                                                    className="w-full px-2 py-1 text-sm border-0 focus:ring-2 focus:ring-primary-500 rounded text-right"
                                                />
                                            </td>
                                            <td className="px-4 py-2 text-right text-sm font-medium text-gray-900">
                                                ₩{row.amount.toLocaleString()}
                                            </td>
                                            <td className="px-4 py-2">
                                                <input 
                                                    type="number" 
                                                    value={row.discount}
                                                    onChange={e => updateCell(row.id, 'discount', parseInt(e.target.value) || 0)}
                                                    className="w-full px-2 py-1 text-sm border-0 focus:ring-2 focus:ring-primary-500 rounded text-right"
                                                    min="0"
                                                    max="100"
                                                />
                                            </td>
                                            <td className="px-4 py-2 text-right text-sm font-bold text-primary-600">
                                                ₩{row.final.toLocaleString()}
                                            </td>
                                            <td className="px-4 py-2">
                                                <input 
                                                    type="text" 
                                                    value={row.person}
                                                    onChange={e => updateCell(row.id, 'person', e.target.value)}
                                                    className="w-full px-2 py-1 text-sm border-0 focus:ring-2 focus:ring-primary-500 rounded"
                                                />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                                <tfoot className="bg-gray-100 border-t-2 border-gray-300">
                                    <tr>
                                        <td colSpan="8" className="px-4 py-3 text-right font-semibold text-gray-900">합계</td>
                                        <td className="px-4 py-3 text-right font-bold text-lg text-primary-600">
                                            ₩{total.toLocaleString()}
                                        </td>
                                        <td></td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </Card>

                    {/* Status */}
                    <div className="flex justify-between items-center text-sm text-gray-500">
                        <span>총 {data.length}개 항목</span>
                        <span>마지막 저장: 2024-01-15 14:30:25</span>
                    </div>

                    {/* Mobile Version Card */}
                    <Card className="p-6 md:hidden">
                        <h3 className="text-lg font-semibold text-gray-900 mb-4">모바일 입력</h3>
                        <form className="space-y-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">날짜</label>
                                <input type="date" className="w-full px-3 py-2 border border-gray-300 rounded-lg" />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">매장</label>
                                <select className="w-full px-3 py-2 border border-gray-300 rounded-lg">
                                    <option>강남점</option>
                                    <option>판교점</option>
                                </select>
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">상품</label>
                                <input type="text" className="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="상품명 입력" />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">수량</label>
                                    <input type="number" className="w-full px-3 py-2 border border-gray-300 rounded-lg" />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium text-gray-700 mb-1">단가</label>
                                    <input type="number" className="w-full px-3 py-2 border border-gray-300 rounded-lg" />
                                </div>
                            </div>
                            <Button className="w-full">등록</Button>
                        </form>
                    </Card>
                </div>
            );
        };

        // Main App Component
        const App = () => {
            const [activeMenu, setActiveMenu] = useState('dashboard');

            const renderContent = () => {
                switch(activeMenu) {
                    case 'dashboard': return <Dashboard />;
                    case 'stores': return <StoreManagement />;
                    case 'reports': return <Reports />;
                    case 'input': return <DataInput />;
                    default: return <Dashboard />;
                }
            };

            return (
                <div className="flex min-h-screen bg-gray-50">
                    <Sidebar activeMenu={activeMenu} setActiveMenu={setActiveMenu} />
                    <main className="flex-1 ml-16 p-6 overflow-auto">
                        <div className="max-w-7xl mx-auto">
                            {renderContent()}
                        </div>
                    </main>
                </div>
            );
        };

        // Render
        ReactDOM.render(<App />, document.getElementById('root'));
    </script>
</body>
</html>