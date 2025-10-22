<?php

namespace App\Jobs;

use App\Helpers\RandomDataGenerator;
use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * 매장 대량 생성 백그라운드 Job
 * 100개 이상 매장 생성 시 자동으로 큐 처리
 */
class ProcessBulkStoreCreationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Job 재시도 횟수
     */
    public $tries = 3;

    /**
     * Job 타임아웃 (10분)
     */
    public $timeout = 600;

    protected $storesData;
    protected $createdBy;
    protected $validationMode;

    /**
     * Create a new job instance.
     *
     * @param  array  $storesData  생성할 매장 데이터 배열
     * @param  int  $createdBy  생성자 user_id
     * @param  string  $validationMode  검증 모드 (reject|skip|auto_create)
     */
    public function __construct(array $storesData, int $createdBy, string $validationMode = 'reject')
    {
        $this->storesData = $storesData;
        $this->createdBy = $createdBy;
        $this->validationMode = $validationMode;
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
            'skipped' => [],
            'auto_created_branches' => [],
        ];

        Log::info('Bulk store creation job started', [
            'total_stores' => count($this->storesData),
            'created_by' => $this->createdBy,
            'validation_mode' => $this->validationMode,
        ]);

        foreach ($this->storesData as $index => $storeData) {
            try {
                DB::beginTransaction();

                // 1. 지사 검증 및 처리 (지사명으로 조회)
                $branch = Branch::where('name', $storeData['branch_name'])->first();

                if (! $branch) {
                    if ($this->validationMode === 'reject') {
                        throw new \Exception("존재하지 않는 지사: {$storeData['branch_name']}");
                    } elseif ($this->validationMode === 'skip') {
                        $results['skipped'][] = [
                            'row' => $index + 2,
                            'reason' => "존재하지 않는 지사: {$storeData['branch_name']}",
                            'data' => $storeData,
                        ];
                        DB::rollBack();

                        continue;
                    } elseif ($this->validationMode === 'auto_create') {
                        // 지사 자동 생성
                        $branch = $this->autoCreateBranch($storeData['branch_name']);
                        $results['auto_created_branches'][] = $branch->name;
                    }
                }

                // 2. 해당 지사의 매장 순번 조회
                $storeSequence = Store::where('branch_id', $branch->id)->count() + 1;

                // 3. 매장 코드 자동 생성
                $storeCode = RandomDataGenerator::generateStoreCode($branch->code, $storeSequence);

                // 4. 주소, 전화번호 랜덤 생성
                $address = RandomDataGenerator::generateAddress();
                $phone = RandomDataGenerator::generatePhoneNumber($address);

                // 5. 매장 생성
                $store = Store::create([
                    'code' => $storeCode,
                    'name' => $storeData['store_name'],
                    'branch_id' => $branch->id,
                    'address' => $address,
                    'phone' => $phone,
                    'status' => 'active',
                ]);

                // 6. 매장장 계정 정보 자동 생성
                $managerName = $storeData['store_name'].' 관리자';
                $username = RandomDataGenerator::generateStoreUsername($storeData['store_name']);
                $email = RandomDataGenerator::generateEmail($username);
                $initialPassword = RandomDataGenerator::generateStorePassword();

                // 7. 사용자명 중복 체크
                if (User::where('username', $username)->exists()) {
                    $username .= '_'.time();
                    $email = RandomDataGenerator::generateEmail($username);
                }

                // 8. 계정 생성
                $user = User::create([
                    'name' => $managerName,
                    'username' => $username,
                    'email' => $email,
                    'password' => Hash::make($initialPassword),
                    'role' => 'store',
                    'store_id' => $store->id,
                    'branch_id' => $branch->id,
                    'status' => 'active',
                    'must_change_password' => true,
                ]);

                $results['success'][] = [
                    'row' => $index + 2,
                    'branch_code' => $branch->code,
                    'branch_name' => $branch->name,
                    'store_code' => $store->code,
                    'store_name' => $store->name,
                    'username' => $user->username,
                    'email' => $user->email,
                    'initial_password' => $initialPassword,
                    'created_at' => now()->format('Y-m-d H:i:s'),
                ];

                DB::commit();

            } catch (\Exception $e) {
                DB::rollBack();

                $results['errors'][] = [
                    'row' => $index + 2,
                    'message' => $e->getMessage(),
                    'data' => $storeData,
                ];

                Log::error('Store creation failed', [
                    'row' => $index + 2,
                    'error' => $e->getMessage(),
                    'data' => $storeData,
                ]);
            }
        }

        $totalTime = round((microtime(true) - $startTime) * 1000, 2);

        Log::info('Bulk store creation job completed', [
            'total_processed' => count($this->storesData),
            'success_count' => count($results['success']),
            'error_count' => count($results['errors']),
            'skipped_count' => count($results['skipped']),
            'auto_created_branches_count' => count($results['auto_created_branches']),
            'total_time_ms' => $totalTime,
        ]);

        return $results;
    }

    /**
     * 지사 자동 생성 (auto_create 모드)
     */
    protected function autoCreateBranch(string $branchName): Branch
    {
        // 현재 최대 지사 번호 조회
        $maxBranchNumber = Branch::max('id') ?? 0;
        $branchCode = RandomDataGenerator::generateBranchCode($maxBranchNumber + 1);

        return Branch::create([
            'code' => $branchCode,
            'name' => $branchName,
            'status' => 'active',
        ]);
    }

    /**
     * Job 실패 시 처리
     */
    public function failed(\Throwable $exception)
    {
        Log::error('Bulk store creation job failed', [
            'error' => $exception->getMessage(),
            'created_by' => $this->createdBy,
            'total_stores' => count($this->storesData),
        ]);
    }
}
