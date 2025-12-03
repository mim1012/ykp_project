{{-- CSRF Token Refresh Component --}}
<script>
    // Production debug flag check (inherit from parent or default to false)
    const csrfLog = (...args) => (window.DEBUG === true) && console.log(...args);

    // CSRF 토큰 자동 갱신 함수
    window.refreshCsrfToken = async function() {
        try {
            // Laravel의 CSRF 쿠키 엔드포인트 호출
            const response = await fetch('/sanctum/csrf-cookie', {
                credentials: 'same-origin'
            });

            if (response.ok) {
                // 쿠키에서 새 토큰 가져오기
                const cookies = document.cookie.split(';');
                let xsrfToken = null;

                for (let cookie of cookies) {
                    const [name, value] = cookie.trim().split('=');
                    if (name === 'XSRF-TOKEN') {
                        xsrfToken = decodeURIComponent(value);
                        break;
                    }
                }

                // 메타 태그 업데이트
                if (xsrfToken) {
                    const metaTag = document.querySelector('meta[name="csrf-token"]');
                    if (metaTag) {
                        metaTag.setAttribute('content', xsrfToken);
                        csrfLog('✅ CSRF 토큰 갱신 완료');
                        return xsrfToken;
                    }
                }
            }
        } catch (error) {
            // Silent fail in production
        }
        return null;
    };

    // 419 에러 발생 시 자동 토큰 갱신
    window.handleCsrfError = async function(retry = true) {
        csrfLog('🔄 CSRF 토큰 오류 감지 - 토큰 갱신 시도');
        const newToken = await refreshCsrfToken();

        if (newToken && retry) {
            csrfLog('🔄 새 토큰으로 재시도');
            return newToken;
        } else {
            if (confirm('보안 토큰이 만료되었습니다. 페이지를 새로고침하시겠습니까?')) {
                location.reload();
            }
            return null;
        }
    };

    // 페이지 로드 시 토큰 갱신 (30분마다)
    setInterval(refreshCsrfToken, 30 * 60 * 1000);
</script>
