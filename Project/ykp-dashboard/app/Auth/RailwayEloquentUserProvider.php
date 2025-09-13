<?php

namespace App\Auth;

use App\Helpers\DatabaseHelper;
use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class RailwayEloquentUserProvider extends EloquentUserProvider
{
    /**
     * Railway PostgreSQL 환경에서 사용자 ID로 조회 (재시도 로직 포함)
     */
    public function retrieveById($identifier)
    {
        return DatabaseHelper::executeWithRetry(function () use ($identifier) {
            $model = $this->createModel();
            return $this->newModelQuery($model)
                        ->where($model->getAuthIdentifierName(), $identifier)
                        ->first();
        });
    }

    /**
     * Railway PostgreSQL 환경에서 remember token으로 사용자 조회
     */
    public function retrieveByToken($identifier, $token)
    {
        return DatabaseHelper::executeWithRetry(function () use ($identifier, $token) {
            $model = $this->createModel();
            $retrievedModel = $this->newModelQuery($model)->where(
                $model->getAuthIdentifierName(), $identifier
            )->first();

            if (!$retrievedModel) {
                return null;
            }

            $rememberToken = $retrievedModel->getRememberToken();

            return $rememberToken && hash_equals($rememberToken, $token)
                        ? $retrievedModel : null;
        });
    }

    /**
     * Railway PostgreSQL 환경에서 자격 증명으로 사용자 조회
     */
    public function retrieveByCredentials(array $credentials)
    {
        if (empty($credentials) ||
           (count($credentials) === 1 &&
            str_contains($this->firstCredentialKey($credentials), 'password'))) {
            return null;
        }

        return DatabaseHelper::executeWithRetry(function () use ($credentials) {
            $query = $this->newModelQuery();

            foreach ($credentials as $key => $value) {
                if (str_contains($key, 'password')) {
                    continue;
                }

                if (is_array($value) || $value instanceof \Arrayable) {
                    $query->whereIn($key, $value);
                } else {
                    $query->where($key, $value);
                }
            }

            return $query->first();
        });
    }

    /**
     * 자격 증명 배열에서 첫 번째 키 반환
     */
    protected function firstCredentialKey(array $credentials)
    {
        foreach ($credentials as $key => $value) {
            return $key;
        }

        return null;
    }
}