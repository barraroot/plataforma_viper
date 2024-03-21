<?php

namespace App\Filament\Resources\CassinoControllResource\Pages;

use App\Filament\Resources\CassinoControllResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCassinoControll extends EditRecord
{
    protected static string $resource = CassinoControllResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make(),
            Actions\DeleteAction::make(),
        ];
    }
}
