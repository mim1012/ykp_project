<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YKP Dashboard</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #333;
            margin-bottom: 30px;
        }
        .user-info {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin: 20px 0;
        }
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            color: white;
            font-size: 14px;
            transition: all 0.3s;
        }
        .btn-primary { background: #667eea; }
        .btn-success { background: #48bb78; }
        .btn-danger { background: #f56565; }
        .btn-warning { background: #ed8936; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 15px rgba(0,0,0,0.3); }
    </style>
</head>
<body>
    <div class="container">
        <h1>YKP 대시보드</h1>

        @auth
        <div class="user-info">
            <p><strong>사용자:</strong> {{ auth()->user()->name }} ({{ auth()->user()->email }})</p>
            <p><strong>권한:</strong> {{ auth()->user()->role }}</p>
        </div>

        <div class="buttons">
            @if(auth()->user()->role === 'headquarters')
                <a href="/management/stores" class="btn btn-primary">매장 관리</a>
                <a href="/management/sales" class="btn btn-primary">판매 관리</a>
                <button onclick="openCarrierModal()" class="btn btn-success">📡 통신사 관리</button>
                <button onclick="openDealerModal()" class="btn btn-success">🏢 대리점 관리</button>
            @endif

            <a href="/sales/excel-input" class="btn btn-warning">📊 개통표 입력</a>
            <a href="/settlements" class="btn btn-primary">💰 정산 관리</a>

            <form method="POST" action="/logout" style="display: inline;">
                @csrf
                <button type="submit" class="btn btn-danger">로그아웃</button>
            </form>
        </div>

        @if(auth()->user()->role === 'headquarters')
        <!-- 통신사 관리 모달 -->
        <div id="carrierModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
            <div style="background: white; width: 600px; margin: 50px auto; padding: 20px; border-radius: 10px;">
                <h2>📡 통신사 관리</h2>
                <button onclick="closeCarrierModal()" style="float: right;">✖</button>
                <div id="carrierList">
                    <!-- 통신사 목록이 여기에 표시됩니다 -->
                </div>
                <hr>
                <h3>새 통신사 추가</h3>
                <form onsubmit="addCarrier(event)">
                    <input type="text" id="carrier_code" placeholder="코드" required>
                    <input type="text" id="carrier_name" placeholder="이름" required>
                    <input type="number" id="sort_order" placeholder="정렬순서" value="10">
                    <button type="submit" class="btn btn-primary">추가</button>
                </form>
            </div>
        </div>

        <!-- 대리점 관리 모달 -->
        <div id="dealerModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000;">
            <div style="background: white; width: 600px; margin: 50px auto; padding: 20px; border-radius: 10px;">
                <h2>🏢 대리점 관리</h2>
                <button onclick="closeDealerModal()" style="float: right;">✖</button>
                <div id="dealerList">
                    <!-- 대리점 목록이 여기에 표시됩니다 -->
                </div>
            </div>
        </div>
        @endif

        @else
        <p>로그인이 필요합니다.</p>
        <a href="/login" class="btn btn-primary">로그인</a>
        @endauth
    </div>

    @if(auth()->check() && auth()->user()->role === 'headquarters')
    <script>
        function openCarrierModal() {
            document.getElementById('carrierModal').style.display = 'block';
            loadCarriers();
        }

        function closeCarrierModal() {
            document.getElementById('carrierModal').style.display = 'none';
        }

        function openDealerModal() {
            document.getElementById('dealerModal').style.display = 'block';
            loadDealers();
        }

        function closeDealerModal() {
            document.getElementById('dealerModal').style.display = 'none';
        }

        async function loadCarriers() {
            try {
                const response = await fetch('/api/carriers');
                const data = await response.json();
                if (data.success) {
                    const carrierList = document.getElementById('carrierList');
                    carrierList.innerHTML = '<ul>' +
                        data.data.map(carrier =>
                            `<li>${carrier.name} (${carrier.code})</li>`
                        ).join('') + '</ul>';
                }
            } catch (error) {
                console.error('Error loading carriers:', error);
            }
        }

        async function loadDealers() {
            try {
                const response = await fetch('/api/dealers');
                const data = await response.json();
                if (data.success) {
                    const dealerList = document.getElementById('dealerList');
                    dealerList.innerHTML = '<ul>' +
                        data.data.map(dealer =>
                            `<li>${dealer.dealer_name} (${dealer.dealer_code})</li>`
                        ).join('') + '</ul>';
                }
            } catch (error) {
                console.error('Error loading dealers:', error);
            }
        }

        async function addCarrier(event) {
            event.preventDefault();
            try {
                const response = await fetch('/api/carriers', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    body: JSON.stringify({
                        code: document.getElementById('carrier_code').value,
                        name: document.getElementById('carrier_name').value,
                        sort_order: document.getElementById('sort_order').value
                    })
                });
                const data = await response.json();
                if (data.success) {
                    alert('통신사가 추가되었습니다.');
                    loadCarriers();
                    document.getElementById('carrier_code').value = '';
                    document.getElementById('carrier_name').value = '';
                } else {
                    alert('에러: ' + data.message);
                }
            } catch (error) {
                console.error('Error adding carrier:', error);
                alert('통신사 추가 중 오류가 발생했습니다.');
            }
        }
    </script>
    @endif
</body>
</html>