<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    use HasFactory;

    /**
     * The connection name for the model.
     *
     * @var string|null
     */
    // Connection will use default from config/database.php

    protected $fillable = [
        'user_id',
        'activity_type',
        'activity_title',
        'activity_description',
        'activity_data',
        'target_type',
        'target_id',
        'ip_address',
        'user_agent',
        'performed_at',
    ];

    protected $casts = [
        'activity_data' => 'array',
        'performed_at' => 'datetime',
    ];

    // 관계 정의
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function target()
    {
        switch ($this->target_type) {
            case 'store':
                return $this->belongsTo(Store::class, 'target_id');
            case 'branch':
                return $this->belongsTo(Branch::class, 'target_id');
            case 'sale':
                return $this->belongsTo(Sale::class, 'target_id');
            default:
                return;
        }
    }

    // 스코프
    public function scopeRecent($query, $limit = 10)
    {
        return $query->orderBy('performed_at', 'desc')->limit($limit);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('activity_type', $type);
    }

    public function scopeByUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    // 활동 로그 생성 헬퍼
    public static function logActivity($type, $title, $description = null, $targetType = null, $targetId = null, $data = null)
    {
        try {
            return self::create([
                'user_id' => auth()->id(),
                'activity_type' => $type,
                'activity_title' => $title,
                'activity_description' => $description,
                'activity_data' => $data,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'performed_at' => now(),
            ]);
        } catch (Exception $e) {
            \Log::error('Activity log creation failed: '.$e->getMessage());

            return;
        }
    }
}
