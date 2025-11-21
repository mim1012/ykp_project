import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Card, Button, Badge, Icon, LoadingSpinner } from '../ui';

export const CustomerManagement = () => {
    const [activeTab, setActiveTab] = useState('prospect'); // 'prospect' | 'confirmed'
    const [showAddModal, setShowAddModal] = useState(false);
    const [editingCustomer, setEditingCustomer] = useState(null);
    const queryClient = useQueryClient();

    // Fetch customers
    const { data: customers, isLoading, error } = useQuery({
        queryKey: ['customers', activeTab],
        queryFn: async () => {
            const response = await fetch(`/api/customers?status=${activeTab}`);
            if (!response.ok) throw new Error('Failed to fetch customers');
            const result = await response.json();
            return result.data || [];
        }
    });

    // Fetch statistics
    const { data: stats } = useQuery({
        queryKey: ['customer-statistics'],
        queryFn: async () => {
            const response = await fetch('/api/customers/statistics');
            if (!response.ok) throw new Error('Failed to fetch statistics');
            const result = await response.json();
            return result.data || {};
        }
    });

    // Delete customer mutation
    const deleteCustomerMutation = useMutation({
        mutationFn: async (customerId) => {
            const response = await fetch(`/api/customers/${customerId}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                }
            });
            if (!response.ok) throw new Error('Failed to delete customer');
            return response.json();
        },
        onSuccess: () => {
            queryClient.invalidateQueries(['customers']);
            queryClient.invalidateQueries(['customer-statistics']);
        }
    });

    // Convert to confirmed mutation
    const convertToConfirmedMutation = useMutation({
        mutationFn: async (customerId) => {
            const response = await fetch(`/api/customers/${customerId}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify({ status: 'confirmed' })
            });
            if (!response.ok) throw new Error('Failed to convert customer');
            return response.json();
        },
        onSuccess: () => {
            queryClient.invalidateQueries(['customers']);
            queryClient.invalidateQueries(['customer-statistics']);
        }
    });

    const handleDelete = (customerId) => {
        if (confirm('정말 삭제하시겠습니까?')) {
            deleteCustomerMutation.mutate(customerId);
        }
    };

    const handleConvertToConfirmed = (customerId) => {
        if (confirm('확정 고객으로 전환하시겠습니까?')) {
            convertToConfirmedMutation.mutate(customerId);
        }
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
            {/* Statistics Cards */}
            <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                <Card className="p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm text-gray-600">총 고객</p>
                            <p className="text-2xl font-bold text-gray-900">
                                {stats?.total_customers || 0}
                            </p>
                        </div>
                        <div className="p-3 bg-blue-100 rounded-lg">
                            <Icon name="users" className="w-6 h-6 text-blue-600" />
                        </div>
                    </div>
                </Card>

                <Card className="p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm text-gray-600">잠재 고객</p>
                            <p className="text-2xl font-bold text-yellow-600">
                                {stats?.prospect_count || 0}
                            </p>
                        </div>
                        <div className="p-3 bg-yellow-100 rounded-lg">
                            <Icon name="user-plus" className="w-6 h-6 text-yellow-600" />
                        </div>
                    </div>
                </Card>

                <Card className="p-4">
                    <div className="flex items-center justify-between">
                        <div>
                            <p className="text-sm text-gray-600">확정 고객</p>
                            <p className="text-2xl font-bold text-green-600">
                                {stats?.confirmed_count || 0}
                            </p>
                        </div>
                        <div className="p-3 bg-green-100 rounded-lg">
                            <Icon name="user-check" className="w-6 h-6 text-green-600" />
                        </div>
                    </div>
                </Card>
            </div>

            {/* Customer List */}
            <Card>
                <div className="p-6 border-b border-gray-200">
                    <div className="flex items-center justify-between">
                        <div className="flex space-x-2">
                            <Button
                                variant={activeTab === 'prospect' ? 'primary' : 'secondary'}
                                size="sm"
                                onClick={() => setActiveTab('prospect')}
                            >
                                잠재 고객 ({stats?.prospect_count || 0})
                            </Button>
                            <Button
                                variant={activeTab === 'confirmed' ? 'primary' : 'secondary'}
                                size="sm"
                                onClick={() => setActiveTab('confirmed')}
                            >
                                확정 고객 ({stats?.confirmed_count || 0})
                            </Button>
                        </div>
                        <Button
                            variant="primary"
                            size="sm"
                            onClick={() => setShowAddModal(true)}
                        >
                            <Icon name="plus" className="w-4 h-4 mr-1" />
                            고객 추가
                        </Button>
                    </div>
                </div>

                <div className="overflow-x-auto">
                    {customers && customers.length > 0 ? (
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        이름
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        전화번호
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        통신사
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        메모
                                    </th>
                                    <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        등록일
                                    </th>
                                    {activeTab === 'confirmed' && (
                                        <th className="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            판매 연결
                                        </th>
                                    )}
                                    <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        작업
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="bg-white divide-y divide-gray-200">
                                {customers.map((customer) => (
                                    <tr key={customer.id} className="hover:bg-gray-50">
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm font-medium text-gray-900">
                                                {customer.name}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <div className="text-sm text-gray-600">
                                                {customer.phone}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap">
                                            <Badge variant="info">
                                                {customer.carrier || '미지정'}
                                            </Badge>
                                        </td>
                                        <td className="px-6 py-4">
                                            <div className="text-sm text-gray-600 max-w-xs truncate">
                                                {customer.memo || '-'}
                                            </div>
                                        </td>
                                        <td className="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                            {new Date(customer.created_at).toLocaleDateString('ko-KR')}
                                        </td>
                                        {activeTab === 'confirmed' && (
                                            <td className="px-6 py-4 whitespace-nowrap">
                                                {customer.sale_id ? (
                                                    <Badge variant="success">
                                                        연결됨
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="secondary">
                                                        미연결
                                                    </Badge>
                                                )}
                                            </td>
                                        )}
                                        <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                            <div className="flex items-center justify-end space-x-2">
                                                {activeTab === 'prospect' && (
                                                    <Button
                                                        variant="success"
                                                        size="xs"
                                                        onClick={() => handleConvertToConfirmed(customer.id)}
                                                        disabled={convertToConfirmedMutation.isPending}
                                                    >
                                                        확정
                                                    </Button>
                                                )}
                                                <Button
                                                    variant="secondary"
                                                    size="xs"
                                                    onClick={() => setEditingCustomer(customer)}
                                                >
                                                    수정
                                                </Button>
                                                {activeTab === 'prospect' && (
                                                    <Button
                                                        variant="danger"
                                                        size="xs"
                                                        onClick={() => handleDelete(customer.id)}
                                                        disabled={deleteCustomerMutation.isPending}
                                                    >
                                                        삭제
                                                    </Button>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    ) : (
                        <div className="text-center py-12">
                            <Icon name="users" className="w-12 h-12 mx-auto text-gray-400 mb-3" />
                            <p className="text-gray-500">등록된 고객이 없습니다.</p>
                            <Button
                                variant="primary"
                                size="sm"
                                className="mt-4"
                                onClick={() => setShowAddModal(true)}
                            >
                                첫 고객 추가하기
                            </Button>
                        </div>
                    )}
                </div>
            </Card>

            {/* Add/Edit Modal */}
            {(showAddModal || editingCustomer) && (
                <CustomerModal
                    customer={editingCustomer}
                    onClose={() => {
                        setShowAddModal(false);
                        setEditingCustomer(null);
                    }}
                    onSuccess={() => {
                        queryClient.invalidateQueries(['customers']);
                        queryClient.invalidateQueries(['customer-statistics']);
                        setShowAddModal(false);
                        setEditingCustomer(null);
                    }}
                />
            )}
        </div>
    );
};

// Customer Add/Edit Modal Component
const CustomerModal = ({ customer, onClose, onSuccess }) => {
    const [formData, setFormData] = useState({
        name: customer?.name || '',
        phone: customer?.phone || '',
        carrier: customer?.carrier || '',
        memo: customer?.memo || '',
        status: customer?.status || 'prospect'
    });
    const [error, setError] = useState(null);

    const saveMutation = useMutation({
        mutationFn: async (data) => {
            const url = customer ? `/api/customers/${customer.id}` : '/api/customers';
            const method = customer ? 'PUT' : 'POST';

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
                throw new Error(errorData.message || 'Failed to save customer');
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
        if (!formData.name || !formData.phone) {
            setError('이름과 전화번호는 필수입니다.');
            return;
        }

        saveMutation.mutate(formData);
    };

    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <Card className="w-full max-w-md mx-4">
                <div className="p-6">
                    <div className="flex items-center justify-between mb-6">
                        <h3 className="text-lg font-semibold text-gray-900">
                            {customer ? '고객 수정' : '고객 추가'}
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
                                이름 *
                            </label>
                            <input
                                type="text"
                                value={formData.name}
                                onChange={(e) => setFormData({ ...formData, name: e.target.value })}
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                required
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                전화번호 *
                            </label>
                            <input
                                type="tel"
                                value={formData.phone}
                                onChange={(e) => setFormData({ ...formData, phone: e.target.value })}
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="010-1234-5678"
                                required
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                통신사
                            </label>
                            <select
                                value={formData.carrier}
                                onChange={(e) => setFormData({ ...formData, carrier: e.target.value })}
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                            >
                                <option value="">선택안함</option>
                                <option value="SK">SK</option>
                                <option value="KT">KT</option>
                                <option value="LG">LG</option>
                                <option value="MVNO">알뜰폰</option>
                            </select>
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                메모
                            </label>
                            <textarea
                                value={formData.memo}
                                onChange={(e) => setFormData({ ...formData, memo: e.target.value })}
                                rows="3"
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="고객 관련 메모를 입력하세요"
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
