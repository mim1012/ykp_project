import React, { useState, useEffect, Suspense, lazy, memo } from 'react';
import { createRoot } from 'react-dom/client';
import { AnimatePresence } from 'framer-motion';
import { Sidebar } from './components/dashboard';
import { QueryProvider } from './providers/QueryProvider';
import { AnimatedRoute } from './components/animations/PageTransition';
import { LoadingSpinner } from './components/ui/LoadingSpinner';
import ErrorBoundary from './components/ErrorBoundary';

// Lazy load heavy components for code splitting
const Dashboard = lazy(() => import('./components/dashboard').then(module => ({ default: module.Dashboard })));
const StoreManagement = lazy(() => import('./components/dashboard').then(module => ({ default: module.StoreManagement })));
const Reports = lazy(() => import('./components/dashboard').then(module => ({ default: module.Reports })));

// Enhanced loading component with skeleton
const EnhancedLoadingSpinner = memo(() => (
    <LoadingSpinner 
        variant="ring" 
        size="lg" 
        message="페이지를 불러오는 중..." 
    />
));

// Settings placeholder component
const Settings = () => (
    <div className="space-y-6">
        <h1 className="text-2xl font-bold text-gray-900">설정</h1>
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-6">
            <p className="text-gray-600">설정 페이지는 현재 개발 중입니다.</p>
        </div>
    </div>
);

// Custom hook to detect mobile
const useIsMobile = () => {
    const [isMobile, setIsMobile] = useState(false);

    useEffect(() => {
        const checkIsMobile = () => {
            setIsMobile(window.innerWidth < 768); // md breakpoint
        };

        // Check on mount
        checkIsMobile();

        // Add listener for window resize
        window.addEventListener('resize', checkIsMobile);
        
        return () => window.removeEventListener('resize', checkIsMobile);
    }, []);

    return isMobile;
};

// Main App Component with optimizations
const App = memo(() => {
    const [activeMenu, setActiveMenu] = useState('dashboard');
    const isMobile = useIsMobile();
    
    // Service worker registration (disabled for now)
    // useEffect(() => {
    //     if ('serviceWorker' in navigator) {
    //         navigator.serviceWorker.register('/sw.js')
    //             .then((registration) => {
    //                 console.log('Service Worker registered:', registration);
    //             })
    //             .catch((error) => {
    //                 console.error('Service Worker registration failed:', error);
    //             });
    //     }
    // }, []);

    const renderContent = () => {
        const contentMap = {
            dashboard: Dashboard,
            stores: StoreManagement,
            reports: Reports,
            settings: Settings
        };
        
        const Component = contentMap[activeMenu] || Dashboard;
        const isSettings = activeMenu === 'settings';
        
        return (
            <AnimatedRoute routeKey={activeMenu} transitionType="fade">
                {isSettings ? (
                    <Settings />
                ) : (
                    <Suspense fallback={<EnhancedLoadingSpinner />}>
                        <Component />
                    </Suspense>
                )}
            </AnimatedRoute>
        );
    };

    return (
        <QueryProvider>
            <div className="flex min-h-screen bg-gray-50">
                <Sidebar 
                    activeMenu={activeMenu} 
                    setActiveMenu={setActiveMenu} 
                    isMobile={isMobile}
                />
                <main className={`flex-1 overflow-auto ${
                    isMobile 
                        ? 'pt-14' // Add top padding for mobile header
                        : 'ml-16 p-6' // Desktop left margin and padding
                }`}>
                    <div className={`max-w-7xl mx-auto ${isMobile ? 'p-4' : ''}`}>
                        {renderContent()}
                    </div>
                </main>
            </div>
        </QueryProvider>
    );
});

App.displayName = 'App';

// Initialize the app
const container = document.getElementById('dashboard-root');
if (container) {
    const root = createRoot(container);
    root.render(
        <ErrorBoundary>
            <App />
        </ErrorBoundary>
    );
}