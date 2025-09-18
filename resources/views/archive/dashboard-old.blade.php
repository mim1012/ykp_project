<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YKP ERP 대시보드</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Malgun Gothic', sans-serif;
            background: #f0f2f5;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        .user-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.15);
        }
        .stat-title {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            color: #333;
            margin-bottom: 5px;
        }
        .stat-change {
            font-size: 13px;
            padding: 4px 8px;
            border-radius: 4px;
            display: inline-block;
        }
        .positive {
            color: #10b981;
            background: #d1fae5;
        }
        .negative {
            color: #ef4444;
            background: #fee2e2;
        }
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(500px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .chart-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        .chart-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #333;
        }
        .table-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #666;
            border-bottom: 2px solid #e5e7eb;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #e5e7eb;
        }
        tr:hover {
            background: #f8f9fa;
        }
        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary {
            background: #667eea;
            color: white;
        }
        .btn-primary:hover {
            background: #5a67d8;
        }
        .btn-success {
            background: #10b981;
            color: white;
        }
        .actions {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <h1>📊 YKP ERP 실시간 대시보드</h1>
            <div class="user-info">
                <span>👤 본사 관리자</span>
                <span id="current-time"></span>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="actions">
            <a href="/sales/excel-input" class="btn btn-primary">➕ 판매 데이터 입력</a>
            <button onclick="refreshData()" class="btn btn-success">🔄 새로고침</button>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-title">
                    <span>💰</span> 오늘 매출
                </div>
                <div class="stat-value" id="today-revenue">₩0</div>
                <span class="stat-change positive">↑ 12.5%</span>
            </div>
            <div class="stat-card">
                <div class="stat-title">
                    <span>📱</span> 오늘 개통 수
                </div>
                <div class="stat-value" id="today-activations">0</div>
                <span class="stat-change positive">↑ 8.3%</span>
            </div>
            <div class="stat-card">
                <div class="stat-title">
                    <span>🏪</span> 활성 매장
                </div>
                <div class="stat-value" id="active-stores">0</div>
                <span class="stat-change">520개 중</span>
            </div>
            <div class="stat-card">
                <div class="stat-title">
                    <span>📈</span> 월 누적 매출
                </div>
                <div class="stat-value" id="month-revenue">₩0</div>
                <span class="stat-change positive">↑ 15.2%</span>
            </div>
        </div>

        <div class="charts-grid">
            <div class="chart-card">
                <h3 class="chart-title">통신사별 개통 현황</h3>
                <canvas id="carrier-chart"></canvas>
            </div>
            <div class="chart-card">
                <h3 class="chart-title">개통 유형별 현황</h3>
                <canvas id="type-chart"></canvas>
            </div>
        </div>

        <div class="chart-card">
            <h3 class="chart-title">일별 매출 추이 (최근 7일)</h3>
            <canvas id="trend-chart" height="80"></canvas>
        </div>

        <div class="table-card">
            <h3 class="chart-title">지점별 실적 TOP 5</h3>
            <table id="branch-table">
                <thead>
                    <tr>
                        <th>순위</th>
                        <th>지점명</th>
                        <th>오늘 개통</th>
                        <th>오늘 매출</th>
                        <th>전일 대비</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td colspan="5" class="loading">데이터 로딩 중...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        // 시간 표시
        function updateTime() {
            const now = new Date();
            document.getElementById('current-time').textContent = 
                now.toLocaleString('ko-KR', { 
                    year: 'numeric', 
                    month: '2-digit', 
                    day: '2-digit', 
                    hour: '2-digit', 
                    minute: '2-digit', 
                    second: '2-digit' 
                });
        }
        setInterval(updateTime, 1000);
        updateTime();

        // 차트 초기화
        const carrierChart = new Chart(document.getElementById('carrier-chart'), {
            type: 'doughnut',
            data: {
                labels: ['SK', 'KT', 'LG', 'MVNO'],
                datasets: [{
                    data: [35, 30, 25, 10],
                    backgroundColor: ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        const typeChart = new Chart(document.getElementById('type-chart'), {
            type: 'bar',
            data: {
                labels: ['신규', '기변', 'MNP'],
                datasets: [{
                    label: '개통 수',
                    data: [45, 30, 25],
                    backgroundColor: ['#667eea', '#764ba2', '#f59e0b']
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });

        const trendChart = new Chart(document.getElementById('trend-chart'), {
            type: 'line',
            data: {
                labels: ['월', '화', '수', '목', '금', '토', '일'],
                datasets: [{
                    label: '매출',
                    data: [12500000, 13200000, 14100000, 13800000, 15200000, 11200000, 9800000],
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₩' + (value/1000000).toFixed(1) + 'M';
                            }
                        }
                    }
                }
            }
        });

        // 데이터 로드
        async function loadDashboardData() {
            try {
                const response = await fetch('/api/sales/statistics');
                const data = await response.json();
                
                // 통계 업데이트
                document.getElementById('today-revenue').textContent = 
                    '₩' + (data.summary?.total_settlement || 0).toLocaleString();
                document.getElementById('today-activations').textContent = 
                    (data.summary?.total_count || 0).toLocaleString();
                document.getElementById('active-stores').textContent = 
                    (data.summary?.active_stores || 0).toLocaleString();
                
                // 차트 업데이트
                if (data.by_carrier) {
                    carrierChart.data.datasets[0].data = 
                        data.by_carrier.map(c => c.count);
                    carrierChart.update();
                }
                
                if (data.by_activation_type) {
                    typeChart.data.datasets[0].data = 
                        data.by_activation_type.map(t => t.count);
                    typeChart.update();
                }
                
                // 테이블 업데이트
                updateBranchTable();
                
            } catch (error) {
                console.error('데이터 로드 실패:', error);
            }
        }

        function updateBranchTable() {
            const tbody = document.querySelector('#branch-table tbody');
            tbody.innerHTML = `
                <tr>
                    <td>1</td>
                    <td>서울지점</td>
                    <td>45</td>
                    <td>₩5,420,000</td>
                    <td class="positive">↑ 12%</td>
                </tr>
                <tr>
                    <td>2</td>
                    <td>경기지점</td>
                    <td>38</td>
                    <td>₩4,860,000</td>
                    <td class="positive">↑ 8%</td>
                </tr>
                <tr>
                    <td>3</td>
                    <td>인천지점</td>
                    <td>32</td>
                    <td>₩3,920,000</td>
                    <td class="negative">↓ 3%</td>
                </tr>
                <tr>
                    <td>4</td>
                    <td>부산지점</td>
                    <td>28</td>
                    <td>₩3,240,000</td>
                    <td class="positive">↑ 5%</td>
                </tr>
                <tr>
                    <td>5</td>
                    <td>대구지점</td>
                    <td>25</td>
                    <td>₩2,850,000</td>
                    <td class="positive">↑ 10%</td>
                </tr>
            `;
        }

        function refreshData() {
            loadDashboardData();
        }

        // 초기 로드
        loadDashboardData();
        
        // 30초마다 자동 새로고침
        setInterval(loadDashboardData, 30000);
    </script>
</body>
</html>