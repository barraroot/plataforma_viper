<?php

namespace App\Filament\Resources;

use App\Filament\Resources\GgrProvidersResource\Pages;
use App\Models\Provider;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Table;

class GgrProvidersResource extends Resource
{
    protected static ?string $model = Provider::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Ganhos por provedor';

    protected static ?string $modelLabel = 'Ganhos por provedor';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                //
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->query(
                Provider::query()
                    ->with('orders')
            )
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Provedor')
                    ->searchable(),
                Tables\Columns\TextColumn::make('orders_loss_sum_amount')
                    ->label('Perdas')
                    ->money('BRL')
                    ->sum("ordersLoss", "amount"),
                Tables\Columns\TextColumn::make('orders_win_sum_amount')
                    ->label("Ganhos")
                    ->money('BRL')
                    ->sum("ordersWin", "amount"),
                Tables\Columns\TextColumn::make('ordersDifference')
                    ->label("Lucro obtido")
                    ->badge()
                    ->color(function ($state) {
                        if ($state > 0) {
                            return "success";
                        }

                        if ($state < 0) {
                            return "danger";
                        }

                        return "info";
                    })
                    ->money('BRL')
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListGgrProviders::route('/'),
            'create' => Pages\CreateGgrProviders::route('/create'),
            'edit' => Pages\EditGgrProviders::route('/{record}/edit'),
        ];
    }
}
