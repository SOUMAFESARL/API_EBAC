<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('matiere_module_calendrier', function (Blueprint $table) {
            $table->foreignId('id_matiere')->constrained('matieres')->cascadeOnDelete();
            $table->foreignId('id_module_calendrier')->constrained('modules_calendrier')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['id_matiere', 'id_module_calendrier'], 'matiere_module_calendrier_primary');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matiere_module_calendrier');
    }
};
