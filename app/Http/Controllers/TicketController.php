<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Allegato;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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

    /**
     * Aggiorna un ticket esistente
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function updateTicket(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Trova il ticket
        $ticket = Ticket::with([
            'creatore:id,name,cognome,email',
            'assegnato:id,name,cognome,email',
            'categoria:id,nome,descrizione'
        ])->find($id);

        if (!$ticket) {
            return response()->json([
                'error' => 'Ticket non trovato'
            ], 404);
        }

        // Verifica autorizzazioni per modificare il ticket
        if (!$this->canEditTicket($ticket, $user)) {
            return response()->json([
                'error' => 'Non autorizzato a modificare questo ticket'
            ], 403);
        }

        // Validazione dei dati
        $rules = [
            'oggetto' => 'sometimes|string|max:255',
            'corpo' => 'sometimes|string',
            'categoria_id' => 'sometimes|exists:categories,id',
            'stato' => 'sometimes|in:nuovo,aperto,in_lavorazione,in_attesa,risolto,chiuso',
            'assegnato_a' => 'sometimes|nullable|exists:users,id',
            'allegati' => 'sometimes|array|max:5',
            'allegati.*' => [
                'file',
                'max:10240', // 10MB max
                'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain'
            ],
            'rimuovi_allegati' => 'sometimes|array',
            'rimuovi_allegati.*' => 'integer|exists:allegati,id'
        ];

        // Regole specifiche per ruolo
        if ($user->role === 'cliente') {
            // I clienti possono modificare solo oggetto e corpo dei propri ticket
            $rules = array_intersect_key($rules, array_flip(['oggetto', 'corpo', 'allegati', 'rimuovi_allegati']));

            // E solo se il ticket è in stato 'nuovo' o 'aperto'
            if (!in_array($ticket->stato, ['nuovo', 'aperto'])) {
                return response()->json([
                    'error' => 'Non puoi modificare un ticket in questo stato'
                ], 403);
            }
        }

        $validated = $request->validate($rules, [
            'allegati.*.mimes' => 'I file devono essere: immagini (JPG, PNG, GIF, WebP), PDF o documenti Office',
            'allegati.*.max' => 'Ogni file non può superare i 10MB',
            'allegati.max' => 'Puoi caricare massimo 5 allegati',
        ]);

        try {
            // Prepara i dati per l'aggiornamento
            $updateData = [];

            // Campi base che tutti possono modificare (con le dovute autorizzazioni)
            if (isset($validated['oggetto'])) {
                $updateData['oggetto'] = $validated['oggetto'];
            }

            if (isset($validated['corpo'])) {
                $updateData['corpo'] = $validated['corpo'];
            }

            if (isset($validated['categoria_id'])) {
                $updateData['categoria_id'] = $validated['categoria_id'];
            }

            // Campi che solo admin/collaboratori possono modificare
            if (in_array($user->role, ['admin', 'collaboratore'])) {
                if (isset($validated['stato'])) {
                    $updateData['stato'] = $validated['stato'];
                }

                if (array_key_exists('assegnato_a', $validated)) {
                    // Verifica che l'utente assegnato sia admin o collaboratore
                    if ($validated['assegnato_a'] !== null) {
                        $assignee = User::find($validated['assegnato_a']);
                        if (!$assignee || !in_array($assignee->role, ['admin', 'collaboratore'])) {
                            return response()->json([
                                'error' => 'Puoi assegnare il ticket solo ad admin o collaboratori'
                            ], 422);
                        }
                    }
                    $updateData['assegnato_a'] = $validated['assegnato_a'];
                }
            }

            // Aggiorna il ticket
            if (!empty($updateData)) {
                $ticket->update($updateData);
            }

            // Gestione rimozione allegati
            if (isset($validated['rimuovi_allegati']) && !empty($validated['rimuovi_allegati'])) {
                foreach ($validated['rimuovi_allegati'] as $allegatoId) {
                    $allegato = $ticket->allegati()->find($allegatoId);
                    if ($allegato && $this->canDeleteAttachment($allegato, $user)) {
                        // Elimina il file fisico
                        if (Storage::disk('public')->exists($allegato->path)) {
                            Storage::disk('public')->delete($allegato->path);
                        }
                        // Elimina il record
                        $allegato->delete();
                    }
                }
            }

            // Gestione nuovi allegati
            $nuoviAllegati = [];
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

                    $nuoviAllegati[] = [
                        'id' => $allegato->id,
                        'nome_originale' => $allegato->nome_originale,
                        'mime_type' => $allegato->mime_type,
                        'size' => $allegato->size,
                        'size_formatted' => number_format($allegato->size / 1024, 2) . ' KB',
                    ];
                }
            }

            // Ricarica il ticket con le nuove relazioni
            $ticket->refresh();
            $ticket->load([
                'creatore:id,name,cognome,email',
                'assegnato:id,name,cognome,email',
                'categoria:id,nome,descrizione',
                'allegati'
            ]);

            return response()->json([
                'message' => 'Ticket aggiornato con successo',
                'ticket' => [
                    'id' => $ticket->id,
                    'oggetto' => $ticket->oggetto,
                    'corpo' => $ticket->corpo,
                    'stato' => $ticket->stato,
                    'created_at' => $ticket->created_at,
                    'updated_at' => $ticket->updated_at,
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
                    'allegati_count' => $ticket->allegati->count(),
                    'nuovi_allegati' => $nuoviAllegati
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Errore durante l\'aggiornamento del ticket',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verifica se l'utente può modificare il ticket
     *
     * @param Ticket $ticket
     * @param User $user
     * @return bool
     */
    private function canEditTicket(Ticket $ticket, User $user): bool
    {
        switch ($user->role) {
            case 'admin':
                return true; // Admin può modificare qualsiasi ticket

            case 'collaboratore':
                return $ticket->assegnato_a === $user->id; // Solo ticket assegnati

            case 'cliente':
                return $ticket->creato_da === $user->id; // Solo propri ticket

            default:
                return false;
        }
    }

    /**
     * Verifica se l'utente può eliminare l'allegato
     *
     * @param Allegato $allegato
     * @param User $user
     * @return bool
     */
    private function canDeleteAttachment(Allegato $allegato, User $user): bool
    {
        // Admin può eliminare qualsiasi allegato
        if ($user->role === 'admin') {
            return true;
        }

        // Se è un allegato del ticket
        if ($allegato->attachable_type === 'App\Models\Ticket') {
            $ticket = $allegato->attachable;

            // Collaboratore può eliminare allegati dei ticket assegnati
            if ($user->role === 'collaboratore' && $ticket->assegnato_a === $user->id) {
                return true;
            }

            // Cliente può eliminare allegati dei propri ticket (solo se caricati da lui)
            if ($user->role === 'cliente' && $ticket->creato_da === $user->id && $allegato->uploaded_by === $user->id) {
                return true;
            }
        }

        return false;
    }


    /**
     * Crea una nuova discussione/risposta per un ticket
     *
     * @param Request $request
     * @param int $idticket
     * @return JsonResponse
     */
    public function createDiscussion(Request $request, int $idticket): JsonResponse
    {
        $user = $request->user();

        // Trova il ticket
        $ticket = Ticket::with([
            'creatore:id,name,cognome,email',
            'assegnato:id,name,cognome,email',
            'categoria:id,nome,descrizione'
        ])->find($idticket);

        if (!$ticket) {
            return response()->json([
                'error' => 'Ticket non trovato'
            ], 404);
        }

        // Verifica autorizzazioni per rispondere al ticket
        if (!$this->canReplyToTicket($ticket, $user)) {
            return response()->json([
                'error' => 'Non autorizzato a rispondere a questo ticket'
            ], 403);
        }
        // Validazione dei dati
        $rules = [
            'messaggio' => 'required|string|min:3',
            'interno' => 'sometimes|boolean',
            'allegati' => 'sometimes|array|max:5',
            'allegati.*' => [
                'file',
                'max:10240', // 10MB max
                'mimes:jpg,jpeg,png,gif,webp,pdf,doc,docx,xls,xlsx,txt',
                'mimetypes:image/jpeg,image/png,image/gif,image/webp,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-excel,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/plain'
            ],
            // Campi opzionali per admin/collaboratori
            'nuovo_stato' => 'sometimes|in:nuovo,aperto,in_lavorazione,in_attesa,risolto,chiuso',
            'assegna_a' => 'sometimes|nullable|exists:users,id'
        ];

        // Regole specifiche per ruolo
        if ($user->role === 'cliente') {
            // I clienti non possono creare note interne
            $rules['interno'] = 'sometimes|boolean|in:false,0';
            // I clienti non possono cambiare stato o assegnazione
            unset($rules['nuovo_stato'], $rules['assegna_a']);
        }

        $validated = $request->validate($rules, [
            'messaggio.required' => 'Il messaggio è obbligatorio',
            'messaggio.min' => 'Il messaggio deve essere di almeno 3 caratteri',
            'allegati.*.mimes' => 'I file devono essere: immagini (JPG, PNG, GIF, WebP), PDF o documenti Office',
            'allegati.*.max' => 'Ogni file non può superare i 10MB',
            'allegati.max' => 'Puoi caricare massimo 5 allegati',
            'interno.in' => 'I clienti non possono creare note interne',
        ]);

        try {
            // Prepara i dati per la discussione
            $discussionData = [
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'messaggio' => $validated['messaggio'],
                'interno' => $validated['interno'] ?? false
            ];

            // I clienti non possono mai creare note interne
            if ($user->role === 'cliente') {
                $discussionData['interno'] = false;
            }

            // Crea la discussione
            $discussione = \App\Models\Discussione::create($discussionData);

            // Gestione allegati
            $allegatiCreati = [];
            if ($request->hasFile('allegati')) {
                foreach ($request->file('allegati') as $file) {
                    // Salva il file
                    $path = $file->store('discussion-attachments', 'public');

                    // Crea il record dell'allegato
                    $allegato = $discussione->allegati()->create([
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

            // Gestione cambiamenti stato/assegnazione (solo admin/collaboratori)
            $cambiamenti = [];
            if (in_array($user->role, ['admin', 'collaboratore'])) {

                // Cambio stato
                if (isset($validated['nuovo_stato']) && $validated['nuovo_stato'] !== $ticket->stato) {
                    $vecchioStato = $ticket->stato;
                    $ticket->update(['stato' => $validated['nuovo_stato']]);
                    $cambiamenti[] = "Stato cambiato da '{$vecchioStato}' a '{$validated['nuovo_stato']}'";
                }

                // Cambio assegnazione
                if (array_key_exists('assegna_a', $validated)) {
                    if ($validated['assegna_a'] !== null) {
                        // Verifica che l'utente assegnato sia admin o collaboratore
                        $nuovoAssegnato = \App\Models\User::find($validated['assegna_a']);
                        if (!$nuovoAssegnato || !in_array($nuovoAssegnato->role, ['admin', 'collaboratore'])) {
                            return response()->json([
                                'error' => 'Puoi assegnare il ticket solo ad admin o collaboratori'
                            ], 422);
                        }

                        $vecchioAssegnato = $ticket->assegnato ? $ticket->assegnato->nome_completo : 'Nessuno';
                        $ticket->update(['assegnato_a' => $validated['assegna_a']]);
                        $cambiamenti[] = "Ticket assegnato da '{$vecchioAssegnato}' a '{$nuovoAssegnato->nome_completo}'";

                    } else {
                        // Rimuovi assegnazione
                        $vecchioAssegnato = $ticket->assegnato ? $ticket->assegnato->nome_completo : 'Nessuno';
                        $ticket->update(['assegnato_a' => null]);
                        $cambiamenti[] = "Rimossa assegnazione da '{$vecchioAssegnato}'";
                    }
                } else {
                    // Se non specificato ma è una risposta di admin/collaboratore, apri automaticamente il ticket
                    if ($ticket->stato === 'nuovo') {
                        $ticket->update(['stato' => 'aperto']);
                        $cambiamenti[] = "Stato automaticamente cambiato da 'nuovo' a 'aperto'";
                    }
                }
            }

            // Ricarica ticket con relazioni aggiornate
            $ticket->refresh();
            $ticket->load([
                'creatore:id,name,cognome,email',
                'assegnato:id,name,cognome,email',
                'categoria:id,nome,descrizione'
            ]);

            // Carica la discussione con le relazioni
            $discussione->load(['user:id,name,cognome,email', 'allegati']);

            return response()->json([
                'message' => 'Risposta aggiunta con successo',
                'discussione' => [
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
                    'allegati' => $allegatiCreati,
                    'allegati_count' => count($allegatiCreati)
                ],
                'ticket_aggiornato' => [
                    'id' => $ticket->id,
                    'stato' => $ticket->stato,
                    'assegnato' => $ticket->assegnato ? [
                        'id' => $ticket->assegnato->id,
                        'nome' => $ticket->assegnato->name,
                        'cognome' => $ticket->assegnato->cognome,
                        'nome_completo' => $ticket->assegnato->name . ' ' . $ticket->assegnato->cognome
                    ] : null,
                    'updated_at' => $ticket->updated_at
                ],
                'cambiamenti' => $cambiamenti,
                'user_role' => $user->role
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Errore durante la creazione della risposta',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verifica se l'utente può rispondere al ticket
     *
     * @param Ticket $ticket
     * @param User $user
     * @return bool
     */
    private function canReplyToTicket(Ticket $ticket, User $user): bool
    {
        switch ($user->role) {
            case 'admin':
                return true; // Admin può rispondere a qualsiasi ticket

            case 'collaboratore':
                // Collaboratore può rispondere ai ticket assegnati a lui o non assegnati
                return $ticket->assegnato_a === $user->id || $ticket->assegnato_a === null;

            case 'cliente':
                // Cliente può rispondere solo ai propri ticket e solo se non sono chiusi
                return $ticket->creato_da === $user->id && $ticket->stato !== 'chiuso';

            default:
                return false;
        }
    }



// Aggiungi questo metodo al TicketController esistente

    /**
     * Elimina un ticket (soft delete)
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function deleteTicket(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Trova il ticket
        $ticket = Ticket::with([
            'creatore:id,name,cognome,email',
            'assegnato:id,name,cognome,email',
            'categoria:id,nome,descrizione',
            'allegati',
            'discussioni.allegati'
        ])->find($id);

        if (!$ticket) {
            return response()->json([
                'error' => 'Ticket non trovato'
            ], 404);
        }

        // Verifica autorizzazioni per eliminare il ticket
        if (!$this->canDeleteTicket($ticket, $user)) {
            return response()->json([
                'error' => 'Non autorizzato a eliminare questo ticket'
            ], 403);
        }

        // Validazione opzionale per conferma
        $request->validate([
            'conferma' => 'sometimes|boolean',
            'motivo' => 'sometimes|string|max:500',
            'elimina_file' => 'sometimes|boolean' // Se eliminare anche i file fisici
        ]);

        try {
            // Raccogli statistiche pre-eliminazione
            $stats = [
                'ticket_id' => $ticket->id,
                'oggetto' => $ticket->oggetto,
                'stato' => $ticket->stato,
                'creatore' => $ticket->creatore->nome_completo,
                'assegnato' => $ticket->assegnato ? $ticket->assegnato->nome_completo : 'Non assegnato',
                'categoria' => $ticket->categoria->nome,
                'allegati_count' => $ticket->allegati->count(),
                'discussioni_count' => $ticket->discussioni->count(),
                'created_at' => $ticket->created_at,
                'eliminato_da' => $user->nome_completo,
                'eliminato_il' => now(),
                'motivo' => $request->input('motivo', 'Nessun motivo specificato')
            ];

            // Gestione eliminazione file fisici (opzionale)
            $fileEliminati = [];
            if ($request->input('elimina_file', false)) {

                // Elimina allegati del ticket
                foreach ($ticket->allegati as $allegato) {
                    if (Storage::disk('public')->exists($allegato->path)) {
                        Storage::disk('public')->delete($allegato->path);
                        $fileEliminati[] = $allegato->nome_originale;
                    }
                }

                // Elimina allegati delle discussioni
                foreach ($ticket->discussioni as $discussione) {
                    foreach ($discussione->allegati as $allegato) {
                        if (Storage::disk('public')->exists($allegato->path)) {
                            Storage::disk('public')->delete($allegato->path);
                            $fileEliminati[] = $allegato->nome_originale;
                        }
                    }
                }
            }

            // Elimina il ticket (soft delete - mantiene i dati nel DB ma li marca come eliminati)
            $ticket->delete();

            // Log dell'eliminazione (opzionale - puoi salvare in una tabella di audit)
            \Log::info('Ticket eliminato', [
                'ticket_stats' => $stats,
                'file_eliminati' => $fileEliminati,
                'user_id' => $user->id,
                'ip' => $request->ip()
            ]);

            return response()->json([
                'message' => 'Ticket eliminato con successo',
                'ticket_eliminato' => [
                    'id' => $stats['ticket_id'],
                    'oggetto' => $stats['oggetto'],
                    'stato' => $stats['stato'],
                    'eliminato_da' => $stats['eliminato_da'],
                    'eliminato_il' => $stats['eliminato_il'],
                    'motivo' => $stats['motivo']
                ],
                'statistiche' => [
                    'allegati_rimossi' => count($fileEliminati),
                    'discussioni_archiviate' => $stats['discussioni_count'],
                    'file_fisici_eliminati' => $request->input('elimina_file', false)
                ],
                'file_eliminati' => $fileEliminati
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Errore durante l\'eliminazione del ticket',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ripristina un ticket eliminato (solo per admin)
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function restoreTicket(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Solo admin può ripristinare
        if ($user->role !== 'admin') {
            return response()->json([
                'error' => 'Solo gli amministratori possono ripristinare i ticket'
            ], 403);
        }

        // Trova il ticket eliminato
        $ticket = Ticket::withTrashed()->with([
            'creatore:id,name,cognome,email',
            'categoria:id,nome,descrizione'
        ])->find($id);

        if (!$ticket) {
            return response()->json([
                'error' => 'Ticket non trovato'
            ], 404);
        }

        if (!$ticket->trashed()) {
            return response()->json([
                'error' => 'Il ticket non è eliminato'
            ], 400);
        }

        try {
            // Ripristina il ticket
            $ticket->restore();

            // Log del ripristino
            \Log::info('Ticket ripristinato', [
                'ticket_id' => $ticket->id,
                'oggetto' => $ticket->oggetto,
                'ripristinato_da' => $user->nome_completo,
                'ripristinato_il' => now(),
                'user_id' => $user->id
            ]);

            return response()->json([
                'message' => 'Ticket ripristinato con successo',
                'ticket' => [
                    'id' => $ticket->id,
                    'oggetto' => $ticket->oggetto,
                    'stato' => $ticket->stato,
                    'creatore' => $ticket->creatore->nome_completo,
                    'categoria' => $ticket->categoria->nome,
                    'ripristinato_da' => $user->nome_completo,
                    'ripristinato_il' => now()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Errore durante il ripristino del ticket',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lista ticket eliminati (solo per admin)
     *
     * @param Request $request
     * @return JsonResponse
     */
    public function deletedTickets(Request $request): JsonResponse
    {
        $user = $request->user();

        // Solo admin può vedere ticket eliminati
        if ($user->role !== 'admin') {
            return response()->json([
                'error' => 'Solo gli amministratori possono vedere i ticket eliminati'
            ], 403);
        }

        $deletedTickets = Ticket::onlyTrashed()
            ->with([
                'creatore:id,name,cognome,email',
                'categoria:id,nome,descrizione'
            ])
            ->orderBy('deleted_at', 'desc')
            ->paginate(20);

        $ticketsData = $deletedTickets->map(function ($ticket) {
            return [
                'id' => $ticket->id,
                'oggetto' => $ticket->oggetto,
                'stato' => $ticket->stato,
                'creatore' => [
                    'nome_completo' => $ticket->creatore->nome_completo,
                    'email' => $ticket->creatore->email
                ],
                'categoria' => $ticket->categoria->nome,
                'created_at' => $ticket->created_at,
                'deleted_at' => $ticket->deleted_at,
                'giorni_dalla_eliminazione' => $ticket->deleted_at->diffInDays(now())
            ];
        });

        return response()->json([
            'tickets_eliminati' => $ticketsData,
            'pagination' => [
                'current_page' => $deletedTickets->currentPage(),
                'total_pages' => $deletedTickets->lastPage(),
                'total_count' => $deletedTickets->total(),
                'per_page' => $deletedTickets->perPage()
            ]
        ]);
    }

    /**
     * Verifica se l'utente può eliminare il ticket
     *
     * @param Ticket $ticket
     * @param User $user
     * @return bool
     */
    private function canDeleteTicket(Ticket $ticket, User $user): bool
    {
        switch ($user->role) {
            case 'admin':
                return true; // Admin può eliminare qualsiasi ticket

            case 'collaboratore':
                // Collaboratore può eliminare solo ticket assegnati a lui
                // E solo se non sono in stato 'chiuso' o 'risolto' (opzionale)
                return $ticket->assegnato_a === $user->id;

            case 'cliente':
                // Cliente può eliminare solo i propri ticket
                // E solo se sono in stato 'nuovo' o 'aperto' (per evitare perdita di lavoro)
                return $ticket->creato_da === $user->id &&
                    in_array($ticket->stato, ['nuovo', 'aperto']);

            default:
                return false;
        }
    }

    /**
     * Chiude un ticket
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function closeTicket(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Trova il ticket
        $ticket = Ticket::with([
            'creatore:id,name,cognome,email',
            'assegnato:id,name,cognome,email',
            'categoria:id,nome,descrizione'
        ])->find($id);

        if (!$ticket) {
            return response()->json([
                'error' => 'Ticket non trovato'
            ], 404);
        }

        // Verifica autorizzazioni per chiudere il ticket
        if (!$this->canChangeTicketStatus($ticket, $user)) {
            return response()->json([
                'error' => 'Non autorizzato a chiudere questo ticket'
            ], 403);
        }

        // Validazione dati opzionali
        $validated = $request->validate([
            'motivo_chiusura' => 'sometimes|string|max:500',
            'messaggio_finale' => 'sometimes|string|max:1000',
            'risoluzione' => 'sometimes|in:risolto,non_risolto,duplicato,non_valido',
            'valutazione_cliente' => 'sometimes|integer|min:1|max:5', // Solo per clienti
            'note_interne' => 'sometimes|string|max:500' // Solo per staff
        ]);

        // Verifica che il ticket non sia già chiuso
        if ($ticket->stato === 'chiuso') {
            return response()->json([
                'error' => 'Il ticket è già chiuso',
                'ticket' => [
                    'id' => $ticket->id,
                    'stato' => $ticket->stato,
                    'chiuso_il' => $ticket->updated_at
                ]
            ], 400);
        }

        try {
            $vecchioStato = $ticket->stato;

            // Aggiorna il ticket
            $ticket->update([
                'stato' => 'chiuso'
            ]);

            // Crea una discussione automatica per registrare la chiusura
            $messaggioChiusura = $this->generateCloseMessage(
                $user,
                $vecchioStato,
                $validated
            );

            $discussione = \App\Models\Discussione::create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'messaggio' => $messaggioChiusura,
                'interno' => $user->role !== 'cliente' && isset($validated['note_interne'])
            ]);

            // Log della chiusura
            \Log::info('Ticket chiuso', [
                'ticket_id' => $ticket->id,
                'oggetto' => $ticket->oggetto,
                'stato_precedente' => $vecchioStato,
                'chiuso_da' => $user->nome_completo,
                'motivo' => $validated['motivo_chiusura'] ?? 'Nessun motivo specificato',
                'risoluzione' => $validated['risoluzione'] ?? 'non_specificata'
            ]);

            return response()->json([
                'message' => 'Ticket chiuso con successo',
                'ticket' => [
                    'id' => $ticket->id,
                    'oggetto' => $ticket->oggetto,
                    'stato_precedente' => $vecchioStato,
                    'stato_attuale' => 'chiuso',
                    'chiuso_da' => [
                        'id' => $user->id,
                        'nome_completo' => $user->nome_completo,
                        'role' => $user->role
                    ],
                    'chiuso_il' => $ticket->updated_at,
                    'motivo_chiusura' => $validated['motivo_chiusura'] ?? null,
                    'risoluzione' => $validated['risoluzione'] ?? null,
                    'valutazione_cliente' => $validated['valutazione_cliente'] ?? null
                ],
                'discussione_creata' => [
                    'id' => $discussione->id,
                    'messaggio' => $discussione->messaggio,
                    'interno' => $discussione->interno
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Errore durante la chiusura del ticket',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Riapre un ticket chiuso
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function reopenTicket(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Trova il ticket
        $ticket = Ticket::with([
            'creatore:id,name,cognome,email',
            'assegnato:id,name,cognome,email',
            'categoria:id,nome,descrizione'
        ])->find($id);

        if (!$ticket) {
            return response()->json([
                'error' => 'Ticket non trovato'
            ], 404);
        }

        // Verifica autorizzazioni per riaprire il ticket
        if (!$this->canReopenTicket($ticket, $user)) {
            return response()->json([
                'error' => 'Non autorizzato a riaprire questo ticket'
            ], 403);
        }

        // Verifica che il ticket sia effettivamente chiuso
        if ($ticket->stato !== 'chiuso') {
            return response()->json([
                'error' => 'Il ticket non è chiuso',
                'stato_attuale' => $ticket->stato
            ], 400);
        }

        // Validazione
        $validated = $request->validate([
            'motivo_riapertura' => 'required|string|max:500',
            'nuovo_stato' => 'sometimes|in:aperto,in_lavorazione,in_attesa',
            'riassegna_a' => 'sometimes|nullable|exists:users,id'
        ]);

        try {
            $nuovoStato = $validated['nuovo_stato'] ?? 'aperto';

            // Aggiorna il ticket
            $updateData = ['stato' => $nuovoStato];

            // Gestione riassegnazione
            if (isset($validated['riassegna_a'])) {
                if ($validated['riassegna_a'] !== null) {
                    $nuovoAssegnato = \App\Models\User::find($validated['riassegna_a']);
                    if (!$nuovoAssegnato || !in_array($nuovoAssegnato->role, ['admin', 'collaboratore'])) {
                        return response()->json([
                            'error' => 'Puoi assegnare il ticket solo ad admin o collaboratori'
                        ], 422);
                    }
                    $updateData['assegnato_a'] = $validated['riassegna_a'];
                }
            }

            $ticket->update($updateData);

            // Crea discussione per documentare la riapertura
            $messaggioRiapertura = $this->generateReopenMessage($user, $validated);

            $discussione = \App\Models\Discussione::create([
                'ticket_id' => $ticket->id,
                'user_id' => $user->id,
                'messaggio' => $messaggioRiapertura,
                'interno' => false
            ]);

            // Log della riapertura
            Log::info('Ticket riaperto', [
                'ticket_id' => $ticket->id,
                'riaperto_da' => $user->nome_completo,
                'nuovo_stato' => $nuovoStato,
                'motivo' => $validated['motivo_riapertura']
            ]);

            return response()->json([
                'message' => 'Ticket riaperto con successo',
                'ticket' => [
                    'id' => $ticket->id,
                    'oggetto' => $ticket->oggetto,
                    'stato_precedente' => 'chiuso',
                    'stato_attuale' => $nuovoStato,
                    'riaperto_da' => [
                        'id' => $user->id,
                        'nome_completo' => $user->nome_completo
                    ],
                    'riaperto_il' => $ticket->updated_at,
                    'motivo_riapertura' => $validated['motivo_riapertura'],
                    'assegnato' => $ticket->assegnato ? [
                        'id' => $ticket->assegnato->id,
                        'nome_completo' => $ticket->assegnato->nome_completo
                    ] : null
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Errore durante la riapertura del ticket',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Cambia lo stato di un ticket (generico)
     *
     * @param Request $request
     * @param int $id
     * @return JsonResponse
     */
    public function changeTicketStatus(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        // Trova il ticket
        $ticket = Ticket::with([
            'creatore:id,name,cognome,email',
            'assegnato:id,name,cognome,email',
            'categoria:id,nome,descrizione'
        ])->find($id);

        if (!$ticket) {
            return response()->json([
                'error' => 'Ticket non trovato'
            ], 404);
        }

        // Verifica autorizzazioni
        if (!$this->canChangeTicketStatus($ticket, $user)) {
            return response()->json([
                'error' => 'Non autorizzato a cambiare lo stato di questo ticket'
            ], 403);
        }

        // Validazione
        $validated = $request->validate([
            'nuovo_stato' => 'required|in:nuovo,aperto,in_lavorazione,in_attesa,risolto,chiuso',
            'motivo' => 'sometimes|string|max:500',
            'messaggio' => 'sometimes|string|max:1000'
        ]);

        $vecchioStato = $ticket->stato;
        $nuovoStato = $validated['nuovo_stato'];

        // Verifica che il cambio di stato sia logico
        if (!$this->isValidStatusTransition($vecchioStato, $nuovoStato, $user)) {
            return response()->json([
                'error' => "Cambio di stato non valido da '{$vecchioStato}' a '{$nuovoStato}'"
            ], 400);
        }

        try {
            // Aggiorna il ticket
            $ticket->update(['stato' => $nuovoStato]);

            // Crea discussione per documentare il cambio
            if (isset($validated['messaggio']) || isset($validated['motivo'])) {
                $messaggio = $validated['messaggio'] ?? "Stato cambiato da '{$vecchioStato}' a '{$nuovoStato}'";
                if (isset($validated['motivo'])) {
                    $messaggio .= "\n\nMotivo: " . $validated['motivo'];
                }

                \App\Models\Discussione::create([
                    'ticket_id' => $ticket->id,
                    'user_id' => $user->id,
                    'messaggio' => $messaggio,
                    'interno' => $user->role !== 'cliente'
                ]);
            }

            return response()->json([
                'message' => "Stato cambiato con successo",
                'ticket' => [
                    'id' => $ticket->id,
                    'oggetto' => $ticket->oggetto,
                    'stato_precedente' => $vecchioStato,
                    'stato_attuale' => $nuovoStato,
                    'modificato_da' => $user->nome_completo,
                    'modificato_il' => $ticket->updated_at,
                    'motivo' => $validated['motivo'] ?? null
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Errore durante il cambio di stato',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verifica se l'utente può cambiare lo stato del ticket
     */
    private function canChangeTicketStatus(Ticket $ticket, User $user): bool
    {
        switch ($user->role) {
            case 'admin':
                return true;
            case 'collaboratore':
                return $ticket->assegnato_a === $user->id || $ticket->assegnato_a === null;
            case 'cliente':
                // I clienti possono solo chiudere i propri ticket se risolti
                return $ticket->creato_da === $user->id && $ticket->stato === 'risolto';
            default:
                return false;
        }
    }

    /**
     * Verifica se l'utente può riaprire il ticket
     */
    private function canReopenTicket(Ticket $ticket, User $user): bool
    {
        switch ($user->role) {
            case 'admin':
                return true;
            case 'collaboratore':
                return $ticket->assegnato_a === $user->id;
            case 'cliente':
                return $ticket->creato_da === $user->id;
            default:
                return false;
        }
    }

    /**
     * Verifica se la transizione di stato è valida
     */
    private function isValidStatusTransition(string $from, string $to, User $user): bool
    {
        // Definisci le transizioni valide
        $validTransitions = [
            'nuovo' => ['aperto', 'in_lavorazione', 'chiuso'],
            'aperto' => ['in_lavorazione', 'in_attesa', 'risolto', 'chiuso'],
            'in_lavorazione' => ['aperto', 'in_attesa', 'risolto', 'chiuso'],
            'in_attesa' => ['aperto', 'in_lavorazione', 'risolto', 'chiuso'],
            'risolto' => ['chiuso', 'aperto'], // Può essere riaperto se non risolto
            'chiuso' => $user->role === 'admin' ? ['aperto', 'in_lavorazione'] : []
        ];

        return in_array($to, $validTransitions[$from] ?? []);
    }

    /**
     * Genera il messaggio di chiusura
     */
    private function generateCloseMessage(User $user, string $oldStatus, array $data): string
    {
        $message = "🔒 <strong>Ticket chiuso</strong> da {$user->nome_completo}";
        $message .= "<br>Stato precedente: <em>{$oldStatus}</em>";

        if (isset($data['risoluzione'])) {
            $resolutionMap = [
                'risolto' => '✅ Risolto',
                'non_risolto' => '❌ Non risolto',
                'duplicato' => '🔄 Duplicato',
                'non_valido' => '⚠️ Non valido'
            ];
            $message .= "<br>Risoluzione: " . $resolutionMap[$data['risoluzione']];
        }

        if (isset($data['motivo_chiusura'])) {
            $message .= "<br><br><strong>Motivo:</strong> " . $data['motivo_chiusura'];
        }

        if (isset($data['messaggio_finale'])) {
            $message .= "<br><br>" . $data['messaggio_finale'];
        }

        if (isset($data['valutazione_cliente'])) {
            $stars = str_repeat('⭐', $data['valutazione_cliente']);
            $message .= "<br><br><strong>Valutazione cliente:</strong> {$stars} ({$data['valutazione_cliente']}/5)";
        }

        return $message;
    }

    /**
     * Genera il messaggio di riapertura
     */
    private function generateReopenMessage(User $user, array $data): string
    {
        $message = "🔓 <strong>Ticket riaperto</strong> da {$user->nome_completo}";
        $message .= "<br><br><strong>Motivo:</strong> " . $data['motivo_riapertura'];

        return $message;
    }
}
