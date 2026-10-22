<?php

namespace Database\Factories;

use App\Models\Drama;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Drama> */
class DramaFactory extends Factory
{
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'status' => 1,
            'language_id' => \App\Models\Language::firstOrCreate(['code' => 'en-US'], ['name' => 'English (US)', 'native_name' => 'English', 'flag_emoji' => '🇺🇸', 'sort' => 10])->id,
            'free_episodes' => 6,
            'episodes_num' => 0,
            'video_url' => '/test-media/sample.mp4',
            'cover_url' => '/test-media/cover.svg',
        ];
    }
}
