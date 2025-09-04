<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YKP ERP 대시보드 - {{ auth()->user()->name }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css" rel="stylesheet">
    <style>
        /* 기존 premium-dashboard 스타일 재사용 */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Malgun Gothic', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8fafc;
            color: #333;
        }
        
        /* 사이드바 - 권한별 메뉴 표시 */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 60px;
            height: 100vh;
            background: #6c5ce7;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 20px 0;
            z-index: 1000;
        }
        .sidebar-icon {
            width: 35px;
            height: 35px;
            margin: 10px 0;
            background: rgba(255,255,255,0.2);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            cursor: pointer;
            transition: all 0.2s;
        }
        .sidebar-icon:hover {
            background: rgba(255,255,255,0.3);
        }
        .sidebar-icon.active {
            background: white;
            color: #6c5ce7;
        }
        .sidebar-icon.disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        
        /* 툴팁 */
        .tooltip {
            position: relative;
        }
        .tooltip .tooltip-text {
            visibility: hidden;
            width: 140px;
            background-color: #2d3748;
            color: white;
            text-align: center;
            border-radius: 6px;
            padding: 8px 12px;
            position: absolute;
            z-index: 1001;
            left: 100%;
            top: 50%;
            transform: translateY(-50%);
            margin-left: 15px;
            opacity: 0;
            transition: opacity 0.3s ease;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
        }
        .tooltip .tooltip-text::before {
            content: "";
            position: absolute;
            top: 50%;
            right: 100%;
            transform: translateY(-50%);
            border: 6px solid transparent;
            border-right-color: #2d3748;
        }
        .tooltip:hover .tooltip-text {
            visibility: visible;
            opacity: 1;
        }
        
        /* 메인 컨텐츠 */
        .main-content {
            margin-left: 60px;
            padding: 0;
        }
        
        /* 헤더 - 사용자 정보 표시 */
        .header {
            background: white;
            padding: 15px 30px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            font-size: 24px;
            font-weight: 700;
            color: #2d3748;
        }
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .role-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .role-headquarters {
            background: #fecaca;
            color: #991b1b;
        }
        .role-branch {
            background: #bfdbfe;
            color: #1e40af;
        }
        .role-store {
            background: #bbf7d0;
            color: #065f46;
        }
        .role-developer {
            background: #f3e8ff;
            color: #7c3aed;
        }
        
        /* 권한 알림 */
        .permission-info {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            color: #1e40af;
            padding: 15px 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* 나머지 스타일은 premium-dashboard와 동일 */
        .dashboard-content {
            padding: 20px 25px;
            max-height: calc(100vh - 140px);
            overflow-y: auto;
        }
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 25px;
        }
        .kpi-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
            min-height: 120px;
        }
        .kpi-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 8px;
        }
        .kpi-title {
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
        }
        .kpi-value {
            font-size: 28px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 4px;
        }
        .kpi-subtitle {
            font-size: 12px;
            color: #94a3b8;
        }
        .kpi-trend {
            font-size: 12px;
            font-weight: 600;
            padding: 2px 6px;
            border-radius: 4px;
        }
        .trend-up {
            background: #dcfce7;
            color: #16a34a;
        }
        .trend-down {
            background: #fee2e2;
            color: #dc2626;
        }
    </style>
</head>
<body>
    <!-- 사이드바 - 권한별 메뉴 표시 -->
    <div class="sidebar">
        <div class="sidebar-icon active tooltip" onclick="showDashboard()">
            Y
            <span class="tooltip-text">메인 대시보드</span>
        </div>
        
        <!-- 모든 사용자 접근 가능 -->
        <div class="sidebar-icon tooltip" onclick="openSimpleInput()">
            📝
            <span class="tooltip-text">간단한 개통 입력</span>
        </div>
        
        <div class="sidebar-icon tooltip" onclick="openSettlement()">
            📊
            <span class="tooltip-text">정산표 시스템</span>
        </div>
        
        <div class="sidebar-icon tooltip" onclick="openDailyExpenses()">
            💳
            <span class="tooltip-text">일일지출 관리</span>
        </div>
        
        <!-- 지사장 이상만 접근 가능 -->
        @if(auth()->user()->isHeadquarters() || auth()->user()->isBranch())
        <div class="sidebar-icon tooltip" onclick="openManagement()">
            📋
            <span class="tooltip-text">완전한 판매관리</span>
        </div>
        
        <div class="sidebar-icon tooltip" onclick="openFixedExpenses()">
            💰
            <span class="tooltip-text">고정지출 관리</span>
        </div>
        
        <div class="sidebar-icon tooltip" onclick="openPayroll()">
            👥
            <span class="tooltip-text">직원급여 관리</span>
        </div>
        @else
        <!-- 매장 직원은 비활성화 -->
        <div class="sidebar-icon disabled tooltip">
            📋
            <span class="tooltip-text">권한 없음 (지사장 이상)</span>
        </div>
        
        <div class="sidebar-icon disabled tooltip">
            💰
            <span class="tooltip-text">권한 없음 (지사장 이상)</span>
        </div>
        
        <div class="sidebar-icon disabled tooltip">
            👥
            <span class="tooltip-text">권한 없음 (지사장 이상)</span>
        </div>
        @endif
        
        <!-- 개발자 전용 -->
        @if(auth()->user()->isDeveloper())
        <div class="sidebar-icon tooltip" onclick="openDeveloperTools()">
            🛠️
            <span class="tooltip-text">개발자 도구</span>
        </div>
        @endif
        
        <!-- 본사 + 개발자 -->
        @if(auth()->user()->isSuperUser())
        <div class="sidebar-icon tooltip" onclick="openAdmin()">
            ⚙️
            <span class="tooltip-text">관리자 패널</span>
        </div>
        @else
        <div class="sidebar-icon disabled tooltip">
            ⚙️
            <span class="tooltip-text">권한 없음 (본사/개발자만)</span>
        </div>
        @endif
    </div>

    <!-- 메인 컨텐츠 -->
    <div class="main-content">
        <!-- 헤더 - 사용자 정보 및 권한 표시 -->
        <div class="header">
            <h1>
                @if(auth()->user()->isDeveloper())
                    🛠️ 개발자 시스템 관리 대시보드
                @elseif(auth()->user()->isHeadquarters())
                    📊 본사 통합 대시보드
                @elseif(auth()->user()->isBranch())
                    🏢 {{ auth()->user()->branch->name }} 지사 대시보드
                @else
                    🏪 {{ auth()->user()->store->name }} 매장 대시보드
                @endif
            </h1>
            <div class="user-info">
                <span class="role-badge 
                    @if(auth()->user()->isDeveloper()) role-developer
                    @elseif(auth()->user()->isHeadquarters()) role-headquarters
                    @elseif(auth()->user()->isBranch()) role-branch  
                    @else role-store @endif">
                    {{ auth()->user()->role }}
                </span>
                <span>{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('auth.logout') }}" style="margin: 0;">
                    @csrf
                    <button type="submit" style="background: #ef4444; color: white; padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer;">
                        로그아웃
                    </button>
                </form>
            </div>
        </div>

        <!-- 권한 정보 알림 -->
        <div class="permission-info">
            <span>ℹ️</span>
            <div>
                <strong>데이터 접근 범위:</strong>
                @if(auth()->user()->isHeadquarters())
                    전체 지사 및 매장 데이터 ({{ auth()->user()->getAccessibleStoreIds() ? count(auth()->user()->getAccessibleStoreIds()) : '전체' }}개 매장)
                @elseif(auth()->user()->isBranch())
                    {{ auth()->user()->branch->name }} 지사 및 산하 매장 데이터 ({{ auth()->user()->getAccessibleStoreIds() ? count(auth()->user()->getAccessibleStoreIds()) : '전체' }}개 매장)
                @else
                    {{ auth()->user()->store->name }} 매장 데이터만
                @endif
            </div>
        </div>

        <!-- 대시보드 컨텐츠 - 권한별 데이터 -->
        <div class="dashboard-content">
            <!-- KPI 카드 - 권한별 데이터 범위 -->
            <div class="kpi-grid">
                <div class="kpi-card" id="todaySales">
                    <div class="kpi-header">
                        <span class="kpi-title">
                            @if(auth()->user()->isHeadquarters()) 전체 @elseif(auth()->user()->isBranch()) 지사 @else 매장 @endif
                            오늘 매출
                        </span>
                        <span class="kpi-trend trend-up">+ 12.5%</span>
                    </div>
                    <div class="kpi-value">₩0</div>
                    <div class="kpi-subtitle">전월 동일 요일 대비</div>
                </div>
                
                <div class="kpi-card" id="monthSales">
                    <div class="kpi-header">
                        <span class="kpi-title">
                            @if(auth()->user()->isHeadquarters()) 전체 @elseif(auth()->user()->isBranch()) 지사 @else 매장 @endif
                            이번 달 매출
                        </span>
                        <span class="kpi-trend trend-up">+ 8.2%</span>
                    </div>
                    <div class="kpi-value">₩0</div>
                    <div class="kpi-subtitle">전월 동기 대비</div>
                </div>
                
                <div class="kpi-card" id="activationCount">
                    <div class="kpi-header">
                        <span class="kpi-title">개통 건수</span>
                    </div>
                    <div class="kpi-value">0건</div>
                    <div class="kpi-subtitle">이번 달 누적</div>
                </div>
                
                <div class="kpi-card" id="accessibleStores">
                    <div class="kpi-header">
                        <span class="kpi-title">관리 범위</span>
                    </div>
                    <div class="kpi-value">
                        @if(auth()->user()->isHeadquarters())
                            전체 매장
                        @elseif(auth()->user()->isBranch())
                            {{ count(auth()->user()->getAccessibleStoreIds()) }}개 매장
                        @else
                            1개 매장
                        @endif
                    </div>
                    <div class="kpi-subtitle">접근 가능 범위</div>
                </div>
            </div>

            <!-- 권한별 안내 메시지 -->
            <div style="background: white; border-radius: 12px; padding: 24px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); margin-bottom: 20px;">
                <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 12px; color: #1f2937;">
                    🎯 {{ auth()->user()->name }}님의 업무 메뉴
                </h3>
                
                @if(auth()->user()->isDeveloper())
                <div style="color: #7c3aed; background: #f3e8ff; padding: 12px; border-radius: 8px;">
                    <strong>🛠️ 개발자 최고 권한</strong><br>
                    • 모든 데이터 무제한 접근<br>
                    • 시스템 로그 및 디버깅<br>
                    • 데이터베이스 직접 관리<br>
                    • 개발/테스트 도구 접근<br>
                    • 긴급 상황 대응 권한
                </div>
                @elseif(auth()->user()->isHeadquarters())
                <div style="color: #065f46; background: #ecfdf5; padding: 12px; border-radius: 8px;">
                    <strong>📊 본사 관리자 권한</strong><br>
                    • 전체 지사/매장 데이터 통합 관리<br>
                    • 모든 메뉴 접근 가능<br>
                    • 사용자 계정 관리<br>
                    • 시스템 설정 관리
                </div>
                @elseif(auth()->user()->isBranch())
                <div style="color: #1e40af; background: #eff6ff; padding: 12px; border-radius: 8px;">
                    <strong>🏢 지사 관리자 권한</strong><br>
                    • {{ auth()->user()->branch->name }} 지사 데이터 관리<br>
                    • 산하 매장 데이터 조회<br>
                    • 급여 및 지출 관리 가능<br>
                    • 매장 직원 관리
                </div>
                @else
                <div style="color: #166534; background: #f0fdf4; padding: 12px; border-radius: 8px;">
                    <strong>🏪 매장 직원 권한</strong><br>
                    • {{ auth()->user()->store->name }} 매장 데이터만<br>
                    • 개통 입력 및 정산 처리<br>
                    • 일일지출 등록<br>
                    • 기본 조회 기능
                </div>
                @endif
            </div>

            <!-- 퀵 액션 버튼들 -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px;">
                <!-- 모든 사용자 공통 -->
                <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h4 style="font-weight: 600; margin-bottom: 12px;">📝 개통 업무</h4>
                    <button onclick="openSimpleInput()" style="width: 100%; background: #3b82f6; color: white; padding: 10px; border: none; border-radius: 6px; margin-bottom: 8px; cursor: pointer;">
                        간단한 개통 입력
                    </button>
                    <button onclick="openSettlement()" style="width: 100%; background: #10b981; color: white; padding: 10px; border: none; border-radius: 6px; cursor: pointer;">
                        정산표 시스템
                    </button>
                </div>

                <!-- 지사장 이상만 -->
                @if(auth()->user()->isHeadquarters() || auth()->user()->isBranch())
                <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h4 style="font-weight: 600; margin-bottom: 12px;">💼 관리 업무</h4>
                    <button onclick="openPayroll()" style="width: 100%; background: #8b5cf6; color: white; padding: 10px; border: none; border-radius: 6px; margin-bottom: 8px; cursor: pointer;">
                        직원급여 관리
                    </button>
                    <button onclick="openFixedExpenses()" style="width: 100%; background: #f59e0b; color: white; padding: 10px; border: none; border-radius: 6px; cursor: pointer;">
                        고정지출 관리
                    </button>
                </div>
                @endif

                <!-- 개발자 전용 -->
                @if(auth()->user()->isDeveloper())
                <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h4 style="font-weight: 600; margin-bottom: 12px;">🛠️ 개발자 도구</h4>
                    <button onclick="openDeveloperTools()" style="width: 100%; background: #7c3aed; color: white; padding: 10px; border: none; border-radius: 6px; margin-bottom: 8px; cursor: pointer;">
                        시스템 로그 확인
                    </button>
                    <button onclick="openApiDocs()" style="width: 100%; background: #059669; color: white; padding: 10px; border: none; border-radius: 6px; margin-bottom: 8px; cursor: pointer;">
                        API 문서 및 테스트
                    </button>
                    <button onclick="openDatabaseTools()" style="width: 100%; background: #dc2626; color: white; padding: 10px; border: none; border-radius: 6px; cursor: pointer;">
                        데이터베이스 관리
                    </button>
                </div>
                @endif

                <!-- 본사 + 개발자 -->
                @if(auth()->user()->isSuperUser())
                <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h4 style="font-weight: 600; margin-bottom: 12px;">⚙️ 시스템 관리</h4>
                    <button onclick="openAdmin()" style="width: 100%; background: #ef4444; color: white; padding: 10px; border: none; border-radius: 6px; margin-bottom: 8px; cursor: pointer;">
                        관리자 패널
                    </button>
                    <button onclick="openManagement()" style="width: 100%; background: #6366f1; color: white; padding: 10px; border: none; border-radius: 6px; cursor: pointer;">
                        완전한 판매관리
                    </button>
                </div>
                @endif
            </div>
        </div>
    </div>

    <script>
        // 전역 사용자 데이터
        window.userData = {
            id: {{ auth()->id() }},
            name: '{{ auth()->user()->name }}',
            role: '{{ auth()->user()->role }}',
            branch: '{{ auth()->user()->branch?->name ?? '' }}',
            store: '{{ auth()->user()->store?->name ?? '' }}',
            permissions: {
                canViewAllStores: {{ auth()->user()->isHeadquarters() ? 'true' : 'false' }},
                canViewBranchStores: {{ auth()->user()->isBranch() ? 'true' : 'false' }},
                accessibleStoreIds: {!! json_encode(auth()->user()->getAccessibleStoreIds()) !!}
            }
        };

        // 네비게이션 함수들
        function showDashboard() {
            location.reload();
        }
        
        function openSimpleInput() {
            window.location.href = '/test/simple-aggrid';
        }
        
        function openSettlement() {
            const settlementWindow = window.open('http://localhost:5175', '_blank', 'width=1400,height=800');
            setTimeout(() => {
                if (settlementWindow.closed) {
                    alert('❌ 정산 시스템이 실행되지 않고 있습니다.\n\n터미널에서 실행해주세요:\ncd ykp-settlement && npm run dev');
                }
            }, 1000);
        }
        
        function openDailyExpenses() {
            window.location.href = '/daily-expenses';
        }
        
        function openManagement() {
            @if(auth()->user()->isHeadquarters() || auth()->user()->isBranch())
                window.location.href = '/test/complete-aggrid';
            @else
                alert('⚠️ 권한이 없습니다.\n지사장 이상만 접근 가능합니다.');
            @endif
        }
        
        function openFixedExpenses() {
            @if(auth()->user()->isHeadquarters() || auth()->user()->isBranch())
                window.location.href = '/fixed-expenses';
            @else
                alert('⚠️ 권한이 없습니다.\n지사장 이상만 접근 가능합니다.');
            @endif
        }
        
        function openPayroll() {
            @if(auth()->user()->isHeadquarters() || auth()->user()->isBranch())
                window.location.href = '/payroll';
            @else
                alert('⚠️ 권한이 없습니다.\n지사장 이상만 접근 가능합니다.');
            @endif
        }
        
        function openDeveloperTools() {
            @if(auth()->user()->isDeveloper())
                // 개발자 도구 메뉴 표시
                const tools = [
                    '1. 시스템 로그 확인 (/logs)',
                    '2. API 테스트 도구 (/dev/api-test)', 
                    '3. 데이터베이스 관리 (/dev/database)',
                    '4. 성능 모니터링 (/dev/performance)',
                    '5. 관리자 패널 (/admin)'
                ];
                alert('🛠️ 개발자 도구\n\n' + tools.join('\n'));
            @else
                alert('⚠️ 권한이 없습니다.\n개발자만 접근 가능합니다.');
            @endif
        }
        
        function openApiDocs() {
            @if(auth()->user()->isDeveloper())
                window.open('/dev/api-docs', '_blank');
            @endif
        }
        
        function openDatabaseTools() {
            @if(auth()->user()->isDeveloper())
                window.open('/dev/database', '_blank');
            @endif
        }
        
        function openAdmin() {
            @if(auth()->user()->isSuperUser())
                window.location.href = '/admin';
            @else
                alert('⚠️ 권한이 없습니다.\n본사 관리자 또는 개발자만 접근 가능합니다.');
            @endif
        }

        // 권한별 실시간 데이터 로드
        async function loadRoleBasedData() {
            try {
                // 권한별 데이터 범위로 API 호출
                const userPermissions = window.userData.permissions;
                let apiUrl = '/api/dev/dashboard/overview';
                
                if (!userPermissions.canViewAllStores) {
                    // 매장 직원은 자신의 매장 데이터만
                    const storeIds = userPermissions.accessibleStoreIds;
                    if (storeIds && storeIds.length > 0) {
                        apiUrl += `?store_ids=${storeIds.join(',')}`;
                    }
                }
                
                const response = await fetch(apiUrl);
                const data = await response.json();
                
                if (data.success) {
                    // KPI 카드 업데이트 (권한별 데이터)
                    document.querySelector('#todaySales .kpi-value').textContent = 
                        '₩' + Number(data.data.today.sales).toLocaleString();
                    document.querySelector('#monthSales .kpi-value').textContent = 
                        '₩' + Number(data.data.month.sales).toLocaleString();
                    document.querySelector('#activationCount .kpi-value').textContent = 
                        data.data.month.activations + '건';
                }
                
            } catch (error) {
                console.error('권한별 데이터 로드 오류:', error);
            }
        }

        // 페이지 로드 시 권한별 데이터 로드
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(loadRoleBasedData, 1000);
            
            // 5분마다 데이터 새로고침
            setInterval(loadRoleBasedData, 300000);
        });

        // 사이드바 아이콘 클릭 이벤트
        document.querySelectorAll('.sidebar-icon:not(.disabled)').forEach(icon => {
            icon.addEventListener('click', function() {
                document.querySelectorAll('.sidebar-icon').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
            });
        });
    </script>
</body>
</html>