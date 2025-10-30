<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Goal extends Model
{
    use HasFactory;

    /**
     * The connection name for the model.
     *
     * @var string|null
     */
    // Connection will use default from config/database.php

    protected $fillable = [
        'target_type',
        'target_id',
        'period_type',
        'period_start',
        'period_end',
        'sales_target',
        'activation_target',
        'margin_target',
        'created_by',
        'notes',
        'is_active',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'sales_target' => 'decimal:2',
        'activation_target' => 'integer',
        'margin_target' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    // 관계 정의
    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function target()
    {
        if ($this->target_type === 'store') {
            return $this->belongsTo(Store::class, 'target_id');
        } elseif ($this->target_type === 'branch') {
            return $this->belongsTo(Branch::class, 'target_id');
        }

    }

    // 스코프
    public function scopeCurrentMonth($query)
    {
        // DB 독립적인 날짜 범위 방식 사용
        $startOfMonth = now()->startOfMonth()->format('Y-m-d');
        $endOfMonth = now()->endOfMonth()->format('Y-m-d');

        return $query->whereBetween('period_start', [$startOfMonth, $endOfMonth]);
    }

    public function scopeActive($query)
    {
        // PostgreSQL boolean 타입 호환성을 위해 직접 쿼리
        if (config('database.default') === 'pgsql') {
            return $query->whereRaw('is_active = true');
        }

        return $query->where('is_active', true);
    }

    // 목표 달성률 계산
    public function getAchievementRate($actualSales, $actualActivations)
    {
        $salesRate = $this->sales_target > 0 ? ($actualSales / $this->sales_target) * 100 : 0;
        $activationRate = $this->activation_target > 0 ? ($actualActivations / $this->activation_target) * 100 : 0;

        return [
            'sales_achievement' => round($salesRate, 1),
            'activation_achievement' => round($activationRate, 1),
            'overall_achievement' => round(($salesRate + $activationRate) / 2, 1),
        ];
    }
}
