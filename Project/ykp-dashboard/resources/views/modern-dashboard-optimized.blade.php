<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YKP ERP - Modern Dashboard</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    
    <!-- Preload critical fonts -->
    <link rel="preconnect" href="https://cdn.jsdelivr.net">
    <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
    <link rel="preload" href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css" as="style" onload="this.onload=null;this.rel='stylesheet'">
    <noscript>
        <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css" rel="stylesheet">
    </noscript>
    
    <!-- Vite CSS -->
    @vite(['resources/css/app.css'])
    
    <!-- Preload critical scripts -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/chart.js" as="script">
    <link rel="preload" href="https://unpkg.com/lucide@latest" as="script">
    
    <script>
        // Global CSRF Token and User Data - inline for immediate availability
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
                const flashMessage = document.querySelector('.fixed.top-4.right-4');
                if (flashMessage) flashMessage.remove();
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
                const flashMessage = document.querySelector('.fixed.top-4.right-4');
                if (flashMessage) flashMessage.remove();
            }, 5000);
        </script>
    @endif

    <!-- Dashboard Root - React will mount here -->
    <div id="dashboard-root"></div>

    <!-- Loading fallback for non-JS users -->
    <noscript>
        <div class="flex items-center justify-center min-h-screen">
            <div class="text-center">
                <h1 class="text-2xl font-bold text-gray-900 mb-4">JavaScript가 필요합니다</h1>
                <p class="text-gray-600">이 애플리케이션을 사용하려면 JavaScript를 활성화해주세요.</p>
            </div>
        </div>
    </noscript>

    <!-- Load external dependencies asynchronously -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js" defer></script>
    <script src="https://unpkg.com/lucide@latest" defer></script>
    
    <!-- React App - Safe Version with Auth Check -->
    @auth
        <!-- Only load React for authenticated users -->
        @vite(['resources/js/dashboard.jsx'])
    @else
        <!-- Guest users get static content only -->
        <script>
            console.log('Guest user - showing static welcome');
            document.addEventListener('DOMContentLoaded', () => {
                const root = document.getElementById('dashboard-root');
                if (root) {
                    root.innerHTML = `
                        <div style="padding: 2rem; text-align: center; background: #f9fafb; border-radius: 8px; margin: 2rem;">
                            <h2 style="color: #111827; margin-bottom: 1rem;">YKP Dashboard</h2>
                            <p style="color: #6b7280; margin-bottom: 1rem;">안전한 배포 모드로 실행 중입니다.</p>
                            <a href="/login" style="display: inline-block; padding: 0.75rem 1.5rem; background: #3b82f6; color: white; text-decoration: none; border-radius: 6px; font-weight: 500;">로그인하기</a>
                        </div>
                    `;
                }
            });
        </script>
    @endauth
</body>
</html>