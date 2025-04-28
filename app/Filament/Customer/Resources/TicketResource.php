<?php

namespace App\Filament\Customer\Resources;

use App\Filament\Customer\Resources\TicketResource\Pages;
use App\Models\Ticket;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\FileUpload;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;
    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationGroup = 'Supporto';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Nuovo Ticket')
                    ->schema([
                        Forms\Components\TextInput::make('oggetto')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan('full'),

                        Forms\Components\RichEditor::make('corpo')
                            ->required()
                            ->columnSpan('full'),

                        Forms\Components\Select::make('categoria_id')
                            ->relationship('categoria', 'nome')
                            ->required(),
                    ]),

                Section::make('Allegati')
                    ->schema([
                        FileUpload::make('allegati_temp')
                            ->label('Allegati')
                            ->multiple()
                            ->directory('ticket-attachments')
                            ->preserveFilenames()
                            ->maxFiles(10)
                            ->downloadable()
                            ->disk('public')
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('oggetto')
                    ->searchable(),

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
                    ->dateTime(),

                Tables\Columns\TextColumn::make('ultima_risposta')
                    ->label('Ultima risposta')
                    ->dateTime()
                    ->getStateUsing(function ($record) {
                        $ultimaDiscussione = $record->discussioni()
                            ->orderBy('created_at', 'desc')
                            ->first();

                        return $ultimaDiscussione ? $ultimaDiscussione->created_at : $record->created_at;
                    })
                    ->sortable(query: function ($query, $direction) {
                        return $query
                            ->leftJoin('discussioni', 'tickets.id', '=', 'discussioni.ticket_id')
                            ->select('tickets.*')
                            ->selectRaw('MAX(COALESCE(discussioni.created_at, tickets.created_at)) as latest_activity')
                            ->groupBy('tickets.id', 'tickets.oggetto', 'tickets.corpo', 'tickets.creato_da',
                                'tickets.assegnato_a', 'tickets.categoria_id', 'tickets.stato',
                                'tickets.created_at', 'tickets.updated_at', 'tickets.deleted_at')
                            ->orderBy('latest_activity', $direction);
                    }),

                Tables\Columns\IconColumn::make('has_attachments')
                    ->label('Allegati')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->allegati()->count() > 0)
                    ->trueIcon('heroicon-o-paper-clip')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('gray'),
            ])
            ->defaultSort('ultima_risposta', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('stato')
                    ->options([
                        'nuovo' => 'Nuovo',
                        'aperto' => 'Aperto',
                        'in_lavorazione' => 'In Lavorazione',
                        'in_attesa' => 'In Attesa',
                        'risolto' => 'Risolto',
                        'chiuso' => 'Chiuso',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->defaultSort('ultima_risposta', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['discussioni', 'categoria'])
            ->where('creato_da', auth()->id());
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTickets::route('/'),
            'create' => Pages\CreateTicket::route('/create'),
            'view' => Pages\ViewTicket::route('/{record}'),
        ];
    }
}
