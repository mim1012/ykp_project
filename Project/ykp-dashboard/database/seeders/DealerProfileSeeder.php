<?php

namespace Database\Seeders;

use App\Models\DealerProfile;
use Illuminate\Database\Seeder;

class DealerProfileSeeder extends Seeder
{
    public function run(): void
    {
        $dealers = [
            [
                'dealer_code' => 'DEFAULT',
                'dealer_name' => '기본 대리점',
                'contact_person' => '관리자',
                'phone' => '02-1234-5678',
                'address' => '서울시 강남구',
                'default_sim_fee' => 0,
                'default_mnp_discount' => 800,
                'tax_rate' => 0.133,
                'default_payback_rate' => 0.00,
                'status' => 'active',
                'activated_at' => now()
            ],
            [
                'dealer_code' => 'ENT',
                'dealer_name' => '이앤티',
                'contact_person' => '김대리',
                'phone' => '02-2222-3333',
                'address' => '서울시 서초구',
                'default_sim_fee' => 0,
                'default_mnp_discount' => 800,
                'tax_rate' => 0.133,
                'default_payback_rate' => 2.50,
                'status' => 'active',
                'activated_at' => now()
            ],
            [
                'dealer_code' => 'WIN',
                'dealer_name' => '앤투윈',
                'contact_person' => '박과장',
                'phone' => '02-3333-4444',
                'address' => '서울시 종로구',
                'default_sim_fee' => 0,
                'default_mnp_discount' => 800,
                'tax_rate' => 0.133,
                'default_payback_rate' => 1.80,
                'status' => 'active',
                'activated_at' => now()
            ],
            [
                'dealer_code' => 'CHOSI',
                'dealer_name' => '초시대',
                'contact_person' => '이팀장',
                'phone' => '02-4444-5555',
                'address' => '부산시 해운대구',
                'default_sim_fee' => 0,
                'default_mnp_discount' => 800,
                'tax_rate' => 0.133,
                'default_payback_rate' => 3.00,
                'status' => 'active',
                'activated_at' => now()
            ],
            [
                'dealer_code' => 'AMT',
                'dealer_name' => '아엠티',
                'contact_person' => '최부장',
                'phone' => '031-5555-6666',
                'address' => '경기도 성남시',
                'default_sim_fee' => 0,
                'default_mnp_discount' => 800,
                'tax_rate' => 0.133,
                'default_payback_rate' => 2.20,
                'status' => 'inactive',
                'deactivated_at' => now()
            ]
        ];

        foreach ($dealers as $dealer) {
            DealerProfile::create($dealer);
        }
        
        echo "DealerProfile 시더 실행 완료: " . count($dealers) . "개 대리점 생성\n";
    }
}