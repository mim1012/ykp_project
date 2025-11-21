import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Card, Button, Badge, Icon, LoadingSpinner } from '../ui';

export const ExpenseManagement = () => {
    const [selectedMonth, setSelectedMonth] = useState(new Date().toISOString().slice(0, 7)); // YYYY-MM
    const [showAddModal, setShowAddModal] = useState(false);
    const [editingExpense, setEditingExpense] = useState(null);
    const queryClient = useQueryClient();

    // Fetch expenses for selected month
    const { data: expenses, isLoading, error } = useQuery({
        queryKey: ['expenses', selectedMonth],
        queryFn: async () => {
            const response = await fetch(`/api/expenses?month=${selectedMonth}`);
            if (!response.ok) throw new Error('Failed to fetch expenses');
            const result = await response.json();
            return result.data || [];
        }
    });

    // Fetch monthly summary
    const { data: summary } = useQuery({
        queryKey: ['expense-summary', selectedMonth],
        queryFn: async () => {
            const response = await fetch(`/api/expenses/monthly-summary?month=${selectedMonth}`);
            if (!response.ok) throw new Error('Failed to fetch summary');
            const result = await response.json();
            return result.data || {};
        }
    });

    // Delete expense mutation
    const deleteExpenseMutation = useMutation({
        mutationFn: async (expenseId) => {
            const response = await fetch(`/api/expenses/${expenseId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                }
            });
            if (!response.ok) throw new Error('Failed to delete expense');
            return response.json();
        },
        onSuccess: () => {
            queryClient.invalidateQueries(['expenses']);
            queryClient.invalidateQueries(['expense-summary']);
        }
    });

    const handleDelete = (expenseId) => {
        if (confirm('정말 삭제하시겠습니까?')) {
            deleteExpenseMutation.mutate(expenseId);
        }
    };

    // Generate month options (last 12 months)
    const getMonthOptions = () => {
        const months = [];
        const now = new Date();
        for (let i = 0; i < 12; i++) {
            const date = new Date(now.getFullYear(), now.getMonth() - i, 1);
            const value = date.toISOString().slice(0, 7);
            const label = `${date.getFullYear()}년 ${date.getMonth() + 1}월`;
            months.push({ value, label });
        }
        return months;
    };

    if (isLoading) {
        return (
            <div className="flex items-center justify-center min-h-[400px]">
                <LoadingSpinner size="lg" />
            </div>
        );
    }

    if (error) {
        return (
            <Card className="p-6">
                <div className="text-center text-red-600">
                    <Icon name="alert-circle" className="w-12 h-12 mx-auto mb-2" />
                    <p>데이터를 불러오는데 실패했습니다.</p>
                    <p className="text-sm text-gray-600 mt-1">{error.message}</p>
                </div>
            </Card>
        );
    }

    return (
        <div className="space-y-6">
            {/* Summary Cards */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Card className="p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm text-gray-600">총 지출 건수</p>
                            <p className="text-2xl font-bold text-gray-900">
                                {summary?.total_count || 0}건
                            </p>
                        </div>
                        <div className="p-3 bg-blue-100 rounded-lg">
                            <Icon name="file-text" className="w-6 h-6 text-blue-600" />
                        </div>
                    </div>
                </Card>

                <Card className="p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm text-gray-600">총 지출 금액</p>
                            <p className="text-2xl font-bold text-red-600">
                                ₩{(summary?.total_amount || 0).toLocaleString()}
                            </p>
                        </div>
                        <div className="p-3 bg-red-100 rounded-lg">
                            <Icon name="credit-card" className="w-6 h-6 text-red-600" />
                        </div>
                    </div>
                </Card>

                <Card className="p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm text-gray-600">일 평균 지출</p>
                            <p className="text-2xl font-bold text-gray-700">
                                ₩{(summary?.average_per_day || 0).toLocaleString()}
                            </p>
                        </div>
                        <div className="p-3 bg-gray-100 rounded-lg">
                            <Icon name="trending-down" className="w-6 h-6 text-gray-600" />
                        </div>
                    </div>
                </Card>
            </div>

            {/* Expense List */}
            <Card>
                <div className="p-6 border-b border-gray-200">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center space-x-4">
                            <h3 className="text-lg font-semibold text-gray-900">지출 내역</h3>
                            <select
                                value={selectedMonth}
                                onChange={(e) => setSelectedMonth(e.target.value)}
                                className="px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                {getMonthOptions().map(option => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <Button
                            variant="primary"
                            size="sm"
                            onClick={() => setShowAddModal(true)}
                        >
                            <Icon name="plus" className="w-4 h-4 mr-1" />
                            지출 추가
                        </Button>
                    </div>
                </div>

                <div className="overflow-x-auto">
                    {expenses && expenses.length > 0 ? (
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        날짜
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        카테고리
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        내용
                                    </th>
                                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        금액
                                    </th>
                                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        작업
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {expenses.map((expense) => (
                                    <tr key={expense.id} className="hover:bg-gray-50">
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                            {new Date(expense.expense_date).toLocaleDateString('ko-KR')}
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <Badge variant="info">
                                                {expense.category || '기타'}
                                            </Badge>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="text-sm text-gray-900">
                                                {expense.description}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-right">
                                            <span className="text-sm font-medium text-red-600">
                                                -₩{expense.amount.toLocaleString()}
                                            </span>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div className="flex items-center justify-end space-x-2">
                                                <Button
                                                    variant="secondary"
                                                    size="xs"
                                                    onClick={() => setEditingExpense(expense)}
                                                >
                                                    수정
                                                </Button>
                                                <Button
                                                    variant="danger"
                                                    size="xs"
                                                    onClick={() => handleDelete(expense.id)}
                                                    disabled={deleteExpenseMutation.isPending}
                                                >
                                                    삭제
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                            <tfoot className="bg-gray-50">
                                <tr>
                                    <td colSpan="3" className="px-6 py-4 text-right text-sm font-semibold text-gray-900">
                                        합계:
                                    </td>
                                    <td className="px-6 py-4 text-right">
                                        <span className="text-lg font-bold text-red-600">
                                            -₩{(summary?.total_amount || 0).toLocaleString()}
                                        </span>
                                    </td>
                                    <td></td>
                                </tr>
                            </tfoot>
                        </table>
                    ) : (
                        <div className="text-center py-12">
                            <Icon name="credit-card" className="w-12 h-12 mx-auto text-gray-400 mb-3" />
                            <p className="text-gray-500">등록된 지출 내역이 없습니다.</p>
                            <Button
                                variant="primary"
                                size="sm"
                                className="mt-4"
                                onClick={() => setShowAddModal(true)}
                            >
                                첫 지출 추가하기
                            </Button>
                        </div>
                    )}
                </div>
            </Card>

            {/* Add/Edit Modal */}
            {(showAddModal || editingExpense) && (
                <ExpenseModal
                    expense={editingExpense}
                    onClose={() => {
                        setShowAddModal(false);
                        setEditingExpense(null);
                    }}
                    onSuccess={() => {
                        queryClient.invalidateQueries(['expenses']);
                        queryClient.invalidateQueries(['expense-summary']);
                        setShowAddModal(false);
                        setEditingExpense(null);
                    }}
                />
            )}
        </div>
    );
};

// Expense Add/Edit Modal Component
const ExpenseModal = ({ expense, onClose, onSuccess }) => {
    const [formData, setFormData] = useState({
        expense_date: expense?.expense_date || new Date().toISOString().slice(0, 10),
        category: expense?.category || '',
        description: expense?.description || '',
        amount: expense?.amount || ''
    });
    const [error, setError] = useState(null);

    const categories = ['임대료', '인건비', '통신비', '광고비', '소모품', '기타'];

    const saveMutation = useMutation({
        mutationFn: async (data) => {
            const url = expense ? `/api/expenses/${expense.id}` : '/api/expenses';
            const method = expense ? 'PUT' : 'POST';

            const response = await fetch(url, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify(data)
            });

            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Failed to save expense');
            }

            return response.json();
        },
        onSuccess: () => {
            onSuccess();
        },
        onError: (err) => {
            setError(err.message);
        }
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        setError(null);

        // Validation
        if (!formData.expense_date || !formData.description || !formData.amount) {
            setError('모든 필드를 입력해주세요.');
            return;
        }

        if (isNaN(formData.amount) || formData.amount <= 0) {
            setError('금액은 0보다 큰 숫자여야 합니다.');
            return;
        }

        saveMutation.mutate({
            ...formData,
            amount: parseFloat(formData.amount)
        });
    };

    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <Card className="w-full max-w-md mx-4">
                <div className="p-6">
                    <div className="flex items-center justify-between mb-6">
                        <h3 className="text-lg font-semibold text-gray-900">
                            {expense ? '지출 수정' : '지출 추가'}
                        </h3>
                        <button
                            onClick={onClose}
                            className="text-gray-400 hover:text-gray-600"
                        >
                            <Icon name="x" className="w-5 h-5" />
                        </button>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                날짜 *
                            </label>
                            <input
                                type="date"
                                value={formData.expense_date}
                                onChange={(e) => setFormData({ ...formData, expense_date: e.target.value })}
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                required
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                카테고리
                            </label>
                            <select
                                value={formData.category}
                                onChange={(e) => setFormData({ ...formData, category: e.target.value })}
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="">선택안함</option>
                                {categories.map(cat => (
                                    <option key={cat} value={cat}>{cat}</option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                내용 *
                            </label>
                            <input
                                type="text"
                                value={formData.description}
                                onChange={(e) => setFormData({ ...formData, description: e.target.value })}
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="지출 내용을 입력하세요"
                                required
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                금액 *
                            </label>
                            <input
                                type="number"
                                value={formData.amount}
                                onChange={(e) => setFormData({ ...formData, amount: e.target.value })}
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="0"
                                min="0"
                                step="1"
                                required
                            />
                        </div>

                        {error && (
                            <div className="p-3 bg-red-50 border border-red-200 rounded-lg">
                                <p className="text-sm text-red-600">{error}</p>
                            </div>
                        )}

                        <div className="flex space-x-3 pt-4">
                            <Button
                                type="button"
                                variant="secondary"
                                className="flex-1"
                                onClick={onClose}
                                disabled={saveMutation.isPending}
                            >
                                취소
                            </Button>
                            <Button
                                type="submit"
                                variant="primary"
                                className="flex-1"
                                disabled={saveMutation.isPending}
                            >
                                {saveMutation.isPending ? '저장 중...' : '저장'}
                            </Button>
                        </div>
                    </form>
                </div>
            </Card>
        </div>
    );
};
