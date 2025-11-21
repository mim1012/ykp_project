<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

class GoalFactory extends Factory
{
    protected $model = Goal::class;

    public function definition(): array
    {
        $targetMonth = $this->faker->dateTimeBetween('-6 months', '+6 months')->format('Y-m-01');
        $periodStart = date('Y-m-01', strtotime($targetMonth));
        $periodEnd = date('Y-m-t', strtotime($targetMonth));

        return [
            'store_id' => Store::factory(),
            'target_month' => $targetMonth,
            'target_type' => 'store',
            'period_type' => 'monthly',
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'sales_target' => $this->faker->numberBetween(3000000, 10000000),
            'activation_target' => $this->faker->numberBetween(30, 100),
            'margin_target' => $this->faker->numberBetween(500000, 2000000),
            'created_by' => \App\Models\User::factory(),
        ];
    }
}
