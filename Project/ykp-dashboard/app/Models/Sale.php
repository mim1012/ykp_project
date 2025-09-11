<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sale extends Model
{
    use HasFactory;

    protected $fillable = [
        'dealer_code',
        'store_id',
        'branch_id',
        'sale_date',
        'carrier',
        'dealer_name', // PM 요구사항: 대리점명 추가
        'activation_type',
        'model_name',
        'serial_number', // PM 요구사항: 일련번호 추가
        'base_price',
        'verbal1',
        'verbal2',
        'grade_amount',
        'additional_amount',
        'rebate_total',
        'cash_activation',
        'usim_fee',
        'new_mnp_discount',
        'deduction',
        'settlement_amount',
        'tax',
        'margin_before_tax',
        'cash_received',
        'payback',
        'margin_after_tax',
        'monthly_fee',
        'phone_number',
        'customer_name', // PM 요구사항: 고객명 추가
        'customer_birth_date', // PM 요구사항: 생년월일 추가
        'salesperson',
        'memo',
    ];

    protected $casts = [
        'sale_date' => 'date',
        'customer_birth_date' => 'date', // PM 요구사항: 생년월일 날짜 타입
        'base_price' => 'decimal:2',
        'verbal1' => 'decimal:2',
        'verbal2' => 'decimal:2',
        'grade_amount' => 'decimal:2',
        'additional_amount' => 'decimal:2',
        'rebate_total' => 'decimal:2',
        'cash_activation' => 'decimal:2',
        'usim_fee' => 'decimal:2',
        'new_mnp_discount' => 'decimal:2',
        'deduction' => 'decimal:2',
        'settlement_amount' => 'decimal:2',
        'tax' => 'decimal:2',
        'margin_before_tax' => 'decimal:2',
        'cash_received' => 'decimal:2',
        'payback' => 'decimal:2',
        'margin_after_tax' => 'decimal:2',
        'monthly_fee' => 'decimal:2',
    ];

    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the dealer profile that owns this sale.
     */
    public function dealerProfile(): BelongsTo
    {
        return $this->belongsTo(DealerProfile::class, 'dealer_code', 'dealer_code');
    }

    /**
     * Get the total amount for this sale (calculated field).
     */
    public function getTotalAmountAttribute(): float
    {
        return $this->settlement_amount ?? 0;
    }

    /**
     * Get the MNP discount for this sale.
     */
    public function getMnpDiscountAttribute(): float
    {
        return $this->new_mnp_discount ?? 0;
    }
}
