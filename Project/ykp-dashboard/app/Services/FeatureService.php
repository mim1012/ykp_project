<?php

namespace App\Services;

use Illuminate\Support\Facades\Auth;

class FeatureService
{
    /**
     * 특정 기능이 활성화되어 있는지 확인
     */
    public function isEnabled(string $feature, $user = null): bool
    {
        $user = $user ?? Auth::user();
        $config = config("features.features.{$feature}");
        
        if (!$config || !$config['enabled']) {
            return false;
        }
        
        // 특정 사용자 허용 리스트 확인
        if ($user && !empty($config['allowed_users'])) {
            if (in_array($user->id, $config['allowed_users'])) {
                return true;
            }
        }
        
        // 역할별 허용 확인
        if ($user && !empty($config['allowed_roles'])) {
            if (in_array($user->role, $config['allowed_roles'])) {
                return true;
            }
        }
        
        // 점진적 롤아웃 (퍼센티지 기반)
        $rollout = $config['rollout_percentage'] ?? 0;
        if ($rollout > 0) {
            $hash = crc32($user?->id ?? request()->ip());
            return ($hash % 100) < $rollout;
        }
        
        return false;
    }
    
    /**
     * 모든 활성화된 기능 목록 반환
     */
    public function getEnabledFeatures($user = null): array
    {
        $user = $user ?? Auth::user();
        $features = config('features.features', []);
        $enabled = [];
        
        foreach ($features as $key => $config) {
            if ($this->isEnabled($key, $user)) {
                $enabled[$key] = $config['description'] ?? $key;
            }
        }
        
        return $enabled;
    }
    
    /**
     * 개발자 전용 기능인지 확인
     */
    public function isDeveloperOnly(string $feature): bool
    {
        $config = config("features.features.{$feature}");
        $allowedRoles = $config['allowed_roles'] ?? [];
        
        return $config['rollout_percentage'] === 0 && 
               (in_array('developer', $allowedRoles) || in_array('admin', $allowedRoles));
    }
}