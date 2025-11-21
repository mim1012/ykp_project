import React, { useState } from 'react';
import { useQuery, useMutation, useQueryClient } from '@tanstack/react-query';
import { Card, Button, Badge, Icon, LoadingSpinner } from '../ui';

export const QnaBoard = () => {
    const [selectedPost, setSelectedPost] = useState(null);
    const [showCreateModal, setShowCreateModal] = useState(false);
    const [statusFilter, setStatusFilter] = useState('all'); // 'all', 'pending', 'answered', 'closed'
    const queryClient = useQueryClient();

    // Fetch Q&A posts
    const { data: posts, isLoading } = useQuery({
        queryKey: ['qna-posts', statusFilter],
        queryFn: async () => {
            const url = statusFilter === 'all'
                ? '/api/qna/posts'
                : `/api/qna/posts?status=${statusFilter}`;
            const response = await fetch(url);
            if (!response.ok) throw new Error('Failed to fetch Q&A posts');
            const result = await response.json();
            return result.data || [];
        }
    });

    const getStatusBadge = (status) => {
        const badges = {
            pending: { variant: 'warning', label: '대기중' },
            answered: { variant: 'success', label: '답변완료' },
            closed: { variant: 'secondary', label: '종료' }
        };
        return badges[status] || badges.pending;
    };

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
                    <h2 className="text-2xl font-bold text-gray-900">Q&A 게시판</h2>
                    <p className="text-sm text-gray-600 mt-1">질문을 남기면 본사/지사에서 답변드립니다</p>
                </div>
                <Button
                    variant="primary"
                    onClick={() => setShowCreateModal(true)}
                >
                    <Icon name="plus" className="w-4 h-4 mr-2" />
                    질문 작성
                </Button>
            </div>

            {/* Filters */}
            <Card className="p-4">
                <div className="flex space-x-2">
                    {['all', 'pending', 'answered', 'closed'].map(status => (
                        <Button
                            key={status}
                            variant={statusFilter === status ? 'primary' : 'secondary'}
                            size="sm"
                            onClick={() => setStatusFilter(status)}
                        >
                            {status === 'all' ? '전체' :
                             status === 'pending' ? '대기중' :
                             status === 'answered' ? '답변완료' : '종료'}
                        </Button>
                    ))}
                </div>
            </Card>

            {/* Posts List */}
            <div className="space-y-3">
                {posts && posts.length > 0 ? (
                    posts.map((post) => (
                        <Card
                            key={post.id}
                            className="p-6 hover:shadow-md transition-shadow cursor-pointer"
                            onClick={() => setSelectedPost(post)}
                        >
                            <div className="flex items-start justify-between">
                                <div className="flex-1">
                                    <div className="flex items-center space-x-2 mb-2">
                                        <Badge {...getStatusBadge(post.status)} />
                                        {post.is_private && (
                                            <Badge variant="danger">
                                                <Icon name="lock" className="w-3 h-3 mr-1 inline" />
                                                비밀글
                                            </Badge>
                                        )}
                                        <span className="text-xs text-gray-500">
                                            {new Date(post.created_at).toLocaleDateString('ko-KR')}
                                        </span>
                                    </div>
                                    <h3 className="text-lg font-semibold text-gray-900 mb-2">
                                        {post.title}
                                    </h3>
                                    <p className="text-sm text-gray-600 line-clamp-2">
                                        {post.content}
                                    </p>
                                    <div className="flex items-center space-x-4 mt-3 text-xs text-gray-500">
                                        <span className="flex items-center">
                                            <Icon name="user" className="w-3 h-3 mr-1" />
                                            {post.author?.name || '익명'}
                                        </span>
                                        <span className="flex items-center">
                                            <Icon name="eye" className="w-3 h-3 mr-1" />
                                            {post.view_count || 0}
                                        </span>
                                        <span className="flex items-center">
                                            <Icon name="message-circle" className="w-3 h-3 mr-1" />
                                            {post.replies?.length || 0}
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
                            <p>등록된 질문이 없습니다.</p>
                            <Button
                                variant="primary"
                                size="sm"
                                className="mt-4"
                                onClick={() => setShowCreateModal(true)}
                            >
                                첫 질문 작성하기
                            </Button>
                        </div>
                    </Card>
                )}
            </div>

            {/* Create Modal */}
            {showCreateModal && (
                <QnaCreateModal
                    onClose={() => setShowCreateModal(false)}
                    onSuccess={() => {
                        queryClient.invalidateQueries(['qna-posts']);
                        setShowCreateModal(false);
                    }}
                />
            )}

            {/* Detail Modal */}
            {selectedPost && (
                <QnaDetailModal
                    post={selectedPost}
                    onClose={() => setSelectedPost(null)}
                    onUpdate={() => {
                        queryClient.invalidateQueries(['qna-posts']);
                    }}
                />
            )}
        </div>
    );
};

// Create Q&A Modal
const QnaCreateModal = ({ onClose, onSuccess }) => {
    const [formData, setFormData] = useState({
        title: '',
        content: '',
        is_private: false
    });
    const [error, setError] = useState(null);

    const createMutation = useMutation({
        mutationFn: async (data) => {
            const response = await fetch('/api/qna/posts', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify(data)
            });
            if (!response.ok) throw new Error('Failed to create post');
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
            <Card className="w-full max-w-2xl">
                <div className="p-6">
                    <div className="flex items-center justify-between mb-6">
                        <h3 className="text-lg font-semibold text-gray-900">질문 작성</h3>
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
                                placeholder="질문 제목을 입력하세요"
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
                                placeholder="질문 내용을 자세히 작성해주세요"
                                required
                            />
                        </div>

                        <div className="flex items-center">
                            <input
                                type="checkbox"
                                id="is_private"
                                checked={formData.is_private}
                                onChange={(e) => setFormData({ ...formData, is_private: e.target.checked })}
                                className="w-4 h-4 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                            />
                            <label htmlFor="is_private" className="ml-2 text-sm text-gray-700">
                                비밀글로 작성 (본사와 지사만 볼 수 있습니다)
                            </label>
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

// Q&A Detail Modal
const QnaDetailModal = ({ post, onClose, onUpdate }) => {
    const [replyContent, setReplyContent] = useState('');
    const queryClient = useQueryClient();

    // Fetch full post details
    const { data: postDetail, isLoading } = useQuery({
        queryKey: ['qna-post', post.id],
        queryFn: async () => {
            const response = await fetch(`/api/qna/posts/${post.id}`);
            if (!response.ok) throw new Error('Failed to fetch post');
            const result = await response.json();
            return result.data;
        }
    });

    // Reply mutation
    const replyMutation = useMutation({
        mutationFn: async (content) => {
            const response = await fetch(`/api/qna/posts/${post.id}/replies`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content
                },
                body: JSON.stringify({ content })
            });
            if (!response.ok) throw new Error('Failed to add reply');
            return response.json();
        },
        onSuccess: () => {
            queryClient.invalidateQueries(['qna-post', post.id]);
            setReplyContent('');
            onUpdate();
        }
    });

    const handleReply = (e) => {
        e.preventDefault();
        if (!replyContent.trim()) return;
        replyMutation.mutate(replyContent);
    };

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
                            <Badge variant={postDetail?.status === 'pending' ? 'warning' : 'success'}>
                                {postDetail?.status === 'pending' ? '대기중' : '답변완료'}
                            </Badge>
                            {postDetail?.is_private && (
                                <Badge variant="danger">
                                    <Icon name="lock" className="w-3 h-3 mr-1 inline" />
                                    비밀글
                                </Badge>
                            )}
                        </div>
                        <h2 className="text-xl font-bold text-gray-900">{postDetail?.title}</h2>
                        <div className="flex items-center space-x-4 mt-2 text-sm text-gray-600">
                            <span>{postDetail?.author?.name || '익명'}</span>
                            <span>{new Date(postDetail?.created_at).toLocaleString('ko-KR')}</span>
                            <span>조회 {postDetail?.view_count || 0}</span>
                        </div>
                    </div>
                    <button onClick={onClose} className="text-gray-400 hover:text-gray-600">
                        <Icon name="x" className="w-6 h-6" />
                    </button>
                </div>

                {/* Content */}
                <div className="p-6 border-b border-gray-200">
                    <div className="prose max-w-none">
                        <p className="whitespace-pre-wrap text-gray-700">{postDetail?.content}</p>
                    </div>
                </div>

                {/* Replies */}
                <div className="p-6 bg-gray-50">
                    <h3 className="font-semibold text-gray-900 mb-4">
                        답변 {postDetail?.replies?.length || 0}개
                    </h3>
                    <div className="space-y-4">
                        {postDetail?.replies?.map((reply) => (
                            <Card key={reply.id} className="p-4">
                                <div className="flex items-start justify-between mb-2">
                                    <div className="flex items-center space-x-2">
                                        <span className="font-medium text-gray-900">
                                            {reply.author?.name || '익명'}
                                        </span>
                                        {reply.is_official_answer && (
                                            <Badge variant="success" size="sm">공식답변</Badge>
                                        )}
                                    </div>
                                    <span className="text-xs text-gray-500">
                                        {new Date(reply.created_at).toLocaleString('ko-KR')}
                                    </span>
                                </div>
                                <p className="text-sm text-gray-700 whitespace-pre-wrap">
                                    {reply.content}
                                </p>
                            </Card>
                        ))}
                    </div>

                    {/* Reply Form */}
                    {postDetail?.status !== 'closed' && (
                        <form onSubmit={handleReply} className="mt-4">
                            <textarea
                                value={replyContent}
                                onChange={(e) => setReplyContent(e.target.value)}
                                rows="4"
                                className="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500"
                                placeholder="답변을 입력하세요..."
                            />
                            <div className="flex justify-end mt-2">
                                <Button
                                    type="submit"
                                    variant="primary"
                                    size="sm"
                                    disabled={replyMutation.isPending || !replyContent.trim()}
                                >
                                    {replyMutation.isPending ? '작성 중...' : '답변 작성'}
                                </Button>
                            </div>
                        </form>
                    )}
                </div>
            </div>
        </div>
    );
};
