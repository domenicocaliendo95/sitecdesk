<?php

namespace App\Filament\Widgets;

use App\Models\Ticket;
use Filament\Widgets\ChartWidget;

class TicketStatusChart extends ChartWidget
{
    protected static ?string $heading = 'Distribuzione Ticket per Stato';
    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $ticketsByStatus = Ticket::selectRaw('stato, count(*) as count')
            ->groupBy('stato')
            ->pluck('count', 'stato')
            ->toArray();

        $labels = [
            'nuovo' => 'Nuovo',
            'aperto' => 'Aperto',
            'in_lavorazione' => 'In Lavorazione',
            'in_attesa' => 'In Attesa',
            'risolto' => 'Risolto',
            'chiuso' => 'Chiuso',
        ];

        $data = [];
        $backgroundColors = [];
        $labelArray = [];

        foreach ($labels as $key => $label) {
            $labelArray[] = $label;
            $data[] = $ticketsByStatus[$key] ?? 0;

            // Colori corrispondenti agli stati
            $colors = [
                'nuovo' => 'rgb(239, 68, 68)',         // rosso
                'aperto' => 'rgb(245, 158, 11)',       // arancione
                'in_lavorazione' => 'rgb(59, 130, 246)', // blu
                'in_attesa' => 'rgb(107, 114, 128)',    // grigio
                'risolto' => 'rgb(16, 185, 129)',       // verde
                'chiuso' => 'rgb(75, 85, 99)',          // grigio scuro
            ];

            $backgroundColors[] = $colors[$key];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Ticket per Stato',
                    'data' => $data,
                    'backgroundColor' => $backgroundColors,
                ],
            ],
            'labels' => $labelArray,
        ];
    }

    protected function getType(): string
    {
        return 'pie';
    }
}
