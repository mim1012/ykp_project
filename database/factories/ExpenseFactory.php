<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'expense_date' => $this->faker->dateTimeBetween('-30 days', 'now')->format('Y-m-d'),
            'description' => $this->faker->randomElement([
                '매장 임대료',
                '전기세',
                '수도세',
                '인터넷 통신비',
                '비품 구입',
                '청소용품 구입',
                '사무용품 구입',
                '간식 구입',
                '수리 비용',
                '기타 지출',
            ]),
            'amount' => $this->faker->numberBetween(10000, 2000000),
            'created_by' => null,
        ];
    }
}
