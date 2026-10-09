<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('unicite_active')->nullable()
                ->virtualAs('CASE WHEN deleted_at IS NULL THEN 1 ELSE NULL END');
            $table->unique(['email', 'unicite_active'], 'users_email_actif_unique');
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropUnique('users_email_unique'));
    }

    public function down(): void
    {
        if (DB::table('users')->groupBy('email')->havingRaw('COUNT(*) > 1')->exists()) {
            throw new RuntimeException('Rollback impossible : des comptes partagent un email reutilise. Conserver leur historique avant de retablir la contrainte.');
        }
        Schema::table('users', function (Blueprint $table) {
            $table->unique('email');
            $table->dropUnique('users_email_actif_unique');
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('unicite_active'));
    }
};
