<?php

namespace App\Http\Requests\Admin\Content;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * 剧库（Short 剧）新增 / 修改参数校验。
 */
class DramaRequest extends FormRequest
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
        $dramaId = $this->route('drama');
        $isUpdate = $this->isMethod('patch') || $this->isMethod('put');

        return [
            'title' => [$isUpdate ? 'sometimes' : 'required', 'string', 'max:200'],
            'synopsis' => ['nullable', 'string', 'max:5000'],
            'cover_url' => ['nullable', 'string', 'max:1024', 'url'],
            'video_url' => ['nullable', 'string', 'max:1024', 'url'],
            'status' => ['nullable', 'integer', 'in:0,1,2'],
            'badge_id' => [
                'nullable',
                'integer',
                Rule::exists('badges', 'id')->whereNull('deleted_at'),
            ],
            'language_id' => [
                $isUpdate ? 'sometimes' : 'required',
                'integer',
                Rule::exists('languages', 'id')->whereNull('deleted_at'),
            ],
            'free_episodes' => ['nullable', 'integer', 'min:0', 'max:9999'],
            'tag_ids' => ['nullable', 'array', 'max:50'],
            'tag_ids.*' => [
                'integer',
                Rule::exists('tags', 'id')->whereNull('deleted_at'),
            ],
            'episodes' => ['nullable', 'array', 'max:999'],
            'episodes.*.episode_no' => ['required', 'integer', 'min:1', 'max:9999'],
            'episodes.*.title' => ['nullable', 'string', 'max:200'],
            'episodes.*.duration_sec' => ['nullable', 'integer', 'min:0', 'max:86400'],
            'episodes.*.status' => ['nullable', 'integer', 'in:0,1,2'],
            'episodes.*.is_pay' => ['nullable', 'integer', 'in:0,1'],
            'episodes.*.video_url' => ['nullable', 'string', 'max:1024'],
            'episodes.*.poster_url' => ['nullable', 'string', 'max:1024'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'title.required' => '剧名为必填项',
            'cover_url.url' => '封图必须是合法的 URL',
            'video_url.url' => '预告 / 花絮必须是合法的 URL',
            'status.in' => '状态只能是 0（草稿）/ 1（上架）/ 2（下架）',
            'badge_id.exists' => '所选角标不存在',
            'language_id.required' => '语种为必填项',
            'language_id.exists' => '所选语种不存在',
            'free_episodes.min' => '免费集数不能为负数',
            'tag_ids.*.exists' => '所选标签不存在',
            'episodes.*.episode_no.required' => '集号为必填项',
            'episodes.*.episode_no.min' => '集号必须为正整数',
            'episodes.*.duration_sec.min' => '时长不能为负数',
            'episodes.*.is_pay.in' => '付费标记只能是 0 或 1',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function normalized(): array
    {
        $data = $this->validated();

        foreach (['synopsis', 'cover_url', 'video_url'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        return $data;
    }
}
