<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('matieres', 'volume_horaire')) {
            Schema::table('matieres', function (Blueprint $table) {
                $table->decimal('volume_horaire', 6, 2)->default(0)->after('coefficient');
            });
        }
    }

    public function down(): void
    {
        Schema::table('matieres', function (Blueprint $table) {
            $table->dropColumn('volume_horaire');
        });
    }
};
