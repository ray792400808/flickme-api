<?php

namespace Database\Factories;

use App\Models\AdminAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminAccount>
 */
class AdminAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'administrator-password',
            'role_key' => 'admin',
            'status' => 'active',
            'token_version' => 1,
        ];
    }
}
