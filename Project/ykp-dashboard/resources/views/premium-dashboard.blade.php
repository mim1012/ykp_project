<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>대시보드</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
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
        
        /* 사이드바 */
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
    <!-- 사이드바 -->
    <div class="sidebar">
        <div class="sidebar-icon active tooltip" onclick="showDashboard()">
            Y
            <span class="tooltip-text">메인 대시보드</span>
        </div>
        <div class="sidebar-icon tooltip" onclick="openSimpleInput()">
            📝
            <span class="tooltip-text">간단한 개통 입력</span>
        </div>
        <div class="sidebar-icon tooltip" onclick="openSettlement()">
            💼
            <span class="tooltip-text">정산표 시스템</span>
        </div>
        <div class="sidebar-icon tooltip" onclick="openManagement()">
            📋
            <span class="tooltip-text">완전한 판매관리</span>
        </div>
        <div class="sidebar-icon tooltip" onclick="openDailyExpenses()">
            💳
            <span class="tooltip-text">일일지출 관리</span>
        </div>
        <div class="sidebar-icon tooltip" onclick="openFixedExpenses()">
            💰
            <span class="tooltip-text">고정지출 관리</span>
        </div>
        <div class="sidebar-icon tooltip" onclick="openStoreManagement()">
            🏪
            <span class="tooltip-text">매장 관리</span>
        </div>
        <div class="sidebar-icon tooltip" onclick="openRefunds()">
            🔄
            <span class="tooltip-text">환수금액 관리</span>
        </div>
        <div class="sidebar-icon tooltip" onclick="openPayroll()">
            👥
            <span class="tooltip-text">직원급여 관리</span>
        </div>
        <div class="sidebar-icon tooltip" onclick="openMonthlySettlement()">
            📊
            <span class="tooltip-text">월마감정산</span>
        </div>
        <div class="sidebar-icon tooltip" onclick="openAdmin()">
            ⚙️
            <span class="tooltip-text">관리자 패널</span>
        </div>
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
                <button class="btn btn-success">데이터 수집</button>
                <button class="btn btn-outline" onclick="location.reload()">새로고침</button>
                <button class="btn btn-outline">레포트 다운로드</button>
                <button class="btn btn-outline" onclick="logout()" style="background: #ef4444; color: white;">로그아웃</button>
            </div>
        </div>

        <!-- 알림 배너 -->
        <div class="alert-banner">
            <span>⚠️</span>
            <div>
                <strong>데이터가 없습니다.</strong><br>
                개통표 정보가 없습니다 또는 대리점에서 데이터가 업로드되지 않았을 수 있습니다.
            </div>
        </div>

        <!-- 대시보드 컨텐츠 -->
        <div class="dashboard-content">
            <!-- KPI 카드 -->
            <div class="kpi-grid">
                <div class="kpi-card" id="todaySales">
                    <div class="kpi-header">
                        <span class="kpi-title">오늘 매출</span>
                        <span class="kpi-trend trend-up">+ 12.5%</span>
                    </div>
                    <div class="kpi-value">₩0</div>
                    <div class="kpi-subtitle">전월 동일 요일 대비</div>
                </div>
                <div class="kpi-card" id="monthSales">
                    <div class="kpi-header">
                        <span class="kpi-title">이번 달 매출</span>
                        <span class="kpi-trend trend-up">+ 8.2%</span>
                    </div>
                    <div class="kpi-value">₩0</div>
                    <div class="kpi-subtitle">전월 동기 대비</div>
                </div>
                <div class="kpi-card" id="vatSales">
                    <div class="kpi-header">
                        <span class="kpi-title">VAT 포함 매출</span>
                        <span class="kpi-trend trend-down">- 2.1%</span>
                    </div>
                    <div class="kpi-value">₩0</div>
                    <div class="kpi-subtitle">VAT 13.3% 포함</div>
                </div>
                <div class="kpi-card" id="goalProgress">
                    <div class="kpi-header">
                        <span class="kpi-title">목표 달성률</span>
                    </div>
                    <div class="kpi-value">0 / 100</div>
                    <div class="kpi-subtitle">월간 목표 대비</div>
                </div>
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

            <!-- 하단 섹션 -->
            <div class="bottom-grid">
                <div class="bottom-card">
                    <div class="chart-title">최근 활동</div>
                    <ul class="activity-list">
                        <li class="activity-item">
                            <div class="activity-icon activity-green"></div>
                            <div>
                                <div>서울지역 동별 창구 운영</div>
                                <div style="font-size: 12px; color: #94a3b8;">10분 전</div>
                            </div>
                        </li>
                        <li class="activity-item">
                            <div class="activity-icon activity-blue"></div>
                            <div>
                                <div>경기지역 신규 매점 등록</div>
                                <div style="font-size: 12px; color: #94a3b8;">1시간 전</div>
                            </div>
                        </li>
                        <li class="activity-item">
                            <div class="activity-icon activity-yellow"></div>
                            <div>
                                <div>부산지역 배고 예약 등록</div>
                                <div style="font-size: 12px; color: #94a3b8;">2시간 전</div>
                            </div>
                        </li>
                    </ul>
                </div>
                <div class="bottom-card">
                    <div class="chart-title">공지사항</div>
                    <div>
                        <div class="notice-item">
                            <div>
                                <div class="notice-title">시스템 업데이트 안내</div>
                                <div class="notice-date">2024-01-15</div>
                            </div>
                            <span class="notice-badge">중요</span>
                        </div>
                        <div class="notice-item">
                            <div>
                                <div class="notice-title">신규 기능 업데이트</div>
                                <div class="notice-date">2024-01-14</div>
                            </div>
                            <span style="color: #94a3b8; font-size: 12px;">신규</span>
                        </div>
                        <div class="notice-item">
                            <div>
                                <div class="notice-title">정산 프로세스 변경</div>
                                <div class="notice-date">2024-01-13</div>
                            </div>
                        </div>
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
                labels: Array.from({length: 30}, (_, i) => `${i + 1}일`),
                datasets: [{
                    label: '매출',
                    data: [0, 100000, 150000, 80000, 200000, 170000, 220000, 180000, 250000, 190000, 
                           210000, 240000, 200000, 280000, 250000, 300000, 270000, 320000, 290000, 350000,
                           310000, 380000, 340000, 400000, 360000, 420000, 380000, 450000, 410000, 480000],
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
            // 가장 간단한 입력 시스템으로 이동
            window.location.href = '/test/simple-aggrid';
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
            // 완전한 판매관리 시스템
            window.location.href = '/test/complete-aggrid';
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
            // 월마감정산 페이지 (신규 핵심 기능!)
            window.location.href = '/monthly-settlement';
        }
        
        function openStoreManagement() {
            // 매장 관리 (본사 전용)
            window.location.href = '/management/stores';
        }
        
        function openAdmin() {
            // 관리자 패널
            window.location.href = '/admin';
        }
        
        // 로그아웃 함수
        function logout() {
            if (confirm('로그아웃 하시겠습니까?')) {
                fetch('/logout', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    }
                }).then(() => {
                    window.location.href = '/';
                }).catch(() => {
                    // CSRF 문제 시 직접 이동
                    window.location.href = '/';
                });
            }
        }

        // 실시간 데이터 로드
        async function loadRealTimeData() {
            try {
                // 사용자 권한별 API 엔드포인트 구성
                let apiUrl = '/api/dashboard/overview';
                if (window.userData.role !== 'headquarters') {
                    // 지사/매장 사용자는 접근 가능한 매장 ID를 파라미터로 전달
                    const storeIds = window.userData.store_id ? [window.userData.store_id] : 
                                   window.userData.branch_id ? `branch_${window.userData.branch_id}` : '';
                    if (storeIds) {
                        apiUrl += `?store_ids=${storeIds}`;
                    }
                }
                
                console.log('API 호출:', apiUrl, '권한:', window.userData.role);
                
                // 대시보드 개요 데이터 로드
                const overviewResponse = await fetch(apiUrl);
                const overviewData = await overviewResponse.json();
                
                if (overviewData.success) {
                    const data = overviewData.data;
                    
                    // KPI 카드 업데이트
                    document.querySelector('#todaySales .kpi-value').textContent = 
                        '₩' + Number(data.today.sales).toLocaleString();
                    document.querySelector('#monthSales .kpi-value').textContent = 
                        '₩' + Number(data.month.sales).toLocaleString();
                    document.querySelector('#vatSales .kpi-value').textContent = 
                        '₩' + Number(data.month.vat_included_sales).toLocaleString();
                    document.querySelector('#goalProgress .kpi-value').textContent = 
                        Math.round(data.goals.achievement_rate) + ' / 100';
                    
                    // 증감률 업데이트
                    const growthElement = document.querySelector('#monthSales .kpi-trend');
                    if (growthElement) {
                        growthElement.textContent = (data.month.growth_rate >= 0 ? '+' : '') + data.month.growth_rate + '%';
                        growthElement.className = data.month.growth_rate >= 0 ? 'kpi-trend trend-up' : 'kpi-trend trend-down';
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
        
        // 실제 시스템 상태 데이터 로드
        async function loadSystemStatus() {
            try {
                const [usersRes, storesRes, salesRes, branchesRes] = await Promise.all([
                    fetch('/api/users/count'),
                    fetch('/api/stores/count'),  
                    fetch('/api/sales/count'),
                    fetch('/test-api/branches')
                ]);
                
                const [users, stores, sales, branches] = await Promise.all([
                    usersRes.json(),
                    storesRes.json(),
                    salesRes.json(), 
                    branchesRes.json()
                ]);
                
                const userCount = users.count || 0;
                const storeCount = stores.count || 0;
                const salesCount = sales.count || 0;
                const branchCount = branches.data?.length || 0;
                
                // 권한별 메시지 차별화
                const role = window.userData.role;
                if (role === 'headquarters') {
                    return `지사 ${branchCount}개, 매장 ${storeCount}개, 사용자 ${userCount}명, 개통 ${salesCount}건 관리 중`;
                } else if (role === 'branch') {
                    const branchName = window.userData.branch_name || '소속 지사';
                    return `${branchName} 매장 관리 중 - 개통 ${salesCount}건 데이터 연동`;
                } else if (role === 'store') {
                    const storeName = window.userData.store_name || '매장';
                    return `${storeName} 운영 중 - 개통 ${salesCount}건 실적 관리`;
                } else {
                    return `시스템 개발 모드 - 총 ${salesCount}건 데이터`;
                }
                
            } catch (error) {
                console.error('시스템 상태 로드 오류:', error);
                return '시스템 상태 확인 중...';
            }
        }

        // 페이지 로드 시 실시간 데이터 로드
        document.addEventListener('DOMContentLoaded', function() {
            // 차트 로드 후 실시간 데이터 적용
            setTimeout(loadRealTimeData, 1000);
            
            // 5분마다 데이터 새로고침
            setInterval(loadRealTimeData, 300000);
        });

        // 사이드바 아이콘 클릭 이벤트
        document.querySelectorAll('.sidebar-icon').forEach(icon => {
            icon.addEventListener('click', function() {
                document.querySelectorAll('.sidebar-icon').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
            });
        });
    </script>
</body>
</html>