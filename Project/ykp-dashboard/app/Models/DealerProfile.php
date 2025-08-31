<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DealerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'dealer_code',
        'dealer_name',
        'contact_person',
        'phone',
        'address',
        'default_sim_fee',
        'default_mnp_discount',
        'tax_rate',
        'default_payback_rate',
        'auto_calculate_tax',
        'include_sim_fee_in_settlement',
        'custom_calculation_rules',
        'status',
        'activated_at',
        'deactivated_at'
    ];

    protected $casts = [
        'default_sim_fee' => 'decimal:2',
        'default_mnp_discount' => 'decimal:2',
        'tax_rate' => 'decimal:3',
        'default_payback_rate' => 'decimal:2',
        'auto_calculate_tax' => 'boolean',
        'include_sim_fee_in_settlement' => 'boolean',
        'custom_calculation_rules' => 'array',
        'activated_at' => 'datetime',
        'deactivated_at' => 'datetime'
    ];

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'dealer_code', 'dealer_code');
    }

    public function getCalculationDefaults(): array
    {
        return [
            'simFee' => $this->default_sim_fee,
            'mnpDiscount' => $this->default_mnp_discount,
            'taxRate' => $this->tax_rate,
            'paybackRate' => $this->default_payback_rate,
            'autoCalculateTax' => $this->auto_calculate_tax,
            'includeSimFeeInSettlement' => $this->include_sim_fee_in_settlement,
            'customRules' => $this->custom_calculation_rules ?? []
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('dealer_code', $code);
    }
}