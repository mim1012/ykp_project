<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>매장 일괄 생성 - YKP ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdn.jsdelivr.net/gh/orioncactus/pretendard/dist/web/variable/pretendardvariable.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Pretendard Variable', sans-serif;
        }
        .upload-zone {
            border: 2px dashed #cbd5e1;
            transition: all 0.3s;
        }
        .upload-zone.dragover {
            border-color: #3b82f6;
            background-color: #eff6ff;
        }
        .result-success {
            background-color: #f0fdf4;
            border-left: 4px solid #22c55e;
        }
        .result-error {
            background-color: #fef2f2;
            border-left: 4px solid #ef4444;
        }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen p-8">
        <!-- 헤더 -->
        <div class="max-w-6xl mx-auto mb-8">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">매장 일괄 생성</h1>
                    <p class="mt-2 text-gray-600">지사별 시트로 구성된 엑셀 파일을 업로드하여 매장과 계정을 한번에 생성합니다.</p>
                </div>
                <a href="/management/stores" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition">
                    ← 매장 관리로 돌아가기
                </a>
            </div>
        </div>

        <!-- 안내 사항 -->
        <div class="max-w-6xl mx-auto mb-8">
            <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                <h3 class="text-lg font-semibold text-blue-900 mb-3">📋 엑셀 파일 형식</h3>
                <ul class="space-y-2 text-blue-800">
                    <li class="flex items-start">
                        <span class="mr-2">•</span>
                        <span>각 시트는 지사별로 구성 (예: "서울지사", "부산지사")</span>
                    </li>
                    <li class="flex items-start">
                        <span class="mr-2">•</span>
                        <span>첫 번째 행은 헤더: <code class="bg-blue-100 px-2 py-1 rounded">지사명 | 매장명 | 관리자명 | 전화번호</code></span>
                    </li>
                    <li class="flex items-start">
                        <span class="mr-2">•</span>
                        <span>지사명과 매장명은 필수, 관리자명과 전화번호는 선택</span>
                    </li>
                    <li class="flex items-start">
                        <span class="mr-2">•</span>
                        <span>지사는 미리 생성되어 있어야 합니다</span>
                    </li>
                    <li class="flex items-start">
                        <span class="mr-2">•</span>
                        <span>중복된 매장명은 자동으로 건너뜁니다</span>
                    </li>
                </ul>
            </div>
        </div>

        <!-- 업로드 영역 -->
        <div class="max-w-6xl mx-auto">
            <div class="bg-white rounded-lg shadow-lg p-8">
                <div id="upload-zone" class="upload-zone rounded-lg p-12 text-center">
                    <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12" />
                    </svg>
                    <p class="mt-4 text-lg text-gray-600">엑셀 파일을 여기에 드래그하거나</p>
                    <label for="file-input" class="mt-4 inline-block px-6 py-3 bg-blue-600 text-white rounded-lg cursor-pointer hover:bg-blue-700 transition">
                        파일 선택
                    </label>
                    <input type="file" id="file-input" accept=".xlsx,.xls" class="hidden">
                    <p class="mt-2 text-sm text-gray-500">최대 10MB, .xlsx 또는 .xls 파일만 가능</p>
                </div>

                <div id="selected-file" class="hidden mt-6 p-4 bg-gray-50 rounded-lg">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-3">
                            <svg class="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                            </svg>
                            <div>
                                <p id="file-name" class="font-semibold text-gray-900"></p>
                                <p id="file-size" class="text-sm text-gray-500"></p>
                            </div>
                        </div>
                        <button onclick="uploadFile()" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                            업로드 시작
                        </button>
                    </div>
                </div>

                <!-- 로딩 상태 -->
                <div id="loading" class="hidden mt-6 text-center">
                    <svg class="animate-spin h-12 w-12 mx-auto text-blue-600" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <p class="mt-4 text-gray-600">처리 중입니다... 잠시만 기다려주세요.</p>
                </div>

                <!-- 결과 영역 -->
                <div id="result" class="hidden mt-6"></div>
            </div>
        </div>
    </div>

    <script>
        let selectedFile = null;
        let createdStores = [];

        // 파일 선택
        document.getElementById('file-input').addEventListener('change', function(e) {
            handleFileSelect(e.target.files[0]);
        });

        // 드래그 앤 드롭
        const uploadZone = document.getElementById('upload-zone');
        uploadZone.addEventListener('dragover', function(e) {
            e.preventDefault();
            uploadZone.classList.add('dragover');
        });
        uploadZone.addEventListener('dragleave', function() {
            uploadZone.classList.remove('dragover');
        });
        uploadZone.addEventListener('drop', function(e) {
            e.preventDefault();
            uploadZone.classList.remove('dragover');
            handleFileSelect(e.dataTransfer.files[0]);
        });

        function handleFileSelect(file) {
            if (!file) return;

            // 파일 형식 체크
            if (!file.name.match(/\.(xlsx|xls)$/i)) {
                alert('엑셀 파일(.xlsx, .xls)만 업로드 가능합니다.');
                return;
            }

            // 파일 크기 체크 (10MB)
            if (file.size > 10 * 1024 * 1024) {
                alert('파일 크기는 10MB를 초과할 수 없습니다.');
                return;
            }

            selectedFile = file;

            // UI 업데이트
            document.getElementById('file-name').textContent = file.name;
            document.getElementById('file-size').textContent = formatFileSize(file.size);
            document.getElementById('selected-file').classList.remove('hidden');
            document.getElementById('result').classList.add('hidden');
        }

        function formatFileSize(bytes) {
            if (bytes < 1024) return bytes + ' B';
            if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(2) + ' KB';
            return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
        }

        async function uploadFile() {
            if (!selectedFile) {
                alert('파일을 선택해주세요.');
                return;
            }

            // UI 상태 변경
            document.getElementById('loading').classList.remove('hidden');
            document.getElementById('result').classList.add('hidden');

            const formData = new FormData();
            formData.append('file', selectedFile);

            try {
                const response = await fetch('/api/stores/bulk/multisheet/create', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: formData,
                });

                // Check response status first
                if (!response.ok) {
                    const text = await response.text();
                    console.error('Server response:', text);
                    throw new Error(`서버 오류 (${response.status}): ${response.statusText}`);
                }

                // Check if response is JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    const text = await response.text();
                    console.error('Non-JSON response:', text);
                    throw new Error('서버가 올바른 형식의 응답을 반환하지 않았습니다. 콘솔을 확인해주세요.');
                }

                const data = await response.json();

                if (data.success) {
                    createdStores = data.data.created_stores || [];
                    showResult(data.data);
                } else {
                    throw new Error(data.error || '알 수 없는 오류가 발생했습니다.');
                }
            } catch (error) {
                console.error('Upload error:', error);
                alert('업로드 중 오류가 발생했습니다: ' + error.message);
            } finally {
                document.getElementById('loading').classList.add('hidden');
            }
        }

        function showResult(data) {
            const resultDiv = document.getElementById('result');
            resultDiv.classList.remove('hidden');

            let html = `
                <div class="space-y-6">
                    <!-- 요약 -->
                    <div class="grid grid-cols-2 gap-4">
                        <div class="result-success p-6 rounded-lg">
                            <div class="text-4xl font-bold text-green-700">${data.created_count}</div>
                            <div class="text-green-600 mt-1">성공</div>
                        </div>
                        <div class="result-error p-6 rounded-lg">
                            <div class="text-4xl font-bold text-red-700">${data.error_count}</div>
                            <div class="text-red-600 mt-1">실패</div>
                        </div>
                    </div>

                    <!-- 다운로드 버튼 -->
                    ${data.created_count > 0 ? `
                        <button onclick="downloadAccounts()" class="w-full px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition">
                            📥 생성된 계정 정보 다운로드 (Excel)
                        </button>
                    ` : ''}

                    <!-- 생성된 매장 목록 -->
                    ${data.created_count > 0 ? `
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">생성된 매장 (${data.created_count}개)</h3>
                            <div class="bg-white border rounded-lg overflow-hidden">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">지사명</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">매장명</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">매장코드</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">이메일</th>
                                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">초기 비밀번호</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        ${data.created_stores.map(store => `
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-4 py-3 text-sm text-gray-900">${store.branch_name}</td>
                                                <td class="px-4 py-3 text-sm text-gray-900">${store.store_name}</td>
                                                <td class="px-4 py-3 text-sm text-gray-600">${store.store_code}</td>
                                                <td class="px-4 py-3 text-sm text-blue-600">${store.email}</td>
                                                <td class="px-4 py-3 text-sm font-mono text-gray-600">${store.password}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    ` : ''}

                    <!-- 에러 목록 -->
                    ${data.error_count > 0 ? `
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-3">오류 (${data.error_count}개)</h3>
                            <div class="space-y-2">
                                ${data.errors.map(error => `
                                    <div class="result-error p-4 rounded-lg">
                                        <div class="font-semibold text-red-700">행 ${error.row}: ${error.error}</div>
                                        ${error.data ? `<div class="text-sm text-red-600 mt-1">${JSON.stringify(error.data)}</div>` : ''}
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    ` : ''}
                </div>
            `;

            resultDiv.innerHTML = html;
        }

        async function downloadAccounts() {
            if (createdStores.length === 0) {
                alert('다운로드할 계정 정보가 없습니다.');
                return;
            }

            try {
                const response = await fetch('/api/stores/bulk/multisheet/download-accounts', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    },
                    body: JSON.stringify({ accounts: createdStores }),
                });

                if (!response.ok) {
                    throw new Error('다운로드 실패');
                }

                // Blob으로 변환하여 다운로드
                const blob = await response.blob();
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = '생성된_매장_계정_' + new Date().toISOString().slice(0,10) + '.xlsx';
                document.body.appendChild(a);
                a.click();
                window.URL.revokeObjectURL(url);
                document.body.removeChild(a);
            } catch (error) {
                console.error('Download error:', error);
                alert('다운로드 중 오류가 발생했습니다: ' + error.message);
            }
        }
    </script>
</body>
</html>
