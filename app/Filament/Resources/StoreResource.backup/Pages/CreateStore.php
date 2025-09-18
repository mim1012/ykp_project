<?php

namespace App\Filament\Resources\StoreResource\Pages;

use App\Filament\Resources\StoreResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class CreateStore extends CreateRecord
{
    protected static string $resource = StoreResource::class;

    public function getTitle(): string
    {
        return '새 매장 추가';
    }

    protected function handleRecordCreation(array $data): Model
    {
        // 매장장 계정 생성 데이터 분리
        $createManager = $data['create_manager_account'] ?? false;
        $managerData = [
            'name' => $data['manager_name'] ?? null,
            'email' => $data['manager_email'] ?? null,
            'password' => $data['manager_password'] ?? null,
        ];

        // 매장장 관련 데이터 제거
        unset($data['create_manager_account'], $data['manager_name'], $data['manager_email'], $data['manager_password']);

        // 매장 생성
        $store = static::getModel()::create($data);

        // 매장장 계정 생성 (선택사항)
        if ($createManager && $managerData['name'] && $managerData['email'] && $managerData['password']) {
            try {
                User::create([
                    'name' => $managerData['name'],
                    'email' => $managerData['email'],
                    'password' => Hash::make($managerData['password']),
                    'role' => 'store', // 기본적으로 매장 직원으로 생성
                    'branch_id' => $store->branch_id,
                    'store_id' => $store->id,
                    'is_active' => true,
                ]);

                Notification::make()
                    ->title('매장과 매장장 계정이 생성되었습니다')
                    ->success()
                    ->body("매장: {$store->name}, 매장장: {$managerData['name']} ({$managerData['email']})")
                    ->persistent() // 사용자가 닫을 때까지 유지
                    ->send();

            } catch (\Exception $e) {
                Notification::make()
                    ->title('매장은 생성되었지만 매장장 계정 생성 실패')
                    ->warning()
                    ->body('매장장 계정은 나중에 "직원 추가" 버튼으로 생성해주세요.')
                    ->send();
            }
        } else {
            Notification::make()
                ->title('매장이 생성되었습니다')
                ->success()
                ->body("매장명: {$store->name}")
                ->send();
        }

        return $store;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
