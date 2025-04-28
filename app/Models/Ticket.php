<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Ticket extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'oggetto',
        'corpo',
        'creato_da',
        'assegnato_a',
        'categoria_id',
        'stato'
    ];

    public function creatore()
    {
        return $this->belongsTo(User::class, 'creato_da');
    }

    public function assegnato()
    {
        return $this->belongsTo(User::class, 'assegnato_a');
    }

    public function categoria()
    {
        return $this->belongsTo(Category::class);
    }

    public function discussioni()
    {
        return $this->hasMany(Discussione::class);
    }

    public function discussioni_pubbliche()
    {
        return $this->hasMany(Discussione::class)->where('interno', false);
    }

    public function allegati()
    {
        return $this->morphMany(Allegato::class, 'attachable');
    }
}
