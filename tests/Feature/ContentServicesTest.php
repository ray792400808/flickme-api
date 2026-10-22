<?php

namespace Tests\Feature;

use App\Services\Admin\BadgeService;
use App\Services\Admin\LanguageService;
use App\Services\Admin\TagService;
use Database\Seeders\BadgeSeeder;
use Database\Seeders\LanguageSeeder;
use Database\Seeders\TagSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_options_return_prototype_shaped_payloads(): void
    {
        $this->seed(LanguageSeeder::class);
        $this->seed(BadgeSeeder::class);
        $this->seed(TagSeeder::class);

        $languages = app(LanguageService::class)->options();
        $this->assertSame('en-US', $languages[0]['code']);
        $this->assertSame('简体中文', $languages[1]['native_name']);
        $this->assertCount(7, $languages);

        $badges = app(BadgeService::class)->options();
        $this->assertSame('HOT', $badges[0]['keyword']);

        $tags = app(TagService::class)->options(['type' => 1, 'lang' => 'zh-Hans-CN']);
        $this->assertSame('爱情', $tags[0]['name']);
    }
}
