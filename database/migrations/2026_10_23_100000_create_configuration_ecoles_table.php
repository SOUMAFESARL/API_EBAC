<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configuration_ecoles', function (Blueprint $table) {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('nom_ecole', 180);
            $table->string('code_ecole', 50);
            foreach (['slogan', 'adresse', 'fondateur', 'directeur_actuel', 'situation_geographique', 'ville', 'region'] as $champ) {
                $table->string($champ)->nullable();
            }
            $table->string('email', 150)->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('cellulaire', 30)->nullable();
            $table->text('biographie')->nullable();
            foreach (['logo', 'favicon', 'signature', 'signature_directeur'] as $champ) {
                $table->string($champ)->nullable();
            }
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configuration_ecoles');
    }
};
