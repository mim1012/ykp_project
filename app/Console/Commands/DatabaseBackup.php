<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class DatabaseBackup extends Command
{
    protected $signature = 'db:backup {--format=sql} {--compress} {--upload}';

    protected $description = 'YKP ERP 데이터베이스 자동 백업 시스템';

    public function handle()
    {
        $this->info('YKP ERP 데이터베이스 백업 시작...');

        try {
            // 백업 디렉토리 생성
            $backupDir = storage_path('app/backups');
            if (! file_exists($backupDir)) {
                mkdir($backupDir, 0755, true);
                $this->info("백업 디렉토리 생성: {$backupDir}");
            }

            $timestamp = now()->format('Y-m-d_H-i-s');
            $filename = "ykp_erp_backup_{$timestamp}.sql";
            $filepath = $backupDir.'/'.$filename;

            // 데이터베이스 정보 가져오기
            $dbUrl = config('database.connections.pgsql.url') ?? env('DATABASE_URL');

            if (! $dbUrl) {
                $this->error('데이터베이스 URL이 설정되지 않았습니다.');

                return 1;
            }

            // PostgreSQL 백업 명령어 실행
            $backupCommand = "pg_dump \"{$dbUrl}\" > \"{$filepath}\"";

            $this->info('백업 명령어 실행 중...');
            exec($backupCommand, $output, $exitCode);

            if ($exitCode === 0 && file_exists($filepath)) {
                $fileSize = $this->formatBytes(filesize($filepath));
                $this->info('백업 완료!');
                $this->table(['항목', '값'], [
                    ['파일명', $filename],
                    ['크기', $fileSize],
                    ['경로', $filepath],
                    ['타임스탬프', $timestamp],
                ]);

                // 압축 옵션
                if ($this->option('compress')) {
                    $this->compressBackup($filepath);
                }

                // 오래된 백업 정리
                $this->cleanOldBackups();

                // 백업 성공 로그
                Log::info('Database backup completed successfully', [
                    'filename' => $filename,
                    'size_bytes' => filesize($filepath),
                    'tables_backed_up' => $this->getTableCount(),
                ]);

                return 0;
            } else {
                $this->error("백업 실패 (Exit Code: {$exitCode})");
                Log::error('Database backup failed', [
                    'exit_code' => $exitCode,
                    'command' => $backupCommand,
                    'output' => $output,
                ]);

                return 1;
            }

        } catch (\Exception $e) {
            $this->error("백업 중 오류 발생: {$e->getMessage()}");
            Log::error('Database backup exception', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return 1;
        }
    }

    /**
     * 오래된 백업 파일 정리 (30일 이상)
     */
    private function cleanOldBackups()
    {
        $backupDir = storage_path('app/backups');
        $cutoffTime = now()->subDays(30)->timestamp;
        $deletedCount = 0;

        if (! is_dir($backupDir)) {
            return;
        }

        $files = scandir($backupDir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $filepath = $backupDir.'/'.$file;
            if (filemtime($filepath) < $cutoffTime) {
                unlink($filepath);
                $deletedCount++;
                $this->info("오래된 백업 삭제: {$file}");
            }
        }

        if ($deletedCount > 0) {
            $this->info("{$deletedCount}개 오래된 백업 파일 정리 완료");
        }
    }

    /**
     * 백업 파일 압축
     */
    private function compressBackup($filepath)
    {
        $compressedPath = $filepath.'.gz';
        $command = "gzip \"{$filepath}\"";

        exec($command, $output, $exitCode);

        if ($exitCode === 0 && file_exists($compressedPath)) {
            $originalSize = filesize($filepath);
            $compressedSize = filesize($compressedPath);
            $compressionRatio = round((1 - $compressedSize / $originalSize) * 100, 1);

            $this->info("압축 완료: {$compressionRatio}% 크기 감소");
        }
    }

    /**
     * 파일 크기 형식화
     */
    private function formatBytes($bytes, $precision = 2)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }

    /**
     * 테이블 수 확인
     */
    private function getTableCount()
    {
        try {
            $tables = DB::select("SELECT count(*) as count FROM information_schema.tables WHERE table_schema = 'public'");

            return $tables[0]->count ?? 0;
        } catch (\Exception $e) {
            return 'Unknown';
        }
    }
}
