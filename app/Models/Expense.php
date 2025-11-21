<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Expense extends Model
{
    use HasFactory;

    protected $fillable = [
        'store_id',
        'expense_date',
        'description',
        'amount',
        'created_by',
    ];

    protected $casts = [
        'expense_date' => 'date',
        'amount' => 'decimal:2',
    ];

    // Relationships
    public function store()
    {
        return $this->belongsTo(Store::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeForStore($query, $storeId)
    {
        return $query->where('store_id', $storeId);
    }

    public function scopeForMonth($query, $year, $month)
    {
        return $query->whereYear('expense_date', $year)
                     ->whereMonth('expense_date', $month);
    }

    public function scopeForYear($query, $year)
    {
        return $query->whereYear('expense_date', $year);
    }

    public function scopeDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('expense_date', [$startDate, $endDate]);
    }

    // Static Methods
    /**
     * Get monthly summary for a store
     */
    public static function getMonthlySummary($storeId, $year, $month)
    {
        return static::forStore($storeId)
            ->forMonth($year, $month)
            ->select(
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total'),
                DB::raw('AVG(amount) as average')
            )
            ->first();
    }

    /**
     * Get yearly summary for a store
     */
    public static function getYearlySummary($storeId, $year)
    {
        return static::forStore($storeId)
            ->forYear($year)
            ->select(
                DB::raw('EXTRACT(MONTH FROM expense_date) as month'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total')
            )
            ->groupBy(DB::raw('EXTRACT(MONTH FROM expense_date)'))
            ->orderBy('month')
            ->get();
    }
}
