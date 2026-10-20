<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Drama;
use App\Models\StoryCharacter;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContentController extends Controller
{
    public function tags(Request $request): JsonResponse
    {
        $this->authorizeRead($request);
        $data = $this->filters($request, ['active', 'disabled']);
        $query = Tag::query();
        $this->search($query, $data, 'name');
        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (isset($data['tag_key'])) {
            $query->where('key', $data['tag_key']);
        }

        return response()->json(['data' => $query->orderBy('key')->paginate($data['per_page'] ?? 10)->through(fn (Tag $tag): array => $this->tag($tag))]);
    }

    public function dramas(Request $request): JsonResponse
    {
        return $this->contents($request, Drama::query(), 'title');
    }

    public function characters(Request $request): JsonResponse
    {
        return $this->contents($request, StoryCharacter::query(), 'name');
    }

    public function drama(Request $request, int $drama): JsonResponse
    {
        $this->authorizeRead($request);

        return response()->json(['data' => $this->content(Drama::with('tags')->findOrFail($drama))]);
    }

    public function character(Request $request, int $character): JsonResponse
    {
        $this->authorizeRead($request);

        return response()->json(['data' => $this->content(StoryCharacter::with('tags')->findOrFail($character))]);
    }

    public function tagDetail(Request $request, int $tag): JsonResponse
    {
        $this->authorizeRead($request);

        return response()->json(['data' => $this->tag(Tag::findOrFail($tag))]);
    }

    private function contents(Request $request, Builder $query, string $name): JsonResponse
    {
        $this->authorizeRead($request);
        $data = $this->filters($request, ['published', 'draft', 'unpublished']);
        $this->search($query, $data, $name);
        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (isset($data['tag_key'])) {
            $query->whereHas('tags', fn (Builder $tags): Builder => $tags->where('key', $data['tag_key']));
        }
        if (! empty($data['available'])) {
            if ($query->getModel() instanceof Drama) {
                $query->available();
            } else {
                $query->where('status', 'published');
            }
        }

        return response()->json(['data' => $query->with('tags')->orderBy('id')->paginate($data['per_page'] ?? 10)->through(fn (Model $record): array => $this->content($record))]);
    }

    private function filters(Request $request, array $statuses): array
    {
        return $request->validate(['q' => ['nullable', 'string', 'max:100'], 'tag_key' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in($statuses)], 'available' => ['nullable', 'boolean'], 'page' => ['integer', 'min:1'], 'per_page' => ['integer', 'between:1,100']]);
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
        $permissions = $request->user()->profile()['permissions'];
        abort_unless(in_array($permissions['content'] ?? null, ['r', 'rw'], true) || in_array($permissions['ops'] ?? null, ['r', 'rw'], true), 403, '需要内容或运营读取权限');
    }

    private function tag(Tag $tag): array
    {
        return ['id' => $tag->id, 'key' => $tag->key, 'name' => $tag->name, 'status' => $tag->status];
    }

    private function content(Model $record): array
    {
        $result = ['id' => $record->id, 'name' => $record instanceof Drama ? $record->title : $record->name, 'status' => $record->status, 'cover_url' => $record->cover_url, 'tags' => $record->tags->map(fn (Tag $tag): array => $this->tag($tag))->all()];
        if ($record instanceof Drama) {
            $result += ['title' => $record->title, 'is_playable' => $record->is_playable, 'video_url' => $record->video_url, 'heat' => $record->heat];
        }

        return $result;
    }
}
