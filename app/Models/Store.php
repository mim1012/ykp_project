<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;

    /**
     * The connection name for the model.
     *
     * @var string|null
     */
    // Connection will use default from config/database.php

    protected $fillable = [
        'branch_id',
        'code',
        'name',
        'owner_name',
        'phone',
        'address',
        'status',
        'store_type',
        'business_registration_number',
        'email',
        'opened_at',
        'metadata',
        'created_by',
    ];

    protected $casts = [
        'opened_at' => 'datetime',  // timestamp로 변경 (date가 아닌 datetime)
        'metadata' => 'array',      // JSON 컬럼을 배열로 자동 변환
        'sync_status' => 'array',   // JSON 컬럼을 배열로 자동 변환
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function customers()
    {
        return $this->hasMany(Customer::class);
    }

    public function expenses()
    {
        return $this->hasMany(Expense::class);
    }

    // Scopes
    public function scopeFranchise($query)
    {
        return $query->where('store_type', 'franchise');
    }

    public function scopeDirect($query)
    {
        return $query->where('store_type', 'direct');
    }

    // Methods
    public function canEditClassification(User $user): bool
    {
        // 본사는 모든 매장 수정 가능
        if ($user->isHeadquarters()) {
            return true;
        }

        // 지사는 자기 지사 매장만 수정 가능
        if ($user->isBranch() && $this->branch_id === $user->branch_id) {
            return true;
        }

        return false;
    }
}
