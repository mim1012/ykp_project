import React from 'react';

/**
 * React Error Boundary Component
 *
 * React 컴포넌트 트리에서 발생하는 JavaScript 에러를 포착하고,
 * 에러 로그를 기록하며, 대체 UI를 표시합니다.
 *
 * 사용법:
 * <ErrorBoundary>
 *   <YourComponent />
 * </ErrorBoundary>
 */
class ErrorBoundary extends React.Component {
    constructor(props) {
        super(props);
        this.state = {
            hasError: false,
            error: null,
            errorInfo: null,
            errorCount: 0
        };
    }

    static getDerivedStateFromError(error) {
        // 다음 렌더링에서 대체 UI를 표시하도록 상태를 업데이트합니다.
        return { hasError: true };
    }

    componentDidCatch(error, errorInfo) {
        // 에러 로깅 서비스에 에러를 기록할 수 있습니다
        console.error('ErrorBoundary caught an error:', error, errorInfo);

        // 상태 업데이트
        this.setState(prevState => ({
            error: error,
            errorInfo: errorInfo,
            errorCount: prevState.errorCount + 1
        }));

        // Sentry 등 에러 추적 서비스에 전송 (선택사항)
        if (window.Sentry) {
            window.Sentry.captureException(error, {
                extra: {
                    componentStack: errorInfo.componentStack,
                    errorCount: this.state.errorCount + 1
                }
            });
        }

        // 개발 환경에서는 콘솔에 상세 정보 출력
        if (process.env.NODE_ENV === 'development') {
            console.group('Error Boundary Details');
            console.error('Error:', error.toString());
            console.error('Component Stack:', errorInfo.componentStack);
            console.groupEnd();
        }
    }

    handleReset = () => {
        // 에러 상태 초기화
        this.setState({
            hasError: false,
            error: null,
            errorInfo: null
        });

        // 페이지 새로고침 (선택사항)
        if (this.props.resetOnError) {
            window.location.reload();
        }
    }

    handleReload = () => {
        window.location.reload();
    }

    render() {
        if (this.state.hasError) {
            // 커스텀 대체 UI 사용 (props로 전달된 경우)
            if (this.props.fallback) {
                return this.props.fallback({
                    error: this.state.error,
                    errorInfo: this.state.errorInfo,
                    resetError: this.handleReset
                });
            }

            // 기본 에러 UI
            return (
                <div className="min-h-screen flex items-center justify-center bg-gray-50 px-4">
                    <div className="max-w-md w-full bg-white shadow-lg rounded-lg p-8">
                        {/* 에러 아이콘 */}
                        <div className="flex justify-center mb-4">
                            <div className="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center">
                                <svg
                                    className="w-8 h-8 text-red-600"
                                    fill="none"
                                    stroke="currentColor"
                                    viewBox="0 0 24 24"
                                >
                                    <path
                                        strokeLinecap="round"
                                        strokeLinejoin="round"
                                        strokeWidth={2}
                                        d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                    />
                                </svg>
                            </div>
                        </div>

                        {/* 에러 메시지 */}
                        <h2 className="text-xl font-bold text-gray-900 text-center mb-2">
                            문제가 발생했습니다
                        </h2>
                        <p className="text-gray-600 text-center mb-6">
                            예상치 못한 오류가 발생했습니다. 페이지를 새로고침하거나 잠시 후 다시 시도해주세요.
                        </p>

                        {/* 개발 환경에서만 에러 상세 정보 표시 */}
                        {process.env.NODE_ENV === 'development' && this.state.error && (
                            <details className="mb-6 p-4 bg-gray-100 rounded border border-gray-300">
                                <summary className="cursor-pointer font-medium text-sm text-gray-700 mb-2">
                                    개발자 정보 (프로덕션에서는 숨김)
                                </summary>
                                <div className="mt-2">
                                    <p className="text-xs font-mono text-red-600 mb-2">
                                        {this.state.error.toString()}
                                    </p>
                                    <pre className="text-xs overflow-auto max-h-40 bg-white p-2 rounded border">
                                        {this.state.errorInfo?.componentStack}
                                    </pre>
                                </div>
                            </details>
                        )}

                        {/* 액션 버튼 */}
                        <div className="flex gap-3">
                            <button
                                onClick={this.handleReload}
                                className="flex-1 bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-lg transition-colors"
                            >
                                페이지 새로고침
                            </button>
                            <button
                                onClick={this.handleReset}
                                className="flex-1 bg-gray-200 hover:bg-gray-300 text-gray-800 font-medium py-2 px-4 rounded-lg transition-colors"
                            >
                                다시 시도
                            </button>
                        </div>

                        {/* 에러 발생 횟수 (개발 환경) */}
                        {process.env.NODE_ENV === 'development' && this.state.errorCount > 1 && (
                            <p className="text-xs text-gray-500 text-center mt-4">
                                에러 발생 횟수: {this.state.errorCount}
                            </p>
                        )}

                        {/* 지원 링크 */}
                        <div className="mt-6 pt-6 border-t border-gray-200">
                            <p className="text-sm text-gray-600 text-center">
                                문제가 계속되면{' '}
                                <a
                                    href="/dashboard"
                                    className="text-blue-600 hover:text-blue-700 font-medium"
                                >
                                    대시보드로 돌아가기
                                </a>
                            </p>
                        </div>
                    </div>
                </div>
            );
        }

        // 에러가 없으면 자식 컴포넌트를 정상적으로 렌더링
        return this.props.children;
    }
}

export default ErrorBoundary;
