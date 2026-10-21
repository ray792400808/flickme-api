<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminMenu;
use App\Services\Admin\AdminMenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminMenuController extends Controller
{
    public function __construct(private readonly AdminMenuService $menuService) {}

    public function index(Request $request): JsonResponse
    {
        $tree = $this->menuService->list($request->string('keyword') ?: null);

        return response()->json(['data' => $tree]);
    }

    public function options(): JsonResponse
    {
        return response()->json(['data' => $this->menuService->options()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);

        $menu = $this->menuService->create($data);

        return response()->json(['data' => $menu->load('permission:id,name,code')], 201);
    }

    public function show(AdminMenu $menu): JsonResponse
    {
        $menu->load('permission:id,name,code,type');

        return response()->json(['data' => [
            'id' => $menu->id,
            'parent_id' => $menu->parent_id,
            'permission_id' => $menu->permission_id,
            'permission_code' => $menu->permission?->code,
            'title' => $menu->title,
            'icon' => $menu->icon,
            'path' => $menu->path,
            'name' => $menu->name,
            'component' => $menu->component,
            'redirect' => $menu->redirect,
            'sort' => $menu->sort,
            'is_hidden' => $menu->is_hidden,
            'is_affix' => $menu->is_affix,
            'is_keep_alive' => $menu->is_keep_alive,
            'meta' => $menu->meta,
            'children_count' => $menu->children()->count(),
        ]]);
    }

    public function update(Request $request, AdminMenu $menu): JsonResponse
    {
        $data = $this->validated($request, $menu);

        $updated = $this->menuService->update($menu, $data);

        return response()->json(['data' => $updated]);
    }

    public function destroy(AdminMenu $menu): JsonResponse
    {
        $this->menuService->delete($menu);

        return response()->json(['data' => ['deleted' => true]]);
    }

    public function sort(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer', 'exists:admin_menus,id'],
            'items.*.parent_id' => ['nullable', 'integer', 'exists:admin_menus,id'],
            'items.*.sort' => ['required', 'integer', 'min:0', 'max:9999'],
        ]);

        $this->menuService->sort($data['items']);

        return response()->json(['data' => ['sorted' => true]]);
    }

    // ─── 内部 ───────────────────────────────────────────────

    private function validated(Request $request, ?AdminMenu $menu = null): array
    {
        $sometimes = $menu ? 'sometimes' : 'required';

        return $request->validate([
            'parent_id' => ['nullable', 'integer', 'exists:admin_menus,id'],
            'permission_id' => ['nullable', 'integer', 'exists:admin_permissions,id'],
            'title' => [$sometimes, 'string', 'max:50'],
            'icon' => ['nullable', 'string', 'max:100'],
            'path' => [$sometimes, 'string', 'max:191'],
            'name' => ['nullable', 'string', 'max:100'],
            'component' => ['nullable', 'string', 'max:255'],
            'redirect' => ['nullable', 'string', 'max:255'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'is_hidden' => ['nullable', 'boolean'],
            'is_affix' => ['nullable', 'boolean'],
            'is_keep_alive' => ['nullable', 'boolean'],
            'meta' => ['nullable', 'array'],
        ]);
    }
}
