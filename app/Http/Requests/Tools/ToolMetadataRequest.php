<?php

namespace App\Http\Requests\Tools;

use App\Models\Tool;
use App\Support\Departments;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * The display fields an owner edits in place, without review.
 */
class ToolMetadataRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:60'],
            'summary' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:5000'],
            'icon' => ['required', Rule::in(Tool::ICONS)],
            'accent' => ['required', Rule::in(Tool::ACCENTS)],
            'department' => Departments::rules(),
            'categories' => ['array', 'max:5'],
            'categories.*' => ['string', 'max:30', 'distinct'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'ツール名',
            'summary' => '概要',
            'description' => '説明',
            'icon' => 'アイコン',
            'accent' => 'カラー',
            'department' => '所属',
            'categories' => 'カテゴリ',
            'categories.*' => 'カテゴリ',
        ];
    }
}
