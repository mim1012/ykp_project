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
    public static function executeWithRetry(callable $callback, int $maxRetries = 3)
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
                
                // Exponential backoff 지연 (100ms, 300ms, 900ms)
                $delayMs = 100 * pow(3, $attempt - 1);
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
        
        // Prepared statement 관련 오류
        if (str_contains($errorMessage, 'prepared statement') && 
            str_contains($errorMessage, 'does not exist')) {
            return true;
        }
        
        // Connection 관련 오류
        if (str_contains($errorMessage, 'Connection') || 
            str_contains($errorMessage, 'timeout')) {
            return true;
        }
        
        // Bind parameter 오류
        if (str_contains($errorMessage, 'bind message supplies')) {
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