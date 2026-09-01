<?php

namespace App\Http\Requests\Tools;

use Illuminate\Foundation\Http\FormRequest;

/**
 * The catalog filter a person keeps as their default. Only the three groups
 * the catalog offers are stored, and the values are capped: this lands in a
 * jsonb column that nothing else bounds.
 */
class CatalogFilterRequest extends FormRequest
{
    /** Groups the catalog filters by. */
    private const GROUPS = ['status', 'category', 'department'];

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'filters' => ['present', 'array'],
            'filters.*' => ['array', 'max:50'],
            'filters.*.*' => ['string', 'max:60', 'distinct'],
        ];

        foreach (self::GROUPS as $group) {
            $rules["filters.{$group}"] = ['sometimes', 'array', 'max:50'];
        }

        return $rules;
    }

    public function withValidator(mixed $validator): void
    {
        $validator->after(function ($validator): void {
            $unknown = array_diff(array_keys((array) $this->input('filters', [])), self::GROUPS);

            if ($unknown !== []) {
                $validator->errors()->add('filters', '絞り込みの種類が不正です。');
            }
        });
    }

    /**
     * The filter to store, with the groups in a fixed order so two identical
     * selections are stored identically.
     *
     * @return array<string, list<string>>
     */
    public function filters(): array
    {
        /** @var array<string, list<string>> $input */
        $input = $this->validated('filters');
        $filters = [];

        foreach (self::GROUPS as $group) {
            $values = array_values(array_unique($input[$group] ?? []));

            if ($values !== []) {
                sort($values);
                $filters[$group] = $values;
            }
        }

        return $filters;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['filters' => '絞り込み'];
    }
}
