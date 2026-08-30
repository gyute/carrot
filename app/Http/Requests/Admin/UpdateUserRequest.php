<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Support\Departments;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['required', Rule::enum(UserRole::class)],
            'department' => Departments::rules(),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->input('role') === UserRole::Manager->value && ! $this->filled('department')) {
                $validator->errors()->add('department', '部署管理者には所属が必要です。');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['role' => '権限', 'department' => '所属'];
    }
}
