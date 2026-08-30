<?php

namespace App\Support;

use Illuminate\Validation\Rule;

class Departments
{
    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_map('strval', (array) config('catalog.departments', []));
    }

    /**
     * An unconfigured list means free text, so a fresh install is usable
     * before anyone fills it in.
     *
     * @return array<int, mixed>
     */
    public static function rules(): array
    {
        $departments = self::all();

        return $departments === []
            ? ['nullable', 'string', 'max:60']
            : ['nullable', 'string', Rule::in($departments)];
    }
}
