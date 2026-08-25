<?php

namespace App\Http\Requests\Tools;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExportStoreRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var array<string, mixed> $definitions */
        $definitions = config('exports.definitions', []);

        return [
            'definition' => ['required', 'string', Rule::in(array_keys($definitions))],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'definition' => 'エクスポート定義',
        ];
    }
}
