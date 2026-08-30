<?php

namespace App\Http\Requests\Admin;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Retiring someone hands their tools to somebody else, so the form has to say
 * who. Leaving it blank lets the action fall back to their department manager.
 */
class RetireUserRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $leaving = $this->route('user');

        $successor = Rule::exists(User::class, 'ulid')->whereNull('deleted_at');

        if ($leaving instanceof User) {
            $successor->whereNot('ulid', $leaving->ulid);
        }

        return [
            'successor' => ['nullable', 'string', $successor],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['successor' => '引き継ぎ先'];
    }
}
