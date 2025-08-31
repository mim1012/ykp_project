<?php

namespace App\Filament\Resources\StoreResource\Pages;

use App\Filament\Resources\StoreResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;

class ViewStore extends ViewRecord
{
    protected static string $resource = StoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
    
    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('매장 정보')
                    ->schema([
                        Infolists\Components\TextEntry::make('name')
                            ->label('매장명'),
                        Infolists\Components\TextEntry::make('code')
                            ->label('매장코드')
                            ->copyable(),
                        Infolists\Components\TextEntry::make('branch.name')
                            ->label('소속지사')
                            ->badge()
                            ->color('info'),
                        Infolists\Components\TextEntry::make('status')
                            ->label('운영상태')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'active' => 'success',
                                'inactive' => 'warning',
                                'closed' => 'danger',
                            })
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                'active' => '운영중',
                                'inactive' => '휴업',
                                'closed' => '폐업',
                                default => $state,
                            }),
                        Infolists\Components\TextEntry::make('address')
                            ->label('주소'),
                        Infolists\Components\TextEntry::make('phone')
                            ->label('전화번호')
                            ->copyable(),
                    ])->columns(2),
                    
                Infolists\Components\Section::make('직원 현황')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('users')
                            ->label('직원 목록')
                            ->schema([
                                Infolists\Components\TextEntry::make('name')
                                    ->label('이름'),
                                Infolists\Components\TextEntry::make('email')
                                    ->label('이메일')
                                    ->copyable(),
                                Infolists\Components\TextEntry::make('role')
                                    ->label('역할')
                                    ->badge()
                                    ->color('primary')
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'store' => '매장직원',
                                        'branch' => '매장장',
                                        'headquarters' => '본사',
                                        default => $state,
                                    }),
                                Infolists\Components\TextEntry::make('status')
                                    ->label('상태')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'active' => 'success',
                                        'inactive' => 'warning',
                                        default => 'gray',
                                    })
                                    ->formatStateUsing(fn (string $state): string => match ($state) {
                                        'active' => '활성',
                                        'inactive' => '비활성',
                                        default => $state,
                                    }),
                            ])
                            ->columns(4),
                    ]),
                    
                Infolists\Components\Section::make('등록 정보')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('등록일시')
                            ->dateTime(),
                        Infolists\Components\TextEntry::make('updated_at')
                            ->label('수정일시')
                            ->dateTime(),
                    ])->columns(2),
            ]);
    }
}