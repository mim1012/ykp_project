import React, { useState, useEffect } from 'react';
import { Icon } from '../ui';
import { UserProfile } from './UserProfile';
import { handleLogout } from '../../utils/auth';

export const Sidebar = ({ activeMenu, setActiveMenu, isMobile = false }) => {
    const [isMobileMenuOpen, setIsMobileMenuOpen] = useState(false);
    
    const menuItems = [
        { id: 'dashboard', icon: 'layout-dashboard', label: '대시보드' },
        ...(window.features?.excel_input_form ? [
            { id: 'excel-input', icon: 'grid-3x3', label: '개통표 입력 (Excel)', url: '/sales/excel-input' }
        ] : []),
        { id: 'complete-aggrid', icon: 'table', label: '완전한 AgGrid', url: '/test/complete-aggrid', badge: 'DEV ONLY' },
        { id: 'simple-aggrid', icon: 'grid', label: '간단 AgGrid', url: '/test/simple-aggrid', badge: 'DEV ONLY' },
        { id: 'stores', icon: 'store', label: '매장 관리' },
        { id: 'reports', icon: 'file-text', label: '보고서' },
        { id: 'settings', icon: 'settings', label: '설정' }
    ];

    // Close mobile menu when clicking outside
    useEffect(() => {
        const handleClickOutside = (event) => {
            if (isMobileMenuOpen && !event.target.closest('.mobile-sidebar')) {
                setIsMobileMenuOpen(false);
            }
        };

        document.addEventListener('click', handleClickOutside);
        return () => document.removeEventListener('click', handleClickOutside);
    }, [isMobileMenuOpen]);

    // Close mobile menu on route change
    useEffect(() => {
        setIsMobileMenuOpen(false);
    }, [activeMenu]);

    // Desktop Sidebar
    if (!isMobile) {
        return (
            <div className="fixed left-0 top-0 h-full w-16 bg-white border-r border-gray-200 flex flex-col items-center py-4 z-50">
                <div className="mb-6">
                    <div className="w-10 h-10 bg-gradient-to-br from-primary-500 to-purple-600 rounded-xl flex items-center justify-center text-white font-bold">
                        Y
                    </div>
                </div>
                
                <UserProfile user={window.userData} />
                
                <nav className="flex-1 flex flex-col gap-2">
                    {menuItems.map(item => (
                        item.url ? (
                            <a
                                key={item.id}
                                href={item.url}
                                className="w-12 h-12 rounded-lg flex items-center justify-center transition-all group relative text-gray-500 hover:bg-gray-100 hover:text-gray-700"
                            >
                                <div className="relative">
                                    <Icon name={item.icon} className="w-5 h-5" />
                                    {item.badge && (
                                        <span className="absolute -top-1 -right-1 text-xs bg-red-500 text-white rounded-full px-1 leading-none">
                                            {item.badge}
                                        </span>
                                    )}
                                </div>
                                <span className="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none">
                                    {item.label} {item.badge && <span className="text-red-400">({item.badge})</span>}
                                </span>
                            </a>
                        ) : (
                            <button
                                key={item.id}
                                onClick={() => setActiveMenu(item.id)}
                                className={`w-12 h-12 rounded-lg flex items-center justify-center transition-all group relative
                                    ${activeMenu === item.id 
                                        ? 'bg-primary-50 text-primary-600' 
                                        : 'text-gray-500 hover:bg-gray-100 hover:text-gray-700'}`}
                            >
                                <Icon name={item.icon} className="w-5 h-5" />
                                <span className="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none">
                                    {item.label}
                                </span>
                            </button>
                        )
                    ))}
                </nav>
                <button 
                    onClick={handleLogout}
                    className="w-12 h-12 rounded-lg flex items-center justify-center text-gray-500 hover:bg-red-50 hover:text-red-600 group relative transition-all"
                    title="로그아웃"
                >
                    <Icon name="log-out" className="w-5 h-5" />
                    <span className="absolute left-full ml-2 px-2 py-1 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none">
                        로그아웃
                    </span>
                </button>
            </div>
        );
    }

    // Mobile Sidebar with Hamburger Menu
    return (
        <>
            {/* Mobile Header with Hamburger */}
            <div className="fixed top-0 left-0 right-0 h-14 bg-white border-b border-gray-200 flex items-center justify-between px-4 z-50">
                <div className="flex items-center gap-3">
                    <button
                        onClick={() => setIsMobileMenuOpen(!isMobileMenuOpen)}
                        className="p-2 rounded-lg text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition-colors"
                        aria-label="메뉴 열기"
                    >
                        <Icon name={isMobileMenuOpen ? 'x' : 'menu'} className="w-6 h-6" />
                    </button>
                    <div className="w-8 h-8 bg-gradient-to-br from-primary-500 to-purple-600 rounded-lg flex items-center justify-center text-white font-bold text-sm">
                        Y
                    </div>
                    <span className="font-semibold text-gray-900">YKP Dashboard</span>
                </div>
                
                {/* Mobile User Profile */}
                <div className="flex items-center gap-2">
                    <UserProfile user={window.userData} compact />
                </div>
            </div>

            {/* Mobile Overlay */}
            {isMobileMenuOpen && (
                <div 
                    className="fixed inset-0 bg-black bg-opacity-50 z-40 transition-opacity"
                    onClick={() => setIsMobileMenuOpen(false)}
                />
            )}

            {/* Mobile Sidebar */}
            <div className={`mobile-sidebar fixed left-0 top-0 h-full w-72 bg-white shadow-xl transform transition-transform duration-300 ease-in-out z-50 ${
                isMobileMenuOpen ? 'translate-x-0' : '-translate-x-full'
            }`}>
                <div className="flex flex-col h-full">
                    {/* Mobile Header */}
                    <div className="flex items-center justify-between p-4 border-b border-gray-200">
                        <div className="flex items-center gap-3">
                            <div className="w-10 h-10 bg-gradient-to-br from-primary-500 to-purple-600 rounded-xl flex items-center justify-center text-white font-bold">
                                Y
                            </div>
                            <div>
                                <h1 className="font-bold text-gray-900">YKP Dashboard</h1>
                                <p className="text-sm text-gray-500">통합 관리 시스템</p>
                            </div>
                        </div>
                        <button
                            onClick={() => setIsMobileMenuOpen(false)}
                            className="p-2 rounded-lg text-gray-500 hover:bg-gray-100"
                        >
                            <Icon name="x" className="w-5 h-5" />
                        </button>
                    </div>

                    {/* Mobile User Info */}
                    <div className="p-4 border-b border-gray-200">
                        <UserProfile user={window.userData} expanded />
                    </div>

                    {/* Mobile Navigation */}
                    <nav className="flex-1 p-4">
                        <div className="space-y-2">
                            {menuItems.map(item => (
                                item.url ? (
                                    <a
                                        key={item.id}
                                        href={item.url}
                                        className="flex items-center gap-3 px-3 py-3 rounded-lg text-gray-700 hover:bg-gray-100 transition-colors"
                                        onClick={() => setIsMobileMenuOpen(false)}
                                    >
                                        <div className="relative">
                                            <Icon name={item.icon} className="w-5 h-5" />
                                            {item.badge && (
                                                <span className="absolute -top-1 -right-1 text-xs bg-red-500 text-white rounded-full px-1 leading-none">
                                                    {item.badge}
                                                </span>
                                            )}
                                        </div>
                                        <span className="font-medium">{item.label}</span>
                                        {item.badge && (
                                            <span className="ml-auto bg-red-100 text-red-800 text-xs px-2 py-1 rounded-full">
                                                {item.badge}
                                            </span>
                                        )}
                                    </a>
                                ) : (
                                    <button
                                        key={item.id}
                                        onClick={() => {
                                            setActiveMenu(item.id);
                                            setIsMobileMenuOpen(false);
                                        }}
                                        className={`w-full flex items-center gap-3 px-3 py-3 rounded-lg transition-colors ${
                                            activeMenu === item.id 
                                                ? 'bg-primary-50 text-primary-600' 
                                                : 'text-gray-700 hover:bg-gray-100'
                                        }`}
                                    >
                                        <Icon name={item.icon} className="w-5 h-5" />
                                        <span className="font-medium">{item.label}</span>
                                    </button>
                                )
                            ))}
                        </div>
                    </nav>

                    {/* Mobile Logout */}
                    <div className="p-4 border-t border-gray-200">
                        <button 
                            onClick={() => {
                                handleLogout();
                                setIsMobileMenuOpen(false);
                            }}
                            className="w-full flex items-center gap-3 px-3 py-3 rounded-lg text-red-600 hover:bg-red-50 transition-colors"
                        >
                            <Icon name="log-out" className="w-5 h-5" />
                            <span className="font-medium">로그아웃</span>
                        </button>
                    </div>
                </div>
            </div>
        </>
    );
};