<?php

namespace Database\Seeders;

use App\Models\Language;
use Illuminate\Database\Seeder;

/**
 * 基础语言。
 *
 * 语言集合来自「标签与合集」原型里多语言名称字段（mlv）覆盖的语种：
 * English / 简体中文 / 繁體中文 / Español / Português / Bahasa Indonesia / ไทย。
 * 这些语言是标签、合集、剧库多语言内容的基准维度，`sort` 决定语言切换器顺序，
 * 排在最前的基础语言即系统默认使用的语种。
 */
class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => 'en-US', 'name' => 'English (US)', 'native_name' => 'English', 'flag_emoji' => '🇺🇸', 'sort' => 10],
            ['code' => 'zh-Hans-CN', 'name' => 'Chinese (Simplified)', 'native_name' => '简体中文', 'flag_emoji' => '🇨🇳', 'sort' => 20],
            ['code' => 'zh-Hant-TW', 'name' => 'Chinese (Traditional)', 'native_name' => '繁體中文', 'flag_emoji' => '🇹🇼', 'sort' => 30],
            ['code' => 'es-ES', 'name' => 'Spanish', 'native_name' => 'Español', 'flag_emoji' => '🇪🇸', 'sort' => 40],
            ['code' => 'pt-BR', 'name' => 'Portuguese (Brazil)', 'native_name' => 'Português', 'flag_emoji' => '🇧🇷', 'sort' => 50],
            ['code' => 'id-ID', 'name' => 'Indonesian', 'native_name' => 'Bahasa Indonesia', 'flag_emoji' => '🇮🇩', 'sort' => 60],
            ['code' => 'th-TH', 'name' => 'Thai', 'native_name' => 'ไทย', 'flag_emoji' => '🇹🇭', 'sort' => 70],
        ];

        foreach ($rows as $row) {
            Language::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'native_name' => $row['native_name'],
                    'flag_emoji' => $row['flag_emoji'],
                    'enabled' => 1,
                    'sort' => $row['sort'],
                ]
            );
        }
    }
}
