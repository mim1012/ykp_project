<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Make sure we have branches and stores first
        if (Branch::count() == 0 || Store::count() == 0) {
            $this->call([
                InitialDataSeeder::class,
            ]);
        }

        $branches = Branch::all();
        $stores = Store::all();

        // Create test users with different roles
        $users = [
            [
                'name' => '본사 관리자',
                'email' => 'admin@ykp.com',
                'password' => Hash::make('password'),
                'role' => 'headquarters',
                'branch_id' => null,
                'store_id' => null,
            ],
            [
                'name' => '서울지사 관리자',
                'email' => 'branch@ykp.com',
                'password' => Hash::make('password'),
                'role' => 'branch',
                'branch_id' => $branches->where('name', '서울지사')->first()?->id ?? $branches->first()?->id,
                'store_id' => null,
            ],
            [
                'name' => '강남점 점장',
                'email' => 'store@ykp.com',
                'password' => Hash::make('password'),
                'role' => 'store',
                'branch_id' => $branches->where('name', '서울지사')->first()?->id ?? $branches->first()?->id,
                'store_id' => $stores->where('name', '강남점')->first()?->id ?? $stores->first()?->id,
            ],
        ];

        // Additional branch managers
        foreach ($branches as $branch) {
            if ($branch->name !== '서울지사') { // Skip already created Seoul branch manager
                User::updateOrCreate(
                    ['email' => strtolower(str_replace('지사', '', $branch->name)).'@ykp.com'],
                    [
                        'name' => $branch->name.' 관리자',
                        'password' => Hash::make('password'),
                        'role' => 'branch',
                        'branch_id' => $branch->id,
                        'store_id' => null,
                    ]
                );
            }
        }

        // Additional store managers
        $storeManagers = [
            ['name' => '판교점', 'email' => 'pangyo@ykp.com'],
            ['name' => '송도점', 'email' => 'songdo@ykp.com'],
            ['name' => '해운대점', 'email' => 'haeundae@ykp.com'],
            ['name' => '동성로점', 'email' => 'dongseong@ykp.com'],
        ];

        foreach ($storeManagers as $manager) {
            $store = $stores->where('name', $manager['name'])->first();
            if ($store) {
                User::updateOrCreate(
                    ['email' => $manager['email']],
                    [
                        'name' => $manager['name'].' 점장',
                        'password' => Hash::make('password'),
                        'role' => 'store',
                        'branch_id' => $store->branch_id,
                        'store_id' => $store->id,
                    ]
                );
            }
        }

        // Create main test users
        foreach ($users as $userData) {
            User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }

        $this->command->info('Test users created successfully!');
        $this->command->info('Login credentials:');
        $this->command->info('본사 관리자: admin@ykp.com / password');
        $this->command->info('지사 관리자: branch@ykp.com / password');
        $this->command->info('매장 점장: store@ykp.com / password');
    }
}
