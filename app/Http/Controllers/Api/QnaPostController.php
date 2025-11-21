<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\QnaPost;
use App\Models\QnaReply;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class QnaPostController extends Controller
{
    /**
     * List Q&A posts with RBAC filtering
     * GET /api/qna/posts?status=pending&is_private=false&page=1&per_page=20
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $perPage = $request->input('per_page', 20);

            $query = QnaPost::with(['author:id,name,role', 'store:id,name', 'branch:id,name', 'replies'])
                ->visibleTo($user);

            // Status filter
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            // Private filter
            if ($request->has('is_private')) {
                $query->where('is_private', filter_var($request->is_private, FILTER_VALIDATE_BOOLEAN));
            }

            // Search filter
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'LIKE', "%{$search}%")
                      ->orWhere('content', 'LIKE', "%{$search}%");
                });
            }

            $posts = $query->orderBy('created_at', 'desc')
                          ->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $posts->items(),
                'pagination' => [
                    'current_page' => $posts->currentPage(),
                    'per_page' => $posts->perPage(),
                    'total' => $posts->total(),
                    'last_page' => $posts->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get Q&A posts', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get Q&A posts',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get single Q&A post with replies
     * GET /api/qna/posts/{id}
     */
    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $post = QnaPost::with([
                'author:id,name,role',
                'store:id,name',
                'branch:id,name',
                'replies.author:id,name,role',
            ])->findOrFail($id);

            // Check permission
            if (! $post->canView($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view this post',
                ], 403);
            }

            // Increment view count (exclude author)
            if ($post->author_user_id !== $user->id) {
                $post->incrementViewCount();
            }

            return response()->json([
                'success' => true,
                'data' => $post,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get Q&A post', [
                'post_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get Q&A post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create new Q&A post (Store users only)
     * POST /api/qna/posts
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Only store users can create Q&A posts
            if (! $user->isStore()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only store users can create Q&A posts',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'is_private' => 'boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $post = QnaPost::create([
                'title' => $request->title,
                'content' => $request->content,
                'author_user_id' => $user->id,
                'author_role' => $user->role,
                'store_id' => $user->store_id,
                'branch_id' => $user->branch_id,
                'is_private' => $request->input('is_private', false),
                'status' => 'pending',
                'view_count' => 0,
            ]);

            $post->load(['author:id,name,role', 'store:id,name', 'branch:id,name']);

            return response()->json([
                'success' => true,
                'message' => 'Q&A post created successfully',
                'data' => $post,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create Q&A post', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create Q&A post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update Q&A post (Author only)
     * PUT /api/qna/posts/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $post = QnaPost::findOrFail($id);

            // Only author can update
            if ($post->author_user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this post',
                ], 403);
            }

            // Cannot update answered or closed posts
            if (in_array($post->status, ['answered', 'closed'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update answered or closed posts',
                ], 400);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'content' => 'sometimes|string',
                'is_private' => 'sometimes|boolean',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $post->update($request->only(['title', 'content', 'is_private']));
            $post->load(['author:id,name,role', 'store:id,name', 'branch:id,name', 'replies']);

            return response()->json([
                'success' => true,
                'message' => 'Q&A post updated successfully',
                'data' => $post,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update Q&A post', [
                'post_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update Q&A post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete Q&A post (Author only, pending status only)
     * DELETE /api/qna/posts/{id}
     */
    public function destroy($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $post = QnaPost::findOrFail($id);

            // Only author can delete
            if ($post->author_user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this post',
                ], 403);
            }

            // Can only delete pending posts
            if ($post->status !== 'pending') {
                return response()->json([
                    'success' => false,
                    'message' => 'Can only delete pending posts',
                ], 400);
            }

            $post->delete();

            return response()->json([
                'success' => true,
                'message' => 'Q&A post deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete Q&A post', [
                'post_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete Q&A post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add reply to Q&A post
     * POST /api/qna/posts/{id}/replies
     */
    public function addReply(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $post = QnaPost::findOrFail($id);

            // Check permissions
            if (! $post->canReply($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to reply to this post',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'content' => 'required|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            DB::beginTransaction();

            $reply = QnaReply::create([
                'qna_post_id' => $post->id,
                'author_user_id' => $user->id,
                'content' => $request->content,
                'is_official_answer' => $user->isHeadquarters(),
            ]);

            // Update post status to 'answered' if HQ replied
            if ($user->isHeadquarters() && $post->status === 'pending') {
                $post->update(['status' => 'answered']);
            }

            DB::commit();

            $reply->load('author:id,name,role');

            return response()->json([
                'success' => true,
                'message' => 'Reply added successfully',
                'data' => $reply,
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to add reply', [
                'post_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to add reply',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Close Q&A post (HQ or author only)
     * POST /api/qna/posts/{id}/close
     */
    public function close($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $post = QnaPost::findOrFail($id);

            // Only HQ or author can close
            if (! ($user->isHeadquarters() || $post->author_user_id === $user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to close this post',
                ], 403);
            }

            $post->update(['status' => 'closed']);

            return response()->json([
                'success' => true,
                'message' => 'Q&A post closed successfully',
                'data' => $post,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to close Q&A post', [
                'post_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to close Q&A post',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
