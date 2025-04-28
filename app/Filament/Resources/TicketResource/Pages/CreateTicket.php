<?php

namespace App\Filament\Resources\TicketResource\Pages;

use App\Filament\Resources\TicketResource;
use Filament\Actions;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

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
        return $this->getResource()::getUrl('index');
    }
}
