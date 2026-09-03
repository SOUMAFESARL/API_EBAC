<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etudiants', function (Blueprint $table) {
            $table->string('situation_matrimonial', 50)->nullable()->after('statut_professionnel');
            $table->unsignedSmallInteger('nombre_enfant')->nullable()->after('situation_matrimonial');
        });
    }

    public function down(): void
    {
        Schema::table('etudiants', function (Blueprint $table) {
            $table->dropColumn(['situation_matrimonial', 'nombre_enfant']);
        });
    }
};
