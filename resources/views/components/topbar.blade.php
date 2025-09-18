<header class="bg-white/95 border-b border-slate-200 backdrop-blur">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex items-center">
                <h1 class="text-xl font-semibold text-slate-900">{{ $title ?? 'YKP ERP' }}</h1>
                @isset($badge)
                    <span class="ml-2 px-2 py-1 text-xs {{ $badge['bg'] ?? 'bg-slate-50' }} {{ $badge['text'] ?? 'text-slate-700' }} border {{ $badge['border'] ?? 'border-slate-200' }} rounded">{{ $badge['label'] }}</span>
                @endisset
            </div>
            <div class="flex items-center space-x-4">
                <a href="/dashboard" class="text-slate-600 hover:text-slate-900">대시보드</a>
                <a href="/management/stores" class="text-slate-600 hover:text-slate-900">매장 관리</a>
                <a href="/admin" class="text-slate-600 hover:text-slate-900">관리자</a>
            </div>
        </div>
    </div>
</header>

