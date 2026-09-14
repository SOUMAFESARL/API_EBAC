<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publication_programmes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_module_calendrier')->unique()->constrained('modules_calendrier')->cascadeOnDelete();
            $table->string('statut', 20)->default('non_publie')->index();
            $table->unsignedInteger('version')->default(0);
            $table->dateTime('date_publication')->nullable();
            $table->dateTime('date_retrait')->nullable();
            $table->foreignId('publie_par')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('retire_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_programmes');
    }
};
