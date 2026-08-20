<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('matieres')) {
            return;
        }

        Schema::create('matieres', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('libelle', 180);
            $table->foreignId('id_niveau')->constrained('niveaux')->restrictOnDelete();
            $table->decimal('coefficient', 5, 2)->default(1);
            $table->string('type', 50)->nullable();
            $table->text('description')->nullable();
            $table->text('objectifs')->nullable();
            $table->text('prerequis')->nullable();
            $table->decimal('note_validation', 5, 2)->default(10);
            $table->boolean('obligatoire')->default(true);
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('id_niveau');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('matieres');
    }
};
