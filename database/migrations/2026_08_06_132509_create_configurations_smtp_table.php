<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('configurations_smtp', function (Blueprint $table) {
            $table->id();
            $table->string('host', 255);
            $table->unsignedSmallInteger('port')->default(465);
            $table->text('username');
            $table->text('password');
            $table->enum('scheme', ['smtp', 'smtps'])->default('smtps');
            $table->string('from_address', 255);
            $table->string('from_name', 255)->nullable();
            $table->boolean('actif')->default(true)->index();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->foreignId('deleted_by')->nullable()->constrained('users');
            $table->dateTime('deleted_at')->nullable();
            $table->dateTime('cree_le')->useCurrent();
            $table->dateTime('modifie_le')->nullable()->useCurrentOnUpdate();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('configurations_smtp');
    }
};
