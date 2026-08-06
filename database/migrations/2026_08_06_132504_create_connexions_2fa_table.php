<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connexions_2fa', function (Blueprint $table) {
            $table->id('id_tentative');
            $table->foreignId('id_compte')->constrained('users');
            $table->string('code_otp_hash', 255);
            $table->enum('canal', ['SMS', 'Email'])->default('SMS');
            $table->dateTime('envoye_le')->useCurrent();
            $table->dateTime('valide_le')->nullable();
            $table->boolean('reussi')->nullable();
            $table->string('adresse_ip', 45)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->foreignId('deleted_by')->nullable()->constrained('users');
            $table->dateTime('deleted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connexions_2fa');
    }
};
