<?php

namespace App\Http\Controllers\Admin;

use App\Common\Constants;
use App\Http\Controllers\Controller;
use App\Models\Drama;
use App\Models\StoryCharacter;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * 旧版内容库只读接口（保留给 /admin/v1 这套历史契约）。
 *
 * 字段命名沿用旧结构：tags.key、tags.status、dramas.status 等字符串值，
 * 与新内容中台的 TagController / DramaController 互不影响。
 */
class ContentController extends Controller
{
    public function tags(Request $request): Response
    {
        $this->authorizeRead($request);
        $data = $this->filters($request, ['active', 'disabled']);
        $query = Tag::query();
        $this->search($query, $data, 'name');
        if (isset($data['status'])) {
            $query->where('status', $data['status'] === 'active' ? Tag::STATUS_ENABLED : Tag::STATUS_DISABLED);
        }
        if (isset($data['tag_key'])) {
            $query->where('code', $data['tag_key']);
        }

        return $this->success($query->orderBy('code')->paginate($data['per_page'] ?? Constants::DEFAULT_PAGE_SIZE)->through(fn (Tag $tag): array => $this->tag($tag)));
    }

    public function dramas(Request $request): Response
    {
        return $this->contents($request, Drama::query(), 'title');
    }

    public function characters(Request $request): Response
    {
        return $this->contents($request, StoryCharacter::query(), 'name');
    }

    public function drama(Request $request, int $drama): Response
    {
        $this->authorizeRead($request);

        return $this->success($this->content(Drama::with('tags')->findOrFail($drama)));
    }

    public function character(Request $request, int $character): Response
    {
        $this->authorizeRead($request);

        return $this->success($this->content(StoryCharacter::with('tags')->findOrFail($character)));
    }

    public function tagDetail(Request $request, int $tag): Response
    {
        $this->authorizeRead($request);

        return $this->success($this->tag(Tag::findOrFail($tag)));
    }

    private function contents(Request $request, Builder $query, string $name): Response
    {
        $this->authorizeRead($request);
        $data = $this->filters($request, ['published', 'draft', 'unpublished']);
        $this->search($query, $data, $name);
        if (isset($data['status'])) {
            $query->where('status', $this->dramaStatus($data['status']));
        }
        if (isset($data['tag_key'])) {
            $query->whereHas('tags', fn (Builder $tags): Builder => $tags->where('code', $data['tag_key']));
        }
        if (! empty($data['available'])) {
            if ($query->getModel() instanceof Drama) {
                $query->available();
            } else {
                $query->where('status', 'published');
            }
        }

        return $this->success($query->with('tags')->orderBy('id')->paginate($data['per_page'] ?? Constants::DEFAULT_PAGE_SIZE)->through(fn (Model $record): array => $this->content($record)));
    }

    /** 旧契约的字符串状态 → 新表的 tinyint */
    private function dramaStatus(string $status): int
    {
        return match ($status) {
            'published' => Drama::STATUS_PUBLISHED,
            'draft' => Drama::STATUS_DRAFT,
            default => Drama::STATUS_OFFLINE,
        };
    }

    private function filters(Request $request, array $statuses): array
    {
        return $request->validate(['q' => ['nullable', 'string', 'max:100'], 'tag_key' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in($statuses)], 'available' => ['nullable', 'boolean'], 'page' => ['integer', 'min:1'], 'per_page' => ['integer', 'between:1,'.Constants::MAX_PAGE_SIZE]]);
    }

    private function search(Builder $query, array $data, string $name): void
    {
        if (! empty($data['q'])) {
            $query->where(function (Builder $filter) use ($data, $name): void {
                $filter->where('id', $data['q'])->orWhere($name, 'like', '%'.$data['q'].'%');
            });
        }
    }

    private function authorizeRead(Request $request): void
    {
        // RBAC 重构后 AdminUser 没有 profile() 方法，改用统一的权限判定
        $user = $request->user();
        abort_unless($user && ($user->hasPermission('content.view') || $user->hasPermission('ops.view')), 403, '需要内容或运营读取权限');
    }

    private function tag(Tag $tag): array
    {
        return ['id' => $tag->id, 'key' => $tag->code, 'name' => $tag->name, 'status' => $tag->status === Tag::STATUS_ENABLED ? 'active' : 'disabled'];
    }

    private function content(Model $record): array
    {
        $result = ['id' => $record->id, 'name' => $record instanceof Drama ? $record->title : $record->name, 'status' => $record instanceof Drama ? $this->statusLabel($record->status) : $record->status, 'cover_url' => $record->cover_url, 'tags' => $record->tags->map(fn (Tag $tag): array => $this->tag($tag))->all()];
        if ($record instanceof Drama) {
            $result += ['title' => $record->title, 'is_playable' => $record->status === Drama::STATUS_PUBLISHED, 'video_url' => $record->video_url, 'heat' => $record->heat];
        }

        return $result;
    }

    private function statusLabel(int $status): string
    {
        return match ($status) {
            Drama::STATUS_PUBLISHED => 'published',
            Drama::STATUS_DRAFT => 'draft',
            default => 'unpublished',
        };
    }
}