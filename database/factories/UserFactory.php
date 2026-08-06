<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('USR-####'),
            'user_code' => fake()->unique()->bothify('U########'),
            'user_id' => fake()->unique()->uuid(),
            'nom' => fake()->lastName(),
            'prenoms' => fake()->firstName(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('password'),
            'id_role' => 1,
            'is_active' => '1',
            'statut' => 'Actif',
            'tentatives_echouees' => 0,
            'deux_fa_active' => false,
            'cree_le' => now(),
        ];
    }
}
