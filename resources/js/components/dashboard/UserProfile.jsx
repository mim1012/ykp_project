import React, { useState, useRef, useEffect } from 'react';
import { Icon } from '../ui';
import { ChangePasswordModal } from './ChangePasswordModal';

export const UserProfile = ({ user, compact = false, expanded = false }) => {
    const [showMenu, setShowMenu] = useState(false);
    const [showChangePasswordModal, setShowChangePasswordModal] = useState(false);
    const menuRef = useRef(null);

    if (!user) return null;

    const roleLabel = user.role === 'headquarters' ? '본사' : user.role === 'branch' ? '지사' : '매장';

    // 드롭다운 메뉴 외부 클릭 감지
    useEffect(() => {
        const handleClickOutside = (event) => {
            if (menuRef.current && !menuRef.current.contains(event.target)) {
                setShowMenu(false);
            }
        };

        if (showMenu) {
            document.addEventListener('mousedown', handleClickOutside);
            return () => document.removeEventListener('mousedown', handleClickOutside);
        }
    }, [showMenu]);

    // Compact mobile header version
    if (compact) {
        return (
            <>
                <div className="relative" ref={menuRef}>
                    <button
                        onClick={() => setShowMenu(!showMenu)}
                        className="flex items-center gap-2 hover:opacity-80 transition-opacity"
                    >
                        <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-green-500 to-blue-600 flex items-center justify-center text-white font-bold text-xs">
                            {user.name.charAt(0).toUpperCase()}
                        </div>
                        <div className="hidden sm:block">
                            <div className="text-sm font-medium text-gray-900">{user.name}</div>
                            <div className="text-xs text-gray-500">{roleLabel}</div>
                        </div>
                    </button>

                    {/* 드롭다운 메뉴 */}
                    {showMenu && (
                        <div className="absolute right-0 top-full mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
                            <button
                                onClick={() => {
                                    setShowMenu(false);
                                    setShowChangePasswordModal(true);
                                }}
                                className="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2"
                            >
                                <Icon name="lock" className="w-4 h-4" />
                                <span>비밀번호 변경</span>
                            </button>
                        </div>
                    )}
                </div>

                <ChangePasswordModal
                    isOpen={showChangePasswordModal}
                    onClose={() => setShowChangePasswordModal(false)}
                />
            </>
        );
    }

    // Expanded mobile sidebar version
    if (expanded) {
        return (
            <>
                <div className="space-y-3">
                    <div className="flex items-center gap-3">
                        <div className="w-12 h-12 rounded-lg bg-gradient-to-br from-green-500 to-blue-600 flex items-center justify-center text-white font-bold">
                            {user.name.charAt(0).toUpperCase()}
                        </div>
                        <div className="flex-1 min-w-0">
                            <div className="font-semibold text-gray-900 truncate">{user.name}</div>
                            <div className="text-sm text-gray-600 truncate">{user.email}</div>
                            <div className="flex items-center gap-2 mt-1">
                                <span className="inline-block px-2 py-0.5 bg-primary-100 text-primary-700 rounded text-xs font-medium">
                                    {roleLabel}
                                </span>
                                {user.permissions?.canViewAllStores && (
                                    <Icon name="shield-check" className="w-3 h-3 text-green-600" title="전체 권한" />
                                )}
                            </div>
                            {user.branch && (
                                <div className="text-xs text-gray-500 mt-1">지사: {user.branch}</div>
                            )}
                            {user.store && (
                                <div className="text-xs text-gray-500">매장: {user.store}</div>
                            )}
                        </div>
                    </div>

                    {/* 비밀번호 변경 버튼 */}
                    <button
                        onClick={() => setShowChangePasswordModal(true)}
                        className="w-full px-3 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 rounded-lg flex items-center gap-2"
                    >
                        <Icon name="lock" className="w-4 h-4" />
                        <span>비밀번호 변경</span>
                    </button>
                </div>

                <ChangePasswordModal
                    isOpen={showChangePasswordModal}
                    onClose={() => setShowChangePasswordModal(false)}
                />
            </>
        );
    }

    // Default desktop sidebar version
    return (
        <>
            <div className="mb-4 group relative" ref={menuRef}>
                <button
                    onClick={() => setShowMenu(!showMenu)}
                    className="w-12 h-12 rounded-lg bg-gradient-to-br from-green-500 to-blue-600 flex items-center justify-center text-white font-bold text-sm cursor-pointer hover:shadow-lg transition-shadow"
                >
                    {user.name.charAt(0).toUpperCase()}
                </button>

                {/* 호버 시 사용자 정보 툴팁 */}
                <div className="absolute left-full ml-2 px-3 py-2 bg-gray-900 text-white text-xs rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10 min-w-[200px]">
                    <div className="font-semibold">{user.name}</div>
                    <div className="text-gray-300">{user.email}</div>
                    <div className="text-xs mt-1">
                        <span className="inline-block px-2 py-0.5 bg-primary-600 text-white rounded">
                            {roleLabel}
                        </span>
                        {user.permissions?.canViewAllStores && (
                            <Icon name="shield-check" className="w-3 h-3 text-green-400 ml-1 inline" title="전체 권한" />
                        )}
                    </div>
                    {user.branch && <div className="text-gray-300 text-xs">지사: {user.branch}</div>}
                    {user.store && <div className="text-gray-300 text-xs">매장: {user.store}</div>}
                </div>

                {/* 클릭 시 드롭다운 메뉴 */}
                {showMenu && (
                    <div className="absolute left-full ml-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
                        <button
                            onClick={() => {
                                setShowMenu(false);
                                setShowChangePasswordModal(true);
                            }}
                            className="w-full px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-100 flex items-center gap-2"
                        >
                            <Icon name="lock" className="w-4 h-4" />
                            <span>비밀번호 변경</span>
                        </button>
                    </div>
                )}
            </div>

            <ChangePasswordModal
                isOpen={showChangePasswordModal}
                onClose={() => setShowChangePasswordModal(false)}
            />
        </>
    );
};