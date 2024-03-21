<?php

namespace App\Filament\Resources\CassinoControllResource\Pages;

use App\Filament\Resources\CassinoControllResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewCassinoControll extends ViewRecord
{
    protected static string $resource = CassinoControllResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
}
