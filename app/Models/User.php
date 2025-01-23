<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Filament\Panel;
use Filament\Models\Contracts\FilamentUser;

class User extends Authenticatable implements FilamentUser
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'nome',
        'cognome',
        'email',
        'password',
        'azienda',
        'partita_iva',
        'telefono',
        'tag',
        'role'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        // Permette l'accesso al pannello admin solo ad admin e collaboratori
        if ($panel->getId() === 'admin') {
            return in_array($this->role, ['admin', 'collaboratore']);
        }

        // Permette l'accesso al pannello customer solo ai clienti
        if ($panel->getId() === 'customer') {
            return $this->role === 'cliente';
        }

        return false;
    }
}
