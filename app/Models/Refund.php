<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'refund_date',
        'dealer_code',
        'activation_id',
        'customer_name',
        'customer_phone',
        'refund_reason',
        'refund_type',
        'original_amount',
        'refund_amount',
        'penalty_amount',
        'refund_method',
        'processed_by',
        'memo',
    ];

    protected $casts = [
        'refund_date' => 'date',
        'original_amount' => 'decimal:2',
        'refund_amount' => 'decimal:2',
        'penalty_amount' => 'decimal:2',
    ];

    public function dealerProfile(): BelongsTo
    {
        return $this->belongsTo(DealerProfile::class, 'dealer_code', 'dealer_code');
    }

    public function originalSale(): BelongsTo
    {
        return $this->belongsTo(Sale::class, 'activation_id');
    }

    public function scopeByDealer($query, string $dealerCode)
    {
        return $query->where('dealer_code', $dealerCode);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('refund_date', [$startDate, $endDate]);
    }

    public function scopeByReason($query, string $reason)
    {
        return $query->where('refund_reason', $reason);
    }

    public function getRefundRate(): float
    {
        if ($this->original_amount == 0) {
            return 0;
        }

        return round(($this->refund_amount / $this->original_amount) * 100, 2);
    }

    public function getNetLoss(): float
    {
        return $this->refund_amount - $this->penalty_amount;
    }
}
