<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class HeadquartersUserSeeder extends Seeder
{
    /**
     * 초기 본사 관리자 계정 생성
     */
    public function run(): void
    {
        // 기존 본사 계정이 없는 경우에만 생성
        if (!User::where('role', 'headquarters')->exists()) {
            User::create([
                'name' => '본사 관리자',
                'email' => 'admin@ykp.com',
                'password' => Hash::make('admin123!'),
                'role' => 'headquarters',
                'branch_id' => null,
                'store_id' => null,
                'is_active' => true,
                'email_verified_at' => now(),
            ]);

            $this->command->info('본사 관리자 계정이 생성되었습니다.');
            $this->command->info('이메일: admin@ykp.com');
            $this->command->info('비밀번호: admin123!');
        } else {
            $this->command->info('본사 관리자 계정이 이미 존재합니다.');
        }
    }
}