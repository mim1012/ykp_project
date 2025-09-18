/**
 * 🔒 YKP ERP 세션 안정성 관리 스크립트
 * Railway 환경 최적화된 세션/CSRF 토큰 관리
 */

class SessionManager {
    constructor() {
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        this.lastActivity = Date.now();
        this.sessionWarned = false;
        
        console.log('🔒 SessionManager 초기화 완료');
        this.init();
    }
    
    init() {
        // CSRF 토큰 자동 갱신 (5분마다)
        setInterval(() => this.refreshCSRFToken(), 5 * 60 * 1000);
        
        // 세션 활성 상태 추적
        this.trackUserActivity();
        
        // 세션 만료 경고 (7시간 후)
        setTimeout(() => this.sessionExpiryWarning(), 7 * 60 * 60 * 1000);
        
        // API 호출 인터셉터
        this.interceptFetchCalls();
    }
    
    /**
     * CSRF 토큰 자동 갱신
     */
    async refreshCSRFToken() {
        try {
            const response = await fetch('/api/csrf-token', {
                method: 'GET',
                credentials: 'same-origin'
            });
            
            if (response.ok) {
                const data = await response.json();
                this.csrfToken = data.token;
                
                // meta tag 업데이트
                const metaTag = document.querySelector('meta[name="csrf-token"]');
                if (metaTag) {
                    metaTag.setAttribute('content', data.token);
                }
                
                console.log('🔄 CSRF 토큰 갱신 완료');
            }
        } catch (error) {
            console.warn('⚠️ CSRF 토큰 갱신 실패:', error);
        }
    }
    
    /**
     * 사용자 활동 추적
     */
    trackUserActivity() {
        const events = ['click', 'keypress', 'scroll', 'mousemove'];
        events.forEach(event => {
            document.addEventListener(event, () => {
                this.lastActivity = Date.now();
                this.sessionWarned = false;
            }, { passive: true });
        });
        
        // 30분 비활성 시 경고
        setInterval(() => {
            const inactiveTime = Date.now() - this.lastActivity;
            const inactiveMinutes = Math.floor(inactiveTime / (1000 * 60));
            
            if (inactiveMinutes >= 30 && !this.sessionWarned) {
                this.showSessionWarning();
                this.sessionWarned = true;
            }
        }, 60 * 1000); // 1분마다 체크
    }
    
    /**
     * 세션 만료 경고
     */
    sessionExpiryWarning() {
        if (confirm('⏰ 세션이 1시간 후 만료됩니다.\n계속 사용하시겠습니까?')) {
            this.extendSession();
        }
    }
    
    /**
     * 세션 연장
     */
    async extendSession() {
        try {
            const response = await fetch('/api/extend-session', {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': this.csrfToken,
                    'Content-Type': 'application/json'
                },
                credentials: 'same-origin'
            });
            
            if (response.ok) {
                console.log('✅ 세션 연장 완료');
                this.showToast('세션이 연장되었습니다.', 'success');
            }
        } catch (error) {
            console.error('❌ 세션 연장 실패:', error);
        }
    }
    
    /**
     * 비활성 경고 표시
     */
    showSessionWarning() {
        this.showToast('⏰ 30분간 비활성 상태입니다. 세션 만료에 주의하세요.', 'warning');
    }
    
    /**
     * Fetch API 인터셉터 - 자동 CSRF 토큰 추가
     */
    interceptFetchCalls() {
        const originalFetch = window.fetch;
        
        window.fetch = async (url, options = {}) => {
            // POST/PUT/DELETE 요청에 CSRF 토큰 자동 추가
            if (options.method && ['POST', 'PUT', 'DELETE'].includes(options.method.toUpperCase())) {
                options.headers = options.headers || {};
                
                if (!options.headers['X-CSRF-TOKEN']) {
                    options.headers['X-CSRF-TOKEN'] = this.csrfToken;
                }
                
                // credentials 기본 설정
                options.credentials = options.credentials || 'same-origin';
            }
            
            try {
                const response = await originalFetch(url, options);
                
                // 401 오류 시 자동 로그인 페이지 이동
                if (response.status === 401) {
                    console.warn('⚠️ 세션 만료 감지 - 로그인 페이지로 이동');
                    this.showToast('세션이 만료되었습니다. 다시 로그인해주세요.', 'error');
                    
                    setTimeout(() => {
                        window.location.href = '/login';
                    }, 2000);
                }
                
                return response;
            } catch (error) {
                console.error('❌ API 호출 오류:', error);
                throw error;
            }
        };
    }
    
    /**
     * 토스트 알림 표시
     */
    showToast(message, type = 'info') {
        const toastContainer = document.getElementById('toast-container') || this.createToastContainer();
        
        const toast = document.createElement('div');
        const colors = {
            success: 'bg-green-500',
            error: 'bg-red-500', 
            warning: 'bg-yellow-500',
            info: 'bg-blue-500'
        };
        
        toast.className = `${colors[type]} text-white px-4 py-3 rounded-lg shadow-lg mb-2 transform transition-transform duration-300 translate-x-full`;
        toast.innerHTML = `
            <div class="flex items-center justify-between">
                <span>${message}</span>
                <button onclick="this.parentElement.parentElement.remove()" class="ml-4 text-white hover:text-gray-200">×</button>
            </div>
        `;
        
        toastContainer.appendChild(toast);
        
        // 애니메이션
        setTimeout(() => toast.classList.remove('translate-x-full'), 100);
        setTimeout(() => {
            toast.classList.add('translate-x-full');
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }
    
    /**
     * 토스트 컨테이너 생성
     */
    createToastContainer() {
        const container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'fixed top-4 right-4 z-50 space-y-2';
        document.body.appendChild(container);
        return container;
    }
}

// 전역 세션 매니저 초기화
document.addEventListener('DOMContentLoaded', () => {
    window.sessionManager = new SessionManager();
    console.log('✅ YKP ERP 세션 관리 시스템 활성화');
});

// 전역 함수로 노출 (다른 스크립트에서 사용 가능)
window.YKPSession = {
    refreshToken: () => window.sessionManager?.refreshCSRFToken(),
    extendSession: () => window.sessionManager?.extendSession(),
    getToken: () => window.sessionManager?.csrfToken
};