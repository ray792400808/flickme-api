<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Content\BadgeRequest;
use App\Http\Requests\Admin\Content\ContentListRequest;
use App\Models\Badge;
use App\Services\Admin\BadgeService;
use Symfony\Component\HttpFoundation\Response;

/**
 * 角标字典接口。
 */
class BadgeController extends Controller
{
    public function __construct(private readonly BadgeService $badgeService) {}

    public function index(ContentListRequest $request): Response
    {
        $filters = $request->validated();

        return $this->success(
            $this->badgeService->paginate($filters, $filters['per_page'] ?? 10)
                ->through(fn (Badge $badge): array => $this->transform($badge))
        );
    }

    /** 已启用的角标（下拉选项用） */
    public function options(): Response
    {
        return $this->success($this->badgeService->options());
    }

    public function store(BadgeRequest $request): Response
    {
        $badge = $this->badgeService->create($request->normalized());

        return $this->success($this->transform($badge->fresh()));
    }

    public function show(int $badge): Response
    {
        return $this->success($this->transform(Badge::findOrFail($badge)));
    }

    public function update(BadgeRequest $request, int $badge): Response
    {
        $model = Badge::findOrFail($badge);

        $this->badgeService->update($model, $request->normalized());

        return $this->success($this->transform($model->fresh()));
    }

    public function destroy(int $badge): Response
    {
        $this->badgeService->delete(Badge::findOrFail($badge));

        return $this->success(true);
    }

    /**
     * @return array<string, mixed>
     */
    private function transform(Badge $badge): array
    {
        return [
            'id' => $badge->id,
            'keyword' => $badge->keyword,
            'label' => $badge->label,
            'sort' => $badge->sort,
            'enabled' => $badge->enabled,
            'created_at' => $badge->created_at,
            'updated_at' => $badge->updated_at,
        ];
    }
}
