<?php

namespace App\Helpers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PDOException;

class DatabaseHelper
{
    /**
     * Railway PostgreSQL에서 안전한 쿼리 실행을 위한 retry 로직
     * 
     * @param callable $callback
     * @param int $maxRetries
     * @return mixed
     */
    public static function executeWithRetry(callable $callback, int $maxRetries = 5)
    {
        $attempt = 0;
        $lastException = null;
        
        while ($attempt < $maxRetries) {
            try {
                // callback 함수 실행
                $result = $callback();
                
                // 성공시 결과 반환
                if ($attempt > 0) {
                    Log::info("DatabaseHelper: Query succeeded after {$attempt} retries");
                }
                return $result;
                
            } catch (PDOException $e) {
                $attempt++;
                $lastException = $e;
                
                // Railway PostgreSQL 특정 오류 확인
                $shouldRetry = self::shouldRetryError($e);
                
                if (!$shouldRetry || $attempt >= $maxRetries) {
                    Log::error("DatabaseHelper: Query failed permanently", [
                        'attempt' => $attempt,
                        'error' => $e->getMessage(),
                        'should_retry' => $shouldRetry
                    ]);
                    throw $e;
                }
                
                // 개선된 Exponential backoff (50ms, 150ms, 450ms, 1350ms, 4050ms)
                $delayMs = 50 * pow(3, $attempt - 1);
                Log::warning("DatabaseHelper: Retrying query after {$delayMs}ms", [
                    'attempt' => $attempt,
                    'error' => $e->getMessage()
                ]);
                
                usleep($delayMs * 1000); // microseconds
                
            } catch (\Exception $e) {
                // PDO 외 다른 예외는 즉시 실패
                Log::error("DatabaseHelper: Non-PDO exception", [
                    'error' => $e->getMessage(),
                    'type' => get_class($e)
                ]);
                throw $e;
            }
        }
        
        throw $lastException;
    }

    /**
     * Railway PostgreSQL에서 재시도할만한 오류인지 판단
     */
    private static function shouldRetryError(PDOException $e): bool
    {
        $errorMessage = $e->getMessage();
        
        // Prepared statement 관련 오류 (Railway에서 자주 발생)
        if (str_contains($errorMessage, 'prepared statement') && 
            str_contains($errorMessage, 'does not exist')) {
            return true;
        }
        
        // Connection 관련 오류
        if (str_contains($errorMessage, 'Connection') || 
            str_contains($errorMessage, 'timeout') ||
            str_contains($errorMessage, 'server closed') ||
            str_contains($errorMessage, 'broken pipe')) {
            return true;
        }
        
        // Bind parameter 오류
        if (str_contains($errorMessage, 'bind message supplies') ||
            str_contains($errorMessage, 'parameter count')) {
            return true;
        }
        
        // Lock 관련 오류 (동시성 문제)
        if (str_contains($errorMessage, 'deadlock') ||
            str_contains($errorMessage, 'lock wait timeout')) {
            return true;
        }
        
        // PostgreSQL 특정 오류들
        if (str_contains($errorMessage, 'connection to server') ||
            str_contains($errorMessage, 'SSL connection') ||
            str_contains($errorMessage, 'terminating connection')) {
            return true;
        }
        
        return false;
    }

    /**
     * Raw SQL을 사용한 안전한 쿼리 실행
     */
    public static function rawQuery(string $sql, array $bindings = [])
    {
        return self::executeWithRetry(function () use ($sql, $bindings) {
            return DB::select($sql, $bindings);
        });
    }

    /**
     * 안전한 aggregate 쿼리 (count, sum 등)
     */
    public static function safeAggregate(string $table, string $operation, string $column = '*', array $conditions = [])
    {
        return self::executeWithRetry(function () use ($table, $operation, $column, $conditions) {
            $query = DB::table($table);
            
            foreach ($conditions as $field => $value) {
                if (is_array($value)) {
                    $query->whereBetween($field, $value);
                } else {
                    $query->where($field, $value);
                }
            }
            
            return $query->{$operation}($column);
        });
    }
}