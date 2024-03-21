<?php

namespace App\Filament\Resources\GgrProvidersResource\Pages;

use App\Filament\Resources\GgrProvidersResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditGgrProviders extends EditRecord
{
    protected static string $resource = GgrProvidersResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
