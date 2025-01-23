<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('nome');
            $table->string('descrizione')->nullable();
            $table->boolean('attiva')->default(true);
            $table->timestamps();
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('oggetto');
            $table->text('corpo');
            $table->foreignId('creato_da')->constrained('users');
            $table->foreignId('assegnato_a')->nullable()->constrained('users');
            $table->foreignId('categoria_id')->nullable()->constrained('categories');
            $table->enum('stato', ['nuovo', 'aperto', 'in_lavorazione', 'in_attesa', 'risolto', 'chiuso'])
                ->default('nuovo');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('discussioni', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('tickets')->onDelete('cascade');
            $table->text('messaggio');
            $table->boolean('interno')->default(false);
            $table->foreignId('user_id')->constrained('users');
            $table->timestamps();
        });

        Schema::create('allegati', function (Blueprint $table) {
            $table->id();
            $table->morphs('attachable'); // Questo permette di collegare gli allegati sia ai ticket che alle discussioni
            $table->string('nome_originale');
            $table->string('filename');
            $table->string('path');
            $table->string('mime_type');
            $table->integer('size');
            $table->foreignId('uploaded_by')->constrained('users');
            $table->timestamps();
        });

        Schema::create('notifiche', function (Blueprint $table) {
            $table->id();
            $table->string('titolo');
            $table->text('testo');
            $table->string('categoria')->nullable();
            $table->json('tags_destinatari')->nullable()->comment('Array di tag a cui la notifica è destinata');
            $table->boolean('inviata_email')->default(false);
            $table->timestamp('inviata_il')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('notifica_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notifica_id')->constrained('notifiche')->onDelete('cascade');
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->boolean('letto')->default(false);
            $table->timestamp('letto_il')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('notifica_user');
        Schema::dropIfExists('notifiche');
        Schema::dropIfExists('allegati');
        Schema::dropIfExists('discussioni');
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('categories');
        Schema::dropIfExists('users');
    }
};


