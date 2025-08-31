<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('새 사용자 추가')
                ->icon('heroicon-o-user-plus'),
        ];
    }
    
    public function getTitle(): string
    {
        return '사용자 관리';
    }
}