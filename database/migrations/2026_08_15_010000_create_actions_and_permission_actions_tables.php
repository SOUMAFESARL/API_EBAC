<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('actions', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('libelle', 120);
            $table->string('description', 255)->nullable();
            $table->boolean('actif')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->foreignId('deleted_by')->nullable()->constrained('users');
            $table->softDeletes();
        });

        Schema::create('permission_actions', function (Blueprint $table) {
            $table->foreignId('id_permission')->constrained('permissions')->cascadeOnDelete();
            $table->foreignId('id_action')->constrained('actions')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->primary(['id_permission', 'id_action']);
        });

        DB::table('actions')->insert([
            ['code' => 'AJOUTER', 'libelle' => 'Ajouter', 'description' => 'Créer un nouvel enregistrement.', 'actif' => true],
            ['code' => 'SUPPRIMER', 'libelle' => 'Supprimer', 'description' => 'Supprimer un enregistrement.', 'actif' => true],
            ['code' => 'MODIFIER', 'libelle' => 'Modifier', 'description' => 'Modifier un enregistrement.', 'actif' => true],
            ['code' => 'VOIR', 'libelle' => 'Voir', 'description' => 'Consulter un enregistrement.', 'actif' => true],
            ['code' => 'IMPRIMER', 'libelle' => 'Imprimer', 'description' => 'Imprimer les informations.', 'actif' => true],
            ['code' => 'TELECHARGER', 'libelle' => 'Télécharger', 'description' => 'Télécharger un document ou des données.', 'actif' => true],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('permission_actions');
        Schema::dropIfExists('actions');
    }
};
