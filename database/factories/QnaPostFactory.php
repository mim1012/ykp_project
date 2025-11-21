<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\QnaPost;
use App\Models\Store;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class QnaPostFactory extends Factory
{
    protected $model = QnaPost::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'content' => $this->faker->paragraphs(3, true),
            'author_user_id' => User::factory(),
            'author_role' => 'store',
            'store_id' => Store::factory(),
            'branch_id' => Branch::factory(),
            'is_private' => $this->faker->boolean(30), // 30% chance of being private
            'status' => $this->faker->randomElement(['pending', 'answered', 'closed']),
            'view_count' => $this->faker->numberBetween(0, 100),
        ];
    }

    public function pending()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    public function answered()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'answered',
        ]);
    }

    public function closed()
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
        ]);
    }

    public function private()
    {
        return $this->state(fn (array $attributes) => [
            'is_private' => true,
        ]);
    }

    public function public()
    {
        return $this->state(fn (array $attributes) => [
            'is_private' => false,
        ]);
    }
}
