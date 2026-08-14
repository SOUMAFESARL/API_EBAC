<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('connexions_2fa', function (Blueprint $table) {
            $table->string('nom_appareil', 100)->default('api')->after('adresse_ip');
            $table->unsignedTinyInteger('tentatives')->default(0)->after('nom_appareil');
        });
    }

    public function down(): void
    {
        Schema::table('connexions_2fa', function (Blueprint $table) {
            $table->dropColumn(['nom_appareil', 'tentatives']);
        });
    }
};
