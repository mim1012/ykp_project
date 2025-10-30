/**
 * 중앙화된 API 클라이언트
 *
 * 모든 API 요청을 통합 관리하여:
 * - 일관된 에러 처리
 * - 자동 CSRF 토큰 포함
 * - 401 에러 시 자동 로그인 페이지 리다이렉트
 * - 요청 취소 기능
 * - 타임아웃 설정
 *
 * 사용 예시:
 * import api from '@/services/api';
 * const data = await api.get('/dashboard/overview');
 * const result = await api.post('/sales/bulk-save', { sales: [...] });
 */

class ApiClient {
    constructor() {
        this.baseURL = '/api';
        this.timeout = 30000; // 30초
        this.pendingRequests = new Map();
    }

    /**
     * CSRF 토큰 가져오기
     */
    getCsrfToken() {
        return window.csrfToken ||
               document.querySelector('meta[name="csrf-token"]')?.content ||
               '';
    }

    /**
     * 기본 헤더 생성
     */
    getDefaultHeaders() {
        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': this.getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest'
        };
    }

    /**
     * 에러 처리
     */
    async handleError(response, url) {
        // 401 Unauthorized - 세션 만료
        if (response.status === 401) {
            console.warn('Session expired, redirecting to login...');
            window.location.href = '/login?expired=true';
            throw new ApiError('세션이 만료되었습니다. 다시 로그인해주세요.', 401);
        }

        // 419 CSRF Token Mismatch
        if (response.status === 419) {
            console.error('CSRF token mismatch');
            throw new ApiError('보안 토큰이 만료되었습니다. 페이지를 새로고침해주세요.', 419);
        }

        // 429 Too Many Requests
        if (response.status === 429) {
            const retryAfter = response.headers.get('Retry-After');
            const message = retryAfter
                ? `요청이 너무 많습니다. ${retryAfter}초 후에 다시 시도해주세요.`
                : '요청이 너무 많습니다. 잠시 후 다시 시도해주세요.';
            throw new ApiError(message, 429, { retryAfter });
        }

        // 500 Internal Server Error
        if (response.status >= 500) {
            console.error(`Server error on ${url}:`, response.status);
            throw new ApiError('서버 오류가 발생했습니다. 잠시 후 다시 시도해주세요.', response.status);
        }

        // 기타 에러
        let errorMessage = `요청 실패: ${response.status}`;
        try {
            const errorData = await response.json();
            errorMessage = errorData.message || errorData.error || errorMessage;
        } catch (e) {
            // JSON 파싱 실패 시 기본 메시지 사용
        }

        throw new ApiError(errorMessage, response.status);
    }

    /**
     * 타임아웃 처리
     */
    createTimeoutSignal(timeoutMs = this.timeout) {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), timeoutMs);

        return {
            signal: controller.signal,
            cleanup: () => clearTimeout(timeoutId)
        };
    }

    /**
     * 기본 요청 메서드
     */
    async request(url, options = {}) {
        const fullUrl = url.startsWith('http') ? url : `${this.baseURL}${url}`;
        const requestId = `${options.method || 'GET'}_${fullUrl}_${Date.now()}`;

        // 타임아웃 설정
        const { signal, cleanup } = this.createTimeoutSignal(options.timeout);

        try {
            // 기본 헤더와 사용자 헤더 병합
            const headers = {
                ...this.getDefaultHeaders(),
                ...options.headers
            };

            // fetch 옵션 구성
            const fetchOptions = {
                ...options,
                headers,
                signal,
                credentials: 'same-origin' // 쿠키 포함
            };

            // 요청 시작
            this.pendingRequests.set(requestId, { controller: signal, url: fullUrl });

            const response = await fetch(fullUrl, fetchOptions);

            // 요청 완료 - pending 목록에서 제거
            this.pendingRequests.delete(requestId);

            // 에러 처리
            if (!response.ok) {
                await this.handleError(response, fullUrl);
            }

            // 성공 응답 처리
            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return await response.json();
            }

            return await response.text();

        } catch (error) {
            this.pendingRequests.delete(requestId);

            // AbortError (타임아웃 또는 수동 취소)
            if (error.name === 'AbortError') {
                throw new ApiError('요청 시간이 초과되었습니다.', 408);
            }

            // NetworkError
            if (error.message === 'Failed to fetch' || error.message.includes('network')) {
                throw new ApiError('네트워크 연결을 확인해주세요.', 0);
            }

            // 이미 ApiError인 경우 그대로 throw
            if (error instanceof ApiError) {
                throw error;
            }

            // 기타 예상치 못한 에러
            console.error('Unexpected API error:', error);
            throw new ApiError('예상치 못한 오류가 발생했습니다.', 0);

        } finally {
            cleanup();
        }
    }

    /**
     * GET 요청
     */
    async get(url, params = {}, options = {}) {
        // URL 파라미터 추가
        const queryString = new URLSearchParams(params).toString();
        const fullUrl = queryString ? `${url}?${queryString}` : url;

        return this.request(fullUrl, {
            ...options,
            method: 'GET'
        });
    }

    /**
     * POST 요청
     */
    async post(url, data = {}, options = {}) {
        return this.request(url, {
            ...options,
            method: 'POST',
            body: JSON.stringify(data)
        });
    }

    /**
     * PUT 요청
     */
    async put(url, data = {}, options = {}) {
        return this.request(url, {
            ...options,
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }

    /**
     * DELETE 요청
     */
    async delete(url, options = {}) {
        return this.request(url, {
            ...options,
            method: 'DELETE'
        });
    }

    /**
     * PATCH 요청
     */
    async patch(url, data = {}, options = {}) {
        return this.request(url, {
            ...options,
            method: 'PATCH',
            body: JSON.stringify(data)
        });
    }

    /**
     * 모든 진행 중인 요청 취소
     */
    cancelAllRequests() {
        this.pendingRequests.forEach((request, id) => {
            console.log(`Cancelling request: ${id}`);
            request.controller.abort();
        });
        this.pendingRequests.clear();
    }

    /**
     * 특정 URL의 요청 취소
     */
    cancelRequest(url) {
        const entries = Array.from(this.pendingRequests.entries());
        const match = entries.find(([id, req]) => req.url.includes(url));

        if (match) {
            const [id, request] = match;
            console.log(`Cancelling request: ${id}`);
            request.controller.abort();
            this.pendingRequests.delete(id);
        }
    }
}

/**
 * 커스텀 API 에러 클래스
 */
class ApiError extends Error {
    constructor(message, status, data = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.data = data;
    }

    /**
     * 사용자 친화적 에러 메시지
     */
    getUserMessage() {
        switch (this.status) {
            case 0:
                return '네트워크 연결을 확인해주세요.';
            case 401:
                return '로그인이 필요합니다.';
            case 403:
                return '접근 권한이 없습니다.';
            case 404:
                return '요청한 데이터를 찾을 수 없습니다.';
            case 408:
                return '요청 시간이 초과되었습니다.';
            case 419:
                return '페이지를 새로고침해주세요.';
            case 422:
                return '입력 데이터를 확인해주세요.';
            case 429:
                return '요청이 너무 많습니다. 잠시 후 다시 시도해주세요.';
            case 500:
            case 502:
            case 503:
                return '서버 오류가 발생했습니다. 잠시 후 다시 시도해주세요.';
            default:
                return this.message || '오류가 발생했습니다.';
        }
    }

    /**
     * 재시도 가능 여부
     */
    isRetryable() {
        return this.status === 408 || this.status === 429 || this.status >= 500;
    }
}

// 싱글톤 인스턴스 생성
const api = new ApiClient();

// 기본 export
export default api;

// Named exports
export { ApiClient, ApiError };
