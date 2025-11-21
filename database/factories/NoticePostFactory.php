<?php

namespace Database\Factories;

use App\Models\NoticePost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class NoticePostFactory extends Factory
{
    protected $model = NoticePost::class;

    public function definition(): array
    {
        return [
            'title' => $this->faker->sentence(),
            'content' => $this->faker->paragraphs(3, true),
            'author_user_id' => User::factory(),
            'target_audience' => 'all',
            'target_branch_ids' => null,
            'target_store_ids' => null,
            'is_pinned' => false,
            'priority' => $this->faker->numberBetween(0, 10),
            'published_at' => now(),
            'expires_at' => null,
            'view_count' => $this->faker->numberBetween(0, 100),
        ];
    }

    public function forAll()
    {
        return $this->state(fn (array $attributes) => [
            'target_audience' => 'all',
            'target_branch_ids' => null,
            'target_store_ids' => null,
        ]);
    }

    public function forBranches(array $branchIds)
    {
        return $this->state(fn (array $attributes) => [
            'target_audience' => 'branches',
            'target_branch_ids' => $branchIds,
            'target_store_ids' => null,
        ]);
    }

    public function forStores(array $storeIds)
    {
        return $this->state(fn (array $attributes) => [
            'target_audience' => 'stores',
            'target_branch_ids' => null,
            'target_store_ids' => $storeIds,
        ]);
    }

    public function pinned()
    {
        return $this->state(fn (array $attributes) => [
            'is_pinned' => true,
            'priority' => 100,
        ]);
    }

    public function expired()
    {
        return $this->state(fn (array $attributes) => [
            'expires_at' => now()->subDays(1),
        ]);
    }

    public function future()
    {
        return $this->state(fn (array $attributes) => [
            'published_at' => now()->addDays(1),
        ]);
    }
}
