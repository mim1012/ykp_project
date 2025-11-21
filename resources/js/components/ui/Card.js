import React from 'react';

// Card component - 순수 JavaScript (JSX 없음)
export const Card = (props) => {
    const { children, className = "", onClick, ...otherProps } = props;

    return React.createElement('div', {
        className: 'bg-white rounded-xl shadow-[0_2px_10px_rgba(0,0,0,0.03)] border border-gray-100 ' + className,
        onClick: onClick,
        ...otherProps
    }, children);
};

// KPI Card component - 순수 JavaScript (JSX 없음)  
export const KPICard = (props) => {
    const { title, value, change, trend = 'neutral', loading = false, className = "" } = props;

    if (loading) {
        return React.createElement(Card, { className: className },
            React.createElement('div', { className: 'p-6' },
                React.createElement('div', { className: 'animate-pulse' },
                    React.createElement('div', { className: 'h-4 bg-gray-300 rounded w-1/2 mb-2' }),
                    React.createElement('div', { className: 'h-8 bg-gray-300 rounded w-3/4 mb-2' }),
                    React.createElement('div', { className: 'h-3 bg-gray-300 rounded w-1/3' })
                )
            )
        );
    }

    const trendColorMap = {
        positive: 'text-emerald-600',
        negative: 'text-rose-600',
        neutral: 'text-slate-500'
    };

    const trendColor = trendColorMap[trend] || 'text-gray-600';

    return React.createElement(Card, { className: className },
        React.createElement('div', { className: 'p-6' },
            React.createElement('div', { className: 'flex items-center justify-between' },
                React.createElement('div', { className: 'flex-1' },
                    React.createElement('p', {
                        className: 'text-sm font-medium text-gray-600 mb-1'
                    }, title),
                    React.createElement('p', {
                        className: 'text-2xl font-bold text-gray-900'
                    }, value),
                    change ? React.createElement('p', {
                        className: 'text-sm ' + trendColor + ' flex items-center mt-2'
                    }, change) : null
                )
            )
        )
    );
};