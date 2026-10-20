<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tag> */
class TagFactory extends Factory
{
    public function definition(): array
    {
        return ['key' => fake()->unique()->slug(2), 'name' => fake()->words(2, true), 'status' => 'active'];
    }
}
