<?php

namespace App\Filament\Resources\CassinoControllResource\Pages;

use App\Filament\Resources\CassinoControllResource;
use Filament\Actions;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;

class ListCassinoControlls extends ListRecords
{
    use ExposesTableToWidgets;
    protected static string $resource = CassinoControllResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }

    /**
     * @return string[]
     */
    protected function getHeaderWidgets(): array
    {
        return [
            CassinoControllResource\Widgets\GgrOverview::class
        ];
    }
}
