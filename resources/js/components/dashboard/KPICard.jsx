import React from 'react';
import { Card, Badge, Icon } from '../ui';

export const KPICard = ({ title, value, change, changeType, icon, description }) => (
    <Card className="p-4 sm:p-6">
        <div className="flex items-center justify-between mb-3 sm:mb-4">
            <div className="p-2 bg-primary-50 rounded-lg">
                <Icon name={icon} className="w-4 h-4 sm:w-5 sm:h-5 text-primary-600" />
            </div>
            {change && (
                <Badge variant={changeType === 'positive' ? 'success' : changeType === 'negative' ? 'danger' : 'default'}>
                    {changeType === 'positive' ? '↑' : changeType === 'negative' ? '↓' : ''} {change}
                </Badge>
            )}
        </div>
        <div className="space-y-1">
            <p className="text-xs sm:text-sm text-gray-600">{title}</p>
            <p className="text-xl sm:text-2xl font-bold text-gray-900 break-words">{value}</p>
            {description && <p className="text-xs text-gray-500 leading-relaxed">{description}</p>}
        </div>
    </Card>
);