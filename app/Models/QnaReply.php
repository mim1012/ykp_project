<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QnaReply extends Model
{
    use HasFactory;

    protected $fillable = [
        'qna_post_id',
        'author_user_id',
        'content',
        'is_official_answer',
    ];

    protected $casts = [
        'is_official_answer' => 'boolean',
    ];

    // Relationships
    public function qnaPost()
    {
        return $this->belongsTo(QnaPost::class);
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    // Methods
    /**
     * Check if this reply is from headquarters (official answer)
     */
    public function isFromHeadquarters(): bool
    {
        return $this->author && $this->author->isHeadquarters();
    }

    /**
     * Mark as official answer if from headquarters
     */
    public function markAsOfficialIfFromHQ(): void
    {
        if ($this->isFromHeadquarters()) {
            $this->update(['is_official_answer' => true]);
        }
    }
}
