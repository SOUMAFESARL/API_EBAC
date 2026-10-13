<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programmations_rattrapage', function (Blueprint $table) {
            $table->foreignId('id_seance_rattrapage')->nullable()->constrained('seances_cahier_texte')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('programmations_rattrapage', fn (Blueprint $table) => $table->dropConstrainedForeignId('id_seance_rattrapage'));
    }
};
