<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('imports_admissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('annee_entree')->index();
            $table->string('nom_fichier');
            $table->string('arrete_chemin')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });
        Schema::create('nouvelles_admissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_admission_id')->constrained('imports_admissions')->restrictOnDelete();
            $table->unsignedSmallInteger('annee_entree')->index();
            $table->string('nom_prenoms');
            $table->string('region')->nullable();
            $table->string('paroisse')->nullable();
            $table->string('situation_matrimoniale', 50)->nullable();
            $table->string('telephone', 30)->nullable();
            $table->string('adresse')->nullable();
            $table->string('email')->nullable();
            $table->boolean('dossier_depose')->default(false);
            $table->char('empreinte', 64);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['annee_entree', 'empreinte']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nouvelles_admissions');
        Schema::dropIfExists('imports_admissions');
    }
};
