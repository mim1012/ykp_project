<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Q&A 게시판</title>
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
                <h1 class="text-xl font-bold text-gray-900">Q&A 게시판</h1>
            </div>
            <div class="flex items-center gap-3">
                <span class="text-sm text-gray-600">{{ auth()->user()->name ?? '사용자' }}</span>
                @if(auth()->user()->isStore() || auth()->user()->isBranch())
                <button onclick="openWriteModal()" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">
                    질문하기
                </button>
                @endif
            </div>
        </div>
    </header>

    <!-- 메인 컨텐츠 -->
    <main class="max-w-6xl mx-auto px-4 py-6">
        <!-- 검색/필터 -->
        <div class="bg-white rounded-lg shadow-sm p-4 mb-6 flex flex-wrap gap-4 items-center justify-between">
            <div class="flex gap-2">
                <button onclick="filterStatus('all')" id="filter-all" class="px-3 py-1.5 text-sm rounded-lg bg-indigo-600 text-white">전체</button>
                <button onclick="filterStatus('pending')" id="filter-pending" class="px-3 py-1.5 text-sm rounded-lg bg-gray-100 hover:bg-gray-200">대기중</button>
                <button onclick="filterStatus('answered')" id="filter-answered" class="px-3 py-1.5 text-sm rounded-lg bg-gray-100 hover:bg-gray-200">답변완료</button>
            </div>
            <div class="flex gap-2">
                <input type="text" id="search-input" placeholder="검색어 입력..." class="px-3 py-1.5 text-sm border rounded-lg w-64" onkeyup="if(event.key==='Enter') searchPosts()">
                <button onclick="searchPosts()" class="px-4 py-1.5 bg-gray-800 text-white text-sm rounded-lg hover:bg-gray-900">검색</button>
            </div>
        </div>

        <!-- 게시글 목록 -->
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div id="qna-list" class="divide-y">
                <!-- 로딩 중 -->
                <div class="p-8 text-center text-gray-500">
                    <div class="animate-spin w-8 h-8 border-4 border-indigo-500 border-t-transparent rounded-full mx-auto mb-4"></div>
                    게시글을 불러오는 중...
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
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl">
            <div class="px-6 py-4 border-b flex justify-between items-center">
                <h3 class="text-lg font-bold">질문하기</h3>
                <button onclick="closeWriteModal()" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <form id="write-form" class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">제목 *</label>
                    <input type="text" name="title" required class="w-full px-3 py-2 border rounded-lg" placeholder="질문 제목을 입력하세요">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">내용 *</label>
                    <textarea name="content" required rows="8" class="w-full px-3 py-2 border rounded-lg" placeholder="질문 내용을 상세히 작성해주세요"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">이미지 첨부</label>
                    <input type="file" name="images" id="write-images" multiple accept="image/*" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <p class="text-xs text-gray-500 mt-1">최대 5개, 각 2MB 이하 (JPG, PNG, GIF)</p>
                    <div id="write-image-preview" class="flex flex-wrap gap-2 mt-2"></div>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_private" id="is_private" class="rounded">
                    <label for="is_private" class="text-sm text-gray-700">비밀글로 작성 (본사/지사만 열람 가능)</label>
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
                <h3 class="text-lg font-bold">질문 수정</h3>
                <button onclick="closeEditModal()" class="text-gray-400 hover:text-gray-600">&times;</button>
            </div>
            <form id="edit-form" class="p-6 space-y-4">
                <input type="hidden" name="post_id" id="edit-post-id">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">제목 *</label>
                    <input type="text" name="title" id="edit-title" required class="w-full px-3 py-2 border rounded-lg" placeholder="질문 제목을 입력하세요">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">내용 *</label>
                    <textarea name="content" id="edit-content" required rows="8" class="w-full px-3 py-2 border rounded-lg" placeholder="질문 내용을 상세히 작성해주세요"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">이미지 첨부</label>
                    <input type="file" name="images" id="edit-images" multiple accept="image/*" class="w-full px-3 py-2 border rounded-lg text-sm">
                    <p class="text-xs text-gray-500 mt-1">최대 5개, 각 2MB 이하 (JPG, PNG, GIF)</p>
                    <div id="edit-image-preview" class="flex flex-wrap gap-2 mt-2"></div>
                </div>
                <div class="flex items-center gap-2">
                    <input type="checkbox" name="is_private" id="edit-is-private" class="rounded">
                    <label for="edit-is-private" class="text-sm text-gray-700">비밀글로 작성 (본사/지사만 열람 가능)</label>
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
        let currentStatus = 'all';
        let currentSearch = '';
        const userRole = '{{ auth()->user()->role ?? "store" }}';
        const userId = {{ auth()->user()->id ?? 0 }};

        document.addEventListener('DOMContentLoaded', () => {
            loadPosts();
            document.getElementById('write-form').addEventListener('submit', submitPost);
        });

        async function loadPosts() {
            try {
                let url = `/api/qna?page=${currentPage}&per_page=15`;
                if (currentStatus !== 'all') url += `&status=${currentStatus}`;
                if (currentSearch) url += `&search=${encodeURIComponent(currentSearch)}`;

                const response = await fetch(url);
                const result = await response.json();

                if (result.success) {
                    renderPosts(result.data);
                    renderPagination(result.meta || result);
                } else {
                    document.getElementById('qna-list').innerHTML = `<div class="p-8 text-center text-red-500">${result.message}</div>`;
                }
            } catch (error) {
                console.error('Error:', error);
                document.getElementById('qna-list').innerHTML = `<div class="p-8 text-center text-red-500">데이터를 불러올 수 없습니다.</div>`;
            }
        }

        function renderPosts(posts) {
            const container = document.getElementById('qna-list');

            if (!posts || posts.length === 0) {
                container.innerHTML = `<div class="p-8 text-center text-gray-500">등록된 게시글이 없습니다.</div>`;
                return;
            }

            let html = '';
            posts.forEach(post => {
                const statusBadge = post.status === 'answered'
                    ? '<span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full">답변완료</span>'
                    : post.status === 'closed'
                    ? '<span class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded-full">종료</span>'
                    : '<span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 text-xs rounded-full">대기중</span>';

                const privateBadge = post.is_private ? '<span class="px-2 py-0.5 bg-red-100 text-red-600 text-xs rounded-full">비밀글</span>' : '';
                const date = new Date(post.created_at).toLocaleDateString('ko-KR');

                html += `
                    <div class="p-4 hover:bg-gray-50 cursor-pointer" onclick="viewPost(${post.id})">
                        <div class="flex items-start justify-between gap-4">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-1">
                                    ${privateBadge}
                                    ${statusBadge}
                                </div>
                                <h3 class="font-medium text-gray-900 truncate">${post.title}</h3>
                                <p class="text-sm text-gray-500 mt-1">${post.user?.name || '익명'} · ${date} · 조회 ${post.view_count || 0}</p>
                            </div>
                            <div class="text-right text-sm text-gray-500">
                                <div class="text-indigo-600 font-medium">${post.replies_count || 0}개 답변</div>
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

        function filterStatus(status) {
            currentStatus = status;
            currentPage = 1;

            document.querySelectorAll('[id^="filter-"]').forEach(btn => {
                btn.className = 'px-3 py-1.5 text-sm rounded-lg bg-gray-100 hover:bg-gray-200';
            });
            document.getElementById(`filter-${status}`).className = 'px-3 py-1.5 text-sm rounded-lg bg-indigo-600 text-white';

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
                is_private: form.is_private.checked
            };

            try {
                const response = await fetch('/api/qna', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify(data)
                });

                const result = await response.json();
                if (result.success) {
                    alert('질문이 등록되었습니다.');
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
                const response = await fetch(`/api/qna/${id}`);
                const result = await response.json();

                if (result.success) {
                    renderDetailModal(result.data);
                    document.getElementById('detail-modal').classList.remove('hidden');
                } else {
                    alert(result.message || '게시글을 불러올 수 없습니다.');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('오류가 발생했습니다.');
            }
        }

        function renderDetailModal(post) {
            document.getElementById('detail-title').textContent = post.title;

            const statusBadge = post.status === 'answered'
                ? '<span class="px-2 py-0.5 bg-green-100 text-green-700 text-xs rounded-full">답변완료</span>'
                : post.status === 'closed'
                ? '<span class="px-2 py-0.5 bg-gray-100 text-gray-600 text-xs rounded-full">종료</span>'
                : '<span class="px-2 py-0.5 bg-yellow-100 text-yellow-700 text-xs rounded-full">대기중</span>';

            // 본사/지사 또는 글 작성자(매장)가 댓글 달 수 있음
            const canReply = userRole === 'headquarters' || userRole === 'branch' || post.author_user_id === userId;
            const canEdit = post.author_user_id === userId && post.status === 'pending';
            const canDelete = post.author_user_id === userId && post.status === 'pending';

            let repliesHtml = '';
            if (post.replies && post.replies.length > 0) {
                post.replies.forEach(reply => {
                    const officialBadge = reply.is_official_answer ? '<span class="px-2 py-0.5 bg-indigo-100 text-indigo-700 text-xs rounded-full">공식답변</span>' : '';
                    const replyDate = new Date(reply.created_at).toLocaleDateString('ko-KR');
                    repliesHtml += `
                        <div class="bg-gray-50 rounded-lg p-4 mb-3">
                            <div class="flex items-center gap-2 mb-2">
                                ${officialBadge}
                                <span class="text-sm font-medium">${reply.user?.name || '익명'}</span>
                                <span class="text-xs text-gray-500">${replyDate}</span>
                            </div>
                            <p class="text-gray-700 whitespace-pre-wrap">${reply.content}</p>
                        </div>
                    `;
                });
            } else {
                repliesHtml = '<p class="text-gray-500 text-center py-4">아직 답변이 없습니다.</p>';
            }

            const replyFormHtml = canReply && post.status !== 'closed' ? `
                <div class="mt-4 pt-4 border-t">
                    <h4 class="font-medium mb-2">답변 작성</h4>
                    <textarea id="reply-content" rows="3" class="w-full px-3 py-2 border rounded-lg" placeholder="답변을 입력하세요"></textarea>
                    <div class="flex justify-end mt-2">
                        <button onclick="submitReply(${post.id})" class="px-4 py-2 bg-indigo-600 text-white text-sm rounded-lg hover:bg-indigo-700">답변 등록</button>
                    </div>
                </div>
            ` : '';

            const actionButtons = `
                <div class="flex gap-2 mt-4">
                    ${canEdit ? `<button onclick="editPost(${post.id})" class="px-3 py-1.5 bg-gray-200 text-sm rounded-lg hover:bg-gray-300">수정</button>` : ''}
                    ${canDelete ? `<button onclick="deletePost(${post.id})" class="px-3 py-1.5 bg-red-100 text-red-600 text-sm rounded-lg hover:bg-red-200">삭제</button>` : ''}
                </div>
            `;

            document.getElementById('detail-content').innerHTML = `
                <div class="mb-4">
                    <div class="flex items-center gap-2 mb-2">
                        ${statusBadge}
                        ${post.is_private ? '<span class="px-2 py-0.5 bg-red-100 text-red-600 text-xs rounded-full">비밀글</span>' : ''}
                    </div>
                    <p class="text-sm text-gray-500">${post.user?.name || '익명'} · ${new Date(post.created_at).toLocaleDateString('ko-KR')} · 조회 ${post.view_count || 0}</p>
                </div>
                <div class="prose max-w-none mb-6">
                    <p class="whitespace-pre-wrap">${post.content}</p>
                </div>
                ${actionButtons}
                <div class="mt-6 pt-6 border-t">
                    <h4 class="font-bold mb-4">답변 (${post.replies?.length || 0})</h4>
                    ${repliesHtml}
                </div>
                ${replyFormHtml}
            `;
        }

        function closeDetailModal() {
            document.getElementById('detail-modal').classList.add('hidden');
        }

        async function submitReply(postId) {
            const content = document.getElementById('reply-content').value;
            if (!content.trim()) {
                alert('답변 내용을 입력해주세요.');
                return;
            }

            try {
                const response = await fetch(`/api/qna/${postId}/reply`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
                    },
                    body: JSON.stringify({ content })
                });

                const result = await response.json();
                if (result.success) {
                    alert('답변이 등록되었습니다.');
                    viewPost(postId);
                    loadPosts();
                } else {
                    alert(result.message || '답변 등록에 실패했습니다.');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('오류가 발생했습니다.');
            }
        }

        async function deletePost(id) {
            if (!confirm('정말 삭제하시겠습니까?')) return;

            try {
                const response = await fetch(`/api/qna/${id}`, {
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
                const response = await fetch(`/api/qna/${id}`);
                const result = await response.json();

                if (result.success) {
                    const post = result.data;
                    document.getElementById('edit-post-id').value = post.id;
                    document.getElementById('edit-title').value = post.title;
                    document.getElementById('edit-content').value = post.content;
                    document.getElementById('edit-is-private').checked = post.is_private;

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
                    alert(result.message || '게시글을 불러올 수 없습니다.');
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
                    is_private: document.getElementById('edit-is-private').checked
                };

                try {
                    const response = await fetch(`/api/qna/${postId}`, {
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
                            await uploadImages(postId, imageInput.files, 'qna');
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
            document.getElementById('write-images').addEventListener('change', function(e) {
                previewImages(e.target.files, 'write-image-preview');
            });
            document.getElementById('edit-images').addEventListener('change', function(e) {
                previewImages(e.target.files, 'edit-image-preview');
            });
        });

        // 이미지 프리뷰 함수
        function previewImages(files, containerId) {
            const container = document.getElementById(containerId);
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
                    // 프리뷰에서 제거
                    event.target.closest('.relative').remove();
                }
            } catch (error) {
                console.error('Error:', error);
            }
        }
    </script>
</body>
</html>
