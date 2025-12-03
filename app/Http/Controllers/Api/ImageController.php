<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PostImage;
use App\Models\QnaPost;
use App\Models\NoticePost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageController extends Controller
{
    /**
     * Upload images for a post
     * POST /api/images/upload
     */
    public function upload(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();

            $request->validate([
                'post_id' => 'required|integer',
                'type' => 'required|in:qna,notice',
                'images' => 'required|array|max:5',
                'images.*' => 'image|mimes:jpeg,png,gif,webp|max:2048',
            ]);

            $postId = $request->post_id;
            $type = $request->type;

            // Verify post exists and user has permission
            if ($type === 'qna') {
                $post = QnaPost::findOrFail($postId);
                if ($post->author_user_id !== $user->id && !$user->isHeadquarters()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized to upload images to this post',
                    ], 403);
                }
                $imageableType = QnaPost::class;
            } else {
                $post = NoticePost::findOrFail($postId);
                if ($post->author_user_id !== $user->id && !$user->isHeadquarters()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Unauthorized to upload images to this post',
                    ], 403);
                }
                $imageableType = NoticePost::class;
            }

            $uploadedImages = [];

            foreach ($request->file('images') as $image) {
                $filename = Str::uuid() . '.' . $image->getClientOriginalExtension();
                $path = "posts/{$type}/{$postId}";

                // Store the image
                $storedPath = $image->storeAs($path, $filename, 'public');

                // Create database record
                $postImage = PostImage::create([
                    'imageable_type' => $imageableType,
                    'imageable_id' => $postId,
                    'filename' => $filename,
                    'original_name' => $image->getClientOriginalName(),
                    'path' => $storedPath,
                    'url' => Storage::url($storedPath),
                    'mime_type' => $image->getMimeType(),
                    'size' => $image->getSize(),
                    'uploaded_by' => $user->id,
                ]);

                $uploadedImages[] = $postImage;
            }

            return response()->json([
                'success' => true,
                'message' => count($uploadedImages) . ' image(s) uploaded successfully',
                'data' => $uploadedImages,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Image upload failed', [
                'error' => $e->getMessage(),
                'user_id' => $user->id ?? null,
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload images',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete an image
     * DELETE /api/images/{id}
     */
    public function destroy($id): JsonResponse
    {
        try {
            $user = Auth::user();
            $image = PostImage::findOrFail($id);

            // Check permission
            if ($image->uploaded_by !== $user->id && !$user->isHeadquarters()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized to delete this image',
                ], 403);
            }

            // Delete file from storage
            if (Storage::disk('public')->exists($image->path)) {
                Storage::disk('public')->delete($image->path);
            }

            // Delete database record
            $image->delete();

            return response()->json([
                'success' => true,
                'message' => 'Image deleted successfully',
            ]);

        } catch (\Exception $e) {
            Log::error('Image deletion failed', [
                'image_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete image',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
