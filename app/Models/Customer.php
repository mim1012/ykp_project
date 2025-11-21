<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'branch_id',
        'phone_number',
        'customer_name',
        'birth_date',
        'current_device',
        'customer_type',
        'activated_sale_id',
        'first_visit_date',
        'last_contact_date',
        'notes',
        'status',
        'created_by',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'first_visit_date' => 'date',
        'last_contact_date' => 'date',
    ];

    // Relationships
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function activatedSale()
    {
        return $this->belongsTo(Sale::class, 'activated_sale_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeProspects($query)
    {
        return $query->where('customer_type', 'prospect');
    }

    public function scopeActivated($query)
    {
        return $query->where('customer_type', 'activated');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    // Methods
    /**
     * Link prospect customer to sale (convert to activated)
     */
    public function linkToSale(Sale $sale): void
    {
        $this->update([
            'customer_type' => 'activated',
            'activated_sale_id' => $sale->id,
            'status' => 'converted',
            'last_contact_date' => now(),
        ]);
    }

    /**
     * Convert prospect to activated customer
     */
    public function convertToActivated(Sale $sale): void
    {
        $this->linkToSale($sale);
    }

    /**
     * Calculate customer lifetime value
     */
    public function getLifetimeValue(): float
    {
        if ($this->customer_type !== 'activated' || !$this->activated_sale_id) {
            return 0;
        }

        // 해당 고객의 전화번호로 모든 개통 건 찾기
        $sales = Sale::where('store_id', $this->store_id)
            ->where('phone_number', $this->phone_number)
            ->get();

        return $sales->sum('settlement_amount') ?? 0;
    }
}
