// HTTP Helper with CSRF protection
export const secureRequest = async (url, options = {}) => {
    const defaultHeaders = {
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': window.csrfToken,
        'X-Requested-With': 'XMLHttpRequest'
    };

    return fetch(url, {
        ...options,
        headers: {
            ...defaultHeaders,
            ...options.headers
        },
        credentials: 'same-origin' // Include cookies for session auth
    });
};

export const handleApiResponse = async (response) => {
    if (response.status === 401) {
        // Unauthorized - do NOT redirect automatically to prevent infinite loops
        console.warn('401 Unauthorized - user not authenticated');
        throw new Error('로그인이 필요합니다.');
    } else if (response.status === 403) {
        // Forbidden - show access denied message
        throw new Error('접근 권한이 없습니다.');
    } else if (response.status === 500) {
        // Database or server error
        throw new Error('데이터베이스 연결에 문제가 있습니다. 관리자에게 문의하세요.');
    } else if (response.ok) {
        return await response.json();
    } else {
        throw new Error('요청 처리 중 오류가 발생했습니다.');
    }
};