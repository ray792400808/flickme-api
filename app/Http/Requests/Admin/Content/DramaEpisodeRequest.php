<?php

namespace App\Http\Requests\Admin\Content;

use Illuminate\Foundation\Http\FormRequest;

/**
 * 分集（剧集）新增 / 修改参数校验。
 */
class DramaEpisodeRequest extends FormRequest
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
        $isUpdate = $this->isMethod('patch') || $this->isMethod('put');

        return [
            'episode_no' => [
                $isUpdate ? 'sometimes' : 'required',
                'integer',
                'min:1',
                'max:9999',
            ],
            'title' => ['nullable', 'string', 'max:200'],
            'duration_sec' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'status' => ['nullable', 'integer', 'in:0,1,2'],
            'is_pay' => ['nullable', 'integer', 'in:0,1'],
            'video_url' => ['nullable', 'string', 'max:1024'],
            'poster_url' => ['nullable', 'string', 'max:1024'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'episode_no.required' => '集号为必填项',
            'episode_no.min' => '集号必须为正整数',
            'episode_no.max' => '集号不能超过 9999',
            'duration_sec.min' => '时长不能为负数',
            'duration_sec.max' => '单集时长不能超过 86400 秒',
            'status.in' => '状态只能是 0（草稿）/ 1（上架）/ 2（下架）',
            'is_pay.in' => '付费标记只能是 0 或 1',
            'title.max' => '标题不能超过 200 个字符',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function normalized(): array
    {
        $data = $this->validated();

        foreach (['title', 'video_url', 'poster_url'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        return $data;
    }
}
