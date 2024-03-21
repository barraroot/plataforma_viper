<?php

namespace App\Filament\Resources\CassinoControllResource\Widgets;

use App\Filament\Resources\CassinoControllResource\Pages\ListCassinoControlls;
use App\Helpers\Core as Helper;
use Filament\Widgets\Concerns\InteractsWithPageTable;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class GgrOverview extends BaseWidget
{
    use InteractsWithPageTable;

    protected static ?string $pollingInterval = null;

    protected static bool $isLazy = true;

    protected function getTablePage(): string
    {
        return ListCassinoControlls::class;
    }

    protected function getStats(): array
    {
        $queryWin = $this->getPageTableQuery();
        $queryBet = $this->getPageTableQuery();
        $bets = $queryWin->where('type', 'loss')->sum('amount');
        $win = $queryBet->where('type', 'win')->sum('amount');
        return [
            Stat::make("Derrotas", Helper::amountFormatDecimal($bets))
                ->description('Total de perdas dos usuários')
                ->descriptionIcon('heroicon-m-arrow-trending-down')
                ->color('danger'),
            Stat::make("Ganhos", Helper::amountFormatDecimal($win))
                ->description('Total de ganhos dos usuários')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
            Stat::make("Total", Helper::amountFormatDecimal($win - $bets))
                ->description('Total de ganhos dos usuários')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('success'),
        ];
    }
}
