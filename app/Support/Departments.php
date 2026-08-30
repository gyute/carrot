<?php

namespace App\Support;

use Illuminate\Validation\Rule;

/**
 * The departments a tool or a user may belong to. The list is deployment
 * data, not code: it comes from CATALOG_DEPARTMENTS so no real org chart
 * ever lands in the repository.
 *
 * An empty list means "not configured here" and the field falls back to free
 * text, so a fresh install is usable before anyone fills the list in.
 */
class Departments
{
    /**
     * @return array<int, string>
     */
    public static function all(): array
    {
        return array_values(array_filter(
            array_map('strval', (array) config('catalog.departments', [])),
            fn (string $department): bool => $department !== '',
        ));
    }

    /**
     * Validation rules for a department field.
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
