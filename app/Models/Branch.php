<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Branch extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'manager_name',
        'phone',
        'address',
        'status',
    ];

    public function stores()
    {
        return $this->hasMany(Store::class);
    }

    public function sales()
    {
        return $this->hasMany(Sale::class);
    }
}
