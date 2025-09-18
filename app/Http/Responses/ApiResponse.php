<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

/**
 * 표준화된 API 응답 헬퍼 클래스
 *
 * 모든 API 응답의 일관성을 보장하고 클라이언트-서버 간 계약을 명확히 함
 */
class ApiResponse
{
    /**
     * 성공 응답 생성
     */
    public static function success($data = null, string $message = '', array $meta = []): JsonResponse
    {
        $response = [
            'success' => true,
            'data' => $data,
        ];

        if (! empty($message)) {
            $response['message'] = $message;
        }

        if (! empty($meta)) {
            $response['meta'] = $meta;
        }

        $response['timestamp'] = now()->toISOString();

        return response()->json($response);
    }

    /**
     * 에러 응답 생성
     */
    public static function error(string $message, int $code = 400, array $errors = [], array $debug = []): JsonResponse
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        if (config('app.debug') && ! empty($debug)) {
            $response['debug'] = $debug;
        }

        $response['timestamp'] = now()->toISOString();

        // 에러 로깅 (프로덕션 디버깅용)
        Log::error('API Error Response', [
            'message' => $message,
            'code' => $code,
            'errors' => $errors,
            'debug' => $debug,
            'request_url' => request()->fullUrl(),
            'user_id' => auth()->id(),
        ]);

        return response()->json($response, $code);
    }

    /**
     * 검증 실패 응답
     */
    public static function validationError(array $errors, string $message = '입력값이 올바르지 않습니다.'): JsonResponse
    {
        return self::error($message, 422, $errors);
    }

    /**
     * 인증 실패 응답
     */
    public static function unauthorized(string $message = '인증이 필요합니다.'): JsonResponse
    {
        return self::error($message, 401);
    }

    /**
     * 권한 부족 응답
     */
    public static function forbidden(string $message = '접근 권한이 없습니다.'): JsonResponse
    {
        return self::error($message, 403);
    }

    /**
     * 리소스 없음 응답
     */
    public static function notFound(string $message = '요청한 리소스를 찾을 수 없습니다.'): JsonResponse
    {
        return self::error($message, 404);
    }

    /**
     * 서버 에러 응답
     */
    public static function serverError(string $message = '서버 오류가 발생했습니다.', array $debug = []): JsonResponse
    {
        return self::error($message, 500, [], $debug);
    }
}
