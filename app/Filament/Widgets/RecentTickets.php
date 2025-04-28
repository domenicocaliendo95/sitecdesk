<?php

namespace App\Filament\Widgets;

use App\Models\Ticket;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class RecentTickets extends BaseWidget
{
    protected static ?string $heading = 'Ticket Recenti';
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Ticket::query()
                    ->with(['creatore', 'categoria'])
                    ->latest()
                    ->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('oggetto')
                    ->searchable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('creatore.nome_completo')
                    ->label('Cliente')
                    ->formatStateUsing(fn ($record) => $record->creatore->nome . ' ' . $record->creatore->cognome),

                Tables\Columns\BadgeColumn::make('stato')
                    ->colors([
                        'danger' => 'nuovo',
                        'warning' => 'aperto',
                        'info' => 'in_lavorazione',
                        'secondary' => 'in_attesa',
                        'success' => 'risolto',
                        'gray' => 'chiuso',
                    ]),

                Tables\Columns\TextColumn::make('categoria.nome')
                    ->label('Categoria'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('Visualizza')
                    ->icon('heroicon-m-eye')
                    ->url(fn (Ticket $record): string => route('filament.admin.resources.tickets.view', $record)),
            ])
            ->paginated(false);
    }
}
