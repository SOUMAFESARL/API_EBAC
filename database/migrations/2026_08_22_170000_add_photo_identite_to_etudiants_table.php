<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('etudiants', 'photo_identite')) {
            Schema::table('etudiants', function (Blueprint $table) {
                $table->string('photo_identite')->nullable()->after('statut_professionnel');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('etudiants', 'photo_identite')) {
            Schema::table('etudiants', function (Blueprint $table) {
                $table->dropColumn('photo_identite');
            });
        }
    }
};
