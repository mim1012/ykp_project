<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Branch;
use App\Models\Store;
use Illuminate\Support\Facades\Hash;

class LocalTestSeeder extends Seeder
{
    public function run()
    {
        // 1. 지사 생성
        $branch1 = Branch::create([
            'name' => '서울지사',
            'code' => 'SEL',
            'status' => 'active'
        ]);

        $branch2 = Branch::create([
            'name' => '경기지사',
            'code' => 'GYG',
            'status' => 'active'
        ]);

        // 2. 매장 생성
        $store1 = Store::create([
            'name' => '강남점',
            'code' => 'GN001',
            'branch_id' => $branch1->id,
            'address' => '서울시 강남구',
            'phone' => '02-1234-5678',
            'status' => 'active'
        ]);

        $store2 = Store::create([
            'name' => '홍대점',
            'code' => 'HD001',
            'branch_id' => $branch1->id,
            'address' => '서울시 마포구',
            'phone' => '02-2345-6789',
            'status' => 'active'
        ]);

        $store3 = Store::create([
            'name' => '분당점',
            'code' => 'BD001',
            'branch_id' => $branch2->id,
            'address' => '경기도 성남시 분당구',
            'phone' => '031-1234-5678',
            'status' => 'active'
        ]);

        // 3. 사용자 계정 생성

        // 본사 관리자
        User::create([
            'name' => '본사관리자',
            'email' => 'hq@test.com',
            'password' => Hash::make('password'),
            'role' => 'headquarters',
            'is_active' => true
        ]);

        // 지사 관리자
        User::create([
            'name' => '서울지사장',
            'email' => 'seoul@test.com',
            'password' => Hash::make('password'),
            'role' => 'branch',
            'branch_id' => $branch1->id,
            'is_active' => true
        ]);

        User::create([
            'name' => '경기지사장',
            'email' => 'gyeonggi@test.com',
            'password' => Hash::make('password'),
            'role' => 'branch',
            'branch_id' => $branch2->id,
            'is_active' => true
        ]);

        // 매장 직원
        User::create([
            'name' => '강남점직원',
            'email' => 'gangnam@test.com',
            'password' => Hash::make('password'),
            'role' => 'store',
            'store_id' => $store1->id,
            'branch_id' => $branch1->id,
            'is_active' => true
        ]);

        User::create([
            'name' => '홍대점직원',
            'email' => 'hongdae@test.com',
            'password' => Hash::make('password'),
            'role' => 'store',
            'store_id' => $store2->id,
            'branch_id' => $branch1->id,
            'is_active' => true
        ]);

        User::create([
            'name' => '분당점직원',
            'email' => 'bundang@test.com',
            'password' => Hash::make('password'),
            'role' => 'store',
            'store_id' => $store3->id,
            'branch_id' => $branch2->id,
            'is_active' => true
        ]);

        echo "테스트 데이터 생성 완료!\n\n";
        echo "=== 로그인 계정 정보 ===\n";
        echo "본사: hq@test.com / password\n";
        echo "서울지사: seoul@test.com / password\n";
        echo "경기지사: gyeonggi@test.com / password\n";
        echo "강남점: gangnam@test.com / password\n";
        echo "홍대점: hongdae@test.com / password\n";
        echo "분당점: bundang@test.com / password\n";
    }
}