<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('etudiants', function (Blueprint $table) {
            $table->dropColumn('sexe');
        });

        Schema::table('etudiants', function (Blueprint $table) {
            $table->foreignId('civilite_id')
                ->nullable()
                ->after('prenoms')
                ->constrained('civilite')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('etudiants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('civilite_id');
            $table->string('sexe', 20)->nullable()->after('prenoms');
        });
    }
};
