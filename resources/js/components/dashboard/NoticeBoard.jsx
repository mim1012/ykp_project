import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Card, Button, Badge, Icon, LoadingSpinner } from '../ui';

export const NoticeBoard = ({ userRole }) => {
    const [selectedNotice, setSelectedNotice] = useState(null);
    const [showCreateModal, setShowCreateModal] = useState(false);
    const queryClient = useQueryClient();

    // Fetch notices
    const { data: notices, isLoading } = useQuery({
        queryKey: ['notices'],
        queryFn: async () => {
            const response = await fetch('/api/notices');
            if (!response.ok) throw new Error('Failed to fetch notices');
            const result = await response.json();
            return result.data || [];
        }
    });

    const canCreateNotice = userRole === 'headquarters' || userRole === 'branch';

    if (isLoading) {
        return (
            <div className="flex items-center justify-center min-h-[400px]">
                <LoadingSpinner size="lg" />
            </div>
        );
    }

    return (
        <div className="space-y-6">
            {/* Header */}
            <div className="flex items-center justify-between">
                <div>
                    <h2 className="text-2xl font-bold text-gray-900">공지사항</h2>
                    <p className="text-sm text-gray-600 mt-1">중요한 공지사항을 확인하세요</p>
                </div>
                {canCreateNotice && (
                    <Button
                        variant="primary"
                        onClick={() => setShowCreateModal(true)}
                    >
                        <Icon name="plus" className="w-4 h-4 mr-2" />
                        공지 작성
                    </Button>
                )}
            </div>

            {/* Notices List */}
            <div className="space-y-3">
                {notices && notices.length > 0 ? (
                    notices.map((notice) => (
                        <Card
                            key={notice.id}
                            className={`p-6 hover:shadow-md transition-shadow cursor-pointer ${
                                notice.is_pinned ? 'border-2 border-blue-500 bg-blue-50' : ''
                            }`}
                            onClick={() => setSelectedNotice(notice)}
                        >
                            <div className="flex items-start justify-between">
                                <div className="flex-1">
                                    <div className="flex items-center space-x-2 mb-2">
                                        {notice.is_pinned && (
                                            <Badge variant="primary">
                                                <Icon name="pin" className="w-3 h-3 mr-1 inline" />
                                                고정
                                            </Badge>
                                        )}
                                        {notice.priority > 50 && (
                                            <Badge variant="danger">중요</Badge>
                                        )}
                                        <span className="text-xs text-gray-500">
                                            {new Date(notice.published_at || notice.created_at).toLocaleDateString('ko-KR')}
                                        </span>
                                        {notice.expires_at && (
                                            <span className="text-xs text-red-500">
                                                ~ {new Date(notice.expires_at).toLocaleDateString('ko-KR')}
                                            </span>
                                        )}
                                    </div>
                                    <h3 className="text-lg font-semibold text-gray-900 mb-2">
                                        {notice.title}
                                    </h3>
                                    <p className="text-sm text-gray-600 line-clamp-2">
                                        {notice.content}
                                    </p>
                                    <div className="flex items-center space-x-4 mt-3 text-xs text-gray-500">
                                        <span className="flex items-center">
                                            <Icon name="user" className="w-3 h-3 mr-1" />
                                            {notice.author?.name || '익명'}
                                        </span>
                                        <span className="flex items-center">
                                            <Icon name="eye" className="w-3 h-3 mr-1" />
                                            {notice.view_count || 0}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </Card>
                    ))
                ) : (
                    <Card className="p-12">
                        <div className="text-center text-gray-500">
                            <Icon name="inbox" className="w-12 h-12 mx-auto mb-3 text-gray-400" />
                            <p>등록된 공지사항이 없습니다.</p>
                            {canCreateNotice && (
                                <Button
                                    variant="primary"
                                    size="sm"
                                    className="mt-4"
                                    onClick={() => setShowCreateModal(true)}
                                >
                                    첫 공지 작성하기
                                </Button>
                            )}
                        </div>
                    </Card>
                )}
            </div>

            {/* Create Modal */}
            {showCreateModal && (
                <NoticeCreateModal
                    userRole={userRole}
                    onClose={() => setShowCreateModal(false)}
                    onSuccess={() => {
                        queryClient.invalidateQueries(['notices']);
                        setShowCreateModal(false);
                    }}
                />
            )}

            {/* Detail Modal */}
            {selectedNotice && (
                <NoticeDetailModal
                    notice={selectedNotice}
                    onClose={() => setSelectedNotice(null)}
                />
            )}
        </div>
    );
};

// Create Notice Modal
const NoticeCreateModal = ({ userRole, onClose, onSuccess }) => {
    const [formData, setFormData] = useState({
        title: '',
        content: '',
        target_audience: 'all',
        priority: 0,
        published_at: new Date().toISOString().slice(0, 16),
        expires_at: ''
    });
    const [error, setError] = useState(null);

    const createMutation = useMutation({
        mutationFn: async (data) => {
            const response = await fetch('/api/notices', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify(data)
            });
            if (!response.ok) {
                const errorData = await response.json();
                throw new Error(errorData.message || 'Failed to create notice');
            }
            return response.json();
        },
        onSuccess,
        onError: (err) => setError(err.message)
    });

    const handleSubmit = (e) => {
        e.preventDefault();
        if (!formData.title || !formData.content) {
            setError('제목과 내용을 입력해주세요.');
            return;
        }
        createMutation.mutate(formData);
    };

    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <Card className="w-full max-w-2xl max-h-[90vh] overflow-y-auto">
                <div className="p-6">
                    <div className="flex items-center justify-between mb-6">
                        <h3 className="text-lg font-semibold text-gray-900">공지사항 작성</h3>
                        <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
                            <Icon name="x" className="w-5 h-5" />
                        </button>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-4">
                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                제목 *
                            </label>
                            <input
                                type="text"
                                value={formData.title}
                                onChange={(e) => setFormData({ ...formData, title: e.target.value })}
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                placeholder="공지사항 제목"
                                required
                            />
                        </div>

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                내용 *
                            </label>
                            <textarea
                                value={formData.content}
                                onChange={(e) => setFormData({ ...formData, content: e.target.value })}
                                rows="10"
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                placeholder="공지사항 내용을 작성하세요"
                                required
                            />
                        </div>

                        {userRole === 'headquarters' && (
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    대상
                                </label>
                                <select
                                    value={formData.target_audience}
                                    onChange={(e) => setFormData({ ...formData, target_audience: e.target.value })}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                >
                                    <option value="all">전체</option>
                                    <option value="branches">지사만</option>
                                    <option value="stores">매장만</option>
                                    <option value="specific">특정 대상</option>
                                </select>
                            </div>
                        )}

                        <div>
                            <label className="block text-sm font-medium text-gray-700 mb-1">
                                우선순위
                            </label>
                            <select
                                value={formData.priority}
                                onChange={(e) => setFormData({ ...formData, priority: parseInt(e.target.value) })}
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                            >
                                <option value="0">일반</option>
                                <option value="50">중요</option>
                                <option value="100">긴급</option>
                            </select>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    게시일시
                                </label>
                                <input
                                    type="datetime-local"
                                    value={formData.published_at}
                                    onChange={(e) => setFormData({ ...formData, published_at: e.target.value })}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-gray-700 mb-1">
                                    만료일시 (선택)
                                </label>
                                <input
                                    type="datetime-local"
                                    value={formData.expires_at}
                                    onChange={(e) => setFormData({ ...formData, expires_at: e.target.value })}
                                    className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                />
                            </div>
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
                                disabled={createMutation.isPending}
                            >
                                취소
                            </Button>
                            <Button
                                type="submit"
                                variant="primary"
                                className="flex-1"
                                disabled={createMutation.isPending}
                            >
                                {createMutation.isPending ? '작성 중...' : '작성하기'}
                            </Button>
                        </div>
                    </form>
                </div>
            </Card>
        </div>
    );
};

// Notice Detail Modal
const NoticeDetailModal = ({ notice, onClose }) => {
    const { data: noticeDetail, isLoading } = useQuery({
        queryKey: ['notice', notice.id],
        queryFn: async () => {
            const response = await fetch(`/api/notices/${notice.id}`);
            if (!response.ok) throw new Error('Failed to fetch notice');
            const result = await response.json();
            return result.data;
        }
    });

    if (isLoading) {
        return (
            <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
                <LoadingSpinner size="lg" />
            </div>
        );
    }

    return (
        <div className="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
            <div className="bg-white rounded-lg shadow-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
                {/* Header */}
                <div className="sticky top-0 bg-white border-b border-gray-200 p-6 flex items-start justify-between">
                    <div className="flex-1">
                        <div className="flex items-center space-x-2 mb-2">
                            {noticeDetail?.is_pinned && (
                                <Badge variant="primary">
                                    <Icon name="pin" className="w-3 h-3 mr-1 inline" />
                                    고정
                                </Badge>
                            )}
                            {noticeDetail?.priority > 50 && (
                                <Badge variant="danger">중요</Badge>
                            )}
                        </div>
                        <h2 className="text-xl font-bold text-gray-900">{noticeDetail?.title}</h2>
                        <div className="flex items-center space-x-4 mt-2 text-sm text-gray-600">
                            <span>{noticeDetail?.author?.name || '익명'}</span>
                            <span>{new Date(noticeDetail?.published_at || noticeDetail?.created_at).toLocaleString('ko-KR')}</span>
                            <span>조회 {noticeDetail?.view_count || 0}</span>
                        </div>
                    </div>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
                        <Icon name="x" className="w-6 h-6" />
                    </button>
                </div>

                {/* Content */}
                <div className="p-6">
                    <div className="prose max-w-none">
                        <p className="whitespace-pre-wrap text-gray-700">{noticeDetail?.content}</p>
                    </div>

                    {noticeDetail?.expires_at && (
                        <div className="mt-6 p-4 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <p className="text-sm text-yellow-800">
                                <Icon name="calendar" className="w-4 h-4 inline mr-1" />
                                이 공지는 {new Date(noticeDetail.expires_at).toLocaleDateString('ko-KR')}까지 유효합니다.
                            </p>
                        </div>
                    )}
                </div>

                {/* Footer */}
                <div className="sticky bottom-0 bg-gray-50 border-t border-gray-200 p-6 flex justify-end">
                    <Button variant="secondary" onClick={onClose}>
                        닫기
                    </Button>
                </div>
            </div>
        </div>
    );
};
