<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    public function definition(): array
    {
        return [
            'store_id' => Store::factory(),
            'branch_id' => Branch::factory(),
            'phone_number' => $this->faker->unique()->numerify('010-####-####'),
            'customer_name' => $this->faker->name(),
            'birth_date' => $this->faker->date('Y-m-d', '-20 years'),
            'current_device' => $this->faker->randomElement([
                '갤럭시 S23',
                '아이폰 14',
                '갤럭시 Z플립5',
                '아이폰 15 Pro',
                'LG 벨벳',
            ]),
            'customer_type' => 'prospect',
            'activated_sale_id' => null,
            'first_visit_date' => $this->faker->date('Y-m-d', '-30 days'),
            'last_contact_date' => $this->faker->optional()->date('Y-m-d', '-7 days'),
            'notes' => $this->faker->optional()->sentence(),
            'status' => 'active',
            'created_by' => null,
        ];
    }

    /**
     * Indicate that the customer is activated (linked to a sale)
     */
    public function activated()
    {
        return $this->state(function (array $attributes) {
            return [
                'customer_type' => 'activated',
                'status' => 'converted',
            ];
        });
    }

    /**
     * Indicate that the customer is inactive
     */
    public function inactive()
    {
        return $this->state(function (array $attributes) {
            return [
                'status' => 'inactive',
            ];
        });
    }
}
