<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Notifica extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'titolo',
        'testo',
        'categoria',
        'tags_destinatari',
        'inviata_email',
        'inviata_il'
    ];

    protected $casts = [
        'tags_destinatari' => 'array',
        'inviata_il' => 'datetime',
        'inviata_email' => 'boolean'
    ];

    public function utenti()
    {
        return $this->belongsToMany(User::class, 'notifica_user')
            ->withPivot('letto', 'letto_il')
            ->withTimestamps();
    }
}
