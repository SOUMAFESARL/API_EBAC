<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->foreignId('evaluation_autorisee_par')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('evaluation_autorisee_le')->nullable();
            $table->text('motif_autorisation_evaluation')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('presences', function (Blueprint $table) {
            $table->dropConstrainedForeignId('evaluation_autorisee_par');
            $table->dropColumn(['evaluation_autorisee_le', 'motif_autorisation_evaluation']);
        });
    }
};
