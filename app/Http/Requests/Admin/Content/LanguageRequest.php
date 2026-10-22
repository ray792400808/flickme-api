<?php

namespace App\Http\Requests\Admin\Content;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 语言字典的新增 / 修改参数校验。
 */
class LanguageRequest extends FormRequest
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
        $languageId = $this->route('language');
        $isUpdate = $this->isMethod('patch') || $this->isMethod('put');

        return [
            'code' => [
                $isUpdate ? 'sometimes' : 'required',
                'string',
                'max:16',
                'regex:/^[a-zA-Z]{2,3}(-[a-zA-Z0-9]{2,8})*$/',
                Rule::unique('languages', 'code')->ignore($languageId),
            ],
            'name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:100'],
            'native_name' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:100'],
            'flag_emoji' => ['nullable', 'string', 'max:16'],
            'enabled' => ['nullable', 'integer', 'in:0,1'],
            'sort' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'code.required' => '语种代码为必填项',
            'code.regex' => '语种代码需为 BCP47 格式，如 zh-CN',
            'code.unique' => '该语种代码已存在',
            'name.required' => '英文名为必填项',
            'native_name.required' => '本地名为必填项',
            'enabled.in' => '启用状态只能是 0 或 1',
            'sort.min' => '排序值不能为负数',
        ];
    }

    /**
     * 归一化：空字符串转 null，避免写入 unique 列时报错。
     *
     * @return array<string, mixed>
     */
    public function normalized(): array
    {
        $data = $this->validated();

        if (array_key_exists('flag_emoji', $data) && $data['flag_emoji'] === '') {
            $data['flag_emoji'] = null;
        }

        return $data;
    }
}
