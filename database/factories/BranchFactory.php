<?php

namespace Database\Factories;

use App\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper($this->faker->unique()->lexify('??')),
            'name' => $this->faker->city().'지사',
            'address' => $this->faker->address(),
            'phone' => $this->faker->phoneNumber(),
            'manager_name' => $this->faker->name(),
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }
}
