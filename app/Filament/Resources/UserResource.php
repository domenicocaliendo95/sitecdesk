<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationGroup = 'Gestione Utenti';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('role')
                    ->label('Ruolo')
                    ->options([
                        'cliente' => 'Cliente',
                        'collaboratore' => 'Collaboratore',
                        'admin' => 'Amministratore',
                    ])
                    ->required()
                    ->visible(fn ($livewire) => auth()->user()->role === 'admin'),

                Forms\Components\TextInput::make('nome')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('cognome')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                Forms\Components\TextInput::make('password')
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $operation): bool => $operation === 'create'),

                Forms\Components\TextInput::make('azienda')
                    ->maxLength(255)
                    ->visible(fn ($record) => $record?->role === 'cliente' || !$record),

                Forms\Components\TextInput::make('partita_iva')
                    ->maxLength(255)
                    ->visible(fn ($record) => $record?->role === 'cliente' || !$record),

                Forms\Components\TextInput::make('telefono')
                    ->tel()
                    ->maxLength(255),

                Forms\Components\TextInput::make('tag')
                    ->maxLength(255)
                    ->visible(fn ($livewire) => auth()->user()->role === 'admin'),

            ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('nome_completo')
                    ->label('Nome Completo')
                    ->searchable(['nome', 'cognome'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable(),

                Tables\Columns\TextColumn::make('role')
                    ->label('Ruolo')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'admin' => 'danger',
                        'collaboratore' => 'warning',
                        'cliente' => 'success',
                    }),

                Tables\Columns\TextColumn::make('azienda')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('tag')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creato il')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('role')
                    ->label('Ruolo')
                    ->options([
                        'cliente' => 'Cliente',
                        'collaboratore' => 'Collaboratore',
                        'admin' => 'Amministratore',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }
}
