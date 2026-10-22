<?php

namespace Database\Seeders;

use App\Models\Language;
use App\Models\Tag;
use App\Models\TagTranslation;
use Illuminate\Database\Seeder;

/**
 * 基础标签与合集。
 *
 * 数据来自「标签与合集」原型：Romance / Thriller / Revenge / CEO / Werewolf 为标签，
 * Forbidden Love / Billionaire Heirs / Secret Identity 为合集。
 * 每条都带英文（默认语种）与简体中文翻译。
 */
class TagSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => 'romance', 'type' => Tag::TYPE_TAG, 'name' => 'Romance', 'zh' => '爱情'],
            ['code' => 'thriller', 'type' => Tag::TYPE_TAG, 'name' => 'Thriller', 'zh' => '惊悚'],
            ['code' => 'revenge', 'type' => Tag::TYPE_TAG, 'name' => 'Revenge', 'zh' => '复仇'],
            ['code' => 'ceo', 'type' => Tag::TYPE_TAG, 'name' => 'CEO', 'zh' => '总裁'],
            ['code' => 'werewolf', 'type' => Tag::TYPE_TAG, 'name' => 'Werewolf', 'zh' => '狼人'],
            ['code' => 'forbidden-love', 'type' => Tag::TYPE_COLLECTION, 'name' => 'Forbidden Love', 'zh' => '禁忌之恋'],
            ['code' => 'billionaire-heirs', 'type' => Tag::TYPE_COLLECTION, 'name' => 'Billionaire Heirs', 'zh' => '豪门继承人'],
            ['code' => 'secret-identity', 'type' => Tag::TYPE_COLLECTION, 'name' => 'Secret Identity', 'zh' => '隐藏身份'],
        ];

        $languages = Language::query()->pluck('code', 'id');
        $englishId = $languages->search('en-US');
        $simplifiedId = $languages->search('zh-Hans-CN');

        foreach ($rows as $row) {
            $tag = Tag::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'type' => $row['type'],
                    'name' => $row['name'],
                    'description' => null,
                    'language_id' => $englishId,
                    'language_code' => 'en-US',
                    'status' => Tag::STATUS_ENABLED,
                ]
            );

            if ($simplifiedId !== false) {
                TagTranslation::query()->updateOrCreate(
                    ['tag_id' => $tag->id, 'language_id' => $simplifiedId],
                    [
                        'name' => $row['zh'],
                        'description' => null,
                        'language_code' => 'zh-Hans-CN',
                    ]
                );
            }
        }
    }
}
