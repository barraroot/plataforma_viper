<?php

namespace App\Filament\Resources\GgrProvidersResource\Pages;

use App\Filament\Resources\GgrProvidersResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListGgrProviders extends ListRecords
{
    protected static string $resource = GgrProvidersResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}
