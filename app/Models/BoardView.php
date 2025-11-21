<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BoardView extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'viewable_type',
        'viewable_id',
        'user_id',
        'viewed_at',
    ];

    protected $casts = [
        'viewed_at' => 'datetime',
    ];

    // Relationships
    public function viewable()
    {
        return $this->morphTo();
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    // Static Methods
    /**
     * Record a view for a post by a user
     */
    public static function recordView($viewable, User $user): void
    {
        static::updateOrCreate(
            [
                'viewable_type' => get_class($viewable),
                'viewable_id' => $viewable->id,
                'user_id' => $user->id,
            ],
            [
                'viewed_at' => now(),
            ]
        );
    }

    /**
     * Check if user has viewed this post
     */
    public static function hasViewed($viewable, User $user): bool
    {
        return static::where('viewable_type', get_class($viewable))
            ->where('viewable_id', $viewable->id)
            ->where('user_id', $user->id)
            ->exists();
    }
}
