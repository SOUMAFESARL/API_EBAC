<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->renameColumn('rang', 'num_promotion');
        });

        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn('capacite');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->renameColumn('num_promotion', 'rang');
            $table->unsignedSmallInteger('capacite')->nullable();
        });
    }
};
