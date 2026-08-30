<?php

namespace App\Http\Requests\Tools;

use App\Enums\ToolKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A request carries nothing runnable: what the person needs, why, and when.
 * The department is not here - it is stamped from the requester, since it
 * decides who else may read the request.
 */
class SubmitToolRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:80'],
            'body' => ['required', 'string', 'max:5000'],
            'categories' => ['array', 'max:5'],
            'categories.*' => ['string', 'max:30', 'distinct'],
            'desired_kind' => ['nullable', Rule::enum(ToolKind::class)],
            'needed_by' => ['nullable', 'date', 'after_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'title' => 'タイトル',
            'body' => '内容',
            'categories' => 'カテゴリ',
            'categories.*' => 'カテゴリ',
            'desired_kind' => '希望する形式',
            'needed_by' => '希望時期',
        ];
    }
}
