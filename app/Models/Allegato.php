<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Allegato extends Model
{
    use HasFactory;

    protected $fillable = [
        'nome_originale',
        'filename',
        'path',
        'mime_type',
        'size',
        'uploaded_by'
    ];

    public function attachable()
    {
        return $this->morphTo();
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
