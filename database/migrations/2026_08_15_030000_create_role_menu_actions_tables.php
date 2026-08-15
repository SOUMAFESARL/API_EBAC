<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('roles', function (Blueprint $table) {
            $table->boolean('actif')->default(true)->after('description');
        });

        Schema::create('menu_actions', function (Blueprint $table) {
            $table->foreignId('id_menu')->constrained('menus')->cascadeOnDelete();
            $table->foreignId('id_action')->constrained('actions')->cascadeOnDelete();
            $table->primary(['id_menu', 'id_action']);
        });

        Schema::create('role_menu_actions', function (Blueprint $table) {
            $table->foreignId('id_role')->constrained('roles')->cascadeOnDelete();
            $table->foreignId('id_menu')->constrained('menus')->cascadeOnDelete();
            $table->foreignId('id_action')->constrained('actions')->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->primary(['id_role', 'id_menu', 'id_action']);
            $table->index(['id_role', 'id_menu']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_menu_actions');
        Schema::dropIfExists('menu_actions');

        Schema::table('roles', function (Blueprint $table) {
            $table->dropColumn('actif');
        });
    }
};
