<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ActivityController extends Controller
{
    /**
     * 최근 활동 조회
     */
    public function recent(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $limit = $request->get('limit', 10);

            $query = ActivityLog::with('user:id,name,role')
                ->orderBy('performed_at', 'desc')
                ->limit($limit);

            // 권한별 필터링
            if ($user->role === 'branch') {
                $query->where(function($q) use ($user) {
                    $q->where('user_id', $user->id)
                      ->orWhereIn('target_id', function($subq) use ($user) {
                          $subq->select('id')->from('stores')->where('branch_id', $user->branch_id);
                      });
                });
            } elseif ($user->role === 'store') {
                $query->where('user_id', $user->id);
            }

            $activities = $query->get()->map(function($activity) {
                return [
                    'id' => $activity->id,
                    'type' => $activity->activity_type,
                    'title' => $activity->activity_title,
                    'description' => $activity->activity_description,
                    'user_name' => $activity->user->name ?? 'Unknown',
                    'user_role' => $activity->user->role ?? 'unknown',
                    'performed_at' => $activity->performed_at->toISOString(),
                    'time_ago' => $activity->performed_at->diffForHumans()
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $activities,
                'meta' => [
                    'count' => $activities->count(),
                    'user_role' => $user->role
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Recent activities fetch failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);

            return response()->json([
                'success' => false,
                'error' => '활동 내역을 불러올 수 없습니다.'
            ], 500);
        }
    }

    /**
     * 활동 로그 기록
     */
    public function log(Request $request): JsonResponse
    {
        $request->validate([
            'activity_type' => 'required|string|max:50',
            'activity_title' => 'required|string|max:255',
            'activity_description' => 'nullable|string|max:1000',
            'target_type' => 'nullable|string|max:50',
            'target_id' => 'nullable|integer',
            'activity_data' => 'nullable|array'
        ]);

        try {
            $activity = ActivityLog::create([
                'user_id' => Auth::id(),
                'activity_type' => $request->activity_type,
                'activity_title' => $request->activity_title,
                'activity_description' => $request->activity_description,
                'activity_data' => $request->activity_data,
                'target_type' => $request->target_type,
                'target_id' => $request->target_id,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'performed_at' => now()
            ]);

            Log::info('Activity logged', [
                'activity_id' => $activity->id,
                'user_id' => Auth::id(),
                'type' => $request->activity_type
            ]);

            return response()->json([
                'success' => true,
                'message' => '활동이 기록되었습니다.',
                'data' => [
                    'id' => $activity->id,
                    'performed_at' => $activity->performed_at->toISOString()
                ]
            ], 201);

        } catch (\Exception $e) {
            Log::error('Activity logging failed', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'error' => '활동 기록 중 오류가 발생했습니다.'
            ], 500);
        }
    }
}
