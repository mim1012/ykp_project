<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * 애플리케이션의 Artisan 명령어들
     */
    protected $commands = [
        Commands\DatabaseBackup::class,
    ];

    /**
     * 애플리케이션의 명령 스케줄 정의
     */
    protected function schedule(Schedule $schedule)
    {
        // 🔄 매일 새벽 3시 데이터베이스 백업
        $schedule->command('db:backup')
            ->dailyAt('03:00')
            ->withoutOverlapping(120) // 2시간 타임아웃
            ->runInBackground()
            ->onFailure(function () {
                \Log::error('YKP ERP 데이터베이스 백업 실패');
            })
            ->onSuccess(function () {
                \Log::info('YKP ERP 데이터베이스 백업 성공');
            });

        // 🗜️ 주말마다 압축 백업 (토요일 새벽 2시)
        $schedule->command('db:backup --compress')
            ->weeklyOn(6, '02:00') // 토요일 새벽 2시
            ->withoutOverlapping(180)
            ->runInBackground();

        // 🧹 매주 일요일 오래된 백업 파일 정리
        $schedule->call(function () {
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
                }
            }

            \Log::info("백업 파일 정리 완료: {$deletedCount}개 파일 삭제");
        })
            ->weeklyOn(7, '04:00') // 일요일 새벽 4시
            ->name('cleanup-old-backups')
            ->withoutOverlapping();

        // 📊 매일 오전 9시 시스템 상태 체크
        $schedule->call(function () {
            $stats = [
                'stores_count' => \App\Models\Store::count(),
                'branches_count' => \App\Models\Branch::count(),
                'users_count' => \App\Models\User::count(),
                'sales_today' => \App\Models\Sale::whereDate('sale_date', today())->count(),
                'memory_usage' => memory_get_peak_usage(true) / 1024 / 1024, // MB
            ];

            // 550개 매장 임계점 체크 (90% = 495개)
            if ($stats['stores_count'] >= 495) {
                \Log::warning('매장 수 임계점 접근', [
                    'current_stores' => $stats['stores_count'],
                    'capacity' => '550개',
                    'usage_percent' => round(($stats['stores_count'] / 550) * 100, 1),
                ]);
            }

            \Log::info('일일 시스템 상태 체크', $stats);
        })
            ->dailyAt('09:00')
            ->name('daily-system-check');

        // ⚡ 매시간 성능 최적화 작업
        $schedule->call(function () {
            try {
                // PostgreSQL VACUUM ANALYZE (성능 최적화)
                DB::statement('VACUUM ANALYZE sales;');
                DB::statement('VACUUM ANALYZE stores;');
                DB::statement('VACUUM ANALYZE branches;');

                \Log::info('데이터베이스 최적화 완료 (VACUUM ANALYZE)');
            } catch (\Exception $e) {
                \Log::warning('데이터베이스 최적화 실패: '.$e->getMessage());
            }
        })
            ->hourly()
            ->between('02:00', '06:00') // 새벽 시간대만 실행
            ->name('db-optimization');
    }

    /**
     * 애플리케이션의 전역 명령어들을 등록
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
