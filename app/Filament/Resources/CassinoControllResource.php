<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CassinoControllResource\Pages;
use App\Filament\Resources\CassinoControllResource\Widgets\GgrOverview;
use App\Models\Order;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class CassinoControllResource extends Resource
{

    use ExposesTableToWidgets;

    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Administração';

    protected static ?string $navigationLabel = 'Ganhos';

    protected static ?string $modelLabel = 'Ganhos';

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
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('transaction_id')
                    ->label('COD')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Usuário')
                    ->searchable(),
                Tables\Columns\TextColumn::make('gameInfo.game_name')
                    ->label('Jogo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Vitória' => 'success',
                        'Perda' => 'danger',
                        default => $state
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        'Perda' => 'Apostado',
                        default => "Vitória"
                    })
                    ->searchable(),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Preço')
                    ->money('BRL')
                    ->searchable(),
                Tables\Columns\TextColumn::make('providers')
                    ->label('Provedor')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data')
                    ->dateTime()
                    ->sortable()
            ])
            ->filters([
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')->label('Criado a partir de'),
                        DatePicker::make('created_until')->label('Criado até'),
                        TextInput::make("user")->label('Usuário'),
                        TextInput::make("jogo")->label('Jogo'),
                        TextInput::make("provedor")->label('Provedor'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'] ?? null,
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            )
                            ->when(
                                $data['user'] ?? null,
                                fn(Builder $query, $value): Builder => $query->whereHas('user', function ($query) use ($value) {
                                    $query->where('name', 'like', '%' . $value . '%')
                                        ->orWhere('email', 'like', '%' . $value . '%');
                                })
                            )
                            ->when(
                                $data['jogo'] ?? null,
                                fn(Builder $query, $value): Builder => $query->whereHas('gameInfo', function ($query) use ($value) {
                                    $query->where('game_name', 'like', '%' . $value . '%')
                                        ->orWhere('game_id', 'like', '%' . $value . '%')
                                        ->orWhere('game_code', 'like', '%' . $value . '%');
                                })
                            )
                            ->when(
                                $data['provedor'] ?? null,
                                fn(Builder $query, $value): Builder => $query->whereHas('gameInfo', function ($query) use ($value) {
                                    $query->whereHas('provider', function ($query) use ($value) {
                                        $query->where('name', 'like', '%' . $value . '%')
                                            ->orWhere('code', 'like', '%' . $value . '%');
                                    });
                                })
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['created_from'] ?? null) {
                            $indicators['created_from'] = 'Criado a partir de ' . Carbon::parse($data['created_from'])->toFormattedDateString();
                        }

                        if ($data['created_until'] ?? null) {
                            $indicators['created_until'] = 'Criado até ' . Carbon::parse($data['created_until'])->toFormattedDateString();
                        }

                        return $indicators;
                    })
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canView(Model $record): bool
    {
        return false;
    }


    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCassinoControlls::route('/'),
            'create' => Pages\CreateCassinoControll::route('/create'),
            'view' => Pages\ViewCassinoControll::route('/{record}'),
            'edit' => Pages\EditCassinoControll::route('/{record}/edit'),
        ];
    }

    /**
     * @return string[]
     */
    public static function getWidgets(): array
    {
        return [
            GgrOverview::class,
        ];
    }


}
