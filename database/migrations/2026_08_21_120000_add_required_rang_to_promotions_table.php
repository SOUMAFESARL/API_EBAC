<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->unsignedSmallInteger('rang')->nullable()->after('code');
        });

        DB::table('promotions')->orderBy('id')->each(function (object $promotion, int $index): void {
            DB::table('promotions')->where('id', $promotion->id)->update(['rang' => $index + 1]);
        });

        Schema::table('promotions', function (Blueprint $table) {
            $table->unsignedSmallInteger('rang')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn('rang');
        });
    }
};
