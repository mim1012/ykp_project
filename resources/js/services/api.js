// Centralized API client with CSRF, timeout, and error handling
class ApiClient {
    constructor() {
        this.baseURL = '/api';
        this.timeout = 30000; // 30초
        this.pendingRequests = new Map();
    }

    getCsrfToken() {
        return window.csrfToken ||
               document.querySelector('meta[name="csrf-token"]')?.content ||
               '';
    }

    getDefaultHeaders() {
        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': this.getCsrfToken(),
            'X-Requested-With': 'XMLHttpRequest'
        };
    }

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

        if (response.status >= 500) {
            console.error(`Server error on ${url}:`, response.status);
            throw new ApiError('서버 오류가 발생했습니다. 잠시 후 다시 시도해주세요.', response.status);
        }

        let errorMessage = `요청 실패: ${response.status}`;
        try {
            const errorData = await response.json();
            errorMessage = errorData.message || errorData.error || errorMessage;
        } catch (e) {
            // JSON 파싱 실패 시 기본 메시지 사용
        }

        throw new ApiError(errorMessage, response.status);
    }

    createTimeoutSignal(timeoutMs = this.timeout) {
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), timeoutMs);

        return {
            signal: controller.signal,
            cleanup: () => clearTimeout(timeoutId)
        };
    }

    async request(url, options = {}) {
        const fullUrl = url.startsWith('http') ? url : `${this.baseURL}${url}`;
        const requestId = `${options.method || 'GET'}_${fullUrl}_${Date.now()}`;
        const { signal, cleanup } = this.createTimeoutSignal(options.timeout);

        try {
            const headers = {
                ...this.getDefaultHeaders(),
                ...options.headers
            };

            const fetchOptions = {
                ...options,
                headers,
                signal,
                credentials: 'same-origin'
            };

            this.pendingRequests.set(requestId, { controller: signal, url: fullUrl });

            const response = await fetch(fullUrl, fetchOptions);

            this.pendingRequests.delete(requestId);

            if (!response.ok) {
                await this.handleError(response, fullUrl);
            }

            const contentType = response.headers.get('content-type');
            if (contentType && contentType.includes('application/json')) {
                return await response.json();
            }

            return await response.text();

        } catch (error) {
            this.pendingRequests.delete(requestId);

            if (error.name === 'AbortError') {
                throw new ApiError('요청 시간이 초과되었습니다.', 408);
            }

            if (error.message === 'Failed to fetch' || error.message.includes('network')) {
                throw new ApiError('네트워크 연결을 확인해주세요.', 0);
            }

            if (error instanceof ApiError) {
                throw error;
            }

            console.error('Unexpected API error:', error);
            throw new ApiError('예상치 못한 오류가 발생했습니다.', 0);

        } finally {
            cleanup();
        }
    }

    async get(url, params = {}, options = {}) {
        const queryString = new URLSearchParams(params).toString();
        const fullUrl = queryString ? `${url}?${queryString}` : url;

        return this.request(fullUrl, {
            ...options,
            method: 'GET'
        });
    }

    async post(url, data = {}, options = {}) {
        return this.request(url, {
            ...options,
            method: 'POST',
            body: JSON.stringify(data)
        });
    }

    async put(url, data = {}, options = {}) {
        return this.request(url, {
            ...options,
            method: 'PUT',
            body: JSON.stringify(data)
        });
    }

    async delete(url, options = {}) {
        return this.request(url, {
            ...options,
            method: 'DELETE'
        });
    }

    async patch(url, data = {}, options = {}) {
        return this.request(url, {
            ...options,
            method: 'PATCH',
            body: JSON.stringify(data)
        });
    }

    cancelAllRequests() {
        this.pendingRequests.forEach((request, id) => {
            console.log(`Cancelling request: ${id}`);
            request.controller.abort();
        });
        this.pendingRequests.clear();
    }

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

class ApiError extends Error {
    constructor(message, status, data = {}) {
        super(message);
        this.name = 'ApiError';
        this.status = status;
        this.data = data;
    }

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

    isRetryable() {
        return this.status === 408 || this.status === 429 || this.status >= 500;
    }
}

const api = new ApiClient();

export default api;

export { ApiClient, ApiError };
