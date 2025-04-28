<?php

namespace App\Filament\Widgets;

use App\Models\Ticket;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class AdminStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $tickets = Ticket::count();
        $nuoviTickets = Ticket::where('stato', 'nuovo')->count();
        $ticketsAperti = Ticket::where('stato', 'aperto')->count();
        $ticketsInLavorazione = Ticket::where('stato', 'in_lavorazione')->count();
        $ticketsRisolti = Ticket::where('stato', 'risolto')->count();
        $clientiAttivi = User::where('role', 'cliente')->count();

        return [
            Stat::make('Totale Ticket', $tickets)
                ->description('Tutti i ticket nel sistema')
                ->descriptionIcon('heroicon-m-ticket')
                ->color('primary'),

            Stat::make('Ticket Nuovi', $nuoviTickets)
                ->description('Da prendere in carico')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color('danger'),

            Stat::make('Ticket Aperti', $ticketsAperti)
                ->description('In attesa di risposta')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('In Lavorazione', $ticketsInLavorazione)
                ->description('Attualmente gestiti')
                ->descriptionIcon('heroicon-m-cog')
                ->color('info'),

            Stat::make('Risolti', $ticketsRisolti)
                ->description('Ticket completati')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('Clienti Attivi', $clientiAttivi)
                ->description('Utenti registrati')
                ->descriptionIcon('heroicon-m-users')
                ->color('secondary'),
        ];
    }
}
