<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class TicketController extends Controller
{
    /**
     * Restituisce tutti i ticket visibili per l'utente autenticato
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function allTickets(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = Ticket::with([
            'creatore:id,name,cognome,email',
            'assegnato:id,name,cognome,email',
            'categoria:id,nome,descrizione',
            'allegati:id,attachable_id,attachable_type,nome_originale,mime_type,size,created_at'
        ]);

        // Filtro basato sul ruolo dell'utente
        switch ($user->role) {
            case 'admin':
                // Admin vede tutti i ticket
                break;

            case 'collaboratore':
                // Collaboratore vede solo i ticket assegnati a lui
                $query->where('assegnato_a', $user->id);
                break;

            case 'cliente':
                // Cliente vede solo i suoi ticket
                $query->where('creato_da', $user->id);
                break;

            default:
                // Ruolo non riconosciuto, nessun ticket
                return response()->json([
                    'tickets' => [],
                    'count' => 0,
                    'message' => 'Ruolo utente non autorizzato'
                ]);
        }

        $tickets = $query->orderBy('updated_at', 'desc')->get();

        // Trasformazione dei dati per l'API
        $ticketsData = $tickets->map(function ($ticket) {
            return [
                'id' => $ticket->id,
                'oggetto' => $ticket->oggetto,
                'corpo' => $ticket->corpo,
                'stato' => $ticket->stato,
                'created_at' => $ticket->created_at,
                'updated_at' => $ticket->updated_at,

                // Informazioni creatore
                'creatore' => $ticket->creatore ? [
                    'id' => $ticket->creatore->id,
                    'nome' => $ticket->creatore->name,
                    'cognome' => $ticket->creatore->cognome,
                    'email' => $ticket->creatore->email,
                    'nome_completo' => $ticket->creatore->name . ' ' . $ticket->creatore->cognome
                ] : null,

                // Informazioni assegnato
                'assegnato' => $ticket->assegnato ? [
                    'id' => $ticket->assegnato->id,
                    'nome' => $ticket->assegnato->name,
                    'cognome' => $ticket->assegnato->cognome,
                    'email' => $ticket->assegnato->email,
                    'nome_completo' => $ticket->assegnato->name . ' ' . $ticket->assegnato->cognome
                ] : null,

                // Informazioni categoria
                'categoria' => $ticket->categoria ? [
                    'id' => $ticket->categoria->id,
                    'nome' => $ticket->categoria->nome,
                    'descrizione' => $ticket->categoria->descrizione
                ] : null,

                // Conteggio allegati
                'allegati_count' => $ticket->allegati->count(),
                'has_allegati' => $ticket->allegati->count() > 0,

                // Allegati (se presenti)
                'allegati' => $ticket->allegati->map(function ($allegato) {
                    return [
                        'id' => $allegato->id,
                        'nome_originale' => $allegato->nome_originale,
                        'mime_type' => $allegato->mime_type,
                        'size' => $allegato->size,
                        'size_formatted' => number_format($allegato->size / 1024, 2) . ' KB',
                        'created_at' => $allegato->created_at,
                        'download_url' => route('ticket.download-attachment', $allegato->id)
                    ];
                })
            ];
        });

        return response()->json([
            'tickets' => $ticketsData,
            'count' => $tickets->count(),
            'user_role' => $user->role,
            'message' => $tickets->count() > 0 ? 'Ticket recuperati con successo' : 'Nessun ticket trovato'
        ]);
    }

    /**
     * Crea un nuovo ticket
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function createTicket(Request $request): JsonResponse
    {
        $user = $request->user();

        // Validazione dei dati
        $validated = $request->validate([
            'oggetto' => 'required|string|max:255',
            'corpo' => 'required|string',
            'categoria_id' => 'required|exists:categories,id',
            'allegati' => 'sometimes|array|max:5', // Ridotto a 5 per mobile
            'allegati.*' => [
                'file',
                'max:10240', // 10MB max
                'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain'
            ],
        ], [
            'allegati.*.mimes' => 'I file devono essere: immagini (JPG, PNG, GIF, WebP), PDF o documenti Office (DOC, DOCX, XLS, XLSX, TXT)',
            'allegati.*.mimetypes' => 'Tipo di file non supportato',
            'allegati.*.max' => 'Ogni file non può superare i 10MB',
            'allegati.max' => 'Puoi caricare massimo 5 allegati',
        ]);

        try {
            // Creazione del ticket
            $ticket = Ticket::create([
                'oggetto' => $validated['oggetto'],
                'corpo' => $validated['corpo'],
                'categoria_id' => $validated['categoria_id'],
                'creato_da' => $user->id,
                'stato' => 'nuovo',
                // Se l'utente è admin o collaboratore può auto-assegnarsi
                'assegnato_a' => in_array($user->role, ['admin', 'collaboratore']) ? $user->id : null,
            ]);

            // Gestione allegati
            $allegatiCreati = [];
            if ($request->hasFile('allegati')) {
                foreach ($request->file('allegati') as $file) {
                    // Salva il file
                    $path = $file->store('ticket-attachments', 'public');

                    // Crea il record dell'allegato
                    $allegato = $ticket->allegati()->create([
                        'nome_originale' => $file->getClientOriginalName(),
                        'filename' => basename($path),
                        'path' => $path,
                        'mime_type' => $file->getMimeType(),
                        'size' => $file->getSize(),
                        'uploaded_by' => $user->id,
                    ]);

                    $allegatiCreati[] = [
                        'id' => $allegato->id,
                        'nome_originale' => $allegato->nome_originale,
                        'mime_type' => $allegato->mime_type,
                        'size' => $allegato->size,
                        'size_formatted' => number_format($allegato->size / 1024, 2) . ' KB',
                    ];
                }
            }

            // Carica le relazioni per la risposta
            $ticket->load([
                'creatore:id,name,cognome,email',
                'assegnato:id,name,cognome,email',
                'categoria:id,nome,descrizione'
            ]);

            return response()->json([
                'message' => 'Ticket creato con successo',
                'ticket' => [
                    'id' => $ticket->id,
                    'oggetto' => $ticket->oggetto,
                    'corpo' => $ticket->corpo,
                    'stato' => $ticket->stato,
                    'created_at' => $ticket->created_at,
                    'creatore' => [
                        'id' => $ticket->creatore->id,
                        'nome' => $ticket->creatore->name,
                        'cognome' => $ticket->creatore->cognome,
                        'email' => $ticket->creatore->email,
                        'nome_completo' => $ticket->creatore->name . ' ' . $ticket->creatore->cognome
                    ],
                    'assegnato' => $ticket->assegnato ? [
                        'id' => $ticket->assegnato->id,
                        'nome' => $ticket->assegnato->name,
                        'cognome' => $ticket->assegnato->cognome,
                        'email' => $ticket->assegnato->email,
                        'nome_completo' => $ticket->assegnato->name . ' ' . $ticket->assegnato->cognome
                    ] : null,
                    'categoria' => [
                        'id' => $ticket->categoria->id,
                        'nome' => $ticket->categoria->nome,
                        'descrizione' => $ticket->categoria->descrizione
                    ],
                    'allegati' => $allegatiCreati,
                    'allegati_count' => count($allegatiCreati)
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Errore durante la creazione del ticket',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restituisce le categorie disponibili per i ticket
     *
     * @return JsonResponse
     */
    public function getCategories(): JsonResponse
    {
        $categories = \App\Models\Category::where('attiva', true)
            ->select('id', 'nome', 'descrizione')
            ->orderBy('nome')
            ->get();

        return response()->json([
            'categories' => $categories,
            'count' => $categories->count()
        ]);
    }

    /**
     * Restituisce i dettagli di un singolo ticket
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function showTicket(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        $query = Ticket::with([
            'creatore:id,name,cognome,email',
            'assegnato:id,name,cognome,email',
            'categoria:id,nome,descrizione',
            'allegati:id,attachable_id,attachable_type,nome_originale,mime_type,size,created_at',
            'discussioni' => function($query) use ($user) {
                // Se è un cliente, mostra solo discussioni non interne
                if ($user->role === 'cliente') {
                    $query->where('interno', false);
                }
                $query->with('user:id,name,cognome,email')->orderBy('created_at', 'asc');
            },
            'discussioni.allegati:id,attachable_id,attachable_type,nome_originale,mime_type,size,created_at'
        ]);

        // Filtro di autorizzazione basato sul ruolo
        switch ($user->role) {
            case 'admin':
                // Admin può vedere tutti i ticket
                break;

            case 'collaboratore':
                // Collaboratore può vedere solo ticket assegnati a lui
                $query->where('assegnato_a', $user->id);
                break;

            case 'cliente':
                // Cliente può vedere solo i suoi ticket
                $query->where('creato_da', $user->id);
                break;

            default:
                return response()->json([
                    'error' => 'Ruolo utente non autorizzato'
                ], 403);
        }

        $ticket = $query->find($id);

        if (!$ticket) {
            return response()->json([
                'error' => 'Ticket non trovato o non autorizzato'
            ], 404);
        }

        // Formattazione dettagliata del ticket
        $ticketData = [
            'id' => $ticket->id,
            'oggetto' => $ticket->oggetto,
            'corpo' => $ticket->corpo,
            'stato' => $ticket->stato,
            'created_at' => $ticket->created_at,
            'updated_at' => $ticket->updated_at,

            'creatore' => $ticket->creatore ? [
                'id' => $ticket->creatore->id,
                'nome' => $ticket->creatore->name,
                'cognome' => $ticket->creatore->cognome,
                'email' => $ticket->creatore->email,
                'nome_completo' => $ticket->creatore->name . ' ' . $ticket->creatore->cognome
            ] : null,

            'assegnato' => $ticket->assegnato ? [
                'id' => $ticket->assegnato->id,
                'nome' => $ticket->assegnato->name,
                'cognome' => $ticket->assegnato->cognome,
                'email' => $ticket->assegnato->email,
                'nome_completo' => $ticket->assegnato->name . ' ' . $ticket->assegnato->cognome
            ] : null,

            'categoria' => $ticket->categoria ? [
                'id' => $ticket->categoria->id,
                'nome' => $ticket->categoria->nome,
                'descrizione' => $ticket->categoria->descrizione
            ] : null,

            'allegati' => $ticket->allegati->map(function ($allegato) {
                return [
                    'id' => $allegato->id,
                    'nome_originale' => $allegato->nome_originale,
                    'mime_type' => $allegato->mime_type,
                    'size' => $allegato->size,
                    'size_formatted' => number_format($allegato->size / 1024, 2) . ' KB',
                    'created_at' => $allegato->created_at,
                    'download_url' => route('ticket.download-attachment', $allegato->id)
                ];
            }),

            'discussioni' => $ticket->discussioni->map(function ($discussione) {
                return [
                    'id' => $discussione->id,
                    'messaggio' => $discussione->messaggio,
                    'interno' => $discussione->interno,
                    'created_at' => $discussione->created_at,
                    'user' => [
                        'id' => $discussione->user->id,
                        'nome' => $discussione->user->name,
                        'cognome' => $discussione->user->cognome,
                        'email' => $discussione->user->email,
                        'nome_completo' => $discussione->user->name . ' ' . $discussione->user->cognome
                    ],
                    'allegati' => $discussione->allegati->map(function ($allegato) {
                        return [
                            'id' => $allegato->id,
                            'nome_originale' => $allegato->nome_originale,
                            'mime_type' => $allegato->mime_type,
                            'size' => $allegato->size,
                            'size_formatted' => number_format($allegato->size / 1024, 2) . ' KB',
                            'created_at' => $allegato->created_at,
                            'download_url' => route('ticket.download-attachment', $allegato->id)
                        ];
                    })
                ];
            })
        ];

        return response()->json([
            'ticket' => $ticketData,
            'user_role' => $user->role
        ]);
    }
}
