<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>판매관리 시스템 - YKP ERP</title>
    @vite(['resources/css/app.css','resources/js/app.js'])
</head>
<body class="bg-gray-50">
    @include('components.topbar', ['title' => 'YKP ERP 판매관리 시스템'])

    <!-- 메인 컨텐츠 -->
    <main class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
        <!-- 섹션 선택 탭 -->
        <div class="flex justify-center mb-8">
            <div class="bg-white rounded-lg shadow-sm p-1 border">
                <button onclick="showSection('input')" 
                        id="input-tab"
                        class="px-6 py-3 rounded-md text-sm font-medium transition-all duration-200 bg-indigo-600 text-white">
                    개통 입력표
                </button>
                <button onclick="showSection('settlement')" 
                        id="settlement-tab"
                        class="px-6 py-3 rounded-md text-sm font-medium transition-all duration-200 text-slate-600 hover:text-slate-900">
                    정산표
                </button>
            </div>
        </div>

        <!-- 개통 입력 섹션 -->
        <div id="input-section" class="section-content">
            <div class="text-center mb-8">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">📝 개통 데이터 입력</h2>
                <p class="text-gray-600">새로운 개통 건을 입력하고 저장하는 시스템</p>
            </div>

            <!-- 개통 입력 시스템 카드 -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <!-- 완전한 AgGrid -->
            <div class="bg-white rounded-lg shadow-lg hover:shadow-xl transition-shadow">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                            <span class="text-2xl">📊</span>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900">완전한 AgGrid</h3>
                            <span class="px-2 py-1 text-xs bg-blue-100 text-blue-800 rounded">추천</span>
                        </div>
                    </div>
                    <p class="text-gray-600 mb-4">Sales 모델의 모든 40개 필드를 지원하는 완전한 개통표</p>
                    <ul class="text-sm text-gray-500 mb-4 space-y-1">
                        <li>✅ 모든 입력 필드 (40개)</li>
                        <li>✅ 실시간 계산</li>
                        <li>✅ 엑셀 내보내기</li>
                        <li>✅ 키보드 단축키</li>
                    </ul>
                    <a href="/test/complete-aggrid" 
                       class="w-full bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 inline-block text-center">
                        완전한 AgGrid 사용하기
                    </a>
                </div>
            </div>

            <!-- 간단한 AgGrid -->
            <div class="bg-white rounded-lg shadow-lg hover:shadow-xl transition-shadow">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                            <span class="text-2xl">⚡</span>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900">간단한 AgGrid</h3>
                            <span class="px-2 py-1 text-xs bg-green-100 text-green-800 rounded">빠른 시작</span>
                        </div>
                    </div>
                    <p class="text-gray-600 mb-4">핵심 필드만으로 빠른 데이터 입력</p>
                    <ul class="text-sm text-gray-500 mb-4 space-y-1">
                        <li>✅ 핵심 필드 (12개)</li>
                        <li>✅ 실시간 계산</li>
                        <li>✅ 빠른 로딩</li>
                        <li>✅ 새 행 추가 확인됨</li>
                    </ul>
                    <a href="/test/simple-aggrid" 
                       class="w-full bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 inline-block text-center">
                        간단한 AgGrid 사용하기
                    </a>
                </div>
            </div>

            <!-- 기존 엑셀 입력 -->
            <div class="bg-white rounded-lg shadow-lg hover:shadow-xl transition-shadow">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-yellow-100 rounded-lg flex items-center justify-center">
                            <span class="text-2xl">📑</span>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900">엑셀 입력</h3>
                            <span class="px-2 py-1 text-xs bg-yellow-100 text-yellow-800 rounded">기존 시스템</span>
                        </div>
                    </div>
                    <p class="text-gray-600 mb-4">Handsontable 기반 엑셀 유사 인터페이스</p>
                    <ul class="text-sm text-gray-500 mb-4 space-y-1">
                        <li>✅ 엑셀 스타일 편집</li>
                        <li>✅ 복사/붙여넣기</li>
                        <li>✅ 검증 기능</li>
                        <li>⚠️ 로딩 시간 있음</li>
                    </ul>
                    <a href="/sales/excel-input" 
                       class="w-full bg-yellow-500 text-white px-4 py-2 rounded hover:bg-yellow-600 inline-block text-center">
                        엑셀 입력 사용하기
                    </a>
                </div>
            </div>

            <!-- 고급 입력 Pro -->
            <div class="bg-white rounded-lg shadow-lg hover:shadow-xl transition-shadow">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                            <span class="text-2xl">🚀</span>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900">고급 입력 Pro</h3>
                            <span class="px-2 py-1 text-xs bg-purple-100 text-purple-800 rounded">전문가용</span>
                        </div>
                    </div>
                    <p class="text-gray-600 mb-4">React + AgGrid 기반 전문적인 데이터 입력</p>
                    <ul class="text-sm text-gray-500 mb-4 space-y-1">
                        <li>✅ React 컴포넌트</li>
                        <li>✅ 고급 필터링</li>
                        <li>✅ 전문 기능</li>
                        <li>✅ 높은 성능</li>
                    </ul>
                    <a href="/sales/advanced-input-pro" 
                       class="w-full bg-purple-500 text-white px-4 py-2 rounded hover:bg-purple-600 inline-block text-center">
                        고급 입력 사용하기
                    </a>
                </div>
            </div>

            <!-- 개선된 입력 -->
            <div class="bg-white rounded-lg shadow-lg hover:shadow-xl transition-shadow">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-gray-100 rounded-lg flex items-center justify-center">
                            <span class="text-2xl">📝</span>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900">개선된 입력</h3>
                            <span class="px-2 py-1 text-xs bg-gray-100 text-gray-800 rounded">기본</span>
                        </div>
                    </div>
                    <p class="text-gray-600 mb-4">Alpine.js 기반 개선된 폼 입력</p>
                    <ul class="text-sm text-gray-500 mb-4 space-y-1">
                        <li>✅ 간단한 인터페이스</li>
                        <li>✅ 빠른 로딩</li>
                        <li>✅ 기본 검증</li>
                        <li>✅ 모바일 친화적</li>
                    </ul>
                    <a href="/sales/improved-input" 
                       class="w-full bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 inline-block text-center">
                        개선된 입력 사용하기
                    </a>
                </div>
            </div>

            <!-- 관리자 패널 -->
            <div class="bg-white rounded-lg shadow-lg hover:shadow-xl transition-shadow">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center">
                            <span class="text-2xl">⚙️</span>
                        </div>
                        <div class="ml-4">
                            <h3 class="text-lg font-semibold text-gray-900">관리자 패널</h3>
                            <span class="px-2 py-1 text-xs bg-red-100 text-red-800 rounded">Filament</span>
                        </div>
                    </div>
                    <p class="text-gray-600 mb-4">Filament 기반 전문 관리자 인터페이스</p>
                    <ul class="text-sm text-gray-500 mb-4 space-y-1">
                        <li>✅ 데이터 관리</li>
                        <li>✅ 사용자 관리</li>
                        <li>✅ 통계 및 리포트</li>
                        <li>✅ 권한 관리</li>
                    </ul>
                    <a href="/admin" 
                       class="w-full bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600 inline-block text-center">
                        관리자 패널 접속
                    </a>
                </div>
            </div>
        </div>
        </div>

        <!-- 정산 섹션 -->
        <div id="settlement-section" class="section-content hidden">
            <div class="text-center mb-8">
                <h2 class="text-3xl font-bold text-gray-900 mb-4">📊 정산표 시스템</h2>
                <p class="text-gray-600">입력된 개통 데이터의 정산 내역을 확인하고 계산하는 시스템</p>
            </div>

            <!-- 정산 시스템 카드 -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                <!-- YKP 정산 시스템 -->
                <div class="bg-white rounded-lg shadow-lg hover:shadow-xl transition-shadow">
                    <div class="p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-emerald-100 rounded-lg flex items-center justify-center">
                                <span class="text-2xl">📊</span>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">YKP 정산 시스템</h3>
                                <span class="px-2 py-1 text-xs bg-emerald-100 text-emerald-800 rounded">React + TypeScript</span>
                            </div>
                        </div>
                        <p class="text-gray-600 mb-4">전문적인 정산표 시스템으로 엑셀과 동일한 UX 제공</p>
                        <ul class="text-sm text-gray-500 mb-4 space-y-1">
                            <li>✅ 엑셀 스타일 인터페이스</li>
                            <li>✅ 실시간 정산 계산</li>
                            <li>✅ 키보드 단축키 (F1 도움말)</li>
                            <li>✅ 복사/붙여넣기 지원</li>
                            <li>✅ 자동 검증 및 표준화</li>
                            <li>✅ 프로파일별 정책 적용</li>
                        </ul>
                        <button onclick="openSettlement()" 
                               class="w-full bg-emerald-500 text-white px-4 py-2 rounded hover:bg-emerald-600">
                            YKP 정산 시스템 열기
                        </button>
                    </div>
                </div>

                <!-- 관리자 정산 조회 -->
                <div class="bg-white rounded-lg shadow-lg hover:shadow-xl transition-shadow">
                    <div class="p-6">
                        <div class="flex items-center mb-4">
                            <div class="w-12 h-12 bg-indigo-100 rounded-lg flex items-center justify-center">
                                <span class="text-2xl">📈</span>
                            </div>
                            <div class="ml-4">
                                <h3 class="text-lg font-semibold text-gray-900">관리자 정산 조회</h3>
                                <span class="px-2 py-1 text-xs bg-indigo-100 text-indigo-800 rounded">Filament</span>
                            </div>
                        </div>
                        <p class="text-gray-600 mb-4">기존 정산 데이터를 조회하고 관리하는 시스템</p>
                        <ul class="text-sm text-gray-500 mb-4 space-y-1">
                            <li>✅ 전체 정산 내역 조회</li>
                            <li>✅ 필터링 및 검색</li>
                            <li>✅ 통계 및 집계</li>
                            <li>✅ 엑셀 내보내기</li>
                            <li>✅ 권한별 접근 제어</li>
                        </ul>
                        <a href="/admin" 
                           class="w-full bg-indigo-500 text-white px-4 py-2 rounded hover:bg-indigo-600 inline-block text-center">
                            관리자 패널 접속
                        </a>
                    </div>
                </div>
            </div>

            <!-- 정산 시스템 특징 -->
            <div class="bg-gradient-to-r from-emerald-50 to-blue-50 rounded-lg p-6 mb-8">
                <h3 class="text-lg font-semibold text-gray-900 mb-4">💡 정산 시스템 특징</h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h4 class="font-medium text-gray-900 mb-2">📊 정산 계산 로직</h4>
                        <ul class="text-sm text-gray-600 space-y-1">
                            <li>• 리베총계 = 액면가 + 구두1 + 구두2 + 그레이드 + 부가추가</li>
                            <li>• 정산금 = 리베총계 - 서류상현금 + 유심비 + 신규/MNP할인</li>
                            <li>• 부가세 = 정산금 × 세율(13.3%)</li>
                            <li>• 세후마진 = 정산금 - 부가세 + 현금받음 + 페이백</li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="font-medium text-gray-900 mb-2">⚙️ 자동화 기능</h4>
                        <ul class="text-sm text-gray-600 space-y-1">
                            <li>• 통신사명 자동 표준화 (sk → SKT, kt → KT)</li>
                            <li>• 개통방식 드롭다운 (신규/MNP/기변)</li>
                            <li>• 날짜 형식 자동 검증 (YYYY-MM-DD)</li>
                            <li>• 금액 자동 부호 적용 및 천 단위 콤마</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- 비교 표 -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="px-6 py-4 border-b">
                <h3 class="text-lg font-semibold text-gray-900">시스템 비교</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">시스템</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">필드 수</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">기술 스택</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">특징</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">추천 대상</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">상태</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">완전한 AgGrid</div>
                                <div class="text-sm text-gray-500">complete-aggrid</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                    40개 필드
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                JavaScript + Laravel API
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                실시간 계산, 모든 필드, 엑셀 내보내기
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                전문 사용자, 상세 입력
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    ✅ 완료
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">간단한 AgGrid</div>
                                <div class="text-sm text-gray-500">simple-aggrid</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    12개 필드
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                JavaScript + Laravel API
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                빠른 로딩, 핵심 기능만
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                일반 사용자, 빠른 입력
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    ✅ 완료
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">엑셀 입력</div>
                                <div class="text-sm text-gray-500">excel-input</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    기존 필드
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                Handsontable
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                엑셀 완전 호환, 복사/붙여넣기
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                엑셀 사용자
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                    기존
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">고급 입력 Pro</div>
                                <div class="text-sm text-gray-500">advanced-input-pro</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                    확장 필드
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                React + AgGrid
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                전문적 UI, 고급 필터링
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                개발자, 고급 사용자
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                    고급
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900">관리자 패널</div>
                                <div class="text-sm text-gray-500">Filament</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">
                                    전체 관리
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                Laravel Filament
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                CRUD, 사용자 관리, 통계
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                관리자, 운영팀
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                    ✅ 작동
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- API 테스트 섹션 -->
        <div class="bg-white rounded-lg shadow p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">API 테스트</h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <button onclick="testCalculationAPI()" 
                            class="w-full bg-indigo-500 text-white px-4 py-2 rounded hover:bg-indigo-600">
                        계산 API 테스트
                    </button>
                </div>
                <div>
                    <button onclick="testProfileAPI()" 
                            class="w-full bg-cyan-500 text-white px-4 py-2 rounded hover:bg-cyan-600">
                        프로파일 API 테스트
                    </button>
                </div>
                <div>
                    <button onclick="runFullSystemTest()" 
                            class="w-full bg-orange-500 text-white px-4 py-2 rounded hover:bg-orange-600">
                        전체 시스템 테스트
                    </button>
                </div>
            </div>
            <div id="api-test-result" class="mt-4 p-4 bg-gray-50 rounded hidden">
                <h4 class="font-semibold mb-2">테스트 결과:</h4>
                <pre id="test-output" class="text-sm"></pre>
            </div>
        </div>
    </main>

    <script>
        // 섹션 전환 함수
        function showSection(section) {
            // 모든 섹션 숨기기
            document.querySelectorAll('.section-content').forEach(el => {
                el.classList.add('hidden');
            });
            
            // 모든 탭 비활성화
            document.querySelectorAll('[id$="-tab"]').forEach(tab => {
                tab.classList.remove('bg-blue-500', 'text-white');
                tab.classList.add('text-gray-600');
            });
            
            // 선택된 섹션 보이기
            document.getElementById(section + '-section').classList.remove('hidden');
            
            // 선택된 탭 활성화
            const selectedTab = document.getElementById(section + '-tab');
            selectedTab.classList.remove('text-gray-600');
            selectedTab.classList.add('bg-blue-500', 'text-white');
        }
        
        // 정산 시스템 열기 함수
        function openSettlement() {
            // 정산 시스템이 별도 포트에서 실행 중인지 확인
            const settlementUrl = 'http://localhost:5173'; // Vite 기본 포트
            
            // 새 창으로 정산 시스템 열기
            const settlementWindow = window.open(settlementUrl, '_blank', 'width=1400,height=800');
            
            // 만약 정산 시스템이 실행되지 않고 있다면 안내 메시지
            setTimeout(() => {
                if (settlementWindow.closed) {
                    alert('❌ YKP 정산 시스템이 실행되지 않고 있습니다.\n\n다음 명령어로 정산 시스템을 먼저 실행해주세요:\n\ncd ykp-settlement\nnpm run dev');
                }
            }, 1000);
        }

        // API 테스트 함수들
        async function testCalculationAPI() {
            const resultDiv = document.getElementById('api-test-result');
            const outputPre = document.getElementById('test-output');
            
            resultDiv.classList.remove('hidden');
            outputPre.textContent = '계산 API 테스트 중...';
            
            try {
                const response = await fetch('/api/calculation/row', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        price_setting: 100000,
                        verbal1: 30000,
                        verbal2: 20000
                    })
                });
                
                const result = await response.json();
                outputPre.textContent = JSON.stringify(result, null, 2);
            } catch (error) {
                outputPre.textContent = 'API 테스트 실패: ' + error.message;
            }
        }
        
        async function testProfileAPI() {
            const resultDiv = document.getElementById('api-test-result');
            const outputPre = document.getElementById('test-output');
            
            resultDiv.classList.remove('hidden');
            outputPre.textContent = '프로파일 API 테스트 중...';
            
            try {
                const response = await fetch('/api/calculation/profiles');
                const result = await response.json();
                outputPre.textContent = JSON.stringify(result, null, 2);
            } catch (error) {
                outputPre.textContent = '프로파일 API 테스트 실패: ' + error.message;
            }
        }
        
        async function runFullSystemTest() {
            const resultDiv = document.getElementById('api-test-result');
            const outputPre = document.getElementById('test-output');
            
            resultDiv.classList.remove('hidden');
            outputPre.textContent = '전체 시스템 테스트 중...\n';
            
            const tests = [
                { name: '계산 API', url: '/api/calculation/row', method: 'POST', data: {price_setting: 100000} },
                { name: '배치 API', url: '/api/calculation/batch', method: 'POST', data: {rows: [{price_setting: 100000}]} },
                { name: '프로파일 API', url: '/api/calculation/profiles', method: 'GET' }
            ];
            
            let results = '';
            
            for (const test of tests) {
                try {
                    const startTime = performance.now();
                    const response = await fetch(test.url, {
                        method: test.method,
                        headers: test.method === 'POST' ? {'Content-Type': 'application/json'} : {},
                        body: test.method === 'POST' ? JSON.stringify(test.data) : undefined
                    });
                    const endTime = performance.now();
                    
                    const result = await response.json();
                    results += `✅ ${test.name}: ${response.status} (${(endTime - startTime).toFixed(1)}ms)\n`;
                } catch (error) {
                    results += `❌ ${test.name}: 실패 (${error.message})\n`;
                }
            }
            
            outputPre.textContent = results;
        }
    </script>
</body>
</html>
