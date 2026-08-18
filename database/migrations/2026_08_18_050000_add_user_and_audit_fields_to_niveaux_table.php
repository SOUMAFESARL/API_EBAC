<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('niveaux', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('user_code', 150)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();

            $table->index('user_code');
        });
    }

    public function down(): void
    {
        Schema::table('niveaux', function (Blueprint $table) {
            $table->dropIndex(['user_code']);
            $table->dropConstrainedForeignId('deleted_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('user_id');
            $table->dropColumn(['user_code', 'deleted_at']);
        });
    }
};
