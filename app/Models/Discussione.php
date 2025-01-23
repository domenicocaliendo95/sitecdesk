<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Discussione extends Model
{
    use HasFactory;

    // Specifichiamo esplicitamente il nome della tabella
    protected $table = 'discussioni';

    protected $fillable = [
        'ticket_id',
        'messaggio',
        'interno',
        'user_id'
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function allegati()
    {
        return $this->morphMany(Allegato::class, 'attachable');
    }
}
