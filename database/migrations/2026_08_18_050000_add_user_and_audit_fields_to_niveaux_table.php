<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('niveaux', 'user_id')) {
            Schema::table('niveaux', fn (Blueprint $table) =>
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete()
            );
        }

        if (! Schema::hasColumn('niveaux', 'user_code')) {
            Schema::table('niveaux', function (Blueprint $table) {
                $table->string('user_code', 150)->nullable();
                $table->index('user_code');
            });
        }

        foreach (['created_by', 'updated_by', 'deleted_by'] as $colonne) {
            if (! Schema::hasColumn('niveaux', $colonne)) {
                Schema::table('niveaux', fn (Blueprint $table) =>
                    $table->foreignId($colonne)->nullable()->constrained('users')->nullOnDelete()
                );
            }
        }

        if (! Schema::hasColumn('niveaux', 'deleted_at')) {
            Schema::table('niveaux', fn (Blueprint $table) => $table->softDeletes());
        }
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
