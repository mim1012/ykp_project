<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YKP ERP - 로그인</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css" rel="stylesheet">
    <style>
        * { font-family: 'Pretendard Variable', -apple-system, BlinkMacSystemFont, system-ui, Roboto, sans-serif; }
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
    </script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full space-y-8">
        <div class="text-center">
            <div class="mx-auto h-20 w-20 bg-gradient-to-br from-primary-500 to-purple-600 rounded-xl flex items-center justify-center text-white font-bold text-2xl">
                Y
            </div>
            <h2 class="mt-6 text-3xl font-bold text-gray-900">YKP ERP 로그인</h2>
            <p class="mt-2 text-sm text-gray-600">계정에 로그인하여 대시보드에 접속하세요</p>
        </div>

        <div class="bg-white py-8 px-6 shadow-lg rounded-xl">
            @if(session('message'))
                <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-800 rounded-lg">
                    {{ session('message') }}
                </div>
            @endif

            <!-- 로그아웃 성공 메시지 (URL 파라미터 기반) -->
            <script>
                if (window.location.search.includes('logout=success')) {
                    const logoutMessage = document.createElement('div');
                    logoutMessage.className = 'mb-4 p-4 bg-blue-50 border border-blue-200 text-blue-800 rounded-lg';
                    logoutMessage.innerHTML = '✅ 로그아웃되었습니다. 다른 계정으로 로그인할 수 있습니다.';

                    const form = document.querySelector('form');
                    if (form) {
                        form.parentNode.insertBefore(logoutMessage, form);
                    }

                    // URL에서 파라미터 제거 (깔끔한 URL 유지)
                    window.history.replaceState({}, document.title, window.location.pathname);
                }
            </script>

            @if($errors->any())
                <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}" class="space-y-6">
                @csrf
                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">이메일 주소</label>
                    <input 
                        id="email" 
                        name="email" 
                        type="email" 
                        autocomplete="username email" 
                        required 
                        value="{{ old('email') }}"
                        class="appearance-none rounded-lg relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-primary-500 focus:border-primary-500 focus:z-10 sm:text-sm @error('email') border-red-300 @enderror" 
                        placeholder="이메일을 입력하세요"
                    >
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">비밀번호</label>
                    <input 
                        id="password" 
                        name="password" 
                        type="password" 
                        autocomplete="current-password" 
                        required 
                        class="appearance-none rounded-lg relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-primary-500 focus:border-primary-500 focus:z-10 sm:text-sm @error('password') border-red-300 @enderror" 
                        placeholder="비밀번호를 입력하세요"
                    >
                </div>

                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <input 
                            id="remember" 
                            name="remember" 
                            type="checkbox" 
                            class="h-4 w-4 text-primary-600 focus:ring-primary-500 border-gray-300 rounded"
                        >
                        <label for="remember" class="ml-2 block text-sm text-gray-900">
                            로그인 상태 유지
                        </label>
                    </div>
                </div>

                <div>
                    <button 
                        type="submit" 
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors"
                    >
                        로그인
                    </button>
                </div>

                @if(config('app.env') !== 'production')
                <div class="text-center">
                    <a href="{{ route('register') }}" class="text-primary-600 hover:text-primary-500 text-sm">
                        계정이 없으신가요? 회원가입
                    </a>
                </div>
                @endif
            </form>
        </div>

    </div>

    <script>
        // CSRF token for AJAX requests
        window.csrf_token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
        
        // 간단한 CSRF 토큰 설정만 유지 (기본 폼 제출 사용)
        // JavaScript로 폼 제출을 가로채지 않고 Laravel 기본 리다이렉트 사용
    </script>
</body>
</html>