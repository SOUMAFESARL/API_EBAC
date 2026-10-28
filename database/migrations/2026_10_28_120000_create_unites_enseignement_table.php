<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('unites_enseignement', function (Blueprint $table) {
            $table->id();
            $table->string('libelle', 180);
            $table->string('code', 50)->unique();
            $table->text('description')->nullable();
            $table->enum('statut', ['Actif', 'Archive']);
            foreach (['user_id', 'created_by', 'updated_by', 'deleted_by'] as $champ) {
                $table->foreignId($champ)->nullable()->constrained('users')->nullOnDelete();
            }
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('unites_enseignement');
    }
};
