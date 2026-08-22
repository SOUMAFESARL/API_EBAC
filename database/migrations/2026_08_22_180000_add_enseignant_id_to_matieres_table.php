<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('matieres', 'enseignant_id')) {
            Schema::table('matieres', function (Blueprint $table) {
                $table->foreignId('enseignant_id')
                    ->nullable()
                    ->after('id_niveau')
                    ->constrained('users')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('matieres', 'enseignant_id')) {
            Schema::table('matieres', function (Blueprint $table) {
                $table->dropConstrainedForeignId('enseignant_id');
            });
        }
    }
};
