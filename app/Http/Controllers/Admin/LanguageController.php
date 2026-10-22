<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Content\ContentListRequest;
use App\Http\Requests\Admin\Content\LanguageRequest;
use App\Models\Language;
use App\Services\Admin\LanguageService;
use Symfony\Component\HttpFoundation\Response;

/**
 * 语言列表接口。
 */
class LanguageController extends Controller
{
    public function __construct(private readonly LanguageService $languageService) {}

    public function index(ContentListRequest $request): Response
    {
        $filters = $request->validated();

        return $this->success(
            $this->languageService->paginate($filters, $filters['per_page'] ?? 10)
                ->through(fn (Language $language): array => $this->transform($language))
        );
    }

    /** 已启用的语言（下拉选项用） */
    public function options(): Response
    {
        return $this->success($this->languageService->options());
    }

    public function store(LanguageRequest $request): Response
    {
        $language = $this->languageService->create($request->normalized());

        return $this->success($this->transform($language->fresh()));
    }

    public function show(int $language): Response
    {
        return $this->success($this->transform(Language::findOrFail($language)));
    }

    public function update(LanguageRequest $request, int $language): Response
    {
        $model = Language::findOrFail($language);

        $this->languageService->update($model, $request->normalized());

        return $this->success($this->transform($model->fresh()));
    }

    public function destroy(int $language): Response
    {
        $this->languageService->delete(Language::findOrFail($language));

        return $this->success(true);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Language $language): array
    {
        return [
            'id' => $language->id,
            'code' => $language->code,
            'name' => $language->name,
            'native_name' => $language->native_name,
            'flag_emoji' => $language->flag_emoji,
            'enabled' => $language->enabled,
            'sort' => $language->sort,
            'created_at' => $language->created_at,
            'updated_at' => $language->updated_at,
        ];
    }
}
