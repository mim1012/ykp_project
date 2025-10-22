<?php

namespace App\Jobs;

use App\Helpers\RandomDataGenerator;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * 지사 대량 생성 백그라운드 Job
 */
class ProcessBulkBranchCreationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $timeout = 600;

    protected $branchesData;
    protected $createdBy;

    /**
     * Create a new job instance.
     *
     * @param  array  $branchesData  생성할 지사 데이터 배열
     * @param  int  $createdBy  생성자 user_id
     */
    public function __construct(array $branchesData, int $createdBy)
    {
        $this->branchesData = $branchesData;
        $this->createdBy = $createdBy;
    }

    /**
     * Execute the job.
     */
    public function handle(): array
    {
        $startTime = microtime(true);
        $results = [
            'success' => [],
            'errors' => [],
        ];

        Log::info('Bulk branch creation job started', [
            'total_branches' => count($this->branchesData),
            'created_by' => $this->createdBy,
        ]);

        // 현재 최대 지사 번호 조회
        $maxBranchNumber = Branch::max('id') ?? 0;
        $currentSequence = $maxBranchNumber + 1;

        foreach ($this->branchesData as $index => $branchData) {
            try {
                DB::beginTransaction();

                // 1. 지사명 중복 체크
                if (Branch::where('name', $branchData['branch_name'])->exists()) {
                    throw new \Exception("이미 존재하는 지사명: {$branchData['branch_name']}");
                }

                // 2. 지사 코드 자동 생성
                $branchCode = RandomDataGenerator::generateBranchCode($currentSequence);

                // 3. 지사 생성
                $branch = Branch::create([
                    'code' => $branchCode,
                    'name' => $branchData['branch_name'],
                    'status' => 'active',
                ]);

                // 4. 지역장 계정 정보 생성
                $managerUsername = RandomDataGenerator::generateBranchUsername($branchData['branch_name']);
                $managerEmail = RandomDataGenerator::generateEmail($managerUsername);
                $initialPassword = RandomDataGenerator::generateBranchPassword();

                // 5. 지역장 계정 중복 체크
                if (User::where('username', $managerUsername)->exists()) {
                    // 중복 시 타임스탬프 추가
                    $managerUsername .= '_'.time();
                    $managerEmail = RandomDataGenerator::generateEmail($managerUsername);
                }

                // 6. 지역장 계정 생성
                $manager = User::create([
                    'name' => $branchData['manager_name'],
                    'username' => $managerUsername,
                    'email' => $managerEmail,
                    'password' => Hash::make($initialPassword),
                    'role' => 'branch',
                    'branch_id' => $branch->id,
                    'store_id' => null,
                    'status' => 'active',
                    'must_change_password' => true,
                ]);

                $results['success'][] = [
                    'row' => $index + 2,
                    'branch_code' => $branch->code,
                    'branch_name' => $branch->name,
                    'manager_name' => $manager->name,
                    'username' => $manager->username,
                    'email' => $manager->email,
                    'initial_password' => $initialPassword,
                    'created_at' => now()->format('Y-m-d H:i:s'),
                ];

                $currentSequence++;
                DB::commit();

            } catch (\Exception $e) {
                DB::rollBack();

                $results['errors'][] = [
                    'row' => $index + 2,
                    'message' => $e->getMessage(),
                    'data' => $branchData,
                ];

                Log::error('Branch creation failed', [
                    'row' => $index + 2,
                    'error' => $e->getMessage(),
                    'data' => $branchData,
                ]);
            }
        }

        $totalTime = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Bulk branch creation job completed', [
            'total_processed' => count($this->branchesData),
            'success_count' => count($results['success']),
            'error_count' => count($results['errors']),
            'total_time_ms' => $totalTime,
        ]);

        return $results;
    }

    /**
     * Job 실패 시 처리
     */
    public function failed(\Throwable $exception)
    {
        Log::error('Bulk branch creation job failed', [
            'error' => $exception->getMessage(),
            'created_by' => $this->createdBy,
            'total_branches' => count($this->branchesData),
        ]);
    }
}
