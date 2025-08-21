import React, { useState } from 'react';
import { Card, Icon } from './index';

export const ResponsiveTable = ({ 
    columns, 
    data, 
    title,
    mobileCardView = true,
    actions = null,
    className = ""
}) => {
    const [viewMode, setViewMode] = useState('table'); // 'table' or 'cards'

    const TableView = () => (
        <div className="overflow-x-auto -mx-4 sm:mx-0">
            <div className="inline-block min-w-full align-middle">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            {columns.map((column, index) => (
                                <th
                                    key={index}
                                    className="px-3 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider whitespace-nowrap"
                                >
                                    {column.header}
                                </th>
                            ))}
                            {actions && (
                                <th className="px-3 sm:px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                    액션
                                </th>
                            )}
                        </tr>
                    </thead>
                    <tbody className="bg-white divide-y divide-gray-200">
                        {data.map((row, rowIndex) => (
                            <tr key={rowIndex} className="hover:bg-gray-50">
                                {columns.map((column, colIndex) => (
                                    <td
                                        key={colIndex}
                                        className="px-3 sm:px-6 py-4 whitespace-nowrap text-sm text-gray-900"
                                    >
                                        {column.render ? column.render(row, rowIndex) : row[column.key]}
                                    </td>
                                ))}
                                {actions && (
                                    <td className="px-3 sm:px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                        {actions(row, rowIndex)}
                                    </td>
                                )}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );

    const CardView = () => (
        <div className="space-y-4">
            {data.map((row, rowIndex) => (
                <Card key={rowIndex} className="p-4">
                    <div className="space-y-2">
                        {columns.map((column, colIndex) => (
                            <div key={colIndex} className="flex justify-between items-start">
                                <span className="text-sm font-medium text-gray-500 flex-shrink-0 mr-3">
                                    {column.header}:
                                </span>
                                <span className="text-sm text-gray-900 text-right">
                                    {column.render ? column.render(row, rowIndex) : row[column.key]}
                                </span>
                            </div>
                        ))}
                        {actions && (
                            <div className="flex justify-end pt-2 border-t border-gray-100 mt-3">
                                {actions(row, rowIndex)}
                            </div>
                        )}
                    </div>
                </Card>
            ))}
        </div>
    );

    return (
        <div className={className}>
            {/* Header with title and view toggle */}
            {(title || mobileCardView) && (
                <div className="flex items-center justify-between mb-4">
                    {title && (
                        <h3 className="text-lg font-semibold text-gray-900">{title}</h3>
                    )}
                    
                    {mobileCardView && (
                        <div className="flex items-center gap-2 sm:hidden">
                            <button
                                onClick={() => setViewMode('table')}
                                className={`p-2 rounded-lg transition-colors ${
                                    viewMode === 'table' 
                                        ? 'bg-primary-100 text-primary-600' 
                                        : 'text-gray-400 hover:text-gray-600'
                                }`}
                                title="테이블 보기"
                            >
                                <Icon name="table" className="w-4 h-4" />
                            </button>
                            <button
                                onClick={() => setViewMode('cards')}
                                className={`p-2 rounded-lg transition-colors ${
                                    viewMode === 'cards' 
                                        ? 'bg-primary-100 text-primary-600' 
                                        : 'text-gray-400 hover:text-gray-600'
                                }`}
                                title="카드 보기"
                            >
                                <Icon name="layout-grid" className="w-4 h-4" />
                            </button>
                        </div>
                    )}
                </div>
            )}

            {/* Table or Card content */}
            <div className="bg-white rounded-lg border border-gray-200 overflow-hidden">
                {/* Desktop always shows table */}
                <div className="hidden sm:block">
                    <TableView />
                </div>
                
                {/* Mobile shows based on toggle */}
                <div className="sm:hidden">
                    {mobileCardView && viewMode === 'cards' ? <CardView /> : <TableView />}
                </div>
            </div>

            {/* Empty state */}
            {data.length === 0 && (
                <div className="text-center py-12">
                    <Icon name="inbox" className="w-12 h-12 text-gray-400 mx-auto mb-4" />
                    <p className="text-gray-500">데이터가 없습니다</p>
                </div>
            )}
        </div>
    );
};