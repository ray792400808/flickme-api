<?php

namespace Database\Factories;

use App\Models\Drama;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Drama> */
class DramaFactory extends Factory
{
    public function definition(): array
    {
        return ['title' => fake()->sentence(3), 'status' => 'published', 'is_playable' => true, 'video_url' => '/test-media/sample.mp4', 'cover_url' => '/test-media/cover.svg', 'heat' => 10];
    }
}
