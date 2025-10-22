<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Helpers\DatabaseHelper;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'must_change_password',
        'role',
        'branch_id',
        'store_id',
        'is_active',
        'last_login_at',
        'created_by_user_id',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',  // PostgreSQL boolean 타입 호환
            'must_change_password' => 'boolean',
        ];
    }

    // 관계 정의
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    // 권한 체크 메서드
    public function isHeadquarters()
    {
        return $this->role === 'headquarters';
    }

    public function isBranch()
    {
        return $this->role === 'branch';
    }

    public function isStore()
    {
        return $this->role === 'store';
    }

    public function isDeveloper()
    {
        return $this->role === 'developer';
    }

    public function isSuperUser()
    {
        return $this->isDeveloper() || $this->isHeadquarters();
    }

    // 접근 가능한 매장 ID 목록
    public function getAccessibleStoreIds()
    {
        if ($this->isDeveloper() || $this->isHeadquarters()) {
            return Store::pluck('id')->toArray(); // 전체 접근
        } elseif ($this->isBranch()) {
            return Store::where('branch_id', $this->branch_id)->pluck('id')->toArray();
        } else {
            return [$this->store_id];
        }
    }

    // 사용자 상태 관리 메서드
    public function isActive(): bool
    {
        return $this->is_active ?? true;
    }

    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    public function deactivate(): bool
    {
        return $this->update(['is_active' => false]);
    }

    public function updateLastLogin(): bool
    {
        return $this->update(['last_login_at' => now()]);
    }

    // 생성자 관계
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function createdUsers()
    {
        return $this->hasMany(User::class, 'created_by_user_id');
    }

    // 활성화된 사용자만 조회하는 스코프
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // 역할별 사용자 조회 스코프
    public function scopeByRole($query, $role)
    {
        return $query->where('role', $role);
    }

    // Railway PostgreSQL 호환 사용자 조회 메서드 (인증용)
    public static function findForAuth($id)
    {
        return DatabaseHelper::executeWithRetry(function () use ($id) {
            return static::find($id);
        });
    }

    // Railway PostgreSQL 호환 이메일 조회 메서드 (인증용)
    public static function findByEmailForAuth($email)
    {
        return DatabaseHelper::executeWithRetry(function () use ($email) {
            return static::where('email', $email)->first();
        });
    }
}
