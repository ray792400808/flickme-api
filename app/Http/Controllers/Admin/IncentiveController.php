<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\IncentiveConfig;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class IncentiveController extends Controller
{
    public function __construct(private IncentiveConfig $configs) {}

    public function show(Request $request, string $resource): Response
    {
        $filters = $request->validate([
            'group' => ['nullable', Rule::in(['新手任务', '观看任务', '日常任务'])],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $document = $this->configs->read($resource);
        if ($resource === 'tasks' && isset($filters['group'])) {
            $document['draft'] = array_values(array_filter($document['draft'], fn (array $task): bool => $task['group'] === $filters['group']));
        }
        if ($resource === 'tasks' && isset($filters['q'])) {
            $document['draft'] = array_values(array_filter($document['draft'], fn (array $task): bool => Str::contains($task['name'], $filters['q'], ignoreCase: true)));
        }

        return $this->success($document);
    }

    public function saveCheckin(Request $request): Response
    {
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'draft' => ['required', 'array', 'list', 'size:7'],
            'draft.*' => ['required', 'array:id,day,reward'],
            'draft.*.reward' => ['required', 'integer', 'min:0', 'max:'.config('wallet.adjustment_limit', 1000000)],
        ]);
        foreach ($data['draft'] as $index => $day) {
            Validator::make($day, [
                'id' => ['required', Rule::in(['CK'.($index + 1)])],
                'day' => ['required', Rule::in(['Day '.($index + 1)])],
            ])->validate();
            $data['draft'][$index]['reward'] = (int) $day['reward'];
        }

        return $this->success($this->configs->save($request, 'checkin', $data['revision'], fn (array $previous): array => $data['draft']));
    }

    public function saveWatch(Request $request): Response
    {
        $data = $request->validate([
            'revision' => ['required', 'integer', 'min:1'],
            'draft' => ['required', 'array:sec,coins,cap'],
            'draft.sec' => ['required', 'integer', 'between:1,86400'],
            'draft.coins' => ['required', 'integer', 'min:1', 'max:'.config('wallet.adjustment_limit', 1000000)],
            'draft.cap' => ['required', 'integer', 'gte:draft.coins', 'max:'.config('wallet.adjustment_limit', 1000000)],
        ]);

        $data['draft'] = array_map(fn (mixed $value): int => (int) $value, $data['draft']);

        return $this->success($this->configs->save($request, 'watch', $data['revision'], fn (array $previous): array => $data['draft']));
    }

    public function storeTask(Request $request): Response
    {
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1'], 'task' => ['required', 'array:group,name,sec,reward,reset,en']]);
        $task = $this->validateTask($data['task']);
        $task['id'] = (string) Str::uuid();

        return $this->success($this->configs->save($request, 'tasks', $data['revision'], function (array $tasks) use ($task): array {
            $tasks[] = $task;

            return $tasks;
        }));
    }

    public function updateTask(Request $request, string $task): Response
    {
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1'], 'task' => ['required', 'array:group,name,sec,reward,reset,en']]);

        return $this->success($this->configs->save($request, 'tasks', $data['revision'], function (array $tasks) use ($data, $task): array {
            $index = array_search($task, array_column($tasks, 'id'), true);
            abort_if($index === false, 404, '任务不存在');
            $tasks[$index] = ['id' => $task] + $this->validateTask(array_replace($tasks[$index], $data['task']));

            return $tasks;
        }));
    }

    public function destroyTask(Request $request, string $task): Response
    {
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1']]);

        return $this->success($this->configs->save($request, 'tasks', $data['revision'], function (array $tasks) use ($task): array {
            $index = array_search($task, array_column($tasks, 'id'), true);
            abort_if($index === false, 404, '任务不存在');
            array_splice($tasks, $index, 1);

            return $tasks;
        }));
    }

    public function publish(Request $request, string $resource): Response
    {
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1']]);

        return $this->success($this->configs->publish($request, $resource, $data['revision']));
    }

    /** @param array<string, mixed> $task
     * @return array<string, mixed>
     */
    private function validateTask(array $task): array
    {
        if (($task['group'] ?? null) !== '观看任务') {
            unset($task['sec']);
        }
        $validated = Validator::make($task, [
            'group' => ['required', Rule::in(['新手任务', '观看任务', '日常任务'])],
            'name' => ['required_unless:group,观看任务', 'nullable', 'string', 'max:200'],
            'sec' => ['required_if:group,观看任务', 'nullable', 'integer', 'between:1,86400'],
            'reward' => ['required', 'integer', 'min:0', 'max:'.config('wallet.adjustment_limit', 1000000)],
            'reset' => ['required', Rule::in(['一次性', '每日'])],
            'en' => ['required', 'boolean'],
        ])->validate();
        $validated['reward'] = (int) $validated['reward'];
        $validated['en'] = (bool) $validated['en'];
        if ($validated['group'] === '观看任务') {
            $validated['sec'] = (int) $validated['sec'];
            $validated['name'] = 'Watch dramas for '.(round($validated['sec'] / 6) / 10).' min';
        } else {
            unset($validated['sec']);
        }

        return $validated;
    }
}
