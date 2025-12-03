<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Sale;
use App\Models\Store;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $basePrice = $this->faker->randomFloat(2, 50000, 2000000);
        $verbal1 = $this->faker->randomFloat(2, 0, 100000);
        $verbal2 = $this->faker->randomFloat(2, 0, 50000);
        $gradeAmount = $this->faker->randomFloat(2, 0, 30000);
        $additionalAmount = $this->faker->randomFloat(2, 0, 20000);

        $rebateTotal = $basePrice + $verbal1 + $verbal2 + $gradeAmount + $additionalAmount;
        $settlementAmount = $rebateTotal;
        $tax = round($settlementAmount * 0.10, 2);
        $marginAfterTax = $settlementAmount - $tax;

        return [
            'store_id' => Store::factory(),
            'branch_id' => Branch::factory(),
            'sale_date' => $this->faker->dateTimeBetween('-1 year', 'now')->format('Y-m-d'),
            'carrier' => $this->faker->randomElement(['SK', 'KT', 'LG', 'MVNO']),
            'activation_type' => $this->faker->randomElement(['신규', '기변', '번이', '유선', '2nd']),
            'model_name' => $this->faker->randomElement([
                'iPhone 15', 'iPhone 15 Pro', 'Galaxy S24', 'Galaxy S24 Ultra',
                'Galaxy A54', 'iPhone 14', 'Galaxy Z Fold5', 'Galaxy Z Flip5',
            ]),
            'base_price' => $basePrice,
            'verbal1' => $verbal1,
            'verbal2' => $verbal2,
            'grade_amount' => $gradeAmount,
            'additional_amount' => $additionalAmount,
            'rebate_total' => $rebateTotal,
            'cash_activation' => $this->faker->randomFloat(2, 0, 100000),
            'usim_fee' => $this->faker->randomFloat(2, 0, 30000),
            'new_mnp_discount' => $this->faker->randomFloat(2, -50000, 0),
            'deduction' => $this->faker->randomFloat(2, 0, 20000),
            'settlement_amount' => $settlementAmount,
            'tax' => $tax,
            'margin_before_tax' => $settlementAmount - $tax,
            'cash_received' => $this->faker->randomFloat(2, 0, 50000),
            'payback' => $this->faker->randomFloat(2, 0, 30000),
            'margin_after_tax' => $marginAfterTax,
            'monthly_fee' => $this->faker->randomFloat(2, 30000, 150000),
            'phone_number' => $this->faker->phoneNumber(),
            'salesperson' => $this->faker->name(),
            'memo' => $this->faker->optional()->sentence(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
