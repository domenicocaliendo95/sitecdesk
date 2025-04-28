<?php

namespace App\Filament\Customer\Resources\TicketResource\Pages;

use App\Filament\Customer\Resources\TicketResource;
use Filament\Resources\Pages\ViewRecord;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\FileUpload;
use Filament\Actions\Action;
use App\Models\Discussione;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;

class ViewTicket extends ViewRecord
{
    protected static string $resource = TicketResource::class;

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

                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Creato il')
                            ->dateTime(),

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
                        Infolists\Components\RepeatableEntry::make('discussioni_pubbliche')
                            ->label('')
                            ->schema([
                                Infolists\Components\TextEntry::make('user.nome_completo')
                                    ->label('Da'),

                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Il')
                                    ->dateTime(),

                                Infolists\Components\TextEntry::make('messaggio')
                                    ->html()
                                    ->columnSpan('full'),

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
                            ->columns(2),
                    ]),

            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('aggiungi_risposta')
                ->label('Aggiungi risposta')
                ->form([
                    RichEditor::make('messaggio')
                        ->label('Messaggio')
                        ->required(),
                    FileUpload::make('allegati')
                        ->multiple()
                        ->directory('discussion-attachments')
                        ->preserveFilenames()
                        ->downloadable()
                ])
                ->action(function (array $data): void {
                    $discussione = new Discussione();
                    $discussione->ticket_id = $this->record->id;
                    $discussione->user_id = auth()->id();
                    $discussione->messaggio = $data['messaggio'];
                    $discussione->interno = false; // I clienti non possono creare note interne
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
