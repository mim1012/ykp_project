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
    protected $connection = 'pgsql_local';

    protected $fillable = [
        'branch_id',
        'code',
        'name',
        'owner_name',
        'phone',
        'address',
        'status',
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
}
