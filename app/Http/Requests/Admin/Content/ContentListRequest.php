<?php

namespace App\Http\Requests\Admin\Content;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 内容管理列表页的公共查询参数校验。
 *
 * 覆盖语言 / 角标 / 标签 / 剧库四个列表接口的分页与排序参数。
 */
class ContentListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'keyword' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:100'],
            'enabled' => ['nullable', 'integer', 'in:0,1'],
            'type' => ['nullable', 'integer', 'in:1,2'],
            'status' => ['nullable', 'integer', 'in:0,1,2'],
            'badge_id' => ['nullable', 'integer'],
            'tag_id' => ['nullable', 'integer'],
            'lang' => ['nullable', 'string', 'max:16'],
            'sort' => ['nullable', 'string', 'in:id,title,status,published_at,created_at,updated_at'],
            'dir' => ['nullable', 'string', 'in:asc,desc'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
        ];
    }
}
