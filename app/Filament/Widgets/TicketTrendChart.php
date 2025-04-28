<?php

namespace App\Filament\Widgets;

use App\Models\Ticket;
use Filament\Widgets\ChartWidget;
use Flowframe\Trend\Trend;
use Flowframe\Trend\TrendValue;

class TicketTrendChart extends ChartWidget
{
    protected static ?string $heading = 'Andamento Ticket Ultimi 30 Giorni';
    protected static ?int $sort = 4;

    protected function getData(): array
    {
        $data = Trend::model(Ticket::class)
            ->between(
                start: now()->subDays(30),
                end: now(),
            )
            ->perDay()
            ->count();

        return [
            'datasets' => [
                [
                    'label' => 'Ticket Creati',
                    'data' => $data->map(fn (TrendValue $value) => $value->aggregate),
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $data->map(fn (TrendValue $value) => \Carbon\Carbon::parse($value->date)->format('d/m')),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
