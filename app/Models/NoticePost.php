<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class NoticePost extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'author_user_id',
        'target_audience',
        'target_branch_ids',
        'target_store_ids',
        'is_pinned',
        'priority',
        'published_at',
        'expires_at',
        'view_count',
    ];

    protected $casts = [
        'target_branch_ids' => 'array',
        'target_store_ids' => 'array',
        'is_pinned' => 'boolean',
        'published_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    // Relationships
    public function author()
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function views()
    {
        return $this->morphMany(BoardView::class, 'viewable');
    }

    public function images()
    {
        return $this->morphMany(PostImage::class, 'imageable');
    }

    // Scopes
    /**
     * Scope: Only published notices
     */
    public function scopePublished($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('published_at')
              ->orWhere('published_at', '<=', now());
        });
    }

    /**
     * Scope: Only active (not expired) notices
     */
    public function scopeActive($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Scope: Filter notices visible to the given user
     */
    public function scopeVisibleTo($query, User $user)
    {
        // 본사는 모든 공지를 볼 수 있음
        if ($user->isHeadquarters()) {
            return $query;
        }

        return $query->where(function ($q) use ($user) {
            // 전체 대상 공지
            $q->where('target_audience', 'all');

            // 지사 사용자: 자기 지사 대상 공지 + 자기 지사가 작성한 공지
            if ($user->isBranch() && $user->branch_id) {
                $q->orWhere(function ($subQ) use ($user) {
                    $subQ->where('target_audience', 'branches')
                         ->whereJsonContains('target_branch_ids', $user->branch_id);
                });

                // 지사가 자기 소속 매장에게 보낸 공지도 볼 수 있음
                $q->orWhere(function ($subQ) use ($user) {
                    $subQ->where('target_audience', 'stores')
                         ->whereHas('author', function ($authorQ) use ($user) {
                             $authorQ->where('branch_id', $user->branch_id);
                         });
                });
            }

            // 매장 대상 공지
            if ($user->store_id) {
                $q->orWhere(function ($subQ) use ($user) {
                    $subQ->where('target_audience', 'stores')
                         ->whereJsonContains('target_store_ids', $user->store_id);
                });
            }

            // 특정 대상 공지 (specific)
            if ($user->branch_id || $user->store_id) {
                $q->orWhere(function ($subQ) use ($user) {
                    $subQ->where('target_audience', 'specific');

                    if ($user->branch_id) {
                        $subQ->whereJsonContains('target_branch_ids', $user->branch_id);
                    }

                    if ($user->store_id) {
                        $subQ->whereJsonContains('target_store_ids', $user->store_id);
                    }
                });
            }
        });
    }

    /**
     * Scope: Pinned notices first, then by priority and date
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('is_pinned', 'desc')
                     ->orderBy('priority', 'desc')
                     ->orderBy('published_at', 'desc');
    }

    // Methods
    /**
     * Check if this notice is targeted to the given user
     */
    public function isTargetedTo(User $user): bool
    {
        // 전체 대상
        if ($this->target_audience === 'all') {
            return true;
        }

        // 지사 대상
        if ($this->target_audience === 'branches' && $user->branch_id) {
            return in_array($user->branch_id, $this->target_branch_ids ?? []);
        }

        // 매장 대상
        if ($this->target_audience === 'stores' && $user->store_id) {
            return in_array($user->store_id, $this->target_store_ids ?? []);
        }

        // 특정 대상
        if ($this->target_audience === 'specific') {
            $targetedByBranch = $user->branch_id && in_array($user->branch_id, $this->target_branch_ids ?? []);
            $targetedByStore = $user->store_id && in_array($user->store_id, $this->target_store_ids ?? []);

            return $targetedByBranch || $targetedByStore;
        }

        return false;
    }

    /**
     * Check if notice is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    /**
     * Check if notice is published
     */
    public function isPublished(): bool
    {
        return !$this->published_at || $this->published_at->isPast();
    }

    /**
     * Increment view count
     */
    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    /**
     * Toggle pin status
     */
    public function togglePin(): void
    {
        $this->update(['is_pinned' => !$this->is_pinned]);
    }
}
