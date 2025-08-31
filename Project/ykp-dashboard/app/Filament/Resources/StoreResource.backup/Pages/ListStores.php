<?php

namespace App\Filament\Resources\StoreResource\Pages;

use App\Filament\Resources\StoreResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Filament\Pages\Concerns\ExposesTableToWidgets;

class ListStores extends ListRecords
{
    use ExposesTableToWidgets;
    
    protected static string $resource = StoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('새 매장 추가')
                ->icon('heroicon-o-plus'),
        ];
    }
    
    public function getTitle(): string
    {
        return '매장 관리';
    }
    
    protected function getHeaderWidgets(): array
    {
        return StoreResource::getWidgets();
    }
}