<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NoticePost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class NoticePostController extends Controller
{
    /**
     * List notices visible to the current user
     * GET /api/notices?include_expired=false&page=1&per_page=20
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $perPage = $request->input('per_page', 20);

            $query = NoticePost::with(['author:id,name,role', 'images'])
                ->visibleTo($user)
                ->published();

            // Exclude expired notices by default
            if (! $request->input('include_expired', false)) {
                $query->active();
            }

            // Search filter
            if ($request->has('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('title', 'LIKE', "%{$search}%")
                      ->orWhere('content', 'LIKE', "%{$search}%");
                });
            }

            $notices = $query->ordered()->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $notices->items(),
                'pagination' => [
                    'current_page' => $notices->currentPage(),
                    'per_page' => $notices->perPage(),
                    'total' => $notices->total(),
                    'last_page' => $notices->lastPage(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get notices', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get notices',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get single notice
     * GET /api/notices/{id}
     */
    public function show($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $notice = NoticePost::with(['author:id,name,role', 'images'])->findOrFail($id);

            // Check if user is targeted
            if (! $notice->isTargetedTo($user)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to view this notice',
                ], 403);
            }

            // Increment view count
            $notice->incrementViewCount();

            return response()->json([
                'success' => true,
                'data' => $notice,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to get notice', [
                'notice_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get notice',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create new notice (HQ or Branch only)
     * POST /api/notices
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            // Only HQ and Branch can create notices
            if (! ($user->isHeadquarters() || $user->isBranch())) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only headquarters and branch users can create notices',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'required|string|max:255',
                'content' => 'required|string',
                'target_audience' => 'required|in:all,branches,stores,specific',
                'target_branch_ids' => 'nullable|array',
                'target_branch_ids.*' => 'exists:branches,id',
                'target_store_ids' => 'nullable|array',
                'target_store_ids.*' => 'exists:stores,id',
                'is_pinned' => 'boolean',
                'priority' => 'integer|min:0|max:100',
                'published_at' => 'nullable|date',
                'expires_at' => 'nullable|date|after:published_at',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // Branch users can only target their own branch
            if ($user->isBranch()) {
                $targetAudience = $request->target_audience;

                if ($targetAudience === 'all') {
                    return response()->json([
                        'success' => false,
                        'message' => 'Branch users cannot create notices for all users',
                    ], 403);
                }

                if ($targetAudience === 'branches') {
                    $targetBranchIds = $request->input('target_branch_ids', []);
                    if (! in_array($user->branch_id, $targetBranchIds)) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Branch users can only target their own branch',
                        ], 403);
                    }
                }

                if ($targetAudience === 'stores') {
                    // Verify all target stores belong to this branch
                    $targetStoreIds = $request->input('target_store_ids', []);
                    $stores = \App\Models\Store::whereIn('id', $targetStoreIds)->pluck('branch_id')->unique();

                    if ($stores->count() !== 1 || $stores->first() !== $user->branch_id) {
                        return response()->json([
                            'success' => false,
                            'message' => 'Branch users can only target stores in their branch',
                        ], 403);
                    }
                }
            }

            $notice = NoticePost::create([
                'title' => $request->title,
                'content' => $request->content,
                'author_user_id' => $user->id,
                'target_audience' => $request->target_audience,
                'target_branch_ids' => $request->input('target_branch_ids'),
                'target_store_ids' => $request->input('target_store_ids'),
                'is_pinned' => $request->input('is_pinned', false),
                'priority' => $request->input('priority', 0),
                'published_at' => $request->input('published_at', now()),
                'expires_at' => $request->input('expires_at'),
                'view_count' => 0,
            ]);

            $notice->load('author:id,name,role');

            return response()->json([
                'success' => true,
                'message' => 'Notice created successfully',
                'data' => $notice,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Failed to create notice', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to create notice',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update notice (Author only)
     * PUT /api/notices/{id}
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $notice = NoticePost::findOrFail($id);

            // Only author can update
            if ($notice->author_user_id !== $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to update this notice',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'title' => 'sometimes|string|max:255',
                'content' => 'sometimes|string',
                'target_audience' => 'sometimes|in:all,branches,stores,specific',
                'target_branch_ids' => 'nullable|array',
                'target_branch_ids.*' => 'exists:branches,id',
                'target_store_ids' => 'nullable|array',
                'target_store_ids.*' => 'exists:stores,id',
                'is_pinned' => 'sometimes|boolean',
                'priority' => 'sometimes|integer|min:0|max:100',
                'published_at' => 'nullable|date',
                'expires_at' => 'nullable|date',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $notice->update($request->only([
                'title',
                'content',
                'target_audience',
                'target_branch_ids',
                'target_store_ids',
                'is_pinned',
                'priority',
                'published_at',
                'expires_at',
            ]));

            $notice->load('author:id,name,role');

            return response()->json([
                'success' => true,
                'message' => 'Notice updated successfully',
                'data' => $notice,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to update notice', [
                'notice_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update notice',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete notice (Author or HQ only)
     * DELETE /api/notices/{id}
     */
    public function destroy($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $notice = NoticePost::findOrFail($id);

            // Only author or HQ can delete
            if (! ($user->isHeadquarters() || $notice->author_user_id === $user->id)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this notice',
                ], 403);
            }

            $notice->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notice deleted successfully',
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to delete notice', [
                'notice_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notice',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Toggle pin status (HQ only)
     * POST /api/notices/{id}/toggle-pin
     */
    public function togglePin($id): JsonResponse
    {
        try {
            $user = Auth::user();

            // Only HQ can pin/unpin
            if (! $user->isHeadquarters()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only headquarters can pin/unpin notices',
                ], 403);
            }

            $notice = NoticePost::findOrFail($id);
            $notice->togglePin();

            return response()->json([
                'success' => true,
                'message' => $notice->is_pinned ? 'Notice pinned' : 'Notice unpinned',
                'data' => $notice,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to toggle pin', [
                'notice_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to toggle pin',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
