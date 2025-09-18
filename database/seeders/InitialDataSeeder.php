<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class InitialDataSeeder extends Seeder
{
    public function run()
    {
        // 본사 관리자
        $hqAdmin = User::create([
            'name' => '본사 관리자',
            'email' => 'admin@ykp.com',
            'password' => Hash::make('password'),
            'role' => 'headquarters',
            'is_active' => true,
        ]);

        // 지점 생성
        $branches = [
            ['code' => 'BR001', 'name' => '서울지점', 'manager_name' => '김지점'],
            ['code' => 'BR002', 'name' => '경기지점', 'manager_name' => '이지점'],
            ['code' => 'BR003', 'name' => '인천지점', 'manager_name' => '박지점'],
            ['code' => 'BR004', 'name' => '부산지점', 'manager_name' => '최지점'],
            ['code' => 'BR005', 'name' => '대구지점', 'manager_name' => '정지점'],
        ];

        foreach ($branches as $branchData) {
            $branch = Branch::create(array_merge($branchData, [
                'phone' => '010-0000-0000',
                'address' => $branchData['name'].' 주소',
                'status' => 'active',
            ]));

            // 지점 관리자 계정
            User::create([
                'name' => $branchData['manager_name'],
                'email' => strtolower($branchData['code']).'@ykp.com',
                'password' => Hash::make('password'),
                'role' => 'branch',
                'branch_id' => $branch->id,
                'is_active' => true,
            ]);

            // 각 지점별 매장 생성 (10개씩)
            for ($i = 1; $i <= 10; $i++) {
                $storeCode = $branchData['code'].sprintf('-%03d', $i);
                $store = Store::create([
                    'branch_id' => $branch->id,
                    'code' => $storeCode,
                    'name' => $branchData['name'].' '.$i.'호점',
                    'owner_name' => '점주'.$i,
                    'phone' => '010-'.rand(1000, 9999).'-'.rand(1000, 9999),
                    'address' => $branchData['name'].' '.$i.'호점 주소',
                    'status' => 'active',
                    'opened_at' => now()->subDays(rand(30, 365)),
                ]);

                // 매장 관리자 계정
                User::create([
                    'name' => $store->owner_name,
                    'email' => strtolower($storeCode).'@ykp.com',
                    'password' => Hash::make('password'),
                    'role' => 'store',
                    'branch_id' => $branch->id,
                    'store_id' => $store->id,
                    'is_active' => true,
                ]);
            }
        }

        $this->command->info('초기 데이터 생성 완료!');
        $this->command->info('본사 관리자: admin@ykp.com / password');
        $this->command->info('지점 관리자: br001@ykp.com / password (BR001~BR005)');
        $this->command->info('매장 관리자: br001-001@ykp.com / password (각 매장별)');
    }
}
