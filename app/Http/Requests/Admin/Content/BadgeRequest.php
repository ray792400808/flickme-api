<?php

namespace App\Http\Requests\Admin\Content;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 角标字典的新增 / 修改参数校验。
 */
class BadgeRequest extends FormRequest
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
        $badgeId = $this->route('badge');
        $isUpdate = $this->isMethod('patch') || $this->isMethod('put');

        return [
            'keyword' => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                'max:64',
                'regex:/^[A-Za-z0-9_-]+$/',
                Rule::unique('badges', 'keyword')->ignore($badgeId),
            ],
            'label' => ['nullable', 'string', 'max:50'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'enabled' => ['nullable', 'integer', 'in:0,1'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'keyword.required' => '角标关键词为必填项',
            'keyword.regex' => '角标关键词只允许字母、数字、下划线与短横线',
            'keyword.unique' => '该角标关键词已存在',
            'label.max' => '显示名称不能超过 50 个字符',
            'enabled.in' => '启用状态只能是 0 或 1',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function normalized(): array
    {
        $data = $this->validated();

        if (array_key_exists('label', $data) && $data['label'] === '') {
            $data['label'] = null;
        }

        return $data;
    }
}
