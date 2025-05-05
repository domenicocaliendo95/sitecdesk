<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\FileUpload;
use Filament\Actions\Action;
use App\Models\Discussione;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

    public ?array $data = [];

    public function mount($record): void
    {
        parent::mount($record);
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                RichEditor::make('messaggio')
                    ->label('Nuovo messaggio')
                    ->required(),
                Toggle::make('interno')
                    ->label('Nota Interna')
                    ->default(false),
                FileUpload::make('allegati')
                    ->multiple()
                    ->directory('discussion-attachments')
                    ->preserveFilenames()
                    ->downloadable()
            ]);
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Dettagli Ticket')
                    ->schema([
                        Infolists\Components\TextEntry::make('oggetto')
                            ->label('Oggetto')
                            ->columnSpan('full'),

                        Infolists\Components\TextEntry::make('stato')
                            ->badge()
                            ->color(fn (string $state): string => match ($state) {
                                'nuovo' => 'danger',
                                'aperto' => 'warning',
                                'in_lavorazione' => 'info',
                                'in_attesa' => 'secondary',
                                'risolto' => 'success',
                                'chiuso' => 'gray',
                            }),

                        Infolists\Components\TextEntry::make('categoria.nome')
                            ->label('Categoria'),

                        Infolists\Components\TextEntry::make('creatore.nome_completo')
                            ->label('Creato da'),

                        Infolists\Components\TextEntry::make('corpo')
                            ->label('Descrizione')
                            ->html()
                            ->columnSpan('full'),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Allegati Ticket')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('allegati')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('nome_originale')
                                    ->label('Nome file'),

                                Infolists\Components\TextEntry::make('mime_type')
                                    ->label('Tipo'),

                                Infolists\Components\TextEntry::make('size')
                                    ->label('Dimensione')
                                    ->formatStateUsing(fn (int $state): string => number_format($state / 1024, 2) . ' KB'),

                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Caricato il')
                                    ->dateTime(),

                                Infolists\Components\TextEntry::make('path')
                                    ->label('Download')
                                    ->formatStateUsing(fn (string $state, $record): string =>
                                        "<a href='" . route('ticket.download-attachment', $record->id) . "'
                                            class='text-primary-600 hover:text-primary-500' target='_blank'>
                                            Scarica
                                        </a>")
                                    ->html(),
                            ])
                            ->columns(5)
                    ])
                    ->visible(fn ($record) => $record->allegati()->count() > 0),

                Infolists\Components\Section::make('Discussioni')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('discussioni')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('user.nome_completo')
                                    ->label('Utente'),

                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Data')
                                    ->dateTime(),

                                Infolists\Components\TextEntry::make('messaggio')
                                    ->html()
                                    ->columnSpan('full'),

                                Infolists\Components\IconEntry::make('interno')
                                    ->label('Nota interna')
                                    ->icon(fn (bool $state): string => match ($state) {
                                        true => 'heroicon-o-eye-slash',
                                        false => 'heroicon-o-eye',
                                    })
                                    ->color(fn (bool $state): string => match ($state) {
                                        true => 'danger',
                                        false => 'success',
                                    }),

                                Infolists\Components\RepeatableEntry::make('allegati')
                                    ->label('Allegati')
                                    ->schema([
                                        Infolists\Components\TextEntry::make('nome_originale')
                                            ->label('File')
                                            ->formatStateUsing(fn (string $state, $record): string =>
                                                "<a href='" . route('ticket.download-attachment', $record->id) . "'
                                                    class='text-primary-600 hover:text-primary-500' target='_blank'>
                                                    " . $state . "
                                                </a>")
                                            ->html(),
                                    ])
                                    ->columnSpan('full')
                            ])
                            ->columns(3),
                    ]),

            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('aggiungi_discussione')
                ->label('Aggiungi risposta')
                ->form([
                    \Filament\Forms\Components\Select::make('assegnato_a')
                        ->label('Assegna a')
                        ->options(function () {
                            return \App\Models\User::whereIn('role', ['admin', 'collaboratore'])
                                ->get()
                                ->mapWithKeys(function ($user) {
                                    return [$user->id => $user->nome . ' ' . $user->cognome];
                                });
                        })
                        ->default(fn() => $this->record->assegnato_a)
                        ->searchable()
                        ->preload()
                        ->placeholder('Seleziona un collaboratore')
                        ->visible(fn () => auth()->user()->role === 'admin' || auth()->user()->role === 'collaboratore'),

                    \Filament\Forms\Components\Select::make('stato')
                        ->label('Stato ticket')
                        ->options([
                            'nuovo' => 'Nuovo',
                            'aperto' => 'Aperto',
                            'in_lavorazione' => 'In Lavorazione',
                            'in_attesa' => 'In Attesa',
                            'risolto' => 'Risolto',
                            'chiuso' => 'Chiuso',
                        ])
                        ->default('aperto')
                        ->required()
                        ->visible(fn () => auth()->user()->role === 'admin' || auth()->user()->role === 'collaboratore'),

                    RichEditor::make('messaggio')
                        ->label('Messaggio')
                        ->required(),
                    Toggle::make('interno')
                        ->label('Nota Interna')
                        ->default(false),
                    FileUpload::make('allegati')
                        ->multiple()
                        ->directory('discussion-attachments')
                        ->preserveFilenames()
                        ->downloadable()
                ])
                ->action(function (array $data): void {
                    // Aggiorna assegnazione e stato del ticket se forniti
                    if (isset($data['assegnato_a']) || isset($data['stato'])) {
                        $updateData = [];

                        if (isset($data['assegnato_a'])) {
                            $updateData['assegnato_a'] = $data['assegnato_a'];
                        }

                        if (isset($data['stato'])) {
                            $updateData['stato'] = $data['stato'];
                        } else {
                            // Se non specificato, riapri il ticket di default
                            $updateData['stato'] = 'aperto';
                        }

                        $this->record->update($updateData);
                    }

                    $discussione = new Discussione();
                    $discussione->ticket_id = $this->record->id;
                    $discussione->user_id = auth()->id();
                    $discussione->messaggio = $data['messaggio'];
                    $discussione->interno = $data['interno'] ?? false;
                    $discussione->save();

                    // Gestione allegati
                    if (isset($data['allegati']) && !empty($data['allegati'])) {
                        foreach ($data['allegati'] as $allegato) {
                            // Filament restituisce già il path del file salvato
                            $discussione->allegati()->create([
                                'nome_originale' => basename($allegato),
                                'filename' => basename($allegato),
                                'path' => $allegato,
                                'mime_type' => Storage::disk('public')->exists($allegato) ? Storage::disk('public')->mimeType($allegato) : 'application/octet-stream',
                                'size' => Storage::disk('public')->exists($allegato) ? Storage::disk('public')->size($allegato) : 0,
                                'uploaded_by' => auth()->id(),
                            ]);
                        }
                    }

                    Notification::make()
                        ->title('Risposta aggiunta con successo')
                        ->success()
                        ->send();

                    $this->redirect(TicketResource::getUrl('view', ['record' => $this->record]));
                }),
        ];
    }
}
