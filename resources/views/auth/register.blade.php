<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>YKP ERP - 회원가입</title>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendardvariable.css" rel="stylesheet">
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
<body class="bg-gray-50 min-h-screen flex items-center justify-center py-12">
    <div class="max-w-md w-full space-y-8">
        <div class="text-center">
            <div class="mx-auto h-20 w-20 bg-gradient-to-br from-primary-500 to-purple-600 rounded-xl flex items-center justify-center text-white font-bold text-2xl">
                Y
            </div>
            <h2 class="mt-6 text-3xl font-bold text-gray-900">YKP ERP 회원가입</h2>
            <p class="mt-2 text-sm text-gray-600">새 계정을 만들어 시작하세요</p>
        </div>

        <div class="bg-white py-8 px-6 shadow-lg rounded-xl">
            @if($errors->any())
                <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-800 rounded-lg">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <form method="POST" action="{{ route('register') }}" class="space-y-6">
                @csrf
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">이름</label>
                    <input 
                        id="name" 
                        name="name" 
                        type="text" 
                        required 
                        value="{{ old('name') }}"
                        class="appearance-none rounded-lg relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm @error('name') border-red-300 @enderror" 
                        placeholder="이름을 입력하세요"
                    >
                </div>

                <div>
                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">이메일 주소</label>
                    <input 
                        id="email" 
                        name="email" 
                        type="email" 
                        required 
                        value="{{ old('email') }}"
                        class="appearance-none rounded-lg relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm @error('email') border-red-300 @enderror" 
                        placeholder="이메일을 입력하세요"
                    >
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-gray-700 mb-1">비밀번호</label>
                    <input 
                        id="password" 
                        name="password" 
                        type="password" 
                        required 
                        class="appearance-none rounded-lg relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm @error('password') border-red-300 @enderror" 
                        placeholder="비밀번호를 입력하세요"
                    >
                </div>

                <div>
                    <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">비밀번호 확인</label>
                    <input 
                        id="password_confirmation" 
                        name="password_confirmation" 
                        type="password" 
                        required 
                        class="appearance-none rounded-lg relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm" 
                        placeholder="비밀번호를 다시 입력하세요"
                    >
                </div>

                <div>
                    <label for="role" class="block text-sm font-medium text-gray-700 mb-1">권한</label>
                    <select 
                        id="role" 
                        name="role" 
                        required
                        class="appearance-none rounded-lg relative block w-full px-3 py-2 border border-gray-300 text-gray-900 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm @error('role') border-red-300 @enderror"
                    >
                        <option value="">권한을 선택하세요</option>
                        <option value="headquarters" {{ old('role') == 'headquarters' ? 'selected' : '' }}>본사</option>
                        <option value="branch" {{ old('role') == 'branch' ? 'selected' : '' }}>지사</option>
                        <option value="store" {{ old('role') == 'store' ? 'selected' : '' }}>매장</option>
                    </select>
                </div>

                <div id="branch-field" class="hidden">
                    <label for="branch_id" class="block text-sm font-medium text-gray-700 mb-1">지사</label>
                    <select 
                        id="branch_id" 
                        name="branch_id"
                        class="appearance-none rounded-lg relative block w-full px-3 py-2 border border-gray-300 text-gray-900 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm"
                    >
                        <option value="">지사를 선택하세요</option>
                        @foreach(App\Models\Branch::all() as $branch)
                            <option value="{{ $branch->id }}" {{ old('branch_id') == $branch->id ? 'selected' : '' }}>
                                {{ $branch->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div id="store-field" class="hidden">
                    <label for="store_id" class="block text-sm font-medium text-gray-700 mb-1">매장</label>
                    <select 
                        id="store_id" 
                        name="store_id"
                        class="appearance-none rounded-lg relative block w-full px-3 py-2 border border-gray-300 text-gray-900 focus:outline-none focus:ring-primary-500 focus:border-primary-500 sm:text-sm"
                    >
                        <option value="">매장을 선택하세요</option>
                        @foreach(App\Models\Store::all() as $store)
                            <option value="{{ $store->id }}" data-branch="{{ $store->branch_id }}" {{ old('store_id') == $store->id ? 'selected' : '' }}>
                                {{ $store->name }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <button 
                        type="submit" 
                        class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-lg text-white bg-primary-600 hover:bg-primary-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500 transition-colors"
                    >
                        회원가입
                    </button>
                </div>

                <div class="text-center">
                    <a href="{{ route('login') }}" class="text-primary-600 hover:text-primary-500 text-sm">
                        이미 계정이 있으신가요? 로그인
                    </a>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Role-based field visibility
        const roleSelect = document.getElementById('role');
        const branchField = document.getElementById('branch-field');
        const storeField = document.getElementById('store-field');
        const branchSelect = document.getElementById('branch_id');
        const storeSelect = document.getElementById('store_id');

        roleSelect.addEventListener('change', function() {
            const role = this.value;
            
            // Hide all conditional fields
            branchField.classList.add('hidden');
            storeField.classList.add('hidden');
            
            // Clear values
            branchSelect.value = '';
            storeSelect.value = '';
            
            if (role === 'branch' || role === 'store') {
                branchField.classList.remove('hidden');
                branchSelect.required = true;
            } else {
                branchSelect.required = false;
            }
            
            if (role === 'store') {
                storeField.classList.remove('hidden');
                storeSelect.required = true;
            } else {
                storeSelect.required = false;
            }
        });

        // Filter stores based on selected branch
        branchSelect.addEventListener('change', function() {
            const branchId = this.value;
            const storeOptions = storeSelect.querySelectorAll('option');
            
            storeSelect.value = '';
            
            storeOptions.forEach(option => {
                if (option.value === '') {
                    option.style.display = 'block';
                    return;
                }
                
                const storeBranchId = option.dataset.branch;
                if (!branchId || storeBranchId === branchId) {
                    option.style.display = 'block';
                } else {
                    option.style.display = 'none';
                }
            });
        });

        // Initialize form state
        if (roleSelect.value) {
            roleSelect.dispatchEvent(new Event('change'));
        }
    </script>
</body>
</html>