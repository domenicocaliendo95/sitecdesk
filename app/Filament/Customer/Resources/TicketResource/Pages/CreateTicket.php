<?php

namespace App\Filament\Customer\Resources\TicketResource\Pages;

use App\Filament\Customer\Resources\TicketResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['creato_da'] = auth()->id();
        $data['stato'] = 'nuovo';

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        // Prepariamo i dati per la creazione
        $allegatiTemp = $data['allegati_temp'] ?? [];
        unset($data['allegati_temp']);

        // Creiamo il ticket
        $ticket = static::getModel()::create($data);

        // Gestiamo gli allegati
        if (!empty($allegatiTemp)) {
            foreach ($allegatiTemp as $allegato) {
                $ticket->allegati()->create([
                    'nome_originale' => basename($allegato),
                    'filename' => basename($allegato),
                    'path' => $allegato,
                    'mime_type' => Storage::disk('public')->mimeType($allegato),
                    'size' => Storage::disk('public')->size($allegato),
                    'uploaded_by' => auth()->id(),
                ]);
            }
        }

        return $ticket;
    }

    protected function getRedirectUrl(): string
    {
        return TicketResource::getUrl('index');
    }
}
