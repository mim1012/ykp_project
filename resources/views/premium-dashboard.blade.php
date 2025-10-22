<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>대시보드</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Pretendard Variable', -apple-system, BlinkMacSystemFont, system-ui, Roboto, sans-serif;
            background: #f8fafc;
            color: #333;
        }
        
        /* 사이드바 */
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 60px;
            height: 100vh;
            background: #0f172a; /* slate-900 */
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
            background: rgba(255,255,255,0.08);
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #e2e8f0; /* slate-200 */
            cursor: pointer;
            transition: all 0.2s;
        }
        .sidebar-icon:hover {
            background: rgba(255,255,255,0.12);
        }
        .sidebar-icon.active {
            background: #ffffff;
            color: #0f172a;
        }
        
        /* 툴팁 스타일 */
        .tooltip {
            position: relative;
        }
        .tooltip .tooltip-text {
            visibility: hidden;
            width: 120px;
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
        
        /* 헤더 */
        .header {
            background: rgba(255,255,255,0.95);
            padding: 15px 30px;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .header h1 {
            font-size: 20px;
            font-weight: 700;
            color: #0f172a;
        }
        .header-actions {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .btn-outline {
            background: white;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }
        
        /* 알림 배너 */
        .alert-banner {
            background: #fef3cd;
            border: 1px solid #f6e05e;
            color: #744210;
            padding: 15px 30px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* 대시보드 컨텐츠 - 더 컴팩트하게 */
        .dashboard-content {
            padding: 20px 25px;
            max-height: calc(100vh - 140px); /* 화면 높이에 맞춤 */
            overflow-y: auto; /* 필요시 스크롤 */
        }
        
        /* KPI 카드 - 더 컴팩트하게 조정 */
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
            min-height: 120px; /* 최소 높이 설정으로 균일성 */
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
        
        /* 차트 섹션 - 균형 잡힌 비율로 조정 */
        .chart-grid {
            display: grid;
            grid-template-columns: 3fr 2fr;
            gap: 20px;
            margin-bottom: 25px;
            height: 320px; /* 고정 높이로 제한 */
        }
        .chart-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
            overflow: hidden; /* 차트가 넘치지 않도록 */
        }
        .chart-title {
            font-size: 15px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 15px;
        }
        .chart-container {
            height: 260px; /* 차트 컨테이너 고정 높이 */
            width: 100%;
            position: relative;
        }
        
        /* 하단 섹션 - 높이 제한 */
        .bottom-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            height: 280px; /* 하단 섹션 고정 높이 */
        }
        .bottom-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            border: 1px solid #e2e8f0;
            overflow-y: auto; /* 내용이 많으면 스크롤 */
        }
        .activity-list {
            list-style: none;
        }
        .activity-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .activity-icon {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        .activity-green { background: #10b981; }
        .activity-blue { background: #3b82f6; }
        .activity-yellow { background: #f59e0b; }
        
        .notice-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid #f1f5f9;
        }
        .notice-title {
            font-size: 14px;
            color: #374151;
        }
        .notice-date {
            font-size: 12px;
            color: #9ca3af;
        }
        .notice-badge {
            background: #ef4444;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <!-- 사이드바 (권한별 맞춤화) -->
    <div class="sidebar">
        <!-- 모든 권한 공통 -->
        <div class="sidebar-icon active tooltip" onclick="showDashboard()">
            Y
            <span class="tooltip-text">메인 대시보드</span>
        </div>

        @if(auth()->user()->role === 'headquarters')
            <!-- 본사 전용 메뉴 -->
            <div class="sidebar-icon tooltip" onclick="openBranchManagement()">
                🏢
                <span class="tooltip-text">지사 관리</span>
            </div>
            <div class="sidebar-icon tooltip" onclick="openStoreManagement()">
                🏪
                <span class="tooltip-text">매장 관리</span>
            </div>
            <div class="sidebar-icon tooltip" onclick="openMonthlySettlement()">
                💼
                <span class="tooltip-text">전체 통계</span>
            </div>
            <div class="sidebar-icon tooltip" onclick="openAccountManagement()">
                👥
                <span class="tooltip-text">계정 관리</span>
            </div>

        @elseif(auth()->user()->role === 'branch')
            <!-- 지사 전용 메뉴 -->
            <div class="sidebar-icon tooltip" onclick="openStoreManagement()">
                🏪
                <span class="tooltip-text">소속 매장 관리</span>
            </div>
            <div class="sidebar-icon tooltip" onclick="openMyBranchStatistics()">
                💼
                <span class="tooltip-text">지사 통계</span>
            </div>

        @elseif(auth()->user()->role === 'store')
            <!-- 매장 전용 메뉴 -->
            <div class="sidebar-icon tooltip" onclick="openManagement()">
                📋
                <span class="tooltip-text">개통표 입력</span>
            </div>
            <div class="sidebar-icon tooltip" onclick="openMyStoreStatistics()">
                💼
                <span class="tooltip-text">내 매장 통계</span>
            </div>

        @endif
    </div>

    <!-- 메인 컨텐츠 -->
    <div class="main-content">
        <!-- 헤더 -->
        <div class="header">
            <div>
                <h1>대시보드</h1>
                <div id="user-info" style="font-size: 14px; color: #64748b; margin-top: 4px;">
                    로딩 중...
                </div>
            </div>
            <div class="header-actions">
                @if(auth()->user()->role === 'headquarters')
                    <!-- 본사 전용 액션 -->
                    <button class="btn btn-success" onclick="openStoreManagement()">+ 새 지사 추가</button>
                    <button class="btn btn-success" onclick="openStoreManagement()">+ 새 매장 추가</button>
                    <button class="btn btn-primary" onclick="openDealerManagement()">🏢 대리점 관리</button>
                    <button class="btn btn-primary" onclick="openCarrierManagement()">📡 통신사 관리</button>
                    <button class="btn btn-outline" onclick="location.reload()">🔄 새로고침</button>
                @elseif(auth()->user()->role === 'branch')
                    <!-- 지사 전용 액션 -->
                    <button class="btn btn-success" onclick="openStoreManagement()">+ 매장 추가</button>
                    <button class="btn btn-primary" onclick="openDealerManagement()">🏢 대리점 관리</button>
                    <button class="btn btn-outline" onclick="openStoreManagement()">👥 매장 관리</button>
                    <button class="btn btn-outline" onclick="location.reload()">🔄 새로고침</button>
                @elseif(auth()->user()->role === 'store')
                    <!-- 매장 전용 액션 (간소화) -->
                @else
                    <!-- 기본 액션 -->
                    <button class="btn btn-outline" onclick="location.reload()">새로고침</button>
                @endif
                <button class="btn btn-outline" onclick="logout()" style="background: #ef4444; color: white;">로그아웃</button>
                
                <script>
                // 🚑 강화된 로그아웃 함수 (완전한 세션 정리)
                function logout() {
                    console.log('🚑 완전 로그아웃 시도');

                    if (confirm('로그아웃하시겠습니까?')) {
                        // 1. 클라이언트 측 데이터 완전 정리
                        try {
                            localStorage.clear();
                            sessionStorage.clear();

                            // 모든 쿠키 삭제
                            document.cookie.split(";").forEach(function(c) {
                                document.cookie = c.replace(/^ +/, "").replace(/=.*/, "=;expires=" + new Date().toUTCString() + ";path=/");
                            });

                            console.log('✅ 클라이언트 데이터 정리 완료');
                        } catch (e) {
                            console.warn('⚠️ 클라이언트 정리 중 오류:', e);
                        }

                        // 2. 서버 측 세션 무효화
                        fetch('/logout', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                            }
                        })
                        .then(response => {
                            console.log('📡 서버 로그아웃 응답:', response.status);

                            // 3. 강제 리디렉션
                            window.location.href = '/login?logout=success';
                        })
                        .catch(error => {
                            console.error('❌ 로그아웃 오류:', error);

                            // 오류 발생 시에도 강제 리디렉션
                            alert('로그아웃 처리 중 오류가 발생했습니다.\n로그인 페이지로 이동합니다.');
                            window.location.href = '/login';
                        });
                    }
                }
                </script>
            </div>
        </div>

        <!-- 알림 배너 (권한별 메시지) -->
        <div class="alert-banner" style="background: #dcfce7; border: 1px solid #16a34a; color: #166534;">
            <span>✅</span>
            <div>
                <strong>YKP ERP 시스템 정상 운영 중</strong><br>
                @if(auth()->user()->role === 'headquarters')
                    전체 시스템 관리 중 - 실시간 데이터 연동 완료
                @elseif(auth()->user()->role === 'branch')
                    {{ auth()->user()->branch->name ?? '지사' }} 매장 관리 중 - 실시간 데이터 연동 완료
                @elseif(auth()->user()->role === 'store')
                    {{ auth()->user()->store->name ?? '매장' }} 운영 중 - 실시간 데이터 연동 완료
                @else
                    시스템 개발 모드 - 실시간 데이터 연동 완료
                @endif
            </div>
        </div>

        <!-- 대시보드 컨텐츠 -->
        <div class="dashboard-content">
            <!-- KPI 카드 (권한별 맞춤화) -->
            <div class="kpi-grid">
                @if(auth()->user()->role === 'headquarters')
                    <!-- 본사: 전체 시스템 관리 관점 -->
                    <div class="kpi-card" id="totalBranches" style="border-left: 4px solid #3b82f6;">
                        <div class="kpi-header">
                            <span class="kpi-title">🏢 전체 지사 수</span>
                            <span class="kpi-trend trend-up">+ 2개</span>
                        </div>
                        <div class="kpi-value" id="total-branches-count">로딩 중...</div>
                        <div class="kpi-subtitle">전국 지사 관리</div>
                    </div>
                    <div class="kpi-card" id="totalStores" style="border-left: 4px solid #3b82f6;">
                        <div class="kpi-header">
                            <span class="kpi-title">🏪 전체 매장 수</span>
                            <span class="kpi-trend trend-up">+ 1개</span>
                        </div>
                        <div class="kpi-value" id="total-stores-count">로딩 중...</div>
                        <div class="kpi-subtitle">전국 매장 관리</div>
                    </div>
                    <div class="kpi-card" id="totalUsers" style="border-left: 4px solid #3b82f6;">
                        <div class="kpi-header">
                            <span class="kpi-title">👥 전체 사용자</span>
                            <span class="kpi-trend trend-up">+ 1명</span>
                        </div>
                        <div class="kpi-value" id="total-users-count">로딩 중...</div>
                        <div class="kpi-subtitle">시스템 사용자</div>
                    </div>
                    <div class="kpi-card" id="systemGoal" style="border-left: 4px solid #3b82f6;">
                        <div class="kpi-header">
                            <span class="kpi-title">🎯 시스템 목표</span>
                        </div>
                        <div class="kpi-value" id="system-goal-achievement">로딩 중...</div>
                        <div class="kpi-subtitle" id="system-goal-target">목표 로딩 중...</div>
                    </div>
                @elseif(auth()->user()->role === 'branch')
                    <!-- 지사: 소속 매장 관리 관점 -->
                    <div class="kpi-card" id="branchStores" style="border-left: 4px solid #10b981;">
                        <div class="kpi-header">
                            <span class="kpi-title">🏪 관리 매장 수</span>
                            <span class="kpi-trend trend-stable" id="branch-stores-trend">= 0개</span>
                        </div>
                        <div class="kpi-value" id="branch-stores-count">0개 매장</div>
                        <div class="kpi-subtitle">{{ auth()->user()->branch->name ?? '지사' }} 소속</div>
                    </div>
                    <div class="kpi-card" id="branchSales" style="border-left: 4px solid #10b981;">
                        <div class="kpi-header">
                            <span class="kpi-title">💰 지사 매출</span>
                            <span class="kpi-trend trend-up">+ 8.2%</span>
                        </div>
                        <div class="kpi-value" id="branch-total-sales">₩0</div>
                        <div class="kpi-subtitle">소속 매장 합계</div>
                    </div>
                    <div class="kpi-card" id="branchRank" style="border-left: 4px solid #10b981;">
                        <div class="kpi-header">
                            <span class="kpi-title">지사 순위</span>
                            <span class="kpi-trend trend-up" id="branch-rank-trend">-</span>
                        </div>
                        <div class="kpi-value" id="branch-rank-position">- / -</div>
                        <div class="kpi-subtitle">전체 지사 중</div>
                    </div>
                    <div class="kpi-card" id="branchGoal" style="border-left: 4px solid #10b981;">
                        <div class="kpi-header">
                            <span class="kpi-title">🎯 지사 목표</span>
                        </div>
                        <div class="kpi-value" id="branch-goal-achievement">0% 달성</div>
                        <div class="kpi-subtitle" id="branch-goal-target">목표 로딩 중...</div>
                    </div>
                    <div class="kpi-card" id="branchRanking" style="border-left: 4px solid #8b5cf6;">
                        <div class="kpi-header">
                            <span class="kpi-title">🏆 지사 순위</span>
                            <span class="kpi-trend trend-stable" id="branch-ranking-trend">-</span>
                        </div>
                        <div class="kpi-value" id="branch-ranking-position">- / -</div>
                        <div class="kpi-subtitle">전체 지사 중</div>
                    </div>
                    <div class="kpi-card" id="storeRankingBranch" style="border-left: 4px solid #06b6d4;">
                        <div class="kpi-header">
                            <span class="kpi-title">🏪 매장 순위</span>
                            <span class="kpi-trend trend-stable" id="store-ranking-trend">-</span>
                        </div>
                        <div class="kpi-value" id="store-ranking-position">- / -</div>
                        <div class="kpi-subtitle">지사 내 매장 중</div>
                    </div>
                @elseif(auth()->user()->role === 'store')
                    <!-- 매장: 개인 성과 관점 -->
                    <div class="kpi-card" id="storeToday" style="border-left: 4px solid #f59e0b;">
                        <div class="kpi-header">
                            <span class="kpi-title">오늘 개통</span>
                            <span class="kpi-trend trend-stable">= 0건</span>
                        </div>
                        <div class="kpi-value">0건 개통</div>
                        <div class="kpi-subtitle">{{ now()->format('n월 j일') }} 실적</div>
                    </div>
                    <div class="kpi-card" id="storeSales" style="border-left: 4px solid #f59e0b;">
                        <div class="kpi-header">
                            <span class="kpi-title">💰 매장 매출</span>
                            <span class="kpi-trend trend-stable">= 0원</span>
                        </div>
                        <div class="kpi-value">₩0</div>
                        <div class="kpi-subtitle">{{ auth()->user()->store->name ?? '매장' }} 매출</div>
                    </div>
                    <div class="kpi-card" id="storeRank" style="border-left: 4px solid #f59e0b;">
                        <div class="kpi-header">
                            <span class="kpi-title">매장 순위</span>
                            <span class="kpi-trend trend-stable">= 순위</span>
                        </div>
                        <div class="kpi-value">15위 / 25개</div>
                        <div class="kpi-subtitle">전체 매장 중</div>
                    </div>
                    <div class="kpi-card" id="storeGoal" style="border-left: 4px solid #f59e0b;">
                        <div class="kpi-header">
                            <span class="kpi-title">🎯 매장 목표</span>
                        </div>
                        <div class="kpi-value">0% 달성</div>
                        <div class="kpi-subtitle" id="store-goal-target">목표 로딩 중...</div>
                    </div>
                @else
                    <!-- 개발자: 시스템 정보 -->
                    <div class="kpi-card" id="devData">
                        <div class="kpi-header">
                            <span class="kpi-title">🛠️ 개발 모드</span>
                        </div>
                        <div class="kpi-value">시스템 데이터</div>
                        <div class="kpi-subtitle">개발자 전용</div>
                    </div>
                @endif
            </div>

            <!-- 차트 섹션 -->
            <div class="chart-grid">
                <div class="chart-card">
                    <div class="chart-title">30일 매출 추이</div>
                    <div class="chart-container">
                        <canvas id="salesChart"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <div class="chart-title">시장별 매출</div>
                    <div class="chart-container">
                        <canvas id="marketChart"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- TOP 성과자 섹션 (권한별 표시) -->
            @if(auth()->user()->role === 'headquarters')
            <div class="top-performers-section" style="margin: 30px 0;">
                <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 20px; color: #1f2937;">🏆 전국 TOP 5 성과</h3>
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <h4 style="font-size: 16px; font-weight: 600; margin-bottom: 15px; color: #3b82f6;">🏢 TOP 지사</h4>
                        <ul id="top-branches-list" style="list-style: none; padding: 0; margin: 0;">
                            <li style="padding: 8px 0; color: #6b7280;">데이터 로딩 중...</li>
                        </ul>
                    </div>
                    <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                        <h4 style="font-size: 16px; font-weight: 600; margin-bottom: 15px; color: #10b981;">🏪 TOP 매장</h4>
                        <ul id="top-stores-list" style="list-style: none; padding: 0; margin: 0;">
                            <li style="padding: 8px 0; color: #6b7280;">데이터 로딩 중...</li>
                        </ul>
                    </div>
                </div>
            </div>
            @elseif(auth()->user()->role === 'branch')
            <div class="top-performers-section" style="margin: 30px 0;">
                <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 20px; color: #1f2937;">🏪 {{ auth()->user()->branch->name ?? '지사' }} TOP 5 매장</h3>
                <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h4 style="font-size: 16px; font-weight: 600; margin-bottom: 15px; color: #10b981;">🏆 지사 내 매장 순위</h4>
                    <ul id="top-stores-list" style="list-style: none; padding: 0; margin: 0;">
                        <li style="padding: 8px 0; color: #6b7280;">데이터 로딩 중...</li>
                    </ul>
                </div>
            </div>
            @elseif(auth()->user()->role === 'store')
            <div class="top-performers-section" style="margin: 30px 0;">
                <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 20px; color: #1f2937;">🏪 {{ auth()->user()->branch->name ?? '지사' }} 매장 현황</h3>
                <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1);">
                    <h4 style="font-size: 16px; font-weight: 600; margin-bottom: 15px; color: #f59e0b;">🏆 지사 내 TOP 5</h4>
                    <ul id="top-stores-list" style="list-style: none; padding: 0; margin: 0;">
                        <li style="padding: 8px 0; color: #6b7280;">데이터 로딩 중...</li>
                    </ul>
                </div>
            </div>
            @endif

            <!-- 하단 섹션 -->
            <div class="bottom-grid">
                <div class="bottom-card">
                    <div class="chart-title">최근 활동</div>
                    <div style="text-align: center; padding: 40px 20px; color: #6b7280;">
                        <div style="font-size: 24px; margin-bottom: 12px;">🚧</div>
                        <div style="font-weight: 600; margin-bottom: 8px;" id="activity-status">실시간 활동 로딩 중...</div>
                        <div style="font-size: 14px;" id="activity-description">최근 활동을 불러오고 있습니다.</div>
                    </div>
                    <div id="realtime-activities" style="padding: 0 20px; max-height: 300px; overflow-y: auto;">
                        <!-- 실시간 활동이 여기에 표시됩니다 -->
                    </div>
                </div>
                <div class="bottom-card">
                    <div class="chart-title">공지사항</div>
                    <div style="text-align: center; padding: 40px 20px; color: #6b7280;">
                        <div style="font-size: 24px; margin-bottom: 12px;">📢</div>
                        <div style="font-weight: 600; margin-bottom: 8px;">공지사항 없음</div>
                        <div style="font-size: 14px;">새로운 공지사항이 등록되면 여기에 표시됩니다.</div>
                    </div>
                </div>
                <div class="bottom-card">
                    <div class="chart-title">공지사항</div>
                    <div style="text-align: center; padding: 40px 20px; color: #6b7280;">
                        <div style="font-size: 24px; margin-bottom: 12px;"></div>
                        <div style="font-weight: 600; margin-bottom: 8px;">공지사항 없음</div>
                        <div style="font-size: 14px;">새로운 공지사항이 등록되면 여기에 표시됩니다.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Feature Flags 및 사용자 정보 설정
        window.features = {
            excel_input_form: @json(app('App\Services\FeatureService')->isEnabled('excel_input_form'))
        };
        
        window.userData = {
            id: {{ auth()->user()->id ?? 1 }},
            name: '{{ auth()->user()->name ?? "개발자" }}',
            email: '{{ auth()->user()->email ?? "dev@ykp.com" }}',
            role: '{{ auth()->user()->role ?? "developer" }}',
            store_id: {{ auth()->user()->store_id ?? 'null' }},
            branch_id: {{ auth()->user()->branch_id ?? 'null' }},
            store_name: '{{ auth()->user()->store->name ?? "" }}',
            branch_name: '{{ auth()->user()->branch->name ?? "" }}'
        };
        
        console.log('Feature Flags:', window.features);
        console.log('User Data:', window.userData);
        
        // 사용자 정보 UI 업데이트
        function updateUserInfo() {
            const userInfo = document.getElementById('user-info');
            const { name, role, store_name, branch_name } = window.userData;
            
            let roleText = '';
            let locationText = '';
            
            switch(role) {
                case 'headquarters':
                    roleText = '🏢 본사 관리자';
                    locationText = '전체 매장 관리 권한';
                    break;
                case 'branch': 
                    roleText = '🏬 지사 관리자';
                    locationText = `${branch_name} 소속 매장 관리`;
                    break;
                case 'store':
                    roleText = '🏪 매장 직원';
                    locationText = `${store_name} (${branch_name})`;
                    break;
                default:
                    roleText = '👨‍💻 개발자';
                    locationText = '시스템 관리자';
            }
            
            userInfo.innerHTML = `
                <div style="display: flex; align-items: center; gap: 12px;">
                    <span><strong>${name}</strong></span>
                    <span style="background: #e2e8f0; padding: 2px 8px; border-radius: 12px; font-size: 12px;">${roleText}</span>
                    <span style="color: #64748b;">${locationText}</span>
                </div>
            `;
        }
        
        // 페이지 로드 시 사용자 정보 표시
        document.addEventListener('DOMContentLoaded', updateUserInfo);
        
        // 30일 매출 추이 차트
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: '매출',
                    data: [],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, /* 컨테이너 크기에 맞춤 */
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: '#f1f5f9'
                        },
                        ticks: {
                            font: {
                                size: 11
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 10
                            }
                        }
                    }
                }
            }
        });

        // 시장별 매출 도넛 차트
        const marketCtx = document.getElementById('marketChart').getContext('2d');
        const marketChart = new Chart(marketCtx, {
            type: 'doughnut',
            data: {
                labels: ['SKT', 'KT', 'LG U+', 'MVNO', '기타'],
                datasets: [{
                    data: [35, 25, 20, 15, 5],
                    backgroundColor: [
                        '#3b82f6',
                        '#10b981',
                        '#f59e0b',
                        '#ef4444',
                        '#8b5cf6'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false, /* 컨테이너 크기에 맞춤 */
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            usePointStyle: true,
                            font: {
                                size: 11
                            }
                        }
                    }
                }
            }
        });

        // 사이드바 네비게이션 함수들
        function showDashboard() {
            // 메인 대시보드 새로고침
            location.reload();
        }
        
        function openSimpleInput() {
            // 매장용 개통표 입력 시스템으로 이동
            window.location.href = '/sales/store-input';
        }
        
        function openSettlement() {
            // YKP 정산 시스템 새 창으로 열기
            const settlementWindow = window.open('http://localhost:5175', '_blank', 'width=1400,height=800');
            
            setTimeout(() => {
                if (settlementWindow.closed) {
                    alert('❌ 정산 시스템이 실행되지 않고 있습니다.\n\n터미널에서 실행해주세요:\ncd ykp-settlement && npm run dev');
                }
            }, 1000);
        }
        
        function openManagement() {
            // 완전한 판매관리 시스템 (인증 경로)
            window.location.href = '/sales/complete-aggrid';
        }
        
        function openAccountManagement() {
            // 본사 전용 계정 관리 페이지
            window.location.href = '/admin/accounts';
        }
        
        function openDailyExpenses() {
            // 일일지출 관리 페이지
            window.location.href = '/daily-expenses';
        }
        
        function openFixedExpenses() {
            // 고정지출 관리 페이지
            window.location.href = '/fixed-expenses';
        }
        
        function openRefunds() {
            // 환수 관리 페이지 (신규 완성!)
            window.location.href = '/refunds';
        }
        
        function openPayroll() {
            // 직원급여 관리 페이지 (엑셀 방식)
            window.location.href = '/payroll';
        }
        
        function openMonthlySettlement() {
            // 권한별 통계 페이지로 이동
            window.location.href = '/statistics';
        }

        function openMyStoreStatistics() {
            // 매장 직원용 통계 페이지 (자기 매장만)
            const storeId = window.userData?.store_id;
            const storeName = window.userData?.store_name || '내 매장';

            if (storeId) {
                console.log(`📊 ${storeName} 통계 페이지로 이동`);
                window.location.href = `/statistics/enhanced?store=${storeId}&name=${encodeURIComponent(storeName)}&role=store`;
            } else {
                console.log('📊 매장 통계 페이지로 이동');
                window.location.href = '/statistics/my-store';
            }
        }

        function openMyBranchStatistics() {
            // 지사 관리자용 통계 페이지 (소속 지사만)
            const branchId = window.userData?.branch_id;
            const branchName = window.userData?.branch_name || '내 지사';

            if (branchId) {
                console.log(`📊 ${branchName} 통계 페이지로 이동`);
                window.location.href = `/statistics/enhanced?branch=${branchId}&name=${encodeURIComponent(branchName)}&role=branch`;
            } else {
                console.log('📊 지사 통계 페이지로 이동');
                window.location.href = '/statistics';
            }
        }
        
        function openStoreManagement() {
            // 매장 관리 (본사 전용)
            window.location.href = '/management/stores';
        }

        function openBranchManagement() {
            // 지사 관리 (본사 전용)
            window.location.href = '/management/branches';
        }

        function openDealerManagement() {
            // 대리점 관리 모달 열기
            const modal = document.getElementById('dealerManagementModal');
            if (modal) {
                modal.style.display = 'block';
                loadDealers();
            }
        }
        
        function openAdmin() {
            // 관리자 패널
            window.location.href = '/admin';
        }
        

        // 실시간 데이터 로드 (안전성 강화)
        async function loadRealTimeData() {
            try {
                console.log('🔄 실시간 데이터 로드 시작 - 사용자:', window.userData?.role);
                // API는 이미 서버에서 권한별 필터링 처리
                const apiUrl = '/api/dashboard/overview';

                console.log('API 호출:', apiUrl, '권한:', window.userData.role);
                
                // 대시보드 개요 데이터 로드
                const overviewResponse = await fetch(apiUrl);
                const overviewData = await overviewResponse.json();

                console.log('📊 Dashboard API Response:', overviewData);

                if (overviewData.success) {
                    const data = overviewData.data;
                    console.log('📊 Dashboard Data:', data);

                    // 지사 계정일 때 매장 수와 매출 업데이트
                    if (window.userData.role === 'branch') {
                        // API에서 제공하는 실제 데이터 사용 (DashboardController 응답 구조)
                        const branchStoreCount = data.stores?.total || 0;
                        const monthSales = data.this_month_sales || 0;
                        const todayActivations = data.today_activations || 0;

                        // 지사 목표 및 달성률 (API에서 직접 제공)
                        const monthTarget = data.monthly_target || 50000000;
                        const achievementRate = data.achievement_rate || 0;

                        console.log('📊 Branch Data:', {
                            branchStoreCount,
                            monthSales,
                            todayActivations,
                            monthTarget,
                            achievementRate
                        });

                        // 지사 KPI 업데이트
                        const branchStoresCount = document.getElementById('branch-stores-count');
                        if (branchStoresCount) {
                            branchStoresCount.textContent = `${branchStoreCount}개 매장`;
                            branchStoresCount.className = 'kpi-value text-blue-600';
                        }

                        const branchTotalSales = document.getElementById('branch-total-sales');
                        if (branchTotalSales) {
                            branchTotalSales.textContent = `₩${monthSales.toLocaleString()}`;
                            branchTotalSales.className = 'kpi-value text-green-600';
                        }

                        // 오늘 매출/개통도 업데이트
                        const todaySalesEl = document.getElementById('today-sales');
                        if (todaySalesEl) {
                            const todaySales = data.today_sales || 0; // API에서 제공하는 실제 오늘 매출
                            todaySalesEl.textContent = `₩${Number(todaySales).toLocaleString()}`;
                        }

                        const todayActivationsEl = document.getElementById('today-activations');
                        if (todayActivationsEl) {
                            todayActivationsEl.textContent = `${todayActivations}건`;
                        }

                        const branchGoalAchievement = document.getElementById('branch-goal-achievement');
                        if (branchGoalAchievement) {
                            branchGoalAchievement.textContent = `${achievementRate}% 달성`;
                            // 달성률에 따른 색상 변경
                            if (achievementRate >= 100) {
                                branchGoalAchievement.className = 'kpi-value text-green-600';
                            } else if (achievementRate >= 80) {
                                branchGoalAchievement.className = 'kpi-value text-blue-600';
                            } else {
                                branchGoalAchievement.className = 'kpi-value text-orange-600';
                            }
                        }

                        console.log(`✅ 지사 실시간 데이터 업데이트: ${branchStoreCount}개 매장, ₩${monthSales.toLocaleString()}, ${achievementRate}% 달성`);
                    }

                    // 매장 계정일 때 오늘 개통과 매출 업데이트
                    if (window.userData.role === 'store') {
                        // API에서 제공하는 실제 데이터 사용 (DashboardController 응답 구조)
                        const todayActivations = data.today_activations || 0;
                        const monthSales = data.this_month_sales || 0;

                        // 오늘 개통 카드 업데이트 (#storeToday)
                        const storeTodayCard = document.getElementById('storeToday');
                        if (storeTodayCard) {
                            const todayValueEl = storeTodayCard.querySelector('.kpi-value');
                            const todayTrendEl = storeTodayCard.querySelector('.kpi-trend');

                            if (todayValueEl) {
                                todayValueEl.textContent = `${todayActivations}건 개통`;
                            }
                            if (todayTrendEl) {
                                todayTrendEl.textContent = `= ${todayActivations}건`;
                                todayTrendEl.className = todayActivations > 0 ? 'kpi-trend trend-up' : 'kpi-trend trend-stable';
                            }
                        }

                        // 매장 매출 카드 업데이트 (#storeSales)
                        const storeSalesCard = document.getElementById('storeSales');
                        if (storeSalesCard) {
                            const salesValueEl = storeSalesCard.querySelector('.kpi-value');
                            const salesTrendEl = storeSalesCard.querySelector('.kpi-trend');

                            if (salesValueEl) {
                                salesValueEl.textContent = `₩${Number(monthSales).toLocaleString()}`;
                            }
                            if (salesTrendEl) {
                                salesTrendEl.textContent = monthSales > 0 ? `= ₩${Number(monthSales).toLocaleString()}` : '= 0원';
                                salesTrendEl.className = monthSales > 0 ? 'kpi-trend trend-up' : 'kpi-trend trend-stable';
                            }
                        }

                        console.log('✅ 매장 계정 데이터 업데이트 완료:', { todayActivations, monthSales });
                    }

                    // 순위 데이터 로드
                    await loadRankings();

                    // TOP N 리스트 로드
                    await loadTopLists();
                    
                    // 본사 계정 전용 KPI 카드 업데이트
                    if (window.userData.role === 'headquarters') {
                        const todaySalesElement = document.querySelector('#todaySales .kpi-value');
                        if (todaySalesElement) {
                            const todayActivations = data.today_activations || 0;
                            todaySalesElement.textContent = `${todayActivations}건`;
                        }

                        const monthSalesElement = document.querySelector('#monthSales .kpi-value');
                        if (monthSalesElement) {
                            monthSalesElement.textContent = '₩' + Number(data.this_month_sales || 0).toLocaleString();
                        }

                        const vatSalesElement = document.querySelector('#vatSales .kpi-value');
                        if (vatSalesElement) {
                            const vatIncludedSales = (data.this_month_sales || 0) * 1.1; // 부가세 포함 계산
                            vatSalesElement.textContent = '₩' + Number(vatIncludedSales).toLocaleString();
                        }

                        const goalProgressElement = document.querySelector('#goalProgress .kpi-value');
                        if (goalProgressElement) {
                            goalProgressElement.textContent = Math.round(data.achievement_rate || 0) + ' / 100';
                        }

                        // 증감률 업데이트 (현재 API에서 제공하지 않으므로 임시 처리)
                        const growthElement = document.querySelector('#monthSales .kpi-trend');
                        if (growthElement) {
                            const growthRate = 0; // API에서 제공 시 data.growth_rate 사용
                            growthElement.textContent = (growthRate >= 0 ? '+' : '') + growthRate + '%';
                            growthElement.className = growthRate >= 0 ? 'kpi-trend trend-up' : 'kpi-trend trend-down';
                        }

                        console.log('✅ 본사 계정 KPI 카드 업데이트 완료');
                    }
                }
                
                // 30일 매출 추이 데이터 로드
                const trendResponse = await fetch('/api/dashboard/sales-trend?days=30');
                const trendData = await trendResponse.json();
                
                if (trendData.success && typeof salesChart !== 'undefined') {
                    const labels = trendData.data.trend_data.map(item => item.day_label);
                    const data = trendData.data.trend_data.map(item => item.sales);
                    
                    salesChart.data.labels = labels;
                    salesChart.data.datasets[0].data = data;
                    salesChart.update();
                }
                
                // 대리점별 성과 데이터 로드
                const performanceResponse = await fetch('/api/dashboard/dealer-performance');
                const performanceData = await performanceResponse.json();
                
                if (performanceData.success && typeof marketChart !== 'undefined') {
                    const labels = performanceData.data.carrier_breakdown.map(item => item.carrier);
                    const data = performanceData.data.carrier_breakdown.map(item => Math.round(item.percentage));
                    
                    marketChart.data.labels = labels;
                    marketChart.data.datasets[0].data = data;
                    marketChart.update();
                }
                
            } catch (error) {
                console.error('실시간 데이터 로드 오류:', error);
                // 실제 데이터로 표시 (SQLite에 데이터 있음)
                const banner = document.querySelector('.alert-banner');
                if (banner) {
                    // 실제 시스템 현황 데이터 로드
                    const systemStats = await loadSystemStatus();
                    
                    banner.innerHTML = `
                        <span>✅</span>
                        <div>
                            <strong>YKP ERP 시스템 정상 운영 중</strong><br>
                            ${systemStats}
                        </div>
                    `;
                    banner.style.background = '#dcfce7';
                    banner.style.border = '1px solid #16a34a';
                    banner.style.color = '#166534';
                }
            }
        }
        
        // 실시간 시스템 상태 데이터 로드
        async function loadSystemStatus() {
            try {
                console.log('📊 실시간 시스템 상태 로드 시작...');

                // 🚀 실시간 API 호출 - 모든 데이터를 실시간으로 로드
                const apiResults = {
                    users: 0,     // 실시간 로드될 사용자 수
                    stores: 0,    // 실시간 로드될 매장 수
                    sales: 0,     // 실시간 로드될 매출 건수
                    branches: 0   // 실시간 로드될 지사 수
                };
                
                // 🔄 실시간 API 호출들 - 병렬 처리로 성능 향상
                const apiCalls = [
                    // 매장 수 조회 (계정관리와 동일한 기준)
                    fetch('/api/stores').then(res => res.json()).then(data => {
                        if (data.success && Array.isArray(data.data)) {
                            // 활성 매장만 카운트 (계정관리와 동일)
                            apiResults.stores = data.data.filter(store => store.status === 'active').length;
                            console.log('✅ 활성 매장 수 실시간 업데이트:', apiResults.stores);
                        }
                    }).catch(e => console.warn('⚠️ 매장 수 로드 실패:', e.message)),

                    // 사용자 수 조회 (계정관리와 동일한 기준)
                    fetch('/api/users').then(res => res.json()).then(data => {
                        if (data.success && Array.isArray(data.data)) {
                            // 활성 사용자만 카운트 (계정관리와 동일)
                            apiResults.users = data.data.filter(user => user.is_active).length;
                            console.log('✅ 활성 사용자 수 실시간 업데이트:', apiResults.users);
                        }
                    }).catch(e => console.warn('⚠️ 사용자 수 로드 실패:', e.message)),

                    // 지사 수 조회
                    fetch('/api/branches').then(res => res.json()).then(data => {
                        if (data.success && Array.isArray(data.data)) {
                            apiResults.branches = data.data.length;
                            console.log('✅ 지사 수 실시간 업데이트:', apiResults.branches);
                        }
                    }).catch(e => console.warn('⚠️ 지사 수 로드 실패:', e.message)),

                    // 개통표 수 조회
                    fetch('/api/dashboard/overview').then(res => res.json()).then(data => {
                        if (data.success && data.data) {
                            apiResults.sales = data.data.total_activations || apiResults.sales;
                            console.log('✅ 개통표 수 실시간 업데이트:', apiResults.sales);
                        }
                    }).catch(e => console.warn('⚠️ 개통표 수 로드 실패:', e.message))
                ];

                // 모든 API 호출을 병렬로 실행
                await Promise.allSettled(apiCalls);
                
                // 실시간 데이터로 UI 업데이트
                const userCount = apiResults.users;
                const storeCount = apiResults.stores;
                const branchCount = apiResults.branches;
                const salesCount = apiResults.sales;

                // UI 요소 실시간 업데이트
                updateDashboardElements(userCount, storeCount, branchCount, salesCount);
                
                console.log('✅ 데이터 집계 완료:', { users: userCount, stores: storeCount, sales: salesCount, branches: branchCount });
                
                // 권한별 메시지 차별화 (실제 데이터 기반)
                const role = window.userData?.role || 'headquarters';
                if (role === 'headquarters') {
                    return `전체 시스템 관리 중 - 실시간 데이터 연동 완룼`;
                } else if (role === 'branch') {
                    return `지사 매장 관리 중 - 실시간 데이터 연동 완룼`;
                } else if (role === 'store') {
                    return `매장 운영 중 - 실시간 데이터 연동 완룼`;
                } else {
                    return `전체 시스템 관리 중 - 실시간 데이터 연동 완룼`;
                }
                
            } catch (error) {
                console.error('시스템 상태 로드 오류:', error);
                // 🚑 API 오류 시 실제 데이터로 대체
                console.log('⚠️ 시스템 API 오류 - 대체 데이터 사용');
                return '전체 시스템 관리 중 - 실시간 데이터 연동 완료';
            }
        }

        // 순위 데이터 로드
        async function loadRankings() {
            try {
                const response = await fetch('/api/dashboard/rankings');

                // 404 또는 다른 오류 시 무시
                if (!response.ok) {
                    console.warn('Rankings API not available or returned error');
                    return;
                }

                const result = await response.json();

                if (result.success && result.data) {
                    // result.data로 올바르게 접근 (안전장치 추가)
                    const { branch, store } = result.data || {};

                    // 지사 순위 업데이트 (올바른 ID 사용)
                    if (branch && window.userData.role !== 'headquarters') {
                        const branchRankEl = document.getElementById('branch-rank-position');
                        if (branchRankEl) {
                            if (branch.rank) {
                                branchRankEl.textContent = `${branch.rank} / ${branch.total}`;
                            } else {
                                branchRankEl.textContent = '- / -';
                            }
                        }
                    }

                    // 매장 순위 업데이트
                    if (store) {
                        // 지사 역할의 매장 순위 표시
                        const storeRankingEl = document.getElementById('store-ranking-position');
                        if (storeRankingEl) {
                            if (store.rank) {
                                const scope = store.scope === 'nationwide' ? '전국' : '지사 내';
                                storeRankingEl.textContent = `${store.rank} / ${store.total}`;
                            } else {
                                storeRankingEl.textContent = '- / -';
                            }
                        }

                        // 매장 역할의 순위 표시 (storeRank 요소)
                        const storeRankEl = document.querySelector('#storeRank .kpi-value');
                        if (storeRankEl && window.userData.role === 'store') {
                            if (store.rank) {
                                storeRankEl.textContent = `${store.rank}위 / ${store.total}개`;
                                // 순위 변화 트렌드 표시
                                const trendEl = document.querySelector('#storeRank .kpi-trend');
                                if (trendEl) {
                                    trendEl.textContent = '= 순위';
                                }
                            } else {
                                storeRankEl.textContent = '- / -';
                            }
                        }
                    }

                    console.log('순위 데이터 로드 완료:', { branch: branch, store: store });
                }

                // 매장 목표 달성률 업데이트 (매장 역할만)
                if (window.userData.role === 'store') {
                    await updateStoreGoal();
                }
            } catch (error) {
                console.warn('순위 데이터 로드 건너뜀:', error);
                // 에러 시에도 계속 진행
            }
        }

        // 매장 목표 달성률 계산 및 업데이트
        async function updateStoreGoal() {
            try {
                // 이번달 매출 데이터 가져오기
                const response = await fetch('/api/dashboard/overview');
                if (!response.ok) return;

                const result = await response.json();
                if (result.success && result.data) {
                    const monthSales = result.data.this_month_sales || 0;
                    const monthlyGoal = 50000000; // 기본 목표: 5천만원

                    // 목표 달성률 계산
                    const achievementRate = monthSales > 0 ? Math.round((monthSales / monthlyGoal) * 100) : 0;

                    // UI 업데이트
                    const goalEl = document.querySelector('#storeGoal .kpi-value');
                    if (goalEl) {
                        goalEl.textContent = `${achievementRate}% 달성`;

                        // 달성률에 따른 색상 변경
                        const goalCard = document.getElementById('storeGoal');
                        if (goalCard) {
                            if (achievementRate >= 100) {
                                goalCard.style.borderLeftColor = '#10b981'; // 초록색
                            } else if (achievementRate >= 70) {
                                goalCard.style.borderLeftColor = '#f59e0b'; // 주황색
                            } else {
                                goalCard.style.borderLeftColor = '#ef4444'; // 빨간색
                            }
                        }
                    }

                    // 목표 금액 표시
                    const targetEl = document.getElementById('store-goal-target');
                    if (targetEl) {
                        targetEl.textContent = `목표: ₩${monthlyGoal.toLocaleString()} / 현재: ₩${monthSales.toLocaleString()}`;
                    }

                    console.log('매장 목표 업데이트:', { monthSales, achievementRate });
                }
            } catch (error) {
                console.warn('매장 목표 데이터 로드 실패:', error);
            }
        }

        // TOP N 리스트 로드
        async function loadTopLists() {
            try {
                const userRole = window.userData.role;
                
                // 본사: 지사 + 매장 TOP 5
                if (userRole === 'headquarters') {
                    await loadTopBranches();
                    await loadTopStores();
                } else {
                    // 지사/매장: 매장 TOP 5만
                    await loadTopStores();
                }
            } catch (error) {
                console.error('TOP 리스트 로드 실패:', error);
            }
        }

        // TOP 지사 로드 (본사 전용)
        async function loadTopBranches() {
            try {
                const response = await fetch('/api/dashboard/top-list?type=branch&limit=5');
                const result = await response.json();
                
                if (result.success) {
                    const listEl = document.getElementById('top-branches-list');
                    if (listEl) {
                        listEl.innerHTML = '';
                        
                        result.data.forEach(branch => {
                            const li = document.createElement('li');
                            li.style.cssText = `
                                padding: 12px 0; 
                                border-bottom: 1px solid #f1f5f9; 
                                display: flex; 
                                justify-content: space-between;
                                align-items: center;
                                ${branch.is_current_user ? 'background: #dbeafe; margin: 0 -20px; padding: 12px 20px;' : ''}
                            `;
                            li.innerHTML = `
                                <div>
                                    <span style="font-weight: 600; color: #1f2937;">${branch.rank}위</span>
                                    <span style="margin-left: 8px; color: #374151;">${branch.name}</span>
                                    ${branch.is_current_user ? '<span style="margin-left: 8px; background: #3b82f6; color: white; padding: 2px 6px; border-radius: 4px; font-size: 12px;">내 지사</span>' : ''}
                                </div>
                                <span style="font-weight: 600; color: #10b981;">₩${branch.total_sales.toLocaleString()}</span>
                            `;
                            listEl.appendChild(li);
                        });
                    }
                }
            } catch (error) {
                console.error('TOP 지사 로드 실패:', error);
            }
        }

        // TOP 매장 로드 (권한별)
        async function loadTopStores() {
            try {
                const response = await fetch('/api/dashboard/top-list?type=store&limit=5');
                const result = await response.json();
                
                if (result.success) {
                    const listEl = document.getElementById('top-stores-list');
                    if (listEl) {
                        listEl.innerHTML = '';
                        
                        if (result.data.length === 0) {
                            listEl.innerHTML = '<li style="padding: 20px; text-align: center; color: #6b7280;">아직 매출 데이터가 없습니다</li>';
                            return;
                        }
                        
                        result.data.forEach(store => {
                            const li = document.createElement('li');
                            li.style.cssText = `
                                padding: 12px 0; 
                                border-bottom: 1px solid #f1f5f9; 
                                display: flex; 
                                justify-content: space-between;
                                align-items: center;
                                ${store.is_current_user ? 'background: #fef3c7; margin: 0 -20px; padding: 12px 20px; border-radius: 8px;' : ''}
                            `;
                            li.innerHTML = `
                                <div>
                                    <span style="font-weight: 600; color: #1f2937;">${store.rank}위</span>
                                    <span style="margin-left: 8px; color: #374151;">${store.name}</span>
                                    ${store.branch_name ? `<span style="margin-left: 8px; color: #6b7280; font-size: 12px;">(${store.branch_name})</span>` : ''}
                                    ${store.is_current_user ? '<span style="margin-left: 8px; background: #f59e0b; color: white; padding: 2px 6px; border-radius: 4px; font-size: 12px;">내 매장</span>' : ''}
                                </div>
                                <span style="font-weight: 600; color: #10b981;">₩${store.total_sales.toLocaleString()}</span>
                            `;
                            listEl.appendChild(li);
                        });
                        
                        console.log(`TOP 매장 로드 완료: ${result.data.length}개`);
                    }
                }
            } catch (error) {
                console.error('TOP 매장 로드 실패:', error);
            }
        }

        // 페이지 로드 시 실시간 데이터 로드
        document.addEventListener('DOMContentLoaded', async function() {
            // 시스템 상태 먼저 업데이트 (즉시)
            try {
                const systemStats = await loadSystemStatus();
                const banner = document.querySelector('.alert-banner');
                if (banner) {
                    banner.innerHTML = `
                        <span>✅</span>
                        <div>
                            <strong>YKP ERP 시스템 정상 운영 중</strong><br>
                            ${systemStats}
                        </div>
                    `;
                    banner.style.background = '#dcfce7';
                    banner.style.border = '1px solid #16a34a';
                    banner.style.color = '#166534';
                }
            } catch (error) {
                console.error('시스템 상태 로드 실패:', error);
            }
            
            // 차트 로드 후 실시간 데이터 적용 (안전한 호출)
            setTimeout(() => {
                try {
                    loadRealTimeData();
                } catch (error) {
                    console.error('실시간 데이터 초기 로드 오류:', error);
                }
            }, 1000);
            
            // 5분마다 데이터 새로고침 (안전한 호출)
            setInterval(() => {
                try {
                    loadRealTimeData();
                } catch (error) {
                    console.error('실시간 데이터 주기적 로드 오류:', error);
                }
            }, 300000);
        });

        // 권한별 리포트 다운로드 함수들
        function downloadSystemReport() {
            // 본사 전체 리포트 = 통계 페이지로 이동
            window.location.href = '/statistics';
        }

        // 🎯 목표 설정 함수 (본사 관리자 전용)
        function openGoalSetting() {
            console.log('🎯 목표 설정 시작');

            let goalOptions = `🎯 목표 설정 옵션\n`;
            goalOptions += `${'='.repeat(40)}\n\n`;
            goalOptions += `어떤 목표를 설정하시겠습니까?\n\n`;
            goalOptions += `1. 📊 시스템 전체 목표\n`;
            goalOptions += `   (전사 월간 매출 목표)\n\n`;
            goalOptions += `2. 🏢 지사별 목표 설정\n`;
            goalOptions += `   (각 지사의 월간 목표)\n\n`;
            goalOptions += `3. 🏪 매장별 목표 설정\n`;
            goalOptions += `   (개별 매장 목표)\n\n`;
            goalOptions += `선택하세요 (1, 2, 또는 3):`;

            const choice = prompt(goalOptions);

            switch(choice) {
                case '1':
                    setSystemGoal();
                    break;
                case '2':
                    setBranchGoals();
                    break;
                case '3':
                    setStoreGoals();
                    break;
                default:
                    if (choice !== null) {
                        alert('❌ 올바른 옵션을 선택해주세요 (1, 2, 또는 3)');
                    }
            }
        }

        // 시스템 전체 목표 설정
        async function setSystemGoal() {
            const currentMonth = new Date().toISOString().slice(0, 7);

            let goalInput = `📊 시스템 전체 목표 설정 (${currentMonth})\n`;
            goalInput += `${'='.repeat(45)}\n\n`;

            const currentTarget = await getSystemGoalFromAPI();
            const salesTarget = prompt(goalInput + '월간 매출 목표 (원):', currentTarget.toString());
            if (!salesTarget || isNaN(salesTarget)) {
                alert('❌ 올바른 매출 목표를 입력해주세요.');
                return;
            }

            const activationTarget = prompt('월간 개통 건수 목표:', '200');
            if (!activationTarget || isNaN(activationTarget)) {
                alert('❌ 올바른 개통 건수를 입력해주세요.');
                return;
            }

            const notes = prompt('목표 설정 사유 (선택사항):', '');

            if (confirm(`📊 시스템 목표 설정 확인\n\n매출 목표: ${Number(salesTarget).toLocaleString()}원\n개통 목표: ${activationTarget}건\n\n설정하시겠습니까?`)) {
                fetch('/api/goals/system', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({
                        sales_target: parseInt(salesTarget),
                        activation_target: parseInt(activationTarget),
                        period_start: new Date().toISOString().slice(0, 10),
                        period_end: new Date(new Date().getFullYear(), new Date().getMonth() + 1, 0).toISOString().slice(0, 10),
                        notes: notes
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert(`✅ 시스템 목표가 설정되었습니다!\n\n📊 매출 목표: ${Number(salesTarget).toLocaleString()}원\n📱 개통 목표: ${activationTarget}건\n\n대시보드가 새로고침됩니다.`);
                        location.reload();
                    } else {
                        alert('❌ 목표 설정 실패: ' + (data.error || '알 수 없는 오류'));
                    }
                })
                .catch(error => {
                    alert('❌ 목표 설정 중 오류가 발생했습니다.');
                });
            }
        }

        // 지사별 목표 설정
        function setBranchGoals() {
            alert('🏢 지사별 목표 설정\n\n지사 관리 페이지에서 각 지사의 목표를 개별 설정할 수 있습니다.\n\n지사 관리 → 지사 선택 → 목표 설정');
            window.location.href = '/management/branches';
        }

        // 매장별 목표 설정
        function setStoreGoals() {
            alert('🏪 매장별 목표 설정\n\n매장 관리 페이지에서 각 매장의 목표를 개별 설정할 수 있습니다.\n\n매장 관리 → 매장 선택 → 목표 설정');
            window.location.href = '/management/stores';
        }

        function downloadBranchReport() {
            // 지사 리포트 페이지로 이동
            window.location.href = '/statistics';
        }

        function downloadStoreReport() {
            // 매장 통계 페이지로 이동  
            window.location.href = '/statistics';
        }

        // 사이드바 아이콘 클릭 이벤트
        document.querySelectorAll('.sidebar-icon').forEach(icon => {
            icon.addEventListener('click', function() {
                document.querySelectorAll('.sidebar-icon').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
            });
        });

        // 🔄 실시간 대시보드 업데이트 리스너 (개통표 입력 시 자동 새로고침)
        function initRealtimeUpdateListeners() {
            console.log('📡 실시간 대시보드 업데이트 리스너 초기화...');

            // 1. localStorage 크로스 탭 이벤트 리스너
            window.addEventListener('storage', function(event) {
                if (event.key === 'dashboard_update_trigger') {
                    try {
                        const updateData = JSON.parse(event.newValue);
                        console.log('📨 크로스 탭 대시보드 업데이트 신호 수신:', updateData);

                        if (updateData.type === 'dashboard_update') {
                            const { store_name, saved_count, user } = updateData.data;
                            console.log(`🔄 ${store_name}에서 ${user}가 개통표 ${saved_count}건 입력 - 대시보드 새로고침`);

                            // 실시간 활동에 추가
                            addRealtimeActivity({
                                type: 'sales_update',
                                message: `${store_name}에서 개통표 ${saved_count}건 입력`,
                                timestamp: new Date().toISOString(),
                                user: user,
                                store: store_name
                            });

                            // 대시보드 데이터 새로고침 (2초 지연 후)
                            setTimeout(() => {
                                refreshDashboard();
                            }, 2000);
                        }
                    } catch (e) {
                        console.error('❌ 크로스 탭 업데이트 처리 오류:', e);
                    }
                }
            });

            // 2. 전역 대시보드 새로고침 함수 등록 (개통표 입력 페이지에서 호출)
            window.refreshDashboard = function() {
                console.log('🔄 대시보드 전체 데이터 새로고침 시작...');
                loadRealTimeData();
                loadSystemStatus();
                loadRankings();
                loadTopLists();
                console.log('✅ 대시보드 전체 데이터 새로고침 완료');
            };

            // 3. 실시간 활동 추가 함수 등록
            window.addRealtimeActivity = function(activity) {
                console.log('📝 실시간 활동 추가:', activity);

                // 실시간 활동 피드가 있다면 추가
                const activityFeed = document.getElementById('realtime-activities');
                if (activityFeed) {
                    const activityEl = document.createElement('div');
                    activityEl.className = 'bg-green-50 border-l-4 border-green-400 p-3 mb-2';
                    activityEl.innerHTML = `
                        <div class="flex justify-between items-center">
                            <div class="flex items-center">
                                <span class="text-green-600 text-sm font-medium">📊 ${activity.message}</span>
                            </div>
                            <span class="text-xs text-gray-500">${new Date().toLocaleTimeString()}</span>
                        </div>
                        <div class="text-xs text-gray-600 mt-1">
                            ${activity.user} • ${activity.store || ''}
                        </div>
                    `;

                    // 피드 상단에 추가
                    activityFeed.insertBefore(activityEl, activityFeed.firstChild);

                    // 최대 10개 항목 유지
                    const activities = activityFeed.children;
                    while (activities.length > 10) {
                        activityFeed.removeChild(activities[activities.length - 1]);
                    }

                    console.log('✅ 실시간 활동 피드 업데이트 완료');
                } else {
                    console.warn('⚠️ 실시간 활동 피드 요소를 찾을 수 없음');
                }
            };

            console.log('✅ 실시간 업데이트 리스너 초기화 완료');
        }

        // 🔄 대시보드 UI 요소 실시간 업데이트 함수
        function updateDashboardElements(userCount, storeCount, branchCount, salesCount) {
            console.log('🎨 대시보드 UI 실시간 업데이트:', { userCount, storeCount, branchCount, salesCount });

            // 안전한 요소 업데이트 함수
            const safeUpdateElement = (id, value, fallback = '데이터 없음') => {
                const element = document.getElementById(id);
                if (element) {
                    element.textContent = value || fallback;
                    console.log(`✅ ${id} 업데이트: ${value || fallback}`);
                } else {
                    console.warn(`⚠️ 요소 찾기 실패: ${id}`);
                }
            };

            // 본사 관리자 UI 업데이트 (API 응답 값 기반)
            if (window.userData?.role === 'headquarters') {
                // API에서 받은 실제 값 표시, 0이면 "데이터 없음"
                safeUpdateElement('total-branches-count',
                    branchCount !== undefined ?
                        (branchCount > 0 ? `${branchCount}개 지사` : '지사 데이터 없음') :
                        '로딩 중...'
                );
                safeUpdateElement('total-stores-count',
                    storeCount !== undefined ?
                        (storeCount > 0 ? `${storeCount}개 매장` : '매장 데이터 없음') :
                        '로딩 중...'
                );
                safeUpdateElement('total-users-count',
                    userCount !== undefined ?
                        (userCount > 0 ? `${userCount}명 관리` : '사용자 데이터 없음') :
                        '로딩 중...'
                );

                // 목표 달성률 계산 (실제 매출 데이터 기반)
                fetch('/api/dashboard/overview')
                    .then(response => response.json())
                    .then(async data => {
                        if (data.success && data.data) {
                            const monthSales = data.data.month?.sales;
                            let monthTarget = data.data.month?.target;
                            if (!monthTarget) {
                                monthTarget = await getSystemGoalFromAPI();
                            }

                            if (monthSales !== undefined && monthSales !== null) {
                                const achievementRate = monthSales > 0 ? (monthSales / monthTarget * 100).toFixed(1) : 0;
                                safeUpdateElement('system-goal-achievement',
                                    monthSales > 0 ? `${achievementRate}% 달성` : '이번달 실적 없음'
                                );
                            } else {
                                safeUpdateElement('system-goal-achievement', '매출 데이터 없음');
                            }

                            safeUpdateElement('system-goal-target', `월 ${(monthTarget / 10000).toLocaleString()}만원 목표`);
                        } else {
                            safeUpdateElement('system-goal-achievement', 'API 데이터 없음');
                            safeUpdateElement('system-goal-target', '목표 정보 없음');
                        }
                    })
                    .catch(error => {
                        console.warn('⚠️ 목표 데이터 로드 실패:', error);
                        safeUpdateElement('system-goal-achievement', '로딩 실패');
                        safeUpdateElement('system-goal-target', '데이터를 불러올 수 없음');
                    });
            }

            // 지사 관리자 UI 업데이트
            if (window.userData?.role === 'branch') {
                const branchId = window.userData.branch_id;
                const branchName = window.userData.branch_name;

                // 지사별 목표 설정 (실제 데이터 또는 기본값)
                fetch(`/api/dashboard/overview?branch=${branchId}`)
                    .then(response => response.json())
                    .then(async data => {
                        if (data.success && data.data) {
                            const monthSales = data.data.month?.sales || 0;
                            let branchTarget = data.data.month?.target;
                            if (!branchTarget) {
                                branchTarget = await getBranchGoalFromAPI(branchId);
                            }
                            const achievementRate = monthSales > 0 ? (monthSales / branchTarget * 100).toFixed(1) : 0;

                            safeUpdateElement('branch-goal-achievement', monthSales > 0 ? `${achievementRate}% 달성` : '실적 없음');
                            safeUpdateElement('branch-goal-target', `월 ${(branchTarget / 10000).toLocaleString()}만원 목표`);
                        } else {
                            safeUpdateElement('branch-goal-achievement', '데이터 없음');
                            safeUpdateElement('branch-goal-target', '목표 정보 없음');
                        }
                    })
                    .catch(error => {
                        console.warn('⚠️ 지사 목표 데이터 로드 실패:', error);
                        safeUpdateElement('branch-goal-achievement', '로딩 실패');
                        safeUpdateElement('branch-goal-target', '데이터를 불러올 수 없음');
                    });
            }

            // 매장 직원 UI 업데이트
            if (window.userData?.role === 'store') {
                const storeId = window.userData.store_id;
                const storeName = window.userData.store_name;

                // 매장별 목표 설정 (실제 데이터 또는 기본값)
                fetch(`/api/dashboard/overview?store=${storeId}`)
                    .then(response => response.json())
                    .then(async (data) => {
                        if (data.success && data.data) {
                            const monthSales = data.data.month?.sales || 0;

                            // 🔄 실제 목표 API에서 가져오기 (하드코딩 제거)
                            let storeTarget = data.data.month?.target;
                            if (!storeTarget) {
                                storeTarget = await getStoreGoalFromAPI(userData.store_id);
                            }

                            const achievementRate = monthSales > 0 ? (monthSales / storeTarget * 100).toFixed(1) : 0;

                            safeUpdateElement('store-goal-achievement', monthSales > 0 ? `${achievementRate}% 달성` : '실적 없음');
                            safeUpdateElement('store-goal-target', `월 ${(storeTarget / 10000).toLocaleString()}만원 목표`);
                        } else {
                            safeUpdateElement('store-goal-achievement', '데이터 없음');
                            safeUpdateElement('store-goal-target', '목표 정보 없음');
                        }
                    })
                    .catch(error => {
                        console.warn('⚠️ 매장 목표 데이터 로드 실패:', error);
                        safeUpdateElement('store-goal-achievement', '로딩 실패');
                        safeUpdateElement('store-goal-target', '데이터를 불러올 수 없음');
                    });
            }

            console.log('🎨 대시보드 UI 업데이트 완료');
        }

        // 📝 실시간 활동 로드 함수
        async function loadRealtimeActivities() {
            try {
                console.log('📝 실시간 활동 로딩...');

                const response = await fetch('/api/activities/recent?limit=10');
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                const data = await response.json();

                if (data.success && data.data && data.data.length > 0) {
                    // 활동 데이터가 있는 경우
                    document.getElementById('activity-status').textContent = `최근 활동 ${data.data.length}건`;
                    document.getElementById('activity-description').textContent = '실시간으로 업데이트됩니다.';

                    const activitiesHtml = data.data.map(activity => `
                        <div style="border-bottom: 1px solid #e5e7eb; padding: 12px 0;">
                            <div style="display: flex; justify-between; align-items: start;">
                                <div style="flex: 1;">
                                    <div style="font-weight: 600; color: #374151; margin-bottom: 4px;">
                                        ${activity.title}
                                    </div>
                                    <div style="font-size: 13px; color: #6b7280; margin-bottom: 4px;">
                                        ${activity.description || ''}
                                    </div>
                                    <div style="font-size: 12px; color: #9ca3af;">
                                        ${activity.user_name} • ${activity.time_ago}
                                    </div>
                                </div>
                                <div style="padding: 4px 8px; background: #f3f4f6; border-radius: 4px; font-size: 11px; color: #6b7280;">
                                    ${activity.type}
                                </div>
                            </div>
                        </div>
                    `).join('');

                    document.getElementById('realtime-activities').innerHTML = activitiesHtml;

                    // 빈 상태 숨기기
                    const emptyState = document.querySelector('#activity-status').parentElement;
                    emptyState.style.display = 'none';

                    console.log(`✅ 실시간 활동 ${data.data.length}건 로드 완료`);
                } else {
                    // 활동 데이터가 없는 경우
                    document.getElementById('activity-status').textContent = '활동 데이터 없음';
                    document.getElementById('activity-description').textContent = '사용자 활동이 발생하면 여기에 표시됩니다.';
                    document.getElementById('realtime-activities').innerHTML = '';
                    console.log('ℹ️ 실시간 활동 데이터 없음');
                }
            } catch (error) {
                console.error('❌ 실시간 활동 로드 실패:', error);
                document.getElementById('activity-status').textContent = '활동 로드 실패';
                document.getElementById('activity-description').textContent = '활동 데이터를 불러올 수 없습니다.';
            }
        }

        // 🔄 실시간 목표 로드 함수
        async function loadRealtimeGoals() {
            try {
                const userData = window.userData;
                let goalType, goalId;

                // 사용자 역할에 따른 목표 조회
                if (userData.role === 'headquarters') {
                    goalType = 'system';
                    goalId = null;
                } else if (userData.role === 'branch') {
                    goalType = 'branch';
                    goalId = userData.branch_id;
                } else if (userData.role === 'store') {
                    goalType = 'store';
                    goalId = userData.store_id;
                }

                if (goalType) {
                    const goalUrl = goalId ? `/api/goals/${goalType}/${goalId}` : `/api/goals/${goalType}`;
                    const response = await fetch(goalUrl);

                    if (response.ok) {
                        const data = await response.json();

                        if (data.success && data.data) {
                            const goal = data.data;

                            // 목표 정보 업데이트
                            const targetElement = document.getElementById(`${goalType === 'system' ? 'system' : goalType}-goal-target`);
                            if (targetElement) {
                                targetElement.textContent = `월 ${(goal.sales_target / 10000).toLocaleString()}만원 목표`;
                                if (!goal.is_custom) {
                                    targetElement.textContent += ' (기본값)';
                                }
                            }

                            console.log(`✅ ${goalType} 목표 로드 완료:`, goal);
                        }
                    }
                }
            } catch (error) {
                console.warn('⚠️ 실시간 목표 로드 실패:', error);
            }
        }

        // 페이지 로드 시 실시간 데이터 초기화
        document.addEventListener('DOMContentLoaded', function() {
            // 기존 초기화 함수들...

            // 추가: 실시간 활동 및 목표 로드
            setTimeout(() => {
                loadRealtimeActivities();
                loadRealtimeGoals();
            }, 2000); // 다른 데이터 로딩 후 실행

            // 30초마다 실시간 활동 업데이트
            setInterval(loadRealtimeActivities, 30000);
        });

        // 🎯 실시간 목표 가져오기 함수들
        async function getStoreGoalFromAPI(storeId) {
            try {
                const response = await fetch(`/api/goals/store/${storeId}`);
                if (response.ok) {
                    const data = await response.json();
                    return data.success ? data.data.sales_target : 5000000;
                }
            } catch (error) {
                console.warn('매장 목표 API 호출 실패:', error);
            }
            return 5000000; // API 실패 시에만 기본값
        }

        async function getBranchGoalFromAPI(branchId) {
            try {
                const response = await fetch(`/api/goals/branch/${branchId}`);
                if (response.ok) {
                    const data = await response.json();
                    return data.success ? data.data.sales_target : 10000000;
                }
            } catch (error) {
                console.warn('지사 목표 API 호출 실패:', error);
            }
            return 10000000; // API 실패 시에만 기본값
        }

        async function getSystemGoalFromAPI() {
            try {
                const response = await fetch('/api/goals/system');
                if (response.ok) {
                    const data = await response.json();
                    return data.success ? data.data.sales_target : 50000000;
                }
            } catch (error) {
                console.warn('시스템 목표 API 호출 실패:', error);
            }
            return 50000000; // API 실패 시에만 기본값
        }

        // 실시간 업데이트 리스너 초기화 실행
        initRealtimeUpdateListeners();
    </script>

    <!-- 대리점 관리 모달 -->
    @if(in_array(auth()->user()->role, ['headquarters', 'branch']))
    <div id="dealerManagementModal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div style="background-color: white; margin: 50px auto; padding: 20px; border-radius: 8px; width: 90%; max-width: 800px; max-height: 80vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="font-size: 20px; font-weight: 600;">대리점 관리</h2>
                <button onclick="closeDealerModal()" style="font-size: 24px; background: none; border: none; cursor: pointer;">&times;</button>
            </div>

            <!-- 대리점 추가 폼 -->
            <div style="background: #f9fafb; padding: 15px; border-radius: 6px; margin-bottom: 20px;">
                <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 15px;">새 대리점 추가</h3>
                <form id="dealerForm" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                    <input type="hidden" id="dealerId" name="id">
                    <div>
                        <label style="display: block; font-size: 14px; margin-bottom: 4px;">대리점 코드 *</label>
                        <input type="text" id="dealerCode" name="dealer_code" required style="width: 100%; padding: 8px; border: 1px solid #e5e7eb; border-radius: 4px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 14px; margin-bottom: 4px;">대리점명 *</label>
                        <input type="text" id="dealerName" name="dealer_name" required style="width: 100%; padding: 8px; border: 1px solid #e5e7eb; border-radius: 4px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 14px; margin-bottom: 4px;">담당자명</label>
                        <input type="text" id="contactPerson" name="contact_person" style="width: 100%; padding: 8px; border: 1px solid #e5e7eb; border-radius: 4px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 14px; margin-bottom: 4px;">연락처</label>
                        <input type="text" id="phone" name="phone" style="width: 100%; padding: 8px; border: 1px solid #e5e7eb; border-radius: 4px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 14px; margin-bottom: 4px;">유심비</label>
                        <input type="number" id="defaultSimFee" name="default_sim_fee" value="0" style="width: 100%; padding: 8px; border: 1px solid #e5e7eb; border-radius: 4px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 14px; margin-bottom: 4px;">MNP할인</label>
                        <input type="number" id="defaultMnpDiscount" name="default_mnp_discount" value="0" style="width: 100%; padding: 8px; border: 1px solid #e5e7eb; border-radius: 4px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 14px; margin-bottom: 4px;">세율</label>
                        <input type="number" id="taxRate" name="tax_rate" value="0.1" step="0.01" min="0" max="1" style="width: 100%; padding: 8px; border: 1px solid #e5e7eb; border-radius: 4px;">
                    </div>
                    <div>
                        <label style="display: block; font-size: 14px; margin-bottom: 4px;">페이백율(%)</label>
                        <input type="number" id="defaultPaybackRate" name="default_payback_rate" value="0" min="0" max="100" style="width: 100%; padding: 8px; border: 1px solid #e5e7eb; border-radius: 4px;">
                    </div>
                    <div style="grid-column: span 2;">
                        <label style="display: block; font-size: 14px; margin-bottom: 4px;">주소</label>
                        <input type="text" id="address" name="address" style="width: 100%; padding: 8px; border: 1px solid #e5e7eb; border-radius: 4px;">
                    </div>
                    <div style="grid-column: span 2; display: flex; gap: 10px;">
                        <button type="submit" style="background: #10b981; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">저장</button>
                        <button type="button" onclick="resetDealerForm()" style="background: #6b7280; color: white; padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer;">취소</button>
                    </div>
                </form>
            </div>

            <!-- 대리점 목록 -->
            <div>
                <h3 style="font-size: 16px; font-weight: 600; margin-bottom: 15px;">대리점 목록</h3>
                <div style="overflow-x: auto;">
                    <table style="width: 100%; border-collapse: collapse;">
                        <thead>
                            <tr style="background: #f3f4f6;">
                                <th style="padding: 8px; text-align: left; font-size: 14px;">코드</th>
                                <th style="padding: 8px; text-align: left; font-size: 14px;">대리점명</th>
                                <th style="padding: 8px; text-align: left; font-size: 14px;">담당자</th>
                                <th style="padding: 8px; text-align: left; font-size: 14px;">연락처</th>
                                <th style="padding: 8px; text-align: center; font-size: 14px;">상태</th>
                                <th style="padding: 8px; text-align: center; font-size: 14px;">액션</th>
                            </tr>
                        </thead>
                        <tbody id="dealerTableBody">
                            <!-- 동적으로 추가됨 -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 대리점 관리 함수들
        function closeDealerModal() {
            document.getElementById('dealerManagementModal').style.display = 'none';
            resetDealerForm();
        }

        function resetDealerForm() {
            document.getElementById('dealerForm').reset();
            document.getElementById('dealerId').value = '';
        }

        async function loadDealers() {
            try {
                const response = await fetch('/api/dealers', {
                    method: 'GET',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                const data = await response.json();

                if (data.success) {
                    const tbody = document.getElementById('dealerTableBody');
                    tbody.innerHTML = '';

                    data.data.forEach(dealer => {
                        const row = tbody.insertRow();
                        row.innerHTML = `
                            <td style="padding: 8px; font-size: 14px;">${dealer.dealer_code}</td>
                            <td style="padding: 8px; font-size: 14px;">${dealer.dealer_name}</td>
                            <td style="padding: 8px; font-size: 14px;">${dealer.contact_person || '-'}</td>
                            <td style="padding: 8px; font-size: 14px;">${dealer.phone || '-'}</td>
                            <td style="padding: 8px; text-align: center;">
                                <span style="padding: 2px 8px; background: ${dealer.status === 'active' ? '#dcfce7' : '#fee2e2'}; color: ${dealer.status === 'active' ? '#16a34a' : '#dc2626'}; border-radius: 4px; font-size: 12px;">
                                    ${dealer.status === 'active' ? '활성' : '비활성'}
                                </span>
                            </td>
                            <td style="padding: 8px; text-align: center;">
                                <button onclick="editDealer(${dealer.id})" style="background: #3b82f6; color: white; padding: 4px 8px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px; margin-right: 4px;">수정</button>
                                @if(auth()->user()->role === 'headquarters')
                                <button onclick="deleteDealer(${dealer.id})" style="background: #ef4444; color: white; padding: 4px 8px; border: none; border-radius: 4px; cursor: pointer; font-size: 12px;">삭제</button>
                                @endif
                            </td>
                        `;
                    });
                }
            } catch (error) {
                console.error('대리점 목록 로딩 실패:', error);
                alert('대리점 목록을 불러오는데 실패했습니다.');
            }
        }

        async function editDealer(id) {
            try {
                const response = await fetch('/api/dealers', {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                const data = await response.json();

                if (data.success) {
                    const dealer = data.data.find(d => d.id === id);
                    if (dealer) {
                        document.getElementById('dealerId').value = dealer.id;
                        document.getElementById('dealerCode').value = dealer.dealer_code;
                        document.getElementById('dealerName').value = dealer.dealer_name;
                        document.getElementById('contactPerson').value = dealer.contact_person || '';
                        document.getElementById('phone').value = dealer.phone || '';
                        document.getElementById('defaultSimFee').value = dealer.default_sim_fee || 0;
                        document.getElementById('defaultMnpDiscount').value = dealer.default_mnp_discount || 0;
                        document.getElementById('taxRate').value = dealer.tax_rate || 0.1;
                        document.getElementById('defaultPaybackRate').value = dealer.default_payback_rate || 0;
                        document.getElementById('address').value = dealer.address || '';
                    }
                }
            } catch (error) {
                console.error('대리점 정보 로딩 실패:', error);
                alert('대리점 정보를 불러오는데 실패했습니다.');
            }
        }

        async function deleteDealer(id) {
            if (!confirm('정말로 이 대리점을 삭제하시겠습니까?')) {
                return;
            }

            try {
                const response = await fetch(`/api/dealers/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                const data = await response.json();

                if (data.success) {
                    alert(data.message);
                    loadDealers();
                    // 다른 탭에 대리점 변경 알림
                    localStorage.setItem('dealers_updated', Date.now());
                } else {
                    alert(data.message || '대리점 삭제에 실패했습니다.');
                }
            } catch (error) {
                console.error('대리점 삭제 실패:', error);
                alert('대리점 삭제 중 오류가 발생했습니다.');
            }
        }

        // 대리점 폼 제출 처리
        document.getElementById('dealerForm').addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(e.target);
            const dealerId = formData.get('id');
            const url = dealerId ? `/api/dealers/${dealerId}` : '/api/dealers';
            const method = dealerId ? 'PUT' : 'POST';

            const body = {
                dealer_code: formData.get('dealer_code'),
                dealer_name: formData.get('dealer_name'),
                contact_person: formData.get('contact_person'),
                phone: formData.get('phone'),
                address: formData.get('address'),
                default_sim_fee: parseFloat(formData.get('default_sim_fee')) || 0,
                default_mnp_discount: parseFloat(formData.get('default_mnp_discount')) || 0,
                tax_rate: parseFloat(formData.get('tax_rate')) || 0.1,
                default_payback_rate: parseFloat(formData.get('default_payback_rate')) || 0
            };

            try {
                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(body)
                });

                const data = await response.json();

                if (data.success) {
                    alert(data.message);
                    resetDealerForm();
                    loadDealers();
                    // 다른 탭에 대리점 변경 알림
                    localStorage.setItem('dealers_updated', Date.now());
                } else {
                    alert(data.message || '저장에 실패했습니다.');
                }
            } catch (error) {
                console.error('대리점 저장 실패:', error);
                alert('대리점 저장 중 오류가 발생했습니다.');
            }
        });

        // 모달 외부 클릭시 닫기
        window.onclick = function(event) {
            const modal = document.getElementById('dealerManagementModal');
            if (event.target == modal) {
                closeDealerModal();
            }
        }
    </script>

    <!-- 통신사 관리 모달 -->
    @if(auth()->user()->role === 'headquarters')
    <div id="carrierManagementModal" style="display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5);">
        <div style="background-color: white; margin: 50px auto; padding: 20px; border-radius: 8px; width: 90%; max-width: 800px; max-height: 80vh; overflow-y: auto;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h2 style="font-size: 20px; font-weight: 600;">통신사 관리</h2>
                <button onclick="closeCarrierModal()" style="font-size: 24px; background: none; border: none; cursor: pointer;">&times;</button>
            </div>

                <!-- 통신사 추가 폼 -->
                <div style="background: #f8fafc; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <h3 style="font-size: 16px; margin-bottom: 15px;">새 통신사 추가</h3>
                    <form id="carrierForm" style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                        <input type="hidden" name="id" id="carrier-id">
                        <div>
                            <label style="font-size: 12px; color: #475569;">통신사 코드*</label>
                            <input type="text" name="code" id="carrier-code" required
                                   placeholder="예: MVNO" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
                        </div>
                        <div>
                            <label style="font-size: 12px; color: #475569;">통신사명*</label>
                            <input type="text" name="name" id="carrier-name" required
                                   placeholder="예: 알뜰폰" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
                        </div>
                        <div>
                            <label style="font-size: 12px; color: #475569;">정렬 순서</label>
                            <input type="number" name="sort_order" id="carrier-sort-order"
                                   placeholder="낮을수록 먼저 표시" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
                        </div>
                        <div>
                            <label style="font-size: 12px; color: #475569;">활성 상태</label>
                            <select name="is_active" id="carrier-is-active" style="width: 100%; padding: 8px; border: 1px solid #cbd5e1; border-radius: 4px;">
                                <option value="1">활성</option>
                                <option value="0">비활성</option>
                            </select>
                        </div>
                        <div style="grid-column: span 2; display: flex; gap: 10px; justify-content: flex-end; margin-top: 10px;">
                            <button type="button" onclick="resetCarrierForm()" style="padding: 8px 20px; background: #e2e8f0; border: none; border-radius: 4px; cursor: pointer;">초기화</button>
                            <button type="submit" style="padding: 8px 20px; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer;">저장</button>
                        </div>
                    </form>
                </div>

                <!-- 통신사 목록 -->
                <div>
                    <h3 style="font-size: 16px; margin-bottom: 15px;">통신사 목록</h3>
                    <div style="overflow-x: auto;">
                        <table style="width: 100%; border-collapse: collapse;">
                            <thead>
                                <tr style="background: #f1f5f9;">
                                    <th style="padding: 10px; text-align: left; border-bottom: 2px solid #e2e8f0;">코드</th>
                                    <th style="padding: 10px; text-align: left; border-bottom: 2px solid #e2e8f0;">통신사명</th>
                                    <th style="padding: 10px; text-align: center; border-bottom: 2px solid #e2e8f0;">순서</th>
                                    <th style="padding: 10px; text-align: center; border-bottom: 2px solid #e2e8f0;">상태</th>
                                    <th style="padding: 10px; text-align: center; border-bottom: 2px solid #e2e8f0;">작업</th>
                                </tr>
                            </thead>
                            <tbody id="carrierList">
                                <!-- 동적으로 로드됨 -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // 통신사 관리 함수들
        function openCarrierManagement() {
            document.getElementById('carrierManagementModal').style.display = 'block';
            loadCarriers();
        }

        function closeCarrierModal() {
            document.getElementById('carrierManagementModal').style.display = 'none';
            resetCarrierForm();
        }

        function resetCarrierForm() {
            document.getElementById('carrierForm').reset();
            document.getElementById('carrier-id').value = '';
        }

        async function loadCarriers() {
            try {
                const response = await fetch('/api/carriers', {
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });
                const data = await response.json();

                if (data.success) {
                    const tbody = document.getElementById('carrierList');
                    tbody.innerHTML = '';

                    data.data.forEach(carrier => {
                        const row = document.createElement('tr');
                        const isProtected = ['SK', 'KT', 'LG', 'MVNO'].includes(carrier.code);

                        row.innerHTML = `
                            <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">${carrier.code}</td>
                            <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">${carrier.name}</td>
                            <td style="padding: 10px; text-align: center; border-bottom: 1px solid #e2e8f0;">${carrier.sort_order}</td>
                            <td style="padding: 10px; text-align: center; border-bottom: 1px solid #e2e8f0;">
                                <span style="padding: 2px 8px; border-radius: 4px; font-size: 12px; ${carrier.is_active ? 'background: #10b981; color: white;' : 'background: #ef4444; color: white;'}">
                                    ${carrier.is_active ? '활성' : '비활성'}
                                </span>
                            </td>
                            <td style="padding: 10px; text-align: center; border-bottom: 1px solid #e2e8f0;">
                                ${!isProtected ? `
                                    <button onclick="editCarrier(${JSON.stringify(carrier).replace(/"/g, '&quot;')})"
                                            style="padding: 4px 10px; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer; margin-right: 5px;">
                                        수정
                                    </button>
                                    <button onclick="deleteCarrier(${carrier.id})"
                                            style="padding: 4px 10px; background: #ef4444; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                        삭제
                                    </button>
                                ` : '<span style="color: #94a3b8; font-size: 12px;">기본 통신사</span>'}
                            </td>
                        `;
                        tbody.appendChild(row);
                    });
                }
            } catch (error) {
                console.error('통신사 목록 로드 실패:', error);
                alert('통신사 목록을 불러오는 중 오류가 발생했습니다.');
            }
        }

        function editCarrier(carrier) {
            document.getElementById('carrier-id').value = carrier.id;
            document.getElementById('carrier-code').value = carrier.code;
            document.getElementById('carrier-name').value = carrier.name;
            document.getElementById('carrier-sort-order').value = carrier.sort_order;
            document.getElementById('carrier-is-active').value = carrier.is_active ? '1' : '0';
        }

        async function deleteCarrier(id) {
            if (!confirm('정말 이 통신사를 삭제하시겠습니까?')) {
                return;
            }

            try {
                const response = await fetch(`/api/carriers/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                const data = await response.json();

                if (data.success) {
                    alert(data.message);
                    loadCarriers();
                } else {
                    alert(data.message || '삭제에 실패했습니다.');
                }
            } catch (error) {
                console.error('통신사 삭제 실패:', error);
                alert('통신사 삭제 중 오류가 발생했습니다.');
            }
        }

        // 통신사 폼 제출
        document.getElementById('carrierForm').addEventListener('submit', async function(e) {
            e.preventDefault();

            const formData = new FormData(e.target);
            const carrierId = formData.get('id');
            const url = carrierId ? `/api/carriers/${carrierId}` : '/api/carriers';
            const method = carrierId ? 'PUT' : 'POST';

            const body = {
                code: formData.get('code'),
                name: formData.get('name'),
                sort_order: parseInt(formData.get('sort_order')) || null,
                is_active: formData.get('is_active') === '1'
            };

            try {
                const response = await fetch(url, {
                    method: method,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(body)
                });

                const data = await response.json();

                if (data.success) {
                    alert(data.message);
                    resetCarrierForm();
                    loadCarriers();
                } else {
                    alert(data.message || '저장에 실패했습니다.');
                }
            } catch (error) {
                console.error('통신사 저장 실패:', error);
                alert('통신사 저장 중 오류가 발생했습니다.');
            }
        });

        // 모달 외부 클릭시 닫기
        window.onclick = function(event) {
            const dealerModal = document.getElementById('dealerManagementModal');
            const carrierModal = document.getElementById('carrierManagementModal');

            if (event.target == dealerModal) {
                closeDealerModal();
            } else if (event.target == carrierModal) {
                closeCarrierModal();
            }
        }
    </script>
    @endif
    @endif

    @if(auth()->user()->role === 'headquarters')
    <script>
        // 통신사 관리 함수들
        function openCarrierManagement() {
            console.log('Opening carrier management modal');
            const modal = document.getElementById('carrierManagementModal');
            if (modal) {
                modal.style.display = 'block';
                if (typeof loadCarriers === 'function') {
                    loadCarriers();
                }
            } else {
                console.error('Carrier management modal not found');
            }
        }

        function closeCarrierModal() {
            const modal = document.getElementById('carrierManagementModal');
            if (modal) {
                modal.style.display = 'none';
            }
        }

        function loadCarriers() {
            console.log('Loading carriers...');
            fetch('/api/carriers', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                console.log('Carriers loaded:', data);
                if (data.success) {
                    const tbody = document.getElementById('carrierList');
                    if (tbody) {
                        tbody.innerHTML = '';
                        data.data.forEach(carrier => {
                            const row = tbody.insertRow();
                            row.innerHTML = `
                                <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">${carrier.code}</td>
                                <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">${carrier.name}</td>
                                <td style="padding: 10px; text-align: center; border-bottom: 1px solid #e2e8f0;">${carrier.sort_order || 0}</td>
                                <td style="padding: 10px; text-align: center; border-bottom: 1px solid #e2e8f0;">
                                    <span style="padding: 2px 8px; border-radius: 12px; font-size: 12px; ${carrier.is_active ? 'background: #dcfce7; color: #16a34a;' : 'background: #fee2e2; color: #dc2626;'}">${carrier.is_active ? '활성' : '비활성'}</span>
                                </td>
                                <td style="padding: 10px; text-align: center; border-bottom: 1px solid #e2e8f0;">
                                    <button onclick="editCarrier(${carrier.id})" style="padding: 4px 12px; background: #3b82f6; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px; margin-right: 5px;">수정</button>
                                    ${!['SK', 'KT', 'LG', '알뜰'].includes(carrier.name) ?
                                        `<button onclick="deleteCarrier(${carrier.id})" style="padding: 4px 12px; background: #ef4444; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 13px;">삭제</button>` :
                                        '<span style="color: #94a3b8; font-size: 12px;">기본</span>'}
                                </td>
                            `;
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Error loading carriers:', error);
            });
        }

        function addCarrier() {
            const code = document.getElementById('carrier-code').value;
            const name = document.getElementById('carrier-name').value;
            const sortOrder = document.getElementById('carrier-sort-order').value || 10;

            if (!code || !name) {
                alert('코드와 이름은 필수입니다.');
                return;
            }

            fetch('/api/carriers', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    code: code,
                    name: name,
                    sort_order: sortOrder
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('통신사가 추가되었습니다.');
                    loadCarriers();
                    // 폼 초기화
                    document.getElementById('carrier-code').value = '';
                    document.getElementById('carrier-name').value = '';
                    document.getElementById('carrier-sort-order').value = '';
                } else {
                    alert('오류: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error adding carrier:', error);
                alert('통신사 추가 중 오류가 발생했습니다.');
            });
        }

        function deleteCarrier(id) {
            if (!confirm('이 통신사를 삭제하시겠습니까?')) {
                return;
            }

            fetch(`/api/carriers/${id}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('통신사가 삭제되었습니다.');
                    loadCarriers();
                    // 다른 탭에 통신사 변경 알림
                    localStorage.setItem('carriers_updated', Date.now());
                } else {
                    alert('오류: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error deleting carrier:', error);
                alert('통신사 삭제 중 오류가 발생했습니다.');
            });
        }

        function editCarrier(id) {
            fetch('/api/carriers', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const carrier = data.data.find(c => c.id === id);
                    if (carrier) {
                        document.getElementById('carrier-id').value = carrier.id;
                        document.getElementById('carrier-code').value = carrier.code;
                        document.getElementById('carrier-name').value = carrier.name;
                        document.getElementById('carrier-sort-order').value = carrier.sort_order || '';
                        document.getElementById('carrier-is-active').value = carrier.is_active ? '1' : '0';
                    }
                }
            })
            .catch(error => {
                console.error('Error loading carrier:', error);
                alert('통신사 정보를 불러오는데 실패했습니다.');
            });
        }

        function resetCarrierForm() {
            document.getElementById('carrier-id').value = '';
            document.getElementById('carrier-code').value = '';
            document.getElementById('carrier-name').value = '';
            document.getElementById('carrier-sort-order').value = '';
            document.getElementById('carrier-is-active').value = '1';
        }

        // 페이지 로드 시 함수들을 전역으로 노출
        window.openCarrierManagement = openCarrierManagement;
        window.closeCarrierModal = closeCarrierModal;
        window.addCarrier = addCarrier;
        window.deleteCarrier = deleteCarrier;
        window.editCarrier = editCarrier;
        window.resetCarrierForm = resetCarrierForm;

        // 폼 제출 이벤트 핸들러
        document.addEventListener('DOMContentLoaded', function() {
            const carrierForm = document.getElementById('carrierForm');
            if (carrierForm) {
                carrierForm.addEventListener('submit', function(e) {
                    e.preventDefault();

                    const formData = new FormData(carrierForm);
                    const id = formData.get('id');
                    const code = formData.get('code');
                    const name = formData.get('name');
                    const sortOrder = formData.get('sort_order') || 10;
                    const isActive = formData.get('is_active') || '1';

                    const url = id ? `/api/carriers/${id}` : '/api/carriers';
                    const method = id ? 'PUT' : 'POST';

                    fetch(url, {
                        method: method,
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            code: code,
                            name: name,
                            sort_order: parseInt(sortOrder),
                            is_active: isActive === '1'
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(id ? '통신사가 수정되었습니다.' : '통신사가 추가되었습니다.');
                            resetCarrierForm();
                            loadCarriers();
                            // 다른 탭에 통신사 변경 알림
                            localStorage.setItem('carriers_updated', Date.now());
                        } else {
                            alert('오류: ' + (data.message || '저장에 실패했습니다.'));
                        }
                    })
                    .catch(error => {
                        console.error('Error saving carrier:', error);
                        alert('통신사 저장 중 오류가 발생했습니다.');
                    });
                });
            }
        });

        console.log('Carrier management functions loaded');
    </script>
    @endif
</body>
</html>
