<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Branch;
use App\Models\Store;
use App\Models\Sale;
use App\Models\User;

class CleanupTestData extends Command
{
    protected $signature = 'cleanup:test-data {--force : Force deletion without confirmation} {--cascade : Delete all related data}';
    protected $description = 'Clean up test branches and related data with cascade deletion';

    public function handle()
    {
        $this->info('🧹 테스트 데이터 정리 시작');

        // 테스트 지사 식별 패턴
        $testPatterns = ['테스트', 'test', 'Test', 'TEST', 'E2E', 'BB01', 'GG001'];

        $testBranches = Branch::where(function($query) use ($testPatterns) {
            foreach ($testPatterns as $pattern) {
                $query->orWhere('name', 'LIKE', "%{$pattern}%")
                      ->orWhere('code', 'LIKE', "%{$pattern}%");
            }
        })->get();

        if ($testBranches->isEmpty()) {
            $this->info('✅ 정리할 테스트 지사가 없습니다.');
            return;
        }

        $this->table(
            ['ID', '지사명', '코드', '매장수', '매출건수', '사용자수', '삭제가능'],
            $testBranches->map(function($branch) {
                $storesCount = Store::where('branch_id', $branch->id)->count();
                $salesCount = Sale::where('branch_id', $branch->id)->count();
                $usersCount = User::where('branch_id', $branch->id)->count();
                $canDelete = ($storesCount + $salesCount + $usersCount) == 0;

                return [
                    $branch->id,
                    $branch->name,
                    $branch->code,
                    $storesCount,
                    $salesCount,
                    $usersCount,
                    $canDelete ? '✅' : '❌'
                ];
            })
        );

        if (!$this->option('force') && !$this->confirm('정말로 삭제 가능한 테스트 지사들을 삭제하시겠습니까?')) {
            $this->info('취소되었습니다.');
            return;
        }

        $deletedCount = 0;
        $skippedCount = 0;

        foreach ($testBranches as $branch) {
            $storesCount = Store::where('branch_id', $branch->id)->count();
            $salesCount = Sale::where('branch_id', $branch->id)->count();
            $usersCount = User::where('branch_id', $branch->id)->count();

            if ($storesCount == 0 && $salesCount == 0 && $usersCount == 0) {
                // 종속 데이터 없음 - 바로 삭제
                $branch->delete();
                $this->info("✅ 삭제 완료: {$branch->name} ({$branch->code})");
                $deletedCount++;
            } else if ($this->option('cascade')) {
                // 🔥 CASCADE 삭제 실행
                $this->warn("🚨 CASCADE 삭제 시작: {$branch->name}");

                // 1단계: Sales 데이터 삭제 (가장 하위)
                if ($salesCount > 0) {
                    $deletedSales = Sale::where('branch_id', $branch->id)->delete();
                    $this->info("   📊 매출 데이터 삭제: {$deletedSales}건");
                }

                // 2단계: Users 데이터 처리 (branch_id, store_id 해제)
                if ($usersCount > 0) {
                    $affectedUsers = User::where('branch_id', $branch->id)
                        ->update([
                            'branch_id' => null,
                            'store_id' => null,
                            'is_active' => false
                        ]);
                    $this->info("   👤 사용자 계정 해제: {$affectedUsers}개");
                }

                // 3단계: Stores 삭제
                if ($storesCount > 0) {
                    $storeNames = Store::where('branch_id', $branch->id)->pluck('name')->toArray();
                    $deletedStores = Store::where('branch_id', $branch->id)->delete();
                    $this->info("   🏪 매장 삭제: {$deletedStores}개 (" . implode(', ', $storeNames) . ")");
                }

                // 4단계: Branch 삭제 (최상위)
                $branch->delete();
                $this->info("   🏢 지사 삭제 완료: {$branch->name}");

                $deletedCount++;
            } else {
                $this->warn("⚠️ 스킵: {$branch->name} - 종속 데이터 존재 (--cascade 옵션 사용 시 삭제 가능)");
                $this->line("   📊 매장: {$storesCount}개, 매출: {$salesCount}건, 사용자: {$usersCount}개");
                $skippedCount++;
            }
        }

        $this->info("🎉 정리 완료! 삭제: {$deletedCount}개, 스킵: {$skippedCount}개");
    }
}