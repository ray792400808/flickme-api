<?php

namespace App\Http\Controllers\Admin;

use App\Common\Constants;
use App\Common\ResponseDef;
use App\Http\Controllers\Controller;
use App\Models\Drama;
use App\Services\AdminMutation;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class OperationsController extends Controller
{
    private const LANGUAGES = ['en', 'zh-Hans', 'zh-Hant', 'es', 'pt', 'id', 'th'];

    private const PLACEMENTS = ['player_exit', 'related', 'following_trending', 'drama_similar', 'home_continue'];

    public function show(Request $request, string $resource): Response
    {
        $identity = $this->identity($request, $resource);
        $this->initialize($identity);

        return $this->success($this->document(DB::table('operation_configs')->where($identity)->first()));
    }

    public function update(Request $request, string $resource): Response
    {
        $identity = $this->identity($request, $resource);
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1', 'max:9007199254740991'], 'draft' => ['present', 'array']]);
        $this->initialize($identity);
        $result = DB::transaction(function () use ($request, $identity, $data, $resource): array {
            $row = DB::table('operation_configs')->where($identity)->lockForUpdate()->first();
            $this->ensureCurrentRevision((int) $row->revision, $data['revision']);
            $this->validateDraft($resource, $data['draft'], json_decode($row->draft, true));
            DB::table('operation_configs')->where('id', $row->id)->update(['draft' => json_encode($data['draft'], JSON_THROW_ON_ERROR), 'revision' => $row->revision + 1]);
            AdminMutation::audit($request, 'ops.save', implode(':', $identity), ['before' => json_decode($row->draft, true), 'after' => $data['draft'], 'revision' => $row->revision + 1]);

            return $this->document(DB::table('operation_configs')->find($row->id));
        });

        return $this->success($result);
    }

    public function publish(Request $request, string $resource): Response
    {
        $identity = $this->identity($request, $resource);
        $data = $request->validate(['revision' => ['required', 'integer', 'min:1', 'max:9007199254740991']]);
        $this->initialize($identity);
        $result = AdminMutation::run($request, 'ops.publish:'.implode(':', $identity), function () use ($request, $identity, $data, $resource): array {
            $row = DB::table('operation_configs')->where($identity)->lockForUpdate()->first();
            $this->ensureCurrentRevision((int) $row->revision, $data['revision']);
            if ($row->published_revision !== null && (int) $row->published_revision === $data['revision']) {
                return $this->document($row);
            }
            $this->validateDraft($resource, json_decode($row->draft, true), json_decode($row->draft, true));
            DB::table('operation_configs')->where('id', $row->id)->update(['published' => $row->draft, 'published_revision' => $row->revision, 'published_at' => now()->getTimestamp()]);
            AdminMutation::audit($request, 'ops.publish', implode(':', $identity), ['before' => $row->published === null ? null : json_decode($row->published, true), 'after' => json_decode($row->draft, true), 'revision' => $row->revision]);

            return $this->document(DB::table('operation_configs')->find($row->id));
        });

        return $this->success($result);
    }

    private function ensureCurrentRevision(int $revision, int $expectedRevision): void
    {
        if ($revision !== $expectedRevision) {
            throw new HttpResponseException($this->error(
                ResponseDef::CODE_REVISION_STALE,
                ResponseDef::MSG_REVISION_STALE,
                ResponseDef::CODE_CONFLICT,
            ));
        }
    }

    private function identity(Request $request, string $resource): array
    {
        abort_unless(in_array($resource, ['chips', 'hot', 'launch', 'words', 'filters', 'recs'], true), 404, '运营资源不存在');
        if ($resource === 'chips') {
            $request->validate(['channel' => ['required', Rule::in(['short', 'stories'])]]);
        } else {
            abort_if($request->has('channel'), 422, '该资源不支持 channel');
        }

        return ['resource' => $resource, 'channel' => $resource === 'chips' ? $request->query('channel') : ''];
    }

    private function initialize(array $identity): void
    {
        $draft = match ($identity['resource']) {
            'chips' => [['id' => 'for_you', 'key' => 'for_you', 'type' => 'system', 'names' => $this->names('For you', '推荐'), 'enabled' => true, 'locked' => true, 'starts_at' => null, 'ends_at' => null]],
            'hot' => ['pool_size' => 50, 'refresh_hours' => 1, 'short_count' => 9, 'stories_count' => 8, 'page_size' => 20, 'overrides' => []],
            'launch' => ['enabled' => false, 'fallback_drama_id' => null, 'cover_url' => '', 'button_text' => $this->names('Start Watching', '开始观看')],
            'recs' => array_map(fn (string $placement): array => ['id' => $placement, 'name' => $placement, 'placement' => $placement, 'source' => match ($placement) {
                'home_continue' => 'recent','related','drama_similar' => 'similar',default => 'trending'
            }, 'count' => 8, 'fallback' => 'trending', 'pinned_drama_id' => null, 'enabled' => false], self::PLACEMENTS),
            default => []
        };
        DB::table('operation_configs')->insertOrIgnore($identity + ['draft' => json_encode($draft, JSON_THROW_ON_ERROR)]);
    }

    private function names(string $english, string $chinese): array
    {
        return array_replace(array_fill_keys(self::LANGUAGES, ''), ['en' => $english, 'zh-Hans' => $chinese]);
    }

    private function document(object $row): array
    {
        $draft = json_decode($row->draft, true);
        if ($row->resource === 'hot') {
            $overrides = collect($draft['overrides'])->keyBy('drama_id');
            $draft['overrides'] = Drama::available()->orderByDesc('heat')->orderBy('id')->get()->map(fn (Drama $drama): array => [
                'drama_id' => $drama->id, 'title' => $drama->title, 'heat' => $drama->heat,
                'pinned' => (bool) ($overrides->get($drama->id)['pinned'] ?? false),
                'blocked' => (bool) ($overrides->get($drama->id)['blocked'] ?? false),
            ])->all();
        }

        return ['revision' => (int) $row->revision, 'published_revision' => $row->published_revision === null ? null : (int) $row->published_revision, 'published_at' => $row->published_at === null ? null : (int) $row->published_at, 'draft' => $draft];
    }

    private function validateDraft(string $resource, array $draft, array $previous): void
    {
        $rules = match ($resource) {
            'chips' => ['draft' => ['array', 'min:1'], 'draft.*' => ['array:id,key,type,names,enabled,locked,starts_at,ends_at'], 'draft.*.key' => ['required', 'string', 'distinct'], 'draft.*.type' => ['required', Rule::in(['system', 'tag'])], 'draft.*.locked' => ['required', 'boolean']],
            'words' => ['draft.*' => ['array:id,names,enabled,starts_at,ends_at']],
            'filters' => ['draft.*' => ['array:id,key,names,tag_keys,enabled'], 'draft.*.key' => ['required', 'string', 'distinct'], 'draft.*.tag_keys' => ['required', 'array', 'min:1'], 'draft.*.tag_keys.*' => ['required', 'string']],
            'recs' => ['draft' => ['array', 'size:5'], 'draft.*' => ['array:id,name,placement,source,count,fallback,pinned_drama_id,enabled'], 'draft.*.name' => ['required', 'string'], 'draft.*.placement' => ['required', 'string', 'distinct', Rule::in(self::PLACEMENTS)], 'draft.*.source' => ['required', Rule::in(['personalized', 'trending', 'similar', 'recent'])], 'draft.*.count' => ['required', 'integer', 'between:1,50'], 'draft.*.fallback' => ['required', Rule::in(['trending', 'none'])], 'draft.*.pinned_drama_id' => ['present', 'nullable', 'integer', 'min:1']],
            'hot' => ['draft' => ['array:pool_size,refresh_hours,short_count,stories_count,page_size,overrides'], 'draft.pool_size' => ['required', 'integer', 'between:1,1000'], 'draft.refresh_hours' => ['required', 'integer', Rule::in([1, 6, 24])], 'draft.short_count' => ['required', 'integer', 'between:1,'.Constants::MAX_PAGE_SIZE], 'draft.stories_count' => ['required', 'integer', 'between:1,'.Constants::MAX_PAGE_SIZE], 'draft.page_size' => ['required', 'integer', 'between:1,'.Constants::MAX_PAGE_SIZE], 'draft.overrides' => ['present', 'array'], 'draft.overrides.*' => ['array:drama_id,title,heat,pinned,blocked'], 'draft.overrides.*.drama_id' => ['required', 'integer', 'min:1', 'distinct'], 'draft.overrides.*.pinned' => ['required', 'boolean'], 'draft.overrides.*.blocked' => ['required', 'boolean']],
            'launch' => ['draft' => ['array:enabled,fallback_drama_id,cover_url,button_text'], 'draft.enabled' => ['required', 'boolean'], 'draft.fallback_drama_id' => ['present', 'nullable', 'integer', 'min:1'], 'draft.cover_url' => ['present', 'nullable', 'string', 'max:2048']],
        };
        if (in_array($resource, ['chips', 'words', 'filters', 'recs'], true)) {
            $this->ensure(array_is_list($draft), 'draft', '必须为数组');
            $rules += ['draft.*.id' => ['required', 'string', 'max:100', 'distinct'], 'draft.*.enabled' => ['required', 'boolean']];
        }
        Validator::make(['draft' => $draft], $rules)->validate();
        if (in_array($resource, ['chips', 'words', 'filters'], true)) {
            foreach ($draft as $i => $item) {
                $this->validateNames($item['names'] ?? null, "draft.$i.names");
                if ($resource !== 'filters') {
                    $this->ensure(array_key_exists('starts_at', $item) && array_key_exists('ends_at', $item), "draft.$i", '必须包含起止时间');
                    foreach (['starts_at', 'ends_at'] as $key) {
                        if ($item[$key] !== null) {
                            $this->ensure(is_int($item[$key]) && $item[$key] >= 0 && $item[$key] <= 253402300799, "draft.$i.$key", '必须为秒级整数时间戳');
                        }
                    }
                    if ($item['starts_at'] !== null && $item['ends_at'] !== null) {
                        $this->ensure($item['ends_at'] > $item['starts_at'], "draft.$i.ends_at", '结束时间必须晚于开始时间');
                    }
                }
                if ($resource === 'chips') {
                    if ($i === 0) {
                        $this->ensure($item === $previous[0], 'draft.0', '固定首位系统分类不能修改');
                    } else {
                        $this->ensure(! $item['locked'], "draft.$i.locked", '仅首个分类可锁定');
                        if ($item['type'] === 'system') {
                            $this->ensure(in_array($item['key'], ['explore'], true), "draft.$i.key", '不支持的系统分类');
                        } else {
                            $this->reference('tags', 'key', $item['key'], "draft.$i.key");
                        }
                    }
                }
                if ($resource === 'filters') {
                    $this->ensure(count($item['tag_keys']) === count(array_unique($item['tag_keys'])), "draft.$i.tag_keys", '标签不能重复');
                    foreach ($item['tag_keys'] as $key) {
                        $this->reference('tags', 'key', $key, "draft.$i.tag_keys");
                    }
                }
            }
        }
        if ($resource === 'launch') {
            $this->validateNames($draft['button_text'] ?? null, 'draft.button_text');
            if ($draft['enabled']) {
                $this->ensure(! empty($draft['fallback_drama_id']), 'draft.fallback_drama_id', '启用时必须选择可播放剧集');
            }
            if ($draft['fallback_drama_id'] !== null) {
                $this->reference('dramas', 'id', $draft['fallback_drama_id'], 'draft.fallback_drama_id');
            }
            if (! empty($draft['cover_url'])) {
                $host = parse_url($draft['cover_url'], PHP_URL_HOST);
                $this->ensure(parse_url($draft['cover_url'], PHP_URL_SCHEME) === 'https' && in_array($host, config('operations.media_hosts'), true), 'draft.cover_url', '必须为受信任媒体域名的 HTTPS 地址');
            }
        }
        if ($resource === 'hot') {
            foreach ($draft['overrides'] as $i => $override) {
                $this->ensure(! ($override['pinned'] && $override['blocked']), "draft.overrides.$i", '不能同时置顶和屏蔽');
                $this->reference('dramas', 'id', $override['drama_id'], "draft.overrides.$i.drama_id");
                $known = Drama::available()->find($override['drama_id']);
                $this->ensure($known !== null && ($override['title'] ?? null) === $known->title && ($override['heat'] ?? null) === $known->heat, "draft.overrides.$i", '剧名和热度只读，请刷新');
            }
        }
        if ($resource === 'recs') {
            foreach ($draft as $i => $item) {
                $known = collect($previous)->firstWhere('id', $item['id']);
                $this->ensure($known !== null && $known['placement'] === $item['placement'] && $known['name'] === $item['name'], "draft.$i", '不能修改固定推荐位');
                $allowed = match ($item['placement']) {
                    'home_continue' => ['recent'],'following_trending' => ['trending'],'related','drama_similar' => ['similar', 'personalized', 'trending'],default => ['personalized', 'trending']
                };
                $this->ensure(in_array($item['source'], $allowed, true), "draft.$i.source", '推荐位不支持该数据来源');
                if ($item['pinned_drama_id'] !== null) {
                    $this->reference('dramas', 'id', $item['pinned_drama_id'], "draft.$i.pinned_drama_id");
                }
            }
        }
    }

    private function reference(string $table, string $column, string|int $value, string $field): void
    {
        $this->ensure(Schema::hasTable($table), $field, '真实内容库尚未接入，无法验证引用');
        $query = DB::table($table)->where($column, $value)->whereNull('deleted_at');
        if ($table === 'dramas') {
            $this->ensure(Schema::hasColumn($table, 'status'), $field, '剧集库缺少可播放状态');
            $query->where('status', 'published')->where('is_playable', true)->whereNotNull('video_url')->where('video_url', '!=', '');
        } elseif ($table === 'tags') {
            $query->where('status', 'active');
        }
        $this->ensure($query->exists(), $field, '引用不存在或不可用');
    }

    private function validateNames(mixed $names, string $field): void
    {
        $this->ensure(is_array($names), $field, '必须提供完整多语言对象');
        $this->ensure(count(array_diff(self::LANGUAGES, array_keys($names))) === 0 && count($names) === 7, $field, '必须包含7个语言键');
        foreach (self::LANGUAGES as $language) {
            $value = $names[$language];
            $this->ensure(is_string($value) && mb_strlen($value) <= 200 && ($language !== 'en' || trim($value) !== ''), $field.'.'.$language, '英文必填，各语言最多200字');
        }
    }

    private function ensure(bool $condition, string $field, string $message): void
    {
        if (! $condition) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }
}
