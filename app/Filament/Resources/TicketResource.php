<?php
namespace App\Filament\Resources;

use App\Filament\Resources\TicketResource\Pages;
use App\Models\Ticket;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Storage;

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;
    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationGroup = 'Assistenza';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Hidden::make('creato_da')
                    ->default(auth()->id())
                    ->required(),

                Section::make('Informazioni Ticket')
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
                            ->required()
                            ->createOptionForm([
                                Forms\Components\TextInput::make('nome')
                                    ->required(),
                                Forms\Components\TextInput::make('descrizione'),
                            ]),

                        Forms\Components\Select::make('stato')
                            ->options([
                                'nuovo' => 'Nuovo',
                                'aperto' => 'Aperto',
                                'in_lavorazione' => 'In Lavorazione',
                                'in_attesa' => 'In Attesa',
                                'risolto' => 'Risolto',
                                'chiuso' => 'Chiuso',
                            ])
                            ->required()
                            ->default('nuovo'),

                        Forms\Components\Select::make('assegnato_a')
                            ->label('Assegnato a')
                            ->options(function () {
                                if (auth()->user()->role === 'admin') {
                                    return User::whereIn('role', ['admin', 'collaboratore'])
                                        ->get()
                                        ->mapWithKeys(function ($user) {
                                            return [$user->id => $user->nome . ' ' . $user->cognome];
                                        });
                                }
                                $user = auth()->user();
                                return [$user->id => $user->nome . ' ' . $user->cognome];
                            })
                            ->searchable()
                            ->preload()
                            ->visible(fn () => in_array(auth()->user()->role, ['admin', 'collaboratore'])),

                    ])->columns(2),

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
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('creatore.nome_completo')
                    ->label('Cliente')
                    ->formatStateUsing(fn ($record) => $record->creatore->nome . ' ' . $record->creatore->cognome)
                    ->searchable(['users.nome', 'users.cognome']),

                Tables\Columns\TextColumn::make('assegnato.nome_completo')
                    ->label('Assegnato a')
                    ->formatStateUsing(fn ($record) => $record->assegnato ? $record->assegnato->nome . ' ' . $record->assegnato->cognome : '-')
                    ->searchable(['users.nome', 'users.cognome']),

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
                    ->label('Categoria')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime()
                    ->sortable(),

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

                Tables\Filters\SelectFilter::make('categoria_id')
                    ->relationship('categoria', 'nome')
                    ->label('Categoria'),

                Tables\Filters\SelectFilter::make('assegnato_a')
                    ->label('Assegnato a')
                    ->options(
                        User::whereIn('role', ['admin', 'collaboratore'])
                            ->get()
                            ->mapWithKeys(function ($user) {
                                return [$user->id => $user->nome . ' ' . $user->cognome];
                            })
                    )
                    ->visible(fn () => auth()->user()->role === 'admin'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make()
                    ->visible(fn (Ticket $record) =>
                        auth()->user()->role === 'admin' ||
                        $record->assegnato_a === auth()->id()
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['discussioni', 'creatore', 'assegnato', 'categoria']);

        if (auth()->user()->role === 'collaboratore') {
            $query->where('assegnato_a', auth()->id());
        }

        return $query;
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getEloquentQuery()->where('stato', 'nuovo')->count();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTickets::route('/'),
            'create' => Pages\CreateTicket::route('/create'),
            'edit' => Pages\EditTicket::route('/{record}/edit'),
            'view' => Pages\ViewTicket::route('/{record}'),
        ];
    }
}
