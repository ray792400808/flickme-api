<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Content\ContentListRequest;
use App\Http\Requests\Admin\Content\TagRequest;
use App\Models\Tag;
use App\Models\TagTranslation;
use App\Services\Admin\TagService;
use Symfony\Component\HttpFoundation\Response;

/**
 * 标签与合集接口。
 */
class TagController extends Controller
{
    public function __construct(private readonly TagService $tagService) {}

    public function index(ContentListRequest $request): Response
    {
        $filters = $request->validated();

        return $this->success(
            $this->tagService->paginate($filters, $filters['per_page'] ?? 10)
                ->through(fn (Tag $tag): array => $this->transform($tag, $filters['lang'] ?? null))
        );
    }

    /** 下拉选项 */
    public function options(ContentListRequest $request): Response
    {
        return $this->success($this->tagService->options($request->validated()));
    }

    /** 统计数据：每个标签关联的剧数 */
    public function stats(): Response
    {
        return $this->success($this->tagService->stats());
    }

    public function store(TagRequest $request): Response
    {
        $tag = $this->tagService->create(
            $request->normalized(),
            $request->user()?->id,
        );

        return $this->success($this->transform($tag->fresh()->load('translations', 'language')));
    }

    public function show(int $tag): Response
    {
        $model = Tag::with(['language', 'translations'])->findOrFail($tag);

        return $this->success($this->transform($model));
    }

    public function update(TagRequest $request, int $tag): Response
    {
        $model = Tag::with('translations')->findOrFail($tag);

        $this->tagService->update($model, $request->normalized());

        return $this->success($this->transform($model->fresh()->load('translations', 'language')));
    }

    public function destroy(int $tag): Response
    {
        $this->tagService->delete(Tag::findOrFail($tag));

        return $this->success(true);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Tag $tag, ?string $languageCode = null): array
    {
        $languageCode = $languageCode ?? $tag->language_code;

        return [
            'id' => $tag->id,
            'code' => $tag->code,
            'type' => $tag->type,
            'type_label' => $tag->type_label,
            'name' => $tag->getName($languageCode),
            'description' => $tag->getDescription($languageCode),
            'language_id' => $tag->language_id,
            'language_code' => $tag->language_code,
            'language' => $tag->language ? [
                'id' => $tag->language->id,
                'code' => $tag->language->code,
                'name' => $tag->language->name,
            ] : null,
            'status' => $tag->status,
            'status_label' => $tag->status_label,
            'translations' => $tag->translations->map(fn (TagTranslation $translation): array => [
                'id' => $translation->id,
                'language_id' => $translation->language_id,
                'language_code' => $translation->language_code,
                'name' => $translation->name,
                'description' => $translation->description,
            ])->all(),
            'created_at' => $tag->created_at,
            'updated_at' => $tag->updated_at,
        ];
    }
}
