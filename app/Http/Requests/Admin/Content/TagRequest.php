<?php

namespace App\Http\Requests\Admin\Content;

use App\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 标签 / 合集的新增 / 修改参数校验。
 */
class TagRequest extends FormRequest
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
        $tagId = $this->route('tag');
        $isUpdate = $this->isMethod('patch') || $this->isMethod('put');

        return [
            'code' => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9][a-z0-9_-]*$/',
                Rule::unique('tags', 'code')->ignore($tagId),
            ],
            'type' => [$isUpdate ? 'sometimes' : 'required', 'integer', 'in:1,2'],
            'name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:2000'],
            'language_id' => [
                $isUpdate ? 'sometimes' : 'required',
                'integer',
                Rule::exists('languages', 'id')->whereNull('deleted_at'),
            ],
            'status' => ['nullable', 'integer', 'in:1,2'],
            'translations' => ['nullable', 'array', 'max:50'],
            'translations.*.language_id' => [
                'required_with:translations',
                'integer',
                Rule::exists('languages', 'id')->whereNull('deleted_at'),
            ],
            'translations.*.name' => ['required_with:translations', 'string', 'max:200'],
            'translations.*.description' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => '稳定标识为必填项',
            'code.regex' => '稳定标识只允许小写字母、数字、下划线与短横线',
            'code.unique' => '该稳定标识已存在',
            'type.required' => '类型为必填项',
            'type.in' => '类型只能是 1（标签）或 2（合集）',
            'name.required' => '名称为必填项',
            'language_id.required' => '默认语种为必填项',
            'language_id.exists' => '所选语种不存在',
            'status.in' => '状态只能是 1（启用）或 2（停用）',
            'translations.*.language_id.required_with' => '翻译语种为必填项',
            'translations.*.language_id.exists' => '翻译语种不存在',
            'translations.*.name.required_with' => '翻译名称为必填项',
        ];
    }

    /**
     * 归一化：清理空字符串，并校验同一语种不重复出现。
     *
     * @return array<string, mixed>
     */
    public function normalized(): array
    {
        $data = $this->validated();

        if (array_key_exists('description', $data) && $data['description'] === '') {
            $data['description'] = null;
        }

        if (array_key_exists('translations', $data) && is_array($data['translations'])) {
            $seen = [];

            $data['translations'] = array_values(array_filter(
                array_map(function (array $translation) use (&$seen): array {
                    if (isset($seen[$translation['language_id']])) {
                        return [];
                    }

                    $seen[$translation['language_id']] = true;

                    return [
                        'language_id' => $translation['language_id'],
                        'name' => $translation['name'],
                        'description' => $translation['description'] ?? null,
                    ];
                }, $data['translations']),
                fn (array $translation): bool => $translation !== []
            ));
        }

        return $data;
    }

    /**
     * 类型中文名（供日志 / 提示使用）。
     */
    public function typeLabel(?int $type): string
    {
        return $type === Tag::TYPE_COLLECTION ? '合集' : '标签';
    }
}
