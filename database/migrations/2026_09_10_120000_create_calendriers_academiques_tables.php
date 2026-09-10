<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendriers_academiques', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_annee_academique')->unique()->constrained('annees_academiques')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
        Schema::create('modules_calendrier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_calendrier')->constrained('calendriers_academiques')->cascadeOnDelete();
            $table->string('libelle', 180);
            $table->unsignedSmallInteger('ordre');
            $table->date('date_debut');
            $table->date('date_fin');
            $table->timestamps();
            $table->unique(['id_calendrier', 'ordre']);
        });
        Schema::create('evenements_calendrier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_calendrier')->constrained('calendriers_academiques')->cascadeOnDelete();
            $table->foreignId('id_module_calendrier')->nullable()->constrained('modules_calendrier')->cascadeOnDelete();
            $table->string('type', 30);
            $table->string('libelle', 180)->nullable();
            $table->date('date_debut');
            $table->date('date_fin');
            $table->timestamps();
            $table->index(['id_calendrier', 'type', 'date_debut']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evenements_calendrier');
        Schema::dropIfExists('modules_calendrier');
        Schema::dropIfExists('calendriers_academiques');
    }
};
