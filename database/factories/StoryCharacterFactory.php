<?php

namespace Database\Factories;

use App\Models\StoryCharacter;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StoryCharacter> */
class StoryCharacterFactory extends Factory
{
    public function definition(): array
    {
        return ['name' => fake()->name(), 'status' => 'published', 'cover_url' => '/test-media/cover.svg'];
    }
}
