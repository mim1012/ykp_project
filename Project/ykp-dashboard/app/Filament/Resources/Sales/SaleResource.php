<?php

namespace App\Filament\Resources\Sales;

use App\Filament\Resources\Sales\Pages\ManageSales;
use App\Models\Sale;
use App\Models\DealerProfile;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SaleResource extends Resource
{
    protected static ?string $model = Sale::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('dealer_code')
                    ->label('대리점')
                    ->options(DealerProfile::active()->pluck('dealer_name', 'dealer_code'))
                    ->searchable()
                    ->preload(),
                TextInput::make('store_id')
                    ->required()
                    ->numeric(),
                TextInput::make('branch_id')
                    ->required()
                    ->numeric(),
                DatePicker::make('sale_date')
                    ->required(),
                TextInput::make('carrier')
                    ->required(),
                TextInput::make('activation_type')
                    ->required(),
                TextInput::make('model_name'),
                TextInput::make('base_price')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('verbal1')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('verbal2')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('grade_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('additional_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('rebate_total')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('cash_activation')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('usim_fee')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('new_mnp_discount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('deduction')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('settlement_amount')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('tax')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('margin_before_tax')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('cash_received')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('payback')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('margin_after_tax')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('monthly_fee')
                    ->required()
                    ->numeric()
                    ->default(0),
                TextInput::make('phone_number')
                    ->tel(),
                TextInput::make('salesperson'),
                Textarea::make('memo')
                    ->columnSpanFull(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('store_id')
                    ->numeric(),
                TextEntry::make('branch_id')
                    ->numeric(),
                TextEntry::make('sale_date')
                    ->date(),
                TextEntry::make('carrier'),
                TextEntry::make('activation_type'),
                TextEntry::make('model_name'),
                TextEntry::make('base_price')
                    ->numeric(),
                TextEntry::make('verbal1')
                    ->numeric(),
                TextEntry::make('verbal2')
                    ->numeric(),
                TextEntry::make('grade_amount')
                    ->numeric(),
                TextEntry::make('additional_amount')
                    ->numeric(),
                TextEntry::make('rebate_total')
                    ->numeric(),
                TextEntry::make('cash_activation')
                    ->numeric(),
                TextEntry::make('usim_fee')
                    ->numeric(),
                TextEntry::make('new_mnp_discount')
                    ->numeric(),
                TextEntry::make('deduction')
                    ->numeric(),
                TextEntry::make('settlement_amount')
                    ->numeric(),
                TextEntry::make('tax')
                    ->numeric(),
                TextEntry::make('margin_before_tax')
                    ->numeric(),
                TextEntry::make('cash_received')
                    ->numeric(),
                TextEntry::make('payback')
                    ->numeric(),
                TextEntry::make('margin_after_tax')
                    ->numeric(),
                TextEntry::make('monthly_fee')
                    ->numeric(),
                TextEntry::make('phone_number'),
                TextEntry::make('salesperson'),
                TextEntry::make('created_at')
                    ->dateTime(),
                TextEntry::make('updated_at')
                    ->dateTime(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('dealerProfile.dealer_name')
                    ->label('대리점')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('store_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('branch_id')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sale_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('carrier')
                    ->searchable(),
                TextColumn::make('activation_type')
                    ->searchable(),
                TextColumn::make('model_name')
                    ->searchable(),
                TextColumn::make('base_price')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('verbal1')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('verbal2')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('grade_amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('additional_amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('rebate_total')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('cash_activation')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('usim_fee')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('new_mnp_discount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('deduction')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('settlement_amount')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('tax')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('margin_before_tax')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('cash_received')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('payback')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('margin_after_tax')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('monthly_fee')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('phone_number')
                    ->searchable(),
                TextColumn::make('salesperson')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSales::route('/'),
        ];
    }
}
