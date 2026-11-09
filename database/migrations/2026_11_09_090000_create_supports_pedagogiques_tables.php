<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supports_pedagogiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_cours')->constrained('cours');
            $table->string('nom', 180);
            $table->string('type', 30);
            $table->foreignId('depose_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('versions_supports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_support')->constrained('supports_pedagogiques')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->string('chemin');
            $table->string('nom_fichier');
            $table->string('format', 10);
            $table->unsignedBigInteger('taille_octets');
            $table->string('note', 300)->nullable();
            $table->foreignId('depose_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['id_support', 'numero']);
        });

        Schema::create('quotas_stockage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('enseignant_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('quota_mo');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('quotas_stockage');
        Schema::dropIfExists('versions_supports');
        Schema::dropIfExists('supports_pedagogiques');
    }
};
