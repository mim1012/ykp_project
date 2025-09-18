<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payroll extends Model
{
    use HasFactory;

    protected $fillable = [
        'year_month',
        'dealer_code',
        'employee_id',
        'employee_name',
        'position',
        'base_salary',
        'incentive_amount',
        'bonus_amount',
        'deduction_amount',
        'total_salary',
        'payment_date',
        'payment_status',
        'memo',
    ];

    protected $casts = [
        'base_salary' => 'decimal:2',
        'incentive_amount' => 'decimal:2',
        'bonus_amount' => 'decimal:2',
        'deduction_amount' => 'decimal:2',
        'total_salary' => 'decimal:2',
        'payment_date' => 'date',
    ];

    public function dealerProfile(): BelongsTo
    {
        return $this->belongsTo(DealerProfile::class, 'dealer_code', 'dealer_code');
    }

    public function scopeByYearMonth($query, string $yearMonth)
    {
        return $query->where('year_month', $yearMonth);
    }

    public function scopeByDealer($query, string $dealerCode)
    {
        return $query->where('dealer_code', $dealerCode);
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }
}
