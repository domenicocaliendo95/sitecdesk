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

class TicketResource extends Resource
{
    protected static ?string $model = Ticket::class;
    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationGroup = 'Supporto';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('oggetto')
                    ->required()
                    ->maxLength(255),

                Forms\Components\RichEditor::make('corpo')
                    ->required()
                    ->columnSpan('full'),

                Forms\Components\Select::make('categoria_id')
                    ->relationship('categoria', 'nome')
                    ->required(),

                Forms\Components\FileUpload::make('allegati')
                    ->multiple()
                    ->directory('ticket-attachments')
                    ->preserveFilenames(),
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

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime(),
            ])
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
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('creato_da', auth()->id());
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
