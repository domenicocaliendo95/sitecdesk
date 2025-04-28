<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Allegato extends Model
{
    use HasFactory;

    protected $table = 'allegati';

    protected $fillable = [
        'nome_originale',
        'filename',
        'path',
        'mime_type',
        'size',
        'uploaded_by',
        'attachable_id',
        'attachable_type'
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
