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

                Infolists\Components\Section::make('Discussioni')
                    ->schema([
                        Infolists\Components\RepeatableEntry::make('discussioni')
                            ->schema([
                                Infolists\Components\TextEntry::make('user.nome_completo')
                                    ->label('Da'),

                                Infolists\Components\TextEntry::make('created_at')
                                    ->label('Il')
                                    ->dateTime(),

                                Infolists\Components\TextEntry::make('messaggio')
                                    ->html()
                                    ->columnSpan('full'),
                            ])
                            ->columns(2)
                            //->filter(fn ($discussione) => !$discussione->interno), // Nascondi le note interne ai clienti
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
                ])
                ->action(function (array $data): void {
                    $discussione = new Discussione();
                    $discussione->ticket_id = $this->record->id;
                    $discussione->user_id = auth()->id();
                    $discussione->messaggio = $data['messaggio'];
                    $discussione->interno = false; // I clienti non possono creare note interne
                    $discussione->save();

                    if (isset($data['allegati'])) {
                        foreach ($data['allegati'] as $allegato) {
                            $discussione->allegati()->create([
                                'nome_originale' => $allegato->getClientOriginalName(),
                                'filename' => $allegato->getFilename(),
                                'path' => $allegato->store('discussion-attachments'),
                                'mime_type' => $allegato->getMimeType(),
                                'size' => $allegato->getSize(),
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
