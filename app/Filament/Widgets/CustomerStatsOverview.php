<?php

namespace App\Filament\Customer\Widgets;

use App\Models\Ticket;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CustomerStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $userId = auth()->id();

        $totaleTickets = Ticket::where('creato_da', $userId)->count();
        $ticketsAperti = Ticket::where('creato_da', $userId)
            ->whereIn('stato', ['nuovo', 'aperto', 'in_lavorazione'])
            ->count();
        $ticketsRisolti = Ticket::where('creato_da', $userId)
            ->where('stato', 'risolto')
            ->count();
        $ticketsInAttesa = Ticket::where('creato_da', $userId)
            ->where('stato', 'in_attesa')
            ->count();

        return [
            Stat::make('I Miei Ticket', $totaleTickets)
                ->description('Totale ticket creati')
                ->descriptionIcon('heroicon-m-ticket')
                ->color('primary'),

            Stat::make('Ticket Aperti', $ticketsAperti)
                ->description('In corso di gestione')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('In Attesa', $ticketsInAttesa)
                ->description('Richiesta di informazioni')
                ->descriptionIcon('heroicon-m-information-circle')
                ->color('info'),

            Stat::make('Risolti', $ticketsRisolti)
                ->description('Ticket completati')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),
        ];
    }
}
