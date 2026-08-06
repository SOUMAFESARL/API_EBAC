<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('code', 150);
            $table->string('user_code', 150);
            $table->string('user_id', 150);
            $table->string('nom', 150);
            $table->string('prenoms', 150);
            $table->string('email', 150)->unique();
            $table->string('password');
            $table->foreignId('id_role');
            $table->enum('is_active', ['1', '0'])->default('1');
            $table->enum('statut', ['Actif', 'Suspendu', 'Bloqué', 'Désactivé'])->default('Actif');
            $table->tinyInteger('tentatives_echouees')->default(0);
            $table->boolean('deux_fa_active')->default(false);
            $table->dateTime('cree_le')->useCurrent();
            $table->dateTime('derniere_connexion')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->foreignId('deleted_by')->nullable()->constrained('users');
            $table->dateTime('deleted_at')->nullable();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('users');
    }
};
