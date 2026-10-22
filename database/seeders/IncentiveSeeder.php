<?php

namespace Database\Seeders;

use App\Models\AdminPermission;
use App\Services\Admin\IncentiveConfig;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class IncentiveSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $module = AdminPermission::query()->firstOrCreate(['code' => 'tasks'], [
                'module' => 'tasks', 'name' => '激励任务', 'type' => AdminPermission::TYPE_MODULE,
            ]);
            foreach (['view' => '查看', 'edit' => '编辑', 'publish' => '发布'] as $action => $name) {
                AdminPermission::query()->firstOrCreate(['code' => 'tasks.'.$action], [
                    'module' => 'tasks', 'parent_id' => $module->id, 'name' => $name, 'type' => AdminPermission::TYPE_BUTTON,
                ]);
            }
            foreach (['checkin', 'tasks', 'watch'] as $resource) {
                app(IncentiveConfig::class)->read($resource);
            }
        });
    }
}
