<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>고객 관리 - YKP ERP</title>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f4f8',
                            100: '#d9e2ec',
                            200: '#bcccdc',
                            300: '#9fb3c8',
                            400: '#829ab1',
                            500: '#627d98',
                            600: '#486581',
                            700: '#334e68',
                            800: '#243b53',
                            900: '#102a43',
                            950: '#06152b',
                        }
                    },
                    fontFamily: {
                        sans: ['Pretendard Variable', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css" rel="stylesheet">
    <style>
        body { font-family: 'Pretendard Variable', sans-serif; }
    </style>
</head>
<body class="bg-gray-50">
<div class="min-h-screen">
    <!-- 헤더 -->
    <div class="bg-white border-b px-6 py-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="/dashboard" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                    </svg>
                </a>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">고객 관리</h1>
                    <p class="text-sm text-gray-500 mt-1">가망고객 등록 및 개통 고객 관리</p>
                </div>
            </div>
            @if(auth()->user()->isStore())
            <button onclick="openAddModal()" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700 flex items-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
                고객 등록
            </button>
            @endif
        </div>
    </div>

    <!-- 통계 카드 -->
    <div class="px-6 py-4">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
            <div class="bg-white rounded-xl p-4 shadow-sm border">
                <div class="text-sm text-gray-500">전체 고객</div>
                <div class="text-2xl font-bold text-gray-900" id="stat-total">0</div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm border">
                <div class="text-sm text-gray-500">가망 고객</div>
                <div class="text-2xl font-bold text-blue-600" id="stat-prospect">0</div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm border">
                <div class="text-sm text-gray-500">개통 고객</div>
                <div class="text-2xl font-bold text-green-600" id="stat-activated">0</div>
            </div>
            <div class="bg-white rounded-xl p-4 shadow-sm border">
                <div class="text-sm text-gray-500">전환율</div>
                <div class="text-2xl font-bold text-purple-600" id="stat-conversion">0%</div>
            </div>
        </div>

        <!-- 검색 및 필터 -->
        <div class="bg-white rounded-xl shadow-sm border p-4 mb-6">
            <div class="flex flex-wrap gap-4 items-center">
                @if(auth()->user()->isHeadquarters() || auth()->user()->isBranch())
                <!-- 본사/지사: 매장 선택 드롭다운 -->
                <div class="min-w-[180px]">
                    <select id="store-filter" onchange="filterByStore()" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 text-sm">
                        <option value="">전체 매장</option>
                    </select>
                </div>
                @endif
                <div class="flex-1 min-w-[200px]">
                    <input type="text" id="search-input" placeholder="이름 또는 전화번호 검색..."
                           class="w-full px-4 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                           onkeyup="if(event.key==='Enter') searchCustomers()">
                </div>
                <div class="flex gap-2">
                    <button onclick="filterByType('all')" id="filter-all"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-indigo-600 text-white">
                        전체
                    </button>
                    <button onclick="filterByType('prospect')" id="filter-prospect"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-gray-200 text-gray-700 hover:bg-gray-300">
                        가망고객
                    </button>
                    <button onclick="filterByType('activated')" id="filter-activated"
                            class="px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-gray-200 text-gray-700 hover:bg-gray-300">
                        개통고객
                    </button>
                </div>
                <button onclick="searchCustomers()" class="px-4 py-2 bg-gray-800 text-white text-sm rounded-lg hover:bg-gray-900">
                    검색
                </button>
            </div>
        </div>

        <!-- 고객 목록 -->
        <div class="bg-white rounded-xl shadow-sm border overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50 border-b">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">고객명</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">전화번호</th>
                            @if(auth()->user()->isHeadquarters() || auth()->user()->isBranch())
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">매장</th>
                            @endif
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">유형</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">현재기기</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">최초방문일</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">최근연락일</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">상태</th>
                            <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase">관리</th>
                        </tr>
                    </thead>
                    <tbody id="customer-list" class="divide-y divide-gray-200">
                        <tr>
                            <td colspan="8" class="px-4 py-8 text-center text-gray-500">로딩 중...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <!-- 페이지네이션 -->
            <div class="px-4 py-3 border-t bg-gray-50" id="pagination"></div>
        </div>
    </div>
</div>

<!-- 고객 등록 모달 -->
<div id="add-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-4 border-b">
            <h3 class="text-lg font-bold">고객 등록</h3>
            <button onclick="closeAddModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
        </div>
        <form id="add-form" onsubmit="submitAddCustomer(event)" class="p-4 space-y-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">고객명 *</label>
                <input type="text" name="customer_name" required
                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">전화번호 *</label>
                <input type="text" name="phone_number" required placeholder="010-0000-0000"
                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">생년월일</label>
                <input type="date" name="birth_date"
                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">현재 사용 기기</label>
                <input type="text" name="current_device" placeholder="예: iPhone 13, Galaxy S22"
                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">고객 유형</label>
                <select name="customer_type" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                    <option value="prospect">가망고객</option>
                    <option value="activated">개통고객</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">최초 방문일</label>
                <input type="date" name="first_visit_date"
                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">메모</label>
                <textarea name="notes" rows="3" placeholder="고객 관련 메모..."
                          class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeAddModal()" class="flex-1 px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">취소</button>
                <button type="submit" class="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">등록</button>
            </div>
        </form>
    </div>
</div>

<!-- 고객 수정 모달 -->
<div id="edit-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-4 border-b">
            <h3 class="text-lg font-bold">고객 정보 수정</h3>
            <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
        </div>
        <form id="edit-form" onsubmit="submitEditCustomer(event)" class="p-4 space-y-4">
            <input type="hidden" name="id" id="edit-id">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">고객명 *</label>
                <input type="text" name="customer_name" id="edit-customer_name" required
                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">전화번호 *</label>
                <input type="text" name="phone_number" id="edit-phone_number" required
                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">생년월일</label>
                <input type="date" name="birth_date" id="edit-birth_date"
                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">현재 사용 기기</label>
                <input type="text" name="current_device" id="edit-current_device"
                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">고객 유형</label>
                <select name="customer_type" id="edit-customer_type" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                    <option value="prospect">가망고객</option>
                    <option value="activated">개통고객</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">상태</label>
                <select name="status" id="edit-status" class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
                    <option value="active">활성</option>
                    <option value="converted">전환됨</option>
                    <option value="inactive">비활성</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">최근 연락일</label>
                <input type="date" name="last_contact_date" id="edit-last_contact_date"
                       class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">메모</label>
                <textarea name="notes" id="edit-notes" rows="3"
                          class="w-full px-3 py-2 border rounded-lg focus:ring-2 focus:ring-indigo-500"></textarea>
            </div>
            <div class="flex gap-3 pt-4">
                <button type="button" onclick="closeEditModal()" class="flex-1 px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">취소</button>
                <button type="submit" class="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">저장</button>
            </div>
        </form>
    </div>
</div>

<!-- 고객 상세 모달 -->
<div id="detail-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-xl shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-4 border-b">
            <h3 class="text-lg font-bold" id="detail-title">고객 상세</h3>
            <button onclick="closeDetailModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
        </div>
        <div id="detail-content" class="p-4"></div>
    </div>
</div>

<script>
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;
    const userRole = '{{ auth()->user()->role }}';
    const isHqOrBranch = userRole === 'headquarters' || userRole === 'branch';
    let currentPage = 1;
    let currentFilter = 'all';
    let currentStoreId = '';
    let allCustomers = [];

    // 초기화
    document.addEventListener('DOMContentLoaded', function() {
        if (isHqOrBranch) {
            loadStores();
        }
        loadCustomers();
        loadStatistics();
    });

    // 매장 목록 로드 (본사/지사용)
    async function loadStores() {
        try {
            const response = await fetch('/api/stores', {
                headers: { 'Accept': 'application/json' }
            });

            if (!response.ok) {
                showToast('매장 목록을 불러올 수 없습니다.', 'error');
                return;
            }

            const result = await response.json();

            if (result.success && result.data) {
                const select = document.getElementById('store-filter');
                if (select) {
                    result.data.forEach(store => {
                        const option = document.createElement('option');
                        option.value = store.id;
                        option.textContent = store.name;
                        select.appendChild(option);
                    });
                }
            } else {
                showToast(result.message || '매장 목록을 불러올 수 없습니다.', 'error');
            }
        } catch (error) {
            console.error('매장 목록 로드 오류:', error);
            showToast('매장 목록 로드 중 오류가 발생했습니다.', 'error');
        }
    }

    // 매장 필터
    function filterByStore() {
        const select = document.getElementById('store-filter');
        currentStoreId = select ? select.value : '';
        loadCustomers(1);
    }

    // 통계 로드
    async function loadStatistics() {
        try {
            const response = await fetch('/api/customers/statistics', {
                headers: { 'Accept': 'application/json' }
            });
            const result = await response.json();
            if (result.success) {
                document.getElementById('stat-total').textContent = result.data.total_customers || 0;
                document.getElementById('stat-prospect').textContent = result.data.prospects || 0;
                document.getElementById('stat-activated').textContent = result.data.activated || 0;
                document.getElementById('stat-conversion').textContent = (result.data.conversion_rate || 0) + '%';
            }
        } catch (error) {
            console.error('통계 로드 오류:', error);
        }
    }

    // 고객 목록 로드
    async function loadCustomers(page = 1) {
        currentPage = page;
        const search = document.getElementById('search-input').value;

        let url = `/api/customers?page=${page}&per_page=20`;
        if (search) url += `&search=${encodeURIComponent(search)}`;
        if (currentFilter !== 'all') url += `&customer_type=${currentFilter}`;
        if (currentStoreId) url += `&store_id=${currentStoreId}`;

        try {
            const response = await fetch(url, {
                headers: { 'Accept': 'application/json' }
            });
            const result = await response.json();

            if (result.success) {
                allCustomers = result.data;
                renderCustomers(result.data);
                renderPagination(result.meta);
            } else {
                document.getElementById('customer-list').innerHTML =
                    '<tr><td colspan="8" class="px-4 py-8 text-center text-red-500">데이터를 불러올 수 없습니다.</td></tr>';
            }
        } catch (error) {
            console.error('고객 목록 로드 오류:', error);
            document.getElementById('customer-list').innerHTML =
                '<tr><td colspan="8" class="px-4 py-8 text-center text-red-500">오류가 발생했습니다.</td></tr>';
        }
    }

    // 고객 목록 렌더링
    function renderCustomers(customers) {
        const tbody = document.getElementById('customer-list');
        const colSpan = isHqOrBranch ? 9 : 8;

        if (!customers || customers.length === 0) {
            tbody.innerHTML = `<tr><td colspan="${colSpan}" class="px-4 py-8 text-center text-gray-500">등록된 고객이 없습니다.</td></tr>`;
            return;
        }

        let html = '';
        customers.forEach(customer => {
            const typeBadge = customer.customer_type === 'prospect'
                ? '<span class="px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded-full">가망</span>'
                : '<span class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded-full">개통</span>';

            const statusBadge = customer.status === 'active'
                ? '<span class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded-full">활성</span>'
                : customer.status === 'converted'
                ? '<span class="px-2 py-1 text-xs bg-purple-100 text-purple-700 rounded-full">전환</span>'
                : '<span class="px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded-full">비활성</span>';

            const firstVisit = customer.first_visit_date ? new Date(customer.first_visit_date).toLocaleDateString('ko-KR') : '-';
            const lastContact = customer.last_contact_date ? new Date(customer.last_contact_date).toLocaleDateString('ko-KR') : '-';
            const storeName = customer.store?.name || '-';

            html += `
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <div class="font-medium text-gray-900">${customer.customer_name || '-'}</div>
                    </td>
                    <td class="px-4 py-3 text-gray-600">${customer.phone_number || '-'}</td>
                    ${isHqOrBranch ? `<td class="px-4 py-3 text-gray-600 text-sm">${storeName}</td>` : ''}
                    <td class="px-4 py-3">${typeBadge}</td>
                    <td class="px-4 py-3 text-gray-600">${customer.current_device || '-'}</td>
                    <td class="px-4 py-3 text-gray-600">${firstVisit}</td>
                    <td class="px-4 py-3 text-gray-600">${lastContact}</td>
                    <td class="px-4 py-3">${statusBadge}</td>
                    <td class="px-4 py-3 text-center">
                        <div class="flex justify-center gap-2">
                            <button onclick="viewCustomer(${customer.id})" class="text-blue-600 hover:text-blue-800" title="상세보기">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                            </button>
                            <button onclick="editCustomer(${customer.id})" class="text-gray-600 hover:text-gray-800" title="수정">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                </svg>
                            </button>
                            <button onclick="deleteCustomer(${customer.id})" class="text-red-600 hover:text-red-800" title="삭제">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        });
        tbody.innerHTML = html;
    }

    // 페이지네이션 렌더링
    function renderPagination(pagination) {
        if (!pagination) return;

        const container = document.getElementById('pagination');
        let html = `<div class="flex items-center justify-between">
            <div class="text-sm text-gray-500">
                총 ${pagination.total}명 중 ${(pagination.current_page - 1) * pagination.per_page + 1}-${Math.min(pagination.current_page * pagination.per_page, pagination.total)}명
            </div>
            <div class="flex gap-1">`;

        for (let i = 1; i <= pagination.last_page; i++) {
            const active = i === pagination.current_page ? 'bg-indigo-600 text-white' : 'bg-white border hover:bg-gray-50';
            html += `<button onclick="loadCustomers(${i})" class="px-3 py-1.5 text-sm rounded-lg ${active}">${i}</button>`;
        }

        html += '</div></div>';
        container.innerHTML = html;
    }

    // 필터
    function filterByType(type) {
        currentFilter = type;

        // 버튼 스타일 변경
        ['all', 'prospect', 'activated'].forEach(t => {
            const btn = document.getElementById(`filter-${t}`);
            if (t === type) {
                btn.className = 'px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-indigo-600 text-white';
            } else {
                btn.className = 'px-4 py-2 rounded-lg text-sm font-medium transition-colors bg-gray-200 text-gray-700 hover:bg-gray-300';
            }
        });

        loadCustomers(1);
    }

    // 검색
    function searchCustomers() {
        loadCustomers(1);
    }

    // 모달 열기/닫기
    function openAddModal() {
        document.getElementById('add-form').reset();
        document.getElementById('add-modal').classList.remove('hidden');
        document.getElementById('add-modal').classList.add('flex');
    }

    function closeAddModal() {
        document.getElementById('add-modal').classList.add('hidden');
        document.getElementById('add-modal').classList.remove('flex');
    }

    function closeEditModal() {
        document.getElementById('edit-modal').classList.add('hidden');
        document.getElementById('edit-modal').classList.remove('flex');
    }

    function closeDetailModal() {
        document.getElementById('detail-modal').classList.add('hidden');
        document.getElementById('detail-modal').classList.remove('flex');
    }

    // 고객 등록
    async function submitAddCustomer(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        try {
            const response = await fetch('/api/customers', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();
            if (result.success) {
                alert('고객이 등록되었습니다.');
                closeAddModal();
                loadCustomers();
                loadStatistics();
            } else {
                alert(result.message || '등록에 실패했습니다.');
            }
        } catch (error) {
            console.error('등록 오류:', error);
            alert('오류가 발생했습니다.');
        }
    }

    // 고객 상세 보기
    async function viewCustomer(id) {
        try {
            const response = await fetch(`/api/customers/${id}`, {
                headers: { 'Accept': 'application/json' }
            });
            const result = await response.json();

            if (result.success) {
                const customer = result.data;
                document.getElementById('detail-title').textContent = customer.customer_name || '고객 상세';

                const typeBadge = customer.customer_type === 'prospect'
                    ? '<span class="px-2 py-1 text-xs bg-blue-100 text-blue-700 rounded-full">가망고객</span>'
                    : '<span class="px-2 py-1 text-xs bg-green-100 text-green-700 rounded-full">개통고객</span>';

                document.getElementById('detail-content').innerHTML = `
                    <div class="space-y-4">
                        <div class="flex items-center gap-2 mb-4">
                            ${typeBadge}
                            <span class="px-2 py-1 text-xs bg-gray-100 text-gray-600 rounded-full">${customer.status || 'active'}</span>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <div class="text-sm text-gray-500">전화번호</div>
                                <div class="font-medium">${customer.phone_number || '-'}</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500">생년월일</div>
                                <div class="font-medium">${customer.birth_date ? new Date(customer.birth_date).toLocaleDateString('ko-KR') : '-'}</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500">현재 기기</div>
                                <div class="font-medium">${customer.current_device || '-'}</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500">최초 방문일</div>
                                <div class="font-medium">${customer.first_visit_date ? new Date(customer.first_visit_date).toLocaleDateString('ko-KR') : '-'}</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500">최근 연락일</div>
                                <div class="font-medium">${customer.last_contact_date ? new Date(customer.last_contact_date).toLocaleDateString('ko-KR') : '-'}</div>
                            </div>
                            <div>
                                <div class="text-sm text-gray-500">등록일</div>
                                <div class="font-medium">${new Date(customer.created_at).toLocaleDateString('ko-KR')}</div>
                            </div>
                        </div>
                        ${customer.notes ? `
                            <div class="pt-4 border-t">
                                <div class="text-sm text-gray-500 mb-1">메모</div>
                                <div class="text-gray-700 whitespace-pre-wrap">${customer.notes}</div>
                            </div>
                        ` : ''}
                        <div class="pt-4 flex gap-2">
                            <button onclick="closeDetailModal(); editCustomer(${customer.id});" class="flex-1 px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">수정</button>
                            <button onclick="closeDetailModal();" class="flex-1 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">닫기</button>
                        </div>
                    </div>
                `;

                document.getElementById('detail-modal').classList.remove('hidden');
                document.getElementById('detail-modal').classList.add('flex');
            } else {
                showToast(result.message || '고객 정보를 불러올 수 없습니다.', 'error');
            }
        } catch (error) {
            console.error('상세 조회 오류:', error);
            showToast('고객 정보를 불러올 수 없습니다.', 'error');
        }
    }

    // 고객 수정
    async function editCustomer(id) {
        try {
            const response = await fetch(`/api/customers/${id}`, {
                headers: { 'Accept': 'application/json' }
            });
            const result = await response.json();

            if (result.success) {
                const customer = result.data;
                document.getElementById('edit-id').value = customer.id;
                document.getElementById('edit-customer_name').value = customer.customer_name || '';
                document.getElementById('edit-phone_number').value = customer.phone_number || '';
                document.getElementById('edit-birth_date').value = customer.birth_date ? customer.birth_date.split('T')[0] : '';
                document.getElementById('edit-current_device').value = customer.current_device || '';
                document.getElementById('edit-customer_type').value = customer.customer_type || 'prospect';
                document.getElementById('edit-status').value = customer.status || 'active';
                document.getElementById('edit-last_contact_date').value = customer.last_contact_date ? customer.last_contact_date.split('T')[0] : '';
                document.getElementById('edit-notes').value = customer.notes || '';

                document.getElementById('edit-modal').classList.remove('hidden');
                document.getElementById('edit-modal').classList.add('flex');
            }
        } catch (error) {
            console.error('수정 폼 로드 오류:', error);
            alert('고객 정보를 불러올 수 없습니다.');
        }
    }

    // 고객 수정 저장
    async function submitEditCustomer(event) {
        event.preventDefault();
        const form = event.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        const id = data.id;
        delete data.id;

        try {
            const response = await fetch(`/api/customers/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(data)
            });

            const result = await response.json();
            if (result.success) {
                alert('고객 정보가 수정되었습니다.');
                closeEditModal();
                loadCustomers(currentPage);
                loadStatistics();
            } else {
                alert(result.message || '수정에 실패했습니다.');
            }
        } catch (error) {
            console.error('수정 오류:', error);
            alert('오류가 발생했습니다.');
        }
    }

    // 고객 삭제
    async function deleteCustomer(id) {
        if (!confirm('이 고객을 삭제하시겠습니까?')) return;

        try {
            const response = await fetch(`/api/customers/${id}`, {
                method: 'DELETE',
                headers: {
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrfToken
                }
            });

            const result = await response.json();
            if (result.success) {
                alert('고객이 삭제되었습니다.');
                loadCustomers(currentPage);
                loadStatistics();
            } else {
                alert(result.message || '삭제에 실패했습니다.');
            }
        } catch (error) {
            console.error('삭제 오류:', error);
            alert('오류가 발생했습니다.');
        }
    }

    // 모달 바깥 클릭 시 닫기
    document.getElementById('add-modal').addEventListener('click', function(e) {
        if (e.target === this) closeAddModal();
    });
    document.getElementById('edit-modal').addEventListener('click', function(e) {
        if (e.target === this) closeEditModal();
    });
    document.getElementById('detail-modal').addEventListener('click', function(e) {
        if (e.target === this) closeDetailModal();
    });
</script>
</body>
</html>
