<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salles', function (Blueprint $table) {
            $table->id();
            $table->string('nom', 180);
            $table->string('code', 30);
            $table->string('statut', 10)->default('Actif')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
            $table->unsignedTinyInteger('unicite_active')->nullable()->virtualAs('CASE WHEN deleted_at IS NULL THEN 1 ELSE NULL END');
            $table->unique(['code', 'unicite_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salles');
    }
};
