<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FixedExpense extends Model
{
    use HasFactory;

    protected $fillable = [
        'year_month',
        'dealer_code',
        'expense_type',
        'description',
        'amount',
        'due_date',
        'payment_date',
        'payment_status',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'due_date' => 'date',
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

    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    public function scopeOverdue($query)
    {
        return $query->where('payment_status', 'overdue')
            ->orWhere(function ($q) {
                $q->where('payment_status', 'pending')
                    ->where('due_date', '<', now()->toDateString());
            });
    }

    public function markAsPaid($paymentDate = null)
    {
        $this->update([
            'payment_status' => 'paid',
            'payment_date' => $paymentDate ?? now()->toDateString(),
        ]);
    }

    public function isOverdue(): bool
    {
        return $this->payment_status === 'pending' &&
               $this->due_date &&
               $this->due_date->isPast();
    }
}
