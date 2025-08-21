import React from 'react';
import { Icon } from '../ui';

export const UserProfile = ({ user, compact = false, expanded = false }) => {
    if (!user) return null;

    const roleLabel = user.role === 'headquarters' ? '본사' : user.role === 'branch' ? '지사' : '매장';

    // Compact mobile header version
    if (compact) {
        return (
            <div className="flex items-center gap-2">
                <div className="w-8 h-8 rounded-lg bg-gradient-to-br from-green-500 to-blue-600 flex items-center justify-center text-white font-bold text-xs">
                    {user.name.charAt(0).toUpperCase()}
                </div>
                <div className="hidden sm:block">
                    <div className="text-sm font-medium text-gray-900">{user.name}</div>
                    <div className="text-xs text-gray-500">{roleLabel}</div>
                </div>
            </div>
        );
    }

    // Expanded mobile sidebar version
    if (expanded) {
        return (
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
        );
    }

    // Default desktop sidebar version
    return (
        <div className="mb-4 group relative">
            <div className="w-12 h-12 rounded-lg bg-gradient-to-br from-green-500 to-blue-600 flex items-center justify-center text-white font-bold text-sm cursor-pointer">
                {user.name.charAt(0).toUpperCase()}
            </div>
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
        </div>
    );
};