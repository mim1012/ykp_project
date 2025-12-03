<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>공지사항</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css" rel="stylesheet">
    <style>
        body { font-family: 'Pretendard Variable', -apple-system, BlinkMacSystemFont, system-ui, sans-serif; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <!-- 헤더 -->
    <header class="bg-white shadow-sm border-b">
        <div class="max-w-6xl mx-auto px-4 py-4 flex justify-between items-center">
            <div class="flex items-center gap-4">
                <a href="/dashboard" class="text-gray-500 hover:text-gray-700">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                    </svg>
                </a>
                <h1 class="text-xl font-bold text-gray-900">공지사항</h1>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm text-gray-600">{{ auth()->user()->name ?? '사용자' }}</span>
                @if(auth()->user()->isHeadquarters() || auth()->user()->isBranch())
                <button onclick="openWriteModal()" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
                    공지 작성
                </button>
                @endif
            </div>
        </div>
    </header>

    <!-- 메인 컨텐츠 -->
    <main class="max-w-6xl mx-auto px-4 py-6">
        <!-- 검색 -->
        <div class="bg-white rounded-lg shadow-sm p-4 mb-6 flex flex-wrap gap-4 items-center justify-end">
            <div class="flex gap-2">
                <input type="text" id="search-input" placeholder="검색어 입력..." class="px-3 py-1.5 text-sm border rounded-lg w-64" onkeyup="if(event.key==='Enter') searchPosts()">
                <button onclick="searchPosts()" class="px-4 py-1.5 bg-gray-800 text-white text-sm rounded-lg hover:bg-gray-900">검색</button>
            </div>
        </div>

        <!-- 공지사항 목록 -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div id="notice-list" class="divide-y">
                <!-- 로딩 중 -->
                <div class="p-8 text-center text-gray-500">
                    <div class="animate-spin w-8 h-8 border-4 border-indigo-500 border-t-transparent rounded-full mx-auto mb-4"></div>
                    공지사항을 불러오는 중...
                </div>
            </div>
        </div>

        <!-- 페이지네이션 -->
        <div id="pagination" class="mt-6 flex justify-center gap-2">
            <!-- 동적 생성 -->
        </div>
    </main>

    <!-- 글쓰기 모달 -->
    <div id="write-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <h3 class="text-lg font-bold">공지 작성</h3>
                <button onclick="closeWriteModal()" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <form id="write-form" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">제목 *</label>
                    <input type="text" name="title" required class="w-full px-3 py-2 border rounded-lg" placeholder="공지 제목을 입력하세요">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">내용 *</label>
                    <textarea name="content" required rows="8" class="w-full px-3 py-2 border rounded-lg" placeholder="공지 내용을 입력하세요"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">대상</label>
                    <select name="target_audience" class="w-full px-3 py-2 border rounded-lg">
                        @if(auth()->user()->isHeadquarters())
                        <option value="all">전체</option>
                        @endif
                        <option value="branches">지사</option>
                        <option value="stores">매장</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">이미지 첨부</label>
                    <input type="file" name="images" id="write-images" multiple accept="image/*" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <p class="text-xs text-gray-500 mt-1">최대 5개, 각 2MB 이하 (JPG, PNG, GIF)</p>
                    <div id="write-image-preview" class="flex flex-wrap gap-2 mt-2"></div>
                </div>
                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" onclick="closeWriteModal()" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">취소</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">등록</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 상세보기 모달 -->
    <div id="detail-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b flex justify-between items-center sticky top-0 bg-white">
                <h3 id="detail-title" class="text-lg font-bold">제목</h3>
                <button onclick="closeDetailModal()" class="text-gray-400 hover:text-gray-600 text-2xl">&times;</button>
            </div>
            <div id="detail-content" class="p-6">
                <!-- 동적 로드 -->
            </div>
        </div>
    </div>

    <!-- 수정 모달 -->
    <div id="edit-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <h3 class="text-lg font-bold">공지 수정</h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <form id="edit-form" class="p-6 space-y-4">
                <input type="hidden" name="post_id" id="edit-post-id">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">제목 *</label>
                    <input type="text" name="title" id="edit-title" required class="w-full px-3 py-2 border rounded-lg" placeholder="공지 제목을 입력하세요">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">내용 *</label>
                    <textarea name="content" id="edit-content" required rows="8" class="w-full px-3 py-2 border rounded-lg" placeholder="공지 내용을 입력하세요"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">대상</label>
                    <select name="target_audience" id="edit-target-audience" class="w-full px-3 py-2 border rounded-lg">
                        @if(auth()->user()->isHeadquarters())
                        <option value="all">전체</option>
                        @endif
                        <option value="branches">지사</option>
                        <option value="stores">매장</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">이미지 첨부</label>
                    <input type="file" name="images" id="edit-images" multiple accept="image/*" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <p class="text-xs text-gray-500 mt-1">최대 5개, 각 2MB 이하 (JPG, PNG, GIF)</p>
                    <div id="edit-image-preview" class="flex flex-wrap gap-2 mt-2"></div>
                </div>
                <div class="flex justify-end gap-3 pt-4">
                    <button type="button" onclick="closeEditModal()" class="px-4 py-2 bg-gray-200 rounded-lg hover:bg-gray-300">취소</button>
                    <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">수정</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        let currentPage = 1;
        let currentSearch = '';
        const userRole = '{{ auth()->user()->role ?? "store" }}';
        const userId = {{ auth()->user()->id ?? 0 }};
        const userBranchId = {{ auth()->user()->branch_id ?? 'null' }};

        document.addEventListener('DOMContentLoaded', () => {
            loadPosts();
            document.getElementById('write-form').addEventListener('submit', submitPost);
        });

        async function loadPosts() {
            try {
                let url = `/api/notices?page=${currentPage}&per_page=15`;
                if (currentSearch) url += `&search=${encodeURIComponent(currentSearch)}`;

                const response = await fetch(url);
                const result = await response.json();

                if (result.success) {
                    renderPosts(result.data);
                    renderPagination(result.meta || result);
                } else {
                    document.getElementById('notice-list').innerHTML = `<div class="p-8 text-center text-red-500">${result.message}</div>`;
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('notice-list').innerHTML = `<div class="p-8 text-center text-red-500">데이터를 불러올 수 없습니다.</div>`;
            }
        }

        function renderPosts(posts) {
            const container = document.getElementById('notice-list');

            if (!posts || posts.length === 0) {
                container.innerHTML = `<div class="p-8 text-center text-gray-500">등록된 공지사항이 없습니다.</div>`;
                return;
            }

            let html = '';
            posts.forEach(post => {
                const pinnedBadge = post.is_pinned ? '<span class="px-2 py-0.5 bg-red-100 text-red-600 text-xs rounded-full">중요</span>' : '';
                const targetBadge = post.target_audience === 'all'
                    ? '<span class="px-2 py-0.5 bg-blue-100 text-blue-600 text-xs rounded-full">전체</span>'
                    : post.target_audience === 'branches'
                    ? '<span class="px-2 py-0.5 bg-purple-100 text-purple-600 text-xs rounded-full">지사</span>'
                    : '<span class="px-2 py-0.5 bg-green-100 text-green-600 text-xs rounded-full">매장</span>';

                const date = new Date(post.created_at).toLocaleDateString('ko-KR');
                const pinnedClass = post.is_pinned ? 'bg-yellow-50' : '';

                html += `
                    <div class="p-4 hover:bg-gray-50 cursor-pointer ${pinnedClass}" onclick="viewPost(${post.id})">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    ${pinnedBadge}
                                    ${targetBadge}
                                </div>
                                <h3 class="font-medium text-gray-900 truncate">${post.title}</h3>
                                <p class="text-sm text-gray-500 mt-1">${post.author?.name || post.user?.name || '관리자'} · ${date} · 조회 ${post.view_count || 0}</p>
                            </div>
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;
        }

        function renderPagination(meta) {
            const container = document.getElementById('pagination');
            const lastPage = meta.last_page || 1;

            if (lastPage <= 1) {
                container.innerHTML = '';
                return;
            }

            let html = '';
            for (let i = 1; i <= lastPage; i++) {
                const active = i === currentPage ? 'bg-indigo-600 text-white' : 'bg-white text-gray-700 hover:bg-gray-100';
                html += `<button onclick="goToPage(${i})" class="px-3 py-1.5 text-sm rounded-lg border ${active}">${i}</button>`;
            }
            container.innerHTML = html;
        }

        function goToPage(page) {
            currentPage = page;
            loadPosts();
        }

        function searchPosts() {
            currentSearch = document.getElementById('search-input').value;
            currentPage = 1;
            loadPosts();
        }

        function openWriteModal() {
            document.getElementById('write-modal').classList.remove('hidden');
        }

        function closeWriteModal() {
            document.getElementById('write-modal').classList.add('hidden');
            document.getElementById('write-form').reset();
        }

        async function submitPost(e) {
            e.preventDefault();
            const form = e.target;

            const data = {
                title: form.title.value,
                content: form.content.value,
                target_audience: form.target_audience.value
            };

            // 지사인 경우 자동으로 자신의 지사 ID 추가
            if (userRole === 'branch' && userBranchId) {
                if (data.target_audience === 'branches') {
                    data.target_branch_ids = [userBranchId];
                }
            }

            try {
                const response = await fetch('/api/notices', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();
                if (result.success) {
                    alert('공지가 등록되었습니다.');
                    closeWriteModal();
                    loadPosts();
                } else {
                    alert(result.message || '등록에 실패했습니다.');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('오류가 발생했습니다.');
            }
        }

        async function viewPost(id) {
            try {
                const response = await fetch(`/api/notices/${id}`);
                const result = await response.json();

                if (result.success) {
                    renderDetailModal(result.data);
                    document.getElementById('detail-modal').classList.remove('hidden');
                } else {
                    alert(result.message || '공지사항을 불러올 수 없습니다.');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('오류가 발생했습니다.');
            }
        }

        function renderDetailModal(post) {
            document.getElementById('detail-title').textContent = post.title;

            const pinnedBadge = post.is_pinned ? '<span class="px-2 py-0.5 bg-red-100 text-red-600 text-xs rounded-full">중요</span>' : '';
            const targetBadge = post.target_audience === 'all'
                ? '<span class="px-2 py-0.5 bg-blue-100 text-blue-600 text-xs rounded-full">전체</span>'
                : post.target_audience === 'branches'
                ? '<span class="px-2 py-0.5 bg-purple-100 text-purple-600 text-xs rounded-full">지사</span>'
                : '<span class="px-2 py-0.5 bg-green-100 text-green-600 text-xs rounded-full">매장</span>';

            const canEdit = post.author_user_id === userId;
            const canDelete = post.author_user_id === userId || userRole === 'headquarters';
            const canPin = userRole === 'headquarters';

            const actionButtons = `
                <div class="flex gap-2 mt-4">
                    ${canPin ? `<button onclick="togglePin(${post.id}, ${post.is_pinned})" class="px-3 py-1.5 bg-yellow-100 text-yellow-700 text-sm rounded-lg hover:bg-yellow-200">${post.is_pinned ? '고정 해제' : '고정'}</button>` : ''}
                    ${canEdit ? `<button onclick="editPost(${post.id})" class="px-3 py-1.5 bg-gray-200 text-sm rounded-lg hover:bg-gray-300">수정</button>` : ''}
                    ${canDelete ? `<button onclick="deletePost(${post.id})" class="px-3 py-1.5 bg-red-100 text-red-600 text-sm rounded-lg hover:bg-red-200">삭제</button>` : ''}
                </div>
            `;

            document.getElementById('detail-content').innerHTML = `
                <div class="mb-4">
                    <div class="flex items-center gap-2 mb-2">
                        ${pinnedBadge}
                        ${targetBadge}
                    </div>
                    <p class="text-sm text-gray-500">${post.author?.name || post.user?.name || '관리자'} · ${new Date(post.created_at).toLocaleDateString('ko-KR')} · 조회 ${post.view_count || 0}</p>
                </div>
                <div class="prose max-w-none mb-6 border-t pt-6">
                    <p class="whitespace-pre-wrap">${post.content}</p>
                </div>
                ${actionButtons}
            `;
        }

        function closeDetailModal() {
            document.getElementById('detail-modal').classList.add('hidden');
        }

        async function togglePin(id, currentlyPinned) {
            try {
                const response = await fetch(`/api/notices/${id}/toggle-pin`, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                const result = await response.json();
                if (result.success) {
                    alert(currentlyPinned ? '고정 해제되었습니다.' : '고정되었습니다.');
                    closeDetailModal();
                    loadPosts();
                } else {
                    alert(result.message || '처리에 실패했습니다.');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('오류가 발생했습니다.');
            }
        }

        async function deletePost(id) {
            if (!confirm('정말 삭제하시겠습니까?')) return;

            try {
                const response = await fetch(`/api/notices/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                const result = await response.json();
                if (result.success) {
                    alert('삭제되었습니다.');
                    closeDetailModal();
                    loadPosts();
                } else {
                    alert(result.message || '삭제에 실패했습니다.');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('오류가 발생했습니다.');
            }
        }

        // 수정 모달 열기
        async function editPost(id) {
            try {
                const response = await fetch(`/api/notices/${id}`);
                const result = await response.json();

                if (result.success) {
                    const post = result.data;
                    document.getElementById('edit-post-id').value = post.id;
                    document.getElementById('edit-title').value = post.title;
                    document.getElementById('edit-content').value = post.content;
                    document.getElementById('edit-target-audience').value = post.target_audience || 'stores';

                    // 기존 이미지 프리뷰
                    const previewContainer = document.getElementById('edit-image-preview');
                    previewContainer.innerHTML = '';
                    if (post.images && post.images.length > 0) {
                        post.images.forEach(img => {
                            previewContainer.innerHTML += `
                                <div class="relative">
                                    <img src="${img.url}" class="w-20 h-20 object-cover rounded border">
                                    <button type="button" onclick="removeExistingImage(${img.id})" class="absolute -top-2 -right-2 w-5 h-5 bg-red-500 text-white rounded-full text-xs">&times;</button>
                                </div>
                            `;
                        });
                    }

                    closeDetailModal();
                    document.getElementById('edit-modal').classList.remove('hidden');
                } else {
                    alert(result.message || '공지를 불러올 수 없습니다.');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('오류가 발생했습니다.');
            }
        }

        function closeEditModal() {
            document.getElementById('edit-modal').classList.add('hidden');
            document.getElementById('edit-form').reset();
            document.getElementById('edit-image-preview').innerHTML = '';
        }

        // 수정 폼 제출
        document.addEventListener('DOMContentLoaded', () => {
            document.getElementById('edit-form').addEventListener('submit', async function(e) {
                e.preventDefault();

                const postId = document.getElementById('edit-post-id').value;
                const data = {
                    title: document.getElementById('edit-title').value,
                    content: document.getElementById('edit-content').value,
                    target_audience: document.getElementById('edit-target-audience').value
                };

                try {
                    const response = await fetch(`/api/notices/${postId}`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                        },
                        body: JSON.stringify(data)
                    });

                    const result = await response.json();
                    if (result.success) {
                        // 이미지 업로드 (새 이미지가 있는 경우)
                        const imageInput = document.getElementById('edit-images');
                        if (imageInput.files.length > 0) {
                            await uploadImages(postId, imageInput.files, 'notice');
                        }

                        alert('수정되었습니다.');
                        closeEditModal();
                        loadPosts();
                    } else {
                        alert(result.message || '수정에 실패했습니다.');
                    }
                } catch (error) {
                    console.error('Error:', error);
                    alert('오류가 발생했습니다.');
                }
            });

            // 이미지 프리뷰 이벤트
            const writeImagesInput = document.getElementById('write-images');
            if (writeImagesInput) {
                writeImagesInput.addEventListener('change', function(e) {
                    previewImages(e.target.files, 'write-image-preview');
                });
            }
            const editImagesInput = document.getElementById('edit-images');
            if (editImagesInput) {
                editImagesInput.addEventListener('change', function(e) {
                    previewImages(e.target.files, 'edit-image-preview');
                });
            }
        });

        // 이미지 프리뷰 함수
        function previewImages(files, containerId) {
            const container = document.getElementById(containerId);
            if (!container) return;
            container.innerHTML = '';

            if (files.length > 5) {
                alert('이미지는 최대 5개까지 첨부할 수 있습니다.');
                return;
            }

            Array.from(files).forEach((file, index) => {
                if (file.size > 2 * 1024 * 1024) {
                    alert(`${file.name}의 크기가 2MB를 초과합니다.`);
                    return;
                }

                const reader = new FileReader();
                reader.onload = function(e) {
                    container.innerHTML += `
                        <div class="relative">
                            <img src="${e.target.result}" class="w-20 h-20 object-cover rounded border">
                        </div>
                    `;
                };
                reader.readAsDataURL(file);
            });
        }

        // 이미지 업로드 함수
        async function uploadImages(postId, files, type) {
            const formData = new FormData();
            formData.append('post_id', postId);
            formData.append('type', type);

            Array.from(files).forEach((file, index) => {
                formData.append(`images[${index}]`, file);
            });

            try {
                const response = await fetch('/api/images/upload', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: formData
                });
                return await response.json();
            } catch (error) {
                console.error('Image upload error:', error);
                return { success: false };
            }
        }

        // 기존 이미지 삭제
        async function removeExistingImage(imageId) {
            if (!confirm('이 이미지를 삭제하시겠습니까?')) return;

            try {
                const response = await fetch(`/api/images/${imageId}`, {
                    method: 'DELETE',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    }
                });

                const result = await response.json();
                if (result.success) {
                    event.target.closest('.relative').remove();
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }
    </script>
</body>
</html>
