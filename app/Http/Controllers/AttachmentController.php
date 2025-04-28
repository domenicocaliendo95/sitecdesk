<?php

namespace App\Http\Controllers;

use App\Models\Allegato;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class AttachmentController extends Controller
{
    public function download($id)
    {
        $allegato = Allegato::findOrFail($id);

        // Verifica autorizzazioni
        if (!$this->canAccessAttachment($allegato)) {
            abort(403, 'Non autorizzato');
        }

        if (!Storage::disk('public')->exists($allegato->path)) {
            abort(404, 'File non trovato');
        }

        return Storage::disk('public')->download($allegato->path, $allegato->nome_originale);
    }

    private function canAccessAttachment(Allegato $allegato): bool
    {
        $user = auth()->user();

        // Admin e collaboratori possono vedere tutti gli allegati
        if (in_array($user->role, ['admin', 'collaboratore'])) {
            return true;
        }

        // Se è un allegato di un ticket
        if ($allegato->attachable_type === 'App\Models\Ticket') {
            $ticket = $allegato->attachable;
            return $ticket->creato_da === $user->id;
        }

        // Se è un allegato di una discussione
        if ($allegato->attachable_type === 'App\Models\Discussione') {
            $discussione = $allegato->attachable;
            $ticket = $discussione->ticket;

            // I clienti non possono vedere allegati di discussioni interne
            if ($discussione->interno) {
                return false;
            }

            return $ticket->creato_da === $user->id;
        }

        return false;
    }
}
