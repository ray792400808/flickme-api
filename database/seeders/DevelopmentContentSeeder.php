<?php

namespace Database\Seeders;

use App\Models\Drama;
use App\Models\StoryCharacter;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DevelopmentContentSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'development', 'testing'])) {
            throw new RuntimeException('DevelopmentContentSeeder 仅允许开发或测试环境运行');
        }
        DB::transaction(function (): void {
            $keys = ['romance', 'ceo', 'fantasy', 'revenge', 'historical'];
            foreach ($keys as $index => $key) {
                Tag::withTrashed()->firstOrCreate(['id' => $index + 1], ['key' => $key, 'name' => ucfirst($key), 'status' => 'active']);
            }
            for ($i = 1; $i <= 10; $i++) {
                $status = $i <= 6 ? 'published' : ($i <= 8 ? 'draft' : 'unpublished');
                $drama = Drama::withTrashed()->firstOrCreate(['id' => $i], ['title' => sprintf('Development Short %02d', $i), 'synopsis' => 'Original local integration fixture.', 'status' => $status, 'is_playable' => $status === 'published', 'cover_url' => '/test-media/cover.svg', 'video_url' => '/test-media/sample.mp4', 'heat' => 110 - $i * 5]);
                if ($drama->wasRecentlyCreated) {
                    $tags = Tag::whereIn('key', [$keys[($i - 1) % 5], $keys[$i % 5]])->pluck('id');
                    $drama->tags()->attach($tags);
                }
            }
            for ($i = 1; $i <= 5; $i++) {
                $character = StoryCharacter::withTrashed()->firstOrCreate(['id' => $i], ['name' => 'Development Character '.$i, 'description' => 'Original fictional integration character.', 'status' => $i === 5 ? 'draft' : 'published', 'cover_url' => '/test-media/cover.svg']);
                if ($character->wasRecentlyCreated) {
                    $tagId = Tag::where('key', $keys[$i - 1])->value('id');
                    if ($tagId !== null) {
                        $character->tags()->attach($tagId);
                    }
                }
            }
            foreach (['short', 'stories'] as $channel) {
                $draft = [['id' => 'for_you', 'key' => 'for_you', 'type' => 'system', 'names' => $this->names('For you'), 'enabled' => true, 'locked' => true, 'starts_at' => null, 'ends_at' => null]];
                foreach ($keys as $key) {
                    if (Tag::where('key', $key)->where('status', 'active')->exists()) {
                        $draft[] = ['id' => 'dev-'.$channel.'-'.$key, 'key' => $key, 'type' => 'tag', 'names' => $this->names(ucfirst($key)), 'enabled' => true, 'locked' => false, 'starts_at' => null, 'ends_at' => null];
                    }
                }
                DB::table('operation_configs')->insertOrIgnore(['resource' => 'chips', 'channel' => $channel, 'revision' => 1, 'draft' => json_encode($draft, JSON_THROW_ON_ERROR)]);
            }
        });
    }

    private function names(string $english): array
    {
        return ['en' => $english, 'zh-Hans' => '', 'zh-Hant' => '', 'es' => '', 'pt' => '', 'id' => '', 'th' => ''];
    }
}
