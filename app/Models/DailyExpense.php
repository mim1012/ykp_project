<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DailyExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'expense_date',
        'dealer_code',
        'category',
        'description',
        'amount',
        'payment_method',
        'receipt_number',
        'approved_by',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
    ];

    public function dealerProfile(): BelongsTo
    {
        return $this->belongsTo(DealerProfile::class, 'dealer_code', 'dealer_code');
    }

    public function scopeByDealer($query, string $dealerCode)
    {
        return $query->where('dealer_code', $dealerCode);
    }

    public function scopeByCategory($query, string $category)
    {
        return $query->where('category', $category);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('expense_date', [$startDate, $endDate]);
    }
}
