<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Ticket;

class TicketController extends Controller
{
    /**
     * Restituisce la lista dei ticket con id, oggetto e stato
     */
    public function index()
    {
        $tickets = Ticket::select('id', 'oggetto', 'stato', 'creato_da', 'assegnato_a', 'updated_at')
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'tickets' => $tickets,
        ]);
    }

    /**
     * Restituisce i dettagli di un ticket specifico
     */
    public function show($id)
    {
        $ticket = Ticket::findOrFail($id);
        $ticket->load('creatore', 'assegnato', 'categoria', 'discussioni', 'allegati');

        return response()->json([
            'ticket' => $ticket,
        ]);
    }
}
