<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Store extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'code',
        'name',
        'owner_name',
        'phone',
        'address',
        'status',
        'opened_at',
    ];

    protected $casts = [
        'opened_at' => 'datetime',  // timestamp로 변경 (date가 아닌 datetime)
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
