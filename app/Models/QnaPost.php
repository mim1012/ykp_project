<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QnaPost extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'content',
        'author_user_id',
        'author_role',
        'store_id',
        'branch_id',
        'is_private',
        'status',
        'view_count',
    ];

    protected $casts = [
        'is_private' => 'boolean',
    ];

    // Relationships
    public function author()
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function replies()
    {
        return $this->hasMany(QnaReply::class)->orderBy('created_at', 'asc');
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
     * Scope: Filter posts visible to the given user based on RBAC
     */
    public function scopeVisibleTo($query, User $user)
    {
        // 본사는 모든 Q&A 조회 가능
        if ($user->isHeadquarters()) {
            return $query;
        }

        // 지사 권한
        if ($user->isBranch()) {
            return $query->where(function ($q) use ($user) {
                // 일반글: 지사 관련 글
                $q->where('is_private', false)
                  ->where('branch_id', $user->branch_id);

                // 비밀글: 지사 관련 비밀글도 볼 수 있음
                $q->orWhere(function ($subQ) use ($user) {
                    $subQ->where('is_private', true)
                         ->where('branch_id', $user->branch_id);
                });
            });
        }

        // 매장 권한: 자기가 작성한 글만
        if ($user->isStore()) {
            return $query->where('author_user_id', $user->id);
        }

        return $query->whereRaw('1 = 0'); // 권한 없음
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeAnswered($query)
    {
        return $query->where('status', 'answered');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    // Methods
    /**
     * Check if user can view this post
     */
    public function canView(User $user): bool
    {
        // 본사는 모든 글 볼 수 있음
        if ($user->isHeadquarters()) {
            return true;
        }

        // 작성자는 항상 볼 수 있음
        if ($this->author_user_id === $user->id) {
            return true;
        }

        // 비밀글인 경우
        if ($this->is_private) {
            // 지사는 자기 지사 매장의 비밀글 볼 수 있음
            if ($user->isBranch() && $this->branch_id === $user->branch_id) {
                return true;
            }

            return false;
        }

        // 일반글은 지사/본사 모두 볼 수 있음
        return true;
    }

    /**
     * Check if user can reply to this post
     */
    public function canReply(User $user): bool
    {
        // 종료된 글에는 답변 불가
        if ($this->status === 'closed') {
            return false;
        }

        // 본사는 모든 글에 답변 가능
        if ($user->isHeadquarters()) {
            return true;
        }

        // 지사는 자기 지사 관련 글에만 답변 가능
        if ($user->isBranch() && $this->branch_id === $user->branch_id) {
            return true;
        }

        // 작성자도 자기 글에 답변(추가 설명) 가능
        if ($this->author_user_id === $user->id) {
            return true;
        }

        return false;
    }

    /**
     * Increment view count
     */
    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }
}
