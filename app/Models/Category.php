<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome',
        'descrizione',
        'attiva'
    ];

    public function tickets()
    {
        return $this->hasMany(Ticket::class);
    }
}
