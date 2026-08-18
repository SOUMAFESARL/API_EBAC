<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('eglises', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('nom', 180);
            $table->string('denomination', 180)->nullable();
            $table->string('adresse', 255)->nullable();
            $table->string('region', 120)->nullable();
            $table->string('district', 120)->nullable();
            $table->string('ville_commune', 120);
            $table->string('telephone', 30)->nullable();
            $table->string('email', 150)->nullable();
            $table->enum('statut', ['Active', 'Suspendue', 'Archivée'])->default('Active');
            $table->unsignedSmallInteger('capacite_max_stagiaires')->default(0);
            $table->json('representants')->nullable();
            $table->text('observations')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_code', 150)->nullable()->comment('Copie de users.code du compte Église associé');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['statut', 'ville_commune']);
            $table->index('nom');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('eglises');
    }
};
