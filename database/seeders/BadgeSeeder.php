<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

/**
 * 基础角标字典。
 *
 * 关键词是稳定标识，前台按关键词判断角标类型，创建后不建议修改。
 */
class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['keyword' => 'HOT', 'label' => '热门', 'sort' => 10],
            ['keyword' => 'NEW', 'label' => '新剧', 'sort' => 20],
            ['keyword' => 'EXCLUSIVE', 'label' => '独家', 'sort' => 30],
        ];

        foreach ($rows as $row) {
            Badge::query()->updateOrCreate(
                ['keyword' => $row['keyword']],
                [
                    'label' => $row['label'],
                    'sort' => $row['sort'],
                    'enabled' => 1,
                ]
            );
        }
    }
}
