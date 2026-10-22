<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Tag> */
class TagFactory extends Factory
{
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->slug(2),
            'type' => 1,
            'name' => fake()->words(2, true),
            'description' => null,
            'status' => 1,
            'language_id' => \App\Models\Language::firstOrCreate(['code' => 'en-US'], ['name' => 'English (US)', 'native_name' => 'English', 'flag_emoji' => '🇺🇸', 'sort' => 10])->id,
            'language_code' => 'en-US',
        ];
    }
}
