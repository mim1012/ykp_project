<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Sale;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdvancedSeeder extends Seeder
{
    private $dealers = [
        'SK텔레콤 강남대리점',
        'KT 판교대리점',
        'LG U+ 홍대대리점',
        'SK텔레콤 부산대리점',
        'KT 대구대리점',
        'LG U+ 인천대리점',
    ];

    private $models = [
        'iPhone 15 Pro', 'Galaxy S24 Ultra', 'Galaxy Z Fold 5',
        'iPhone 15', 'Galaxy A54', 'Xiaomi 13T',
    ];

    private $activationTypes = ['신규', '기변', 'MNP'];

    private $carriers = ['SK', 'KT', 'LG', 'MVNO'];

    public function run()
    {
        // 1. 본사 계정
        $hq = User::create([
            'name' => '본사 관리자',
            'email' => 'hq@ykp.com',
            'password' => Hash::make('password'),
            'role' => 'headquarters',
        ]);

        // 2. 지사 및 매장 생성
        $branches = [
            ['name' => '서울지사', 'code' => 'SEL', 'manager' => '김서울'],
            ['name' => '경기지사', 'code' => 'GGI', 'manager' => '이경기'],
            ['name' => '부산지사', 'code' => 'BSN', 'manager' => '박부산'],
        ];

        foreach ($branches as $branchData) {
            $branch = Branch::create([
                'code' => $branchData['code'],
                'name' => $branchData['name'],
                'manager_name' => $branchData['manager'],
                'phone' => '02-'.rand(1000, 9999).'-'.rand(1000, 9999),
                'address' => $branchData['name'].' 주소',
                'status' => 'active',
            ]);

            // 지사 관리자 계정
            $branchUser = User::create([
                'name' => $branchData['manager'],
                'email' => strtolower($branchData['code']).'@ykp.com',
                'password' => Hash::make('password'),
                'role' => 'branch',
                'branch_id' => $branch->id,
            ]);

            // 각 지사별 5개 매장
            $storeNames = match ($branchData['code']) {
                'SEL' => ['강남역점', '홍대입구점', '명동점', '강북점', '송파점'],
                'GGI' => ['판교점', '수원역점', '일산점', '분당점', '안양점'],
                'BSN' => ['해운대점', '서면점', '남포동점', '광안리점', '센텀시티점'],
            };

            foreach ($storeNames as $idx => $storeName) {
                $store = Store::create([
                    'branch_id' => $branch->id,
                    'code' => $branchData['code'].sprintf('%03d', $idx + 1),
                    'name' => $storeName,
                    'owner_name' => '점주'.($idx + 1),
                    'phone' => '010-'.rand(1000, 9999).'-'.rand(1000, 9999),
                    'address' => $storeName.' 주소',
                    'status' => 'active',
                    'opened_at' => now()->subMonths(rand(6, 24)),
                ]);

                // 매장 관리자 계정
                $storeUser = User::create([
                    'name' => $store->owner_name,
                    'email' => strtolower($store->code).'@ykp.com',
                    'password' => Hash::make('password'),
                    'role' => 'store',
                    'branch_id' => $branch->id,
                    'store_id' => $store->id,
                ]);
            }
        }

        // 3. 6개월치 판매 데이터 생성
        $this->generateSalesData();

        $this->command->info('=================================');
        $this->command->info('시드 데이터 생성 완료!');
        $this->command->info('=================================');
        $this->command->info('테스트 계정:');
        $this->command->info('본사: hq@ykp.com / password');
        $this->command->info('서울지사: sel@ykp.com / password');
        $this->command->info('경기지사: ggi@ykp.com / password');
        $this->command->info('부산지사: bsn@ykp.com / password');
        $this->command->info('강남역점: sel001@ykp.com / password');
        $this->command->info('=================================');
    }

    private function generateSalesData()
    {
        $stores = Store::all();
        $startDate = now()->subMonths(6);
        $endDate = now();

        $totalDays = 180;
        $totalRecords = 0;

        $this->command->info('판매 데이터 생성 시작...');

        foreach ($stores as $store) {
            $currentDate = $startDate->copy();

            while ($currentDate <= $endDate) {
                // 주말은 판매가 많고, 평일은 적게
                $isWeekend = $currentDate->isWeekend();
                $dailySales = $isWeekend ? rand(8, 15) : rand(3, 8);

                for ($i = 0; $i < $dailySales; $i++) {
                    $basePrice = rand(50000, 150000);
                    $verbal1 = rand(10000, 50000);
                    $verbal2 = rand(5000, 30000);
                    $gradeAmount = rand(0, 20000);
                    $additionalAmount = rand(0, 15000);

                    $rebateTotal = $basePrice + $verbal1 + $verbal2 + $gradeAmount + $additionalAmount;

                    $cashActivation = rand(0, 30000);
                    $usimFee = rand(0, 10000);
                    $newMnpDiscount = rand(0, 50000);
                    $deduction = rand(0, 20000);

                    $settlementAmount = $rebateTotal - $cashActivation + $usimFee + $newMnpDiscount + $deduction;
                    $tax = round($settlementAmount * 0.10);
                    $marginBeforeTax = $settlementAmount - $tax;

                    $cashReceived = rand(0, 100000);
                    $payback = rand(0, 50000);
                    $marginAfterTax = $marginBeforeTax + $cashReceived + $payback;

                    Sale::create([
                        'store_id' => $store->id,
                        'branch_id' => $store->branch_id,
                        'sale_date' => $currentDate->format('Y-m-d'),
                        'carrier' => $this->carriers[array_rand($this->carriers)],
                        'activation_type' => $this->activationTypes[array_rand($this->activationTypes)],
                        'model_name' => $this->models[array_rand($this->models)],
                        'base_price' => $basePrice,
                        'verbal1' => $verbal1,
                        'verbal2' => $verbal2,
                        'grade_amount' => $gradeAmount,
                        'additional_amount' => $additionalAmount,
                        'rebate_total' => $rebateTotal,
                        'cash_activation' => $cashActivation,
                        'usim_fee' => $usimFee,
                        'new_mnp_discount' => $newMnpDiscount,
                        'deduction' => $deduction,
                        'settlement_amount' => $settlementAmount,
                        'tax' => $tax,
                        'margin_before_tax' => $marginBeforeTax,
                        'cash_received' => $cashReceived,
                        'payback' => $payback,
                        'margin_after_tax' => $marginAfterTax,
                        'monthly_fee' => rand(30000, 120000),
                        'phone_number' => '010-'.rand(1000, 9999).'-'.rand(1000, 9999),
                        'salesperson' => $this->dealers[array_rand($this->dealers)],
                        'memo' => '',
                    ]);

                    $totalRecords++;
                }

                $currentDate->addDay();
            }
        }

        $this->command->info("총 {$totalRecords}개의 판매 데이터 생성 완료!");
    }
}
