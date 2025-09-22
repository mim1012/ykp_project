<!DOCTYPE html>
<html>
<head>
    <title>Debug Dashboard</title>
</head>
<body>
    <h1>Debug Information</h1>

    @if(auth()->check())
        <h2>User Information:</h2>
        <ul>
            <li>ID: {{ auth()->user()->id }}</li>
            <li>Name: {{ auth()->user()->name }}</li>
            <li>Email: {{ auth()->user()->email }}</li>
            <li>Role: {{ auth()->user()->role }}</li>
            <li>Branch ID: {{ auth()->user()->branch_id ?? 'null' }}</li>
            <li>Store ID: {{ auth()->user()->store_id ?? 'null' }}</li>
        </ul>

        <h2>Test getAccessibleStoreIds:</h2>
        @php
            try {
                $storeIds = auth()->user()->getAccessibleStoreIds();
                echo "<p>Store IDs: " . json_encode($storeIds) . "</p>";
            } catch (\Exception $e) {
                echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>";
                echo "<pre>" . $e->getTraceAsString() . "</pre>";
            }
        @endphp

        <h2>Links:</h2>
        <ul>
            <li><a href="/management/stores">매장 관리</a></li>
            <li><a href="/sales/excel-input">개통표 입력</a></li>
            <li><a href="/settlements">정산 관리</a></li>
        </ul>

        <form method="POST" action="/logout">
            @csrf
            <button type="submit">로그아웃</button>
        </form>
    @else
        <p>Not logged in</p>
        <a href="/login">Login</a>
    @endif

    <hr>
    <h2>PHP Info:</h2>
    <ul>
        <li>PHP Version: {{ PHP_VERSION }}</li>
        <li>Laravel Version: {{ app()->version() }}</li>
    </ul>
</body>
</html>