<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Models\DealerProfile;

return new class extends Migration
{
    public function up(): void
    {
        // 필요한 대리점 목록: sm, w, 더킹, 엔터, 유피, 초시대, 태성, 피디엠, 한주, 해피
        $newDealers = [
            [
                'dealer_code' => 'SM',
                'dealer_name' => 'SM',
                'contact_person' => 'SM 담당자',
                'phone' => '02-1111-2222',
                'address' => '서울시 중구',
                'default_sim_fee' => 0,
                'default_mnp_discount' => 800,
                'tax_rate' => 0.10,
                'default_payback_rate' => 2.00,
                'status' => 'active',
                'activated_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'dealer_code' => 'W',
                'dealer_name' => 'W',
                'contact_person' => 'W 담당자',
                'phone' => '02-2222-3333',
                'address' => '서울시 강남구',
                'default_sim_fee' => 0,
                'default_mnp_discount' => 800,
                'tax_rate' => 0.10,
                'default_payback_rate' => 1.80,
                'status' => 'active',
                'activated_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'dealer_code' => 'KING',
                'dealer_name' => '더킹',
                'contact_person' => '더킹 담당자',
                'phone' => '02-3333-4444',
                'address' => '서울시 서초구',
                'default_sim_fee' => 0,
                'default_mnp_discount' => 800,
                'tax_rate' => 0.10,
                'default_payback_rate' => 2.50,
                'status' => 'active',
                'activated_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'dealer_code' => 'ENTER',
                'dealer_name' => '엔터',
                'contact_person' => '엔터 담당자',
                'phone' => '02-4444-5555',
                'address' => '서울시 마포구',
                'default_sim_fee' => 0,
                'default_mnp_discount' => 800,
                'tax_rate' => 0.10,
                'default_payback_rate' => 2.20,
                'status' => 'active',
                'activated_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'dealer_code' => 'UP',
                'dealer_name' => '유피',
                'contact_person' => '유피 담당자',
                'phone' => '02-5555-6666',
                'address' => '서울시 용산구',
                'default_sim_fee' => 0,
                'default_mnp_discount' => 800,
                'tax_rate' => 0.10,
                'default_payback_rate' => 1.90,
                'status' => 'active',
                'activated_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'dealer_code' => 'TAESUNG',
                'dealer_name' => '태성',
                'contact_person' => '태성 담당자',
                'phone' => '02-6666-7777',
                'address' => '서울시 송파구',
                'default_sim_fee' => 0,
                'default_mnp_discount' => 800,
                'tax_rate' => 0.10,
                'default_payback_rate' => 2.30,
                'status' => 'active',
                'activated_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'dealer_code' => 'PDM',
                'dealer_name' => '피디엠',
                'contact_person' => '피디엠 담당자',
                'phone' => '02-7777-8888',
                'address' => '서울시 영등포구',
                'default_sim_fee' => 0,
                'default_mnp_discount' => 800,
                'tax_rate' => 0.10,
                'default_payback_rate' => 2.10,
                'status' => 'active',
                'activated_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'dealer_code' => 'HANJU',
                'dealer_name' => '한주',
                'contact_person' => '한주 담당자',
                'phone' => '02-8888-9999',
                'address' => '경기도 안양시',
                'default_sim_fee' => 0,
                'default_mnp_discount' => 800,
                'tax_rate' => 0.10,
                'default_payback_rate' => 1.95,
                'status' => 'active',
                'activated_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'dealer_code' => 'HAPPY',
                'dealer_name' => '해피',
                'contact_person' => '해피 담당자',
                'phone' => '02-9999-0000',
                'address' => '인천시 부평구',
                'default_sim_fee' => 0,
                'default_mnp_discount' => 800,
                'tax_rate' => 0.10,
                'default_payback_rate' => 2.40,
                'status' => 'active',
                'activated_at' => now(),
                'created_at' => now(),
                'updated_at' => now()
            ]
        ];

        // 중복 방지를 위한 체크 후 삽입
        foreach ($newDealers as $dealer) {
            // 이미 존재하는지 확인
            $existing = DealerProfile::where('dealer_code', $dealer['dealer_code'])->first();

            if (!$existing) {
                DealerProfile::create($dealer);
                echo "✅ 대리점 추가: {$dealer['dealer_name']} ({$dealer['dealer_code']})\n";
            } else {
                echo "ℹ️ 이미 존재: {$dealer['dealer_name']} ({$dealer['dealer_code']})\n";
            }
        }
    }

    public function down(): void
    {
        // 추가한 대리점들 제거
        $dealerCodes = ['SM', 'W', 'KING', 'ENTER', 'UP', 'TAESUNG', 'PDM', 'HANJU', 'HAPPY'];

        DealerProfile::whereIn('dealer_code', $dealerCodes)->delete();
    }
};