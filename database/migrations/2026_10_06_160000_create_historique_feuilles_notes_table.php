<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('historique_feuilles_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_feuille_notes')->constrained('feuilles_notes')->cascadeOnDelete();
            $table->foreignId('id_acteur')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('statut_avant');
            $table->string('statut_apres');
            $table->text('motif')->nullable();
            $table->timestamp('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('historique_feuilles_notes');
    }
};
