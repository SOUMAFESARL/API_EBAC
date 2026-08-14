<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $role = Role::query()->firstOrCreate(
            ['code' => 'ADMIN'],
            [
                'libelle' => 'Administrateur',
                'description' => 'Administrateur de la plateforme',
                'portee' => 'Globale',
            ],
        );

        User::factory()->create([
            'code' => 'USR-ADMIN',
            'user_code' => 'ADMIN',
            'user_id' => 'admin',
            'nom' => 'Administrateur',
            'prenoms' => 'Système',
            'email' => 'test@example.com',
            'id_role' => $role->id,
        ]);

        $this->call(MenuAdministrationSeeder::class);
    }
}
