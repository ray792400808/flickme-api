<?php

namespace Database\Seeders;

use App\Models\Badge;
use App\Models\Language;
use App\Models\Tag;
use Illuminate\Database\Seeder;

/**
 * 开发环境演示数据：基础标签、角标。
 * 只在 local / development / testing 环境运行。
 */
class DevelopmentDramaSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment(['local', 'development', 'testing'])) {
            return;
        }

        $en = Language::where('code', 'en-US')->first();
        $zh = Language::where('code', 'zh-CN')->first();

        $badges = [
            ['keyword' => 'HOT', 'label' => '热门', 'sort' => 10],
            ['keyword' => 'NEW', 'label' => '最新', 'sort' => 20],
            ['keyword' => 'EXCLUSIVE', 'label' => '独家', 'sort' => 30],
        ];

        foreach ($badges as $badge) {
            Badge::firstOrCreate(['keyword' => $badge['keyword']], $badge);
        }

        $tags = [
            ['code' => 'romance', 'name' => 'Romance', 'zh' => '爱情'],
            ['code' => 'thriller', 'name' => 'Thriller', 'zh' => '惊悚'],
            ['code' => 'revenge', 'name' => 'Revenge', 'zh' => '复仇'],
            ['code' => 'ceo', 'name' => 'CEO', 'zh' => '总裁'],
            ['code' => 'historical', 'name' => 'Historical', 'zh' => '历史'],
            ['code' => 'mystery', 'name' => 'Mystery', 'zh' => '悬疑'],
            ['code' => 'fantasy', 'name' => 'Fantasy', 'zh' => '奇幻'],
            ['code' => 'drama', 'name' => 'Drama', 'zh' => '剧情'],
            ['code' => 'mafia', 'name' => 'Mafia', 'zh' => '黑帮'],
            ['code' => 'werewolf', 'name' => 'Werewolf', 'zh' => '狼人'],
        ];

        foreach ($tags as $index => $tag) {
            $model = Tag::firstOrCreate(
                ['code' => $tag['code']],
                [
                    'type' => 1,
                    'name' => $tag['name'],
                    'description' => null,
                    'status' => 1,
                    'language_id' => $en?->id ?? 1,
                    'language_code' => $en?->code ?? 'en-US',
                ]
            );

            // 中文翻译
            if ($zh) {
                $model->translations()->updateOrCreate(
                    ['language_id' => $zh->id],
                    [
                        'name' => $tag['zh'],
                        'description' => null,
                        'language_code' => $zh->code,
                    ]
                );
            }
        }
    }
}
