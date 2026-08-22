<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('promotions', 'rang') && ! Schema::hasColumn('promotions', 'num_promotion')) {
            Schema::table('promotions', function (Blueprint $table) {
                $table->renameColumn('rang', 'num_promotion');
            });
        } elseif (Schema::hasColumn('promotions', 'rang')) {
            Schema::table('promotions', function (Blueprint $table) {
                $table->dropColumn('rang');
            });
        }

        if (Schema::hasColumn('promotions', 'capacite')) {
            Schema::table('promotions', function (Blueprint $table) {
                $table->dropColumn('capacite');
            });
        }
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->renameColumn('num_promotion', 'rang');
            $table->unsignedSmallInteger('capacite')->nullable();
        });
    }
};
