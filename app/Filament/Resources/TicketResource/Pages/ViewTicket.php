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
use Filament\Notifications\Notification; // Aggiungiamo questo
use Filament\Notifications\Actions\Action as NotificationAction; // E questo se necessario

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
                                        true => 'danger', // Questo renderà l'icona rossa per le note interne
                                        false => 'success',
                                    })
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
                ])
                ->action(function (array $data): void {
                    $discussione = new Discussione();
                    $discussione->ticket_id = $this->record->id;
                    $discussione->user_id = auth()->id();
                    $discussione->messaggio = $data['messaggio'];
                    $discussione->interno = $data['interno'] ?? false;
                    $discussione->save();

                    // Gestione allegati se presenti
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

                    // Sostituiamo il notify con il nuovo sistema di notifiche
                    Notification::make()
                        ->title('Risposta aggiunta con successo')
                        ->success()
                        ->send();

                    $this->redirect(TicketResource::getUrl('view', ['record' => $this->record]));
                }),
        ];
    }
}
