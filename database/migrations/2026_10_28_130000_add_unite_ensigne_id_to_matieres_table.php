<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('matieres', function (Blueprint $table) {
            $table->foreignId('unite_ensigne_id')->nullable()->constrained('unites_enseignement')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('matieres', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unite_ensigne_id');
        });
    }
};
