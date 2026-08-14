<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['created_by_user_id', 'created_by_user_code']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('created_by_user_id', 150)->nullable()->after('created_by');
            $table->string('created_by_user_code', 150)->nullable()->after('created_by_user_id');
        });
    }
};
