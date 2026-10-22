<?php

namespace App\Services\Admin;

use App\Common\ResponseDef;
use App\Common\ResponseUtil;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class IncentiveConfig
{
    /** @return array<string, mixed> */
    public function read(string $resource): array
    {
        DB::table('incentive_configs')->insertOrIgnore([
            'resource' => $resource,
            'draft' => json_encode($this->defaults($resource), JSON_THROW_ON_ERROR),
        ]);
        $row = DB::table('incentive_configs')->where('resource', $resource)->first();

        return [
            'revision' => (int) $row->revision,
            'published_revision' => $row->published_revision === null ? null : (int) $row->published_revision,
            'published_at' => $row->published_at === null ? null : (int) $row->published_at,
            'draft' => json_decode($row->draft, true, flags: JSON_THROW_ON_ERROR),
            'published' => $row->published === null ? null : json_decode($row->published, true, flags: JSON_THROW_ON_ERROR),
        ];
    }

    /** @return array<string, mixed> */
    public function save(Request $request, string $resource, int $revision, Closure $change): array
    {
        $this->read($resource);

        return DB::transaction(function () use ($request, $resource, $revision, $change): array {
            $row = DB::table('incentive_configs')->where('resource', $resource)->lockForUpdate()->first();
            $this->checkRevision($row, $revision);
            $before = json_decode($row->draft, true, flags: JSON_THROW_ON_ERROR);
            $draft = $change($before);
            $values = ['draft' => json_encode($draft, JSON_THROW_ON_ERROR), 'revision' => $revision + 1];
            if ($resource === 'watch') {
                $values += ['published' => $values['draft'], 'published_revision' => $revision + 1, 'published_at' => now()->getTimestamp()];
            }
            DB::table('incentive_configs')->where('id', $row->id)->update($values);
            AdminMutation::audit($request, 'incentive.save', $resource, ['before' => $before, 'after' => $draft, 'revision' => $revision + 1]);

            return $this->read($resource);
        });
    }

    /** @return array<string, mixed> */
    public function publish(Request $request, string $resource, int $revision): array
    {
        $this->read($resource);

        return AdminMutation::run($request, 'incentive.publish:'.$resource, function () use ($request, $resource, $revision): array {
            $row = DB::table('incentive_configs')->where('resource', $resource)->lockForUpdate()->first();
            $this->checkRevision($row, $revision);
            if ((int) $row->published_revision !== $revision) {
                DB::table('incentive_configs')->where('id', $row->id)->update([
                    'published' => $row->draft, 'published_revision' => $revision, 'published_at' => now()->getTimestamp(),
                ]);
                AdminMutation::audit($request, 'incentive.publish', $resource, ['revision' => $revision]);
            }

            return $this->read($resource);
        });
    }

    private function checkRevision(object $row, int $revision): void
    {
        if ((int) $row->revision !== $revision) {
            throw new HttpResponseException(ResponseUtil::error(ResponseDef::CODE_REVISION_STALE, ResponseDef::MSG_REVISION_STALE, ResponseDef::CODE_CONFLICT));
        }
    }

    /** @return array<mixed> */
    private function defaults(string $resource): array
    {
        if ($resource === 'checkin') {
            return array_map(fn (int $reward, int $index): array => ['id' => 'CK'.($index + 1), 'day' => 'Day '.($index + 1), 'reward' => $reward], [10, 15, 20, 25, 30, 50, 100], range(0, 6));
        }
        if ($resource === 'watch') {
            return ['sec' => 5, 'coins' => 2, 'cap' => 600];
        }

        $tasks = [];
        foreach (['Log in to Flickme' => 30, 'Link your WhatsApp account' => 50, 'Bind your email' => 30, 'Follow Flickme on Facebook' => 20, 'Follow Flickme on YouTube' => 20] as $name => $reward) {
            $tasks[] = ['group' => '新手任务', 'name' => $name, 'reward' => $reward, 'reset' => '一次性', 'en' => true];
        }
        foreach ([180 => 5, 300 => 10, 480 => 15, 720 => 25, 900 => 40] as $sec => $reward) {
            $tasks[] = ['group' => '观看任务', 'name' => 'Watch dramas for '.($sec / 60).' min', 'sec' => $sec, 'reward' => $reward, 'reset' => '每日', 'en' => true];
        }
        foreach (['Watch an ad (每日 10 次)' => 5, 'Rate a drama' => 5, 'Share a drama' => 10] as $name => $reward) {
            $tasks[] = ['group' => '日常任务', 'name' => $name, 'reward' => $reward, 'reset' => '每日', 'en' => true];
        }

        return array_map(fn (array $task, int $index): array => ['id' => 'TK'.($index + 1)] + $task, $tasks, array_keys($tasks));
    }
}
