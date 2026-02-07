<?php

namespace App\Console\Commands;

use App\Models\Branch;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Illuminate\Console\Command;

class DeleteSpecificBranches extends Command
{
    protected $signature = 'cleanup:specific-branches {--force : Force deletion without confirmation}';

    protected $description = 'Delete specific test branches by ID with cascade deletion';

    public function handle()
    {
        $this->info('특정 테스트 지사 삭제');

        // 삭제할 특정 지사 ID들
        $targetBranchIds = [1, 6, 7, 12, 13]; // TEST001, GG001, test, E2E999, BB01

        $targetBranches = Branch::whereIn('id', $targetBranchIds)->get();

        if ($targetBranches->isEmpty()) {
            $this->info('삭제할 지사가 없습니다.');

            return;
        }

        $this->table(
            ['ID', '지사명', '코드', '매장수', '매출건수', '사용자수'],
            $targetBranches->map(function ($branch) {
                $storesCount = Store::where('branch_id', $branch->id)->count();
                $salesCount = Sale::where('branch_id', $branch->id)->count();
                $usersCount = User::where('branch_id', $branch->id)->count();

                return [
                    $branch->id,
                    $branch->name,
                    $branch->code,
                    $storesCount,
                    $salesCount,
                    $usersCount,
                ];
            })
        );

        if (! $this->option('force') && ! $this->confirm('위 테스트 지사들과 모든 종속 데이터를 완전히 삭제하시겠습니까?\n(매장, 매출, 사용자 모두 삭제됩니다)')) {
            $this->info('취소되었습니다.');

            return;
        }

        $this->info('CASCADE 삭제 시작...');

        $totalDeleted = 0;
        foreach ($targetBranches as $branch) {
            $this->warn("{$branch->name} ({$branch->code}) 삭제 중...");

            // 1단계: Sales 데이터 삭제
            $salesCount = Sale::where('branch_id', $branch->id)->count();
            if ($salesCount > 0) {
                Sale::where('branch_id', $branch->id)->delete();
                $this->info("   매출 데이터 삭제: {$salesCount}건");
            }

            // Store별 Sales도 삭제
            $stores = Store::where('branch_id', $branch->id)->pluck('id');
            if ($stores->isNotEmpty()) {
                $storeSalesCount = Sale::whereIn('store_id', $stores)->count();
                if ($storeSalesCount > 0) {
                    Sale::whereIn('store_id', $stores)->delete();
                    $this->info("   매장별 매출 데이터 삭제: {$storeSalesCount}건");
                }
            }

            // 2단계: Users 데이터 처리
            $usersCount = User::where('branch_id', $branch->id)->count();
            $storeUsersCount = User::whereIn('store_id', $stores)->count();

            if ($usersCount > 0 || $storeUsersCount > 0) {
                // 지사 사용자 해제
                if ($usersCount > 0) {
                    User::where('branch_id', $branch->id)->update([
                        'branch_id' => null,
                        'store_id' => null,
                        'is_active' => false,
                    ]);
                }

                // 매장 사용자 해제
                if ($storeUsersCount > 0) {
                    User::whereIn('store_id', $stores)->update([
                        'branch_id' => null,
                        'store_id' => null,
                        'is_active' => false,
                    ]);
                }

                $this->info('   사용자 계정 해제: '.($usersCount + $storeUsersCount).'개');
            }

            // 3단계: Stores 삭제
            $storesCount = Store::where('branch_id', $branch->id)->count();
            if ($storesCount > 0) {
                $storeNames = Store::where('branch_id', $branch->id)->pluck('name')->toArray();
                Store::where('branch_id', $branch->id)->delete();
                $this->info("   매장 삭제: {$storesCount}개 (".implode(', ', $storeNames).')');
            }

            // 4단계: Branch 삭제
            $branch->delete();
            $this->info("   지사 삭제 완료: {$branch->name}");

            $totalDeleted++;
        }

        $this->info("완전 정리 완료! 총 {$totalDeleted}개 지사 및 모든 종속 데이터 삭제됨");
        $this->info('매장 관리 페이지를 새로고침하여 확인하세요.');
    }
}
