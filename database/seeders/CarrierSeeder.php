<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\Carrier;

class CarrierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $carriers = [
            ['code' => 'SK', 'name' => 'SK', 'is_active' => true, 'sort_order' => 1],
            ['code' => 'KT', 'name' => 'KT', 'is_active' => true, 'sort_order' => 2],
            ['code' => 'LG', 'name' => 'LG', 'is_active' => true, 'sort_order' => 3],
            ['code' => 'MVNO', 'name' => '알뜰', 'is_active' => true, 'sort_order' => 4],
        ];

        foreach ($carriers as $carrier) {
            Carrier::firstOrCreate(
                ['code' => $carrier['code']],
                $carrier
            );
        }
    }
}