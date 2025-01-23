<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        // Creazione Amministratore
        User::create([
            'name' => 'Admin',
            'cognome' => 'Sistema',
            'email' => 'admin@sitec.it',
            'password' => Hash::make('password'),
            'role' => 'admin',
            'telefono' => '0123456789',
        ]);

        // Creazione Collaboratore
        User::create([
            'name' => 'Mario',
            'cognome' => 'Rossi',
            'email' => 'collaboratore@sitec.it',
            'password' => Hash::make('password'),
            'role' => 'collaboratore',
            'telefono' => '0123456788',
            'tag' => 'supporto-tecnico',
        ]);

        // Creazione Cliente
        User::create([
            'name' => 'Giovanni',
            'cognome' => 'Bianchi',
            'email' => 'cliente@example.com',
            'password' => Hash::make('password'),
            'role' => 'cliente',
            'azienda' => 'Azienda Example Srl',
            'partita_iva' => '12345678901',
            'telefono' => '0123456787',
        ]);
    }
}
