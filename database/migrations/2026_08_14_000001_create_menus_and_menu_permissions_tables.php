<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_parent')->nullable()->constrained('menus')->cascadeOnDelete();
            $table->string('code', 100)->unique();
            $table->string('libelle', 150);
            $table->string('description')->nullable();
            $table->string('route', 180)->nullable();
            $table->string('route_active', 180)->nullable();
            $table->string('icone', 100)->nullable();
            $table->string('groupe', 100)->nullable();
            $table->unsignedSmallInteger('ordre')->default(0);
            $table->boolean('visible')->default(true);
            $table->boolean('actif')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            $table->foreignId('deleted_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();
            $table->index(['id_parent', 'ordre']);
            $table->index(['groupe', 'ordre']);
        });

        Schema::create('menu_permissions', function (Blueprint $table) {
            $table->foreignId('id_menu')->constrained('menus')->cascadeOnDelete();
            $table->foreignId('id_permission')->constrained('permissions')->cascadeOnDelete();
            $table->boolean('permission_principale')->default(false);
            $table->timestamp('created_at')->nullable();
            $table->primary(['id_menu', 'id_permission']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('menu_permissions');
        Schema::dropIfExists('menus');
    }
};
