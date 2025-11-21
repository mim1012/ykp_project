<?php

namespace Database\Factories;

use App\Models\QnaPost;
use App\Models\QnaReply;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class QnaReplyFactory extends Factory
{
    protected $model = QnaReply::class;

    public function definition(): array
    {
        return [
            'qna_post_id' => QnaPost::factory(),
            'author_user_id' => User::factory(),
            'content' => $this->faker->paragraphs(2, true),
            'is_official_answer' => false,
        ];
    }

    public function official()
    {
        return $this->state(fn (array $attributes) => [
            'is_official_answer' => true,
        ]);
    }
}
