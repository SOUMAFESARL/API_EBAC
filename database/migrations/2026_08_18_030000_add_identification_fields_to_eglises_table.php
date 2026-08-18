<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('eglises', function (Blueprint $table) {
            $table->string('sigle', 30)->nullable()->unique()->after('nom');
            $table->string('pasteur_principal', 180)->nullable()->after('sigle');
        });
    }

    public function down(): void
    {
        Schema::table('eglises', function (Blueprint $table) {
            $table->dropUnique(['sigle']);
            $table->dropColumn(['sigle', 'pasteur_principal']);
        });
    }
};
